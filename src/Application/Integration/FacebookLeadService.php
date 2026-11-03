<?php

namespace App\Application\Integration;

use App\Infrastructure\Lead\LeadRepository;
use App\Infrastructure\PortalIntegration\PortalIntegrationRepository;
use App\Infrastructure\Tenant\TenantRepository;
use App\Domain\Lead\Lead;
use PDO;
use Exception;

require_once dirname(dirname(dirname(__DIR__))) . '/app/utils/InputValidator.php';

class FacebookLeadService
{
    private LeadRepository $repository;
    private PDO $pdo;
    private PortalIntegrationRepository $portalIntegrationRepository;
    private TenantRepository $tenantRepository;

    public function __construct(LeadRepository $repository, PDO $pdo)
    {
        $this->repository = $repository;
        $this->pdo = $pdo;
        $this->portalIntegrationRepository = new PortalIntegrationRepository($this->pdo);
        $this->tenantRepository = new TenantRepository($this->pdo);
    }

    /**
     * Valida que o tenant existe, está ativo e que a integração Facebook Leads correspondente
     * está habilitada antes do processamento do webhook.
     *
     * @param int $tenantId
     * @return array{tenant_id:int, credentials:array}
     */
    public function authorizeWebhookTenant(int $tenantId, bool $requireAppSecret = false): array
    {
        $tenant = $this->tenantRepository->findById($tenantId);
        if (!$tenant) {
            throw new \RuntimeException('tenant do webhook Facebook não existe.');
        }

        $status = strtolower(trim((string)($tenant['status'] ?? 'inactive')));
        if ($status !== 'active') {
            throw new \RuntimeException('tenant do webhook Facebook está inativo.');
        }

        $integration = $this->getFacebookIntegration($tenantId);
        if (!$integration) {
            throw new \RuntimeException('integração Facebook Leads não configurada para este tenant.');
        }

        if (!(bool)($integration['enabled'] ?? false)) {
            throw new \RuntimeException('integração Facebook Leads está desabilitada; habilitada requerida.');
        }

        $credentials = is_array($integration['credentials']) ? $integration['credentials'] : [];
        if ($requireAppSecret && (!isset($credentials['app_secret']) || trim((string)$credentials['app_secret']) === '')) {
            throw new \RuntimeException('app_secret ausente ou vazia para a integração Facebook Leads.');
        }

        return [
            'tenant_id' => $tenantId,
            'credentials' => $credentials,
        ];
    }

    /**
     * Valida o payload do webhook Facebook em POST, incluindo assinatura HMAC,
     * tenant autenticado e segredo esperado para a integração.
     */
    /**
     * Orquestra a autenticação HMAC do webhook e a persistência do lead somente após validação.
     */
    public function processAuthenticatedWebhookLead(int $tenantId, string $rawPayload, string $signatureHeader, string $leadgenId): array
    {
        $validated = $this->authorizeWebhookTenant($tenantId, true);
        $tenantId = (int)($validated['tenant_id'] ?? $tenantId);
        $signatureHeader = trim((string)$signatureHeader);
        if ($signatureHeader === '') {
            throw new \RuntimeException('X-Hub-Signature-256 ausente.');
        }

        $appSecret = (string)($validated['credentials']['app_secret'] ?? '');
        if (trim($appSecret) === '') {
            throw new \RuntimeException('app_secret ausente ou vazia para a integração Facebook Leads.');
        }

        if (!$this->validateWebhookSignature($rawPayload, $signatureHeader, $appSecret)) {
            throw new \RuntimeException('assinatura do webhook Facebook inválida.');
        }

        $accessToken = trim((string)($validated['credentials']['access_token'] ?? ''));
        if ($accessToken === '') {
            throw new \RuntimeException('Access Token do Facebook não configurado');
        }

        $graphVersion = trim((string)($validated['credentials']['graph_api_version'] ?? 'v16.0')) ?: 'v16.0';

        return $this->processValidatedFacebookLead($tenantId, $leadgenId, $accessToken, $appSecret, $graphVersion);
    }

    /**
     * Obtém a integração do Facebook para um tenant
     *
     * @param int $tenantId ID do tenant
     * @return array|null Array com 'enabled' e 'credentials', ou null se não existe
     */
    public function getFacebookIntegration(int $tenantId): ?array
    {
        $row = $this->portalIntegrationRepository->findByTenantAndSlug($tenantId, 'facebook_leads');
        if (!$row) {
            return null;
        }

        return [
            'enabled' => (bool)($row['enabled'] ?? false),
            'credentials' => $row['credentials'] ?? [],
        ];
    }

    /**
     * Valida a assinatura do webhook usando X-Hub-Signature-256
     *
     * @param string $rawPayload O payload bruto recebido
     * @param string $signatureHeader O valor do header X-Hub-Signature-256
     * @param string $appSecret O app secret configurado
     * @return bool True se a assinatura é válida
     */
    public function validateWebhookSignature(string $rawPayload, string $signatureHeader, string $appSecret): bool
    {
        $normalizedAppSecret = trim((string)$appSecret);
        $normalizedSignatureHeader = trim((string)$signatureHeader);

        if ($normalizedAppSecret === '' || $normalizedSignatureHeader === '') {
            return false;
        }

        $expectedSignature = 'sha256=' . hash_hmac('sha256', $rawPayload, $appSecret);
        return hash_equals($normalizedSignatureHeader, $expectedSignature);
    }

    /**
     * Valida o token de verificação do webhook
     *
     * @param string $providedToken O token fornecido no request
     * @param string $expectedToken O token configurado na integração
     * @return bool True se os tokens combinam
     */
    public function validateVerifyToken(string $providedToken, string $expectedToken): bool
    {
        if (empty($expectedToken)) {
            return false;
        }

        return hash_equals($expectedToken, $providedToken);
    }

    /**
     * Busca dados do lead no Graph API do Facebook
     *
     * @param string $leadgenId ID do lead no Facebook
     * @param string $accessToken Access token do Facebook
     * @param string|null $appSecret App secret para gerar prova
     * @param string $graphVersion Versão da Graph API (ex: v16.0)
     * @return array Os dados do lead retornados pela Graph API
     * @throws Exception Se houver erro na requisição ou resposta
     */
    public function fetchLeadDataFromGraph(
        string $leadgenId,
        string $accessToken,
        ?string $appSecret,
        string $graphVersion
    ): array {
        $fields = 'field_data,created_time,form_id,page_id,adgroup_id,ad_id';
        $url = sprintf(
            'https://graph.facebook.com/%s/%s?fields=%s&access_token=%s',
            rawurlencode($graphVersion),
            rawurlencode($leadgenId),
            rawurlencode($fields),
            rawurlencode($accessToken)
        );

        if (!empty($appSecret)) {
            $proof = $this->generateAppSecretProof($accessToken, $appSecret);
            $url .= '&appsecret_proof=' . rawurlencode($proof);
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);

        if ($response === false || !empty($error)) {
            throw new Exception('Erro ao consultar Graph API do Facebook: ' . $error);
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new Exception('Resposta inválida da Graph API do Facebook.');
        }

        if (isset($data['error'])) {
            $message = $data['error']['message'] ?? 'Erro desconhecido da Graph API';
            $code = $data['error']['code'] ?? 0;
            throw new Exception(sprintf('Facebook Graph API: %s (code %d)', $message, $code));
        }

        return $data;
    }

    /**
     * Gera a prova do app secret para assinatura de requisições ao Graph API
     *
     * @param string $accessToken Access token
     * @param string $appSecret App secret
     * @return string Hash HMAC SHA256
     */
    private function generateAppSecretProof(string $accessToken, string $appSecret): string
    {
        return hash_hmac('sha256', $accessToken, $appSecret);
    }

    /**
     * Extrai e normaliza campos do lead da resposta do Graph API
     *
     * @param array $leadData Dados retornados pela Graph API
     * @return array Dados normalizados: name, email, phone, message
     */
    public function extractAndValidateLeadFields(array $leadData): array
    {
        $result = [
            'name' => '',
            'email' => '',
            'phone' => '',
            'message' => '',
        ];
        $extra = [];

        $fieldData = $leadData['field_data'] ?? [];
        if (!is_array($fieldData)) {
            return $result;
        }

        foreach ($fieldData as $field) {
            if (!is_array($field) || empty($field['name'])) {
                continue;
            }

            $fieldName = strtolower(trim((string)$field['name']));
            $value = $this->getStringValue($field);
            if ($value === '') {
                continue;
            }

            if (in_array($fieldName, ['full_name', 'name', 'nome'], true)) {
                $result['name'] = $value;
                continue;
            }

            if (strpos($fieldName, 'email') !== false) {
                $result['email'] = $value;
                continue;
            }

            if (strpos($fieldName, 'phone') !== false || strpos($fieldName, 'mobile') !== false) {
                $result['phone'] = $value;
                continue;
            }

            if (in_array($fieldName, ['message', 'messages', 'comments', 'comment', 'observations', 'observacao'], true)) {
                $result['message'] = $value;
                continue;
            }

            $extra[] = ucfirst($fieldName) . ': ' . $value;
        }

        if ($result['message'] === '' && !empty($extra)) {
            $result['message'] = implode("\n", $extra);
        }

        return $result;
    }

    /**
     * Obtém valor string de um campo de lead do Facebook
     *
     * @param array $field Campo do field_data
     * @return string Valor extraído e trimado
     */
    private function getStringValue(array $field): string
    {
        if (!isset($field['values']) || !is_array($field['values']) || count($field['values']) === 0) {
            return '';
        }
        $value = $field['values'][0];
        if (is_array($value)) {
            return trim((string)($value[0] ?? ''));
        }
        return trim((string)$value);
    }

    /**
     * Normaliza número de telefone removendo caracteres não numéricos
     *
     * @param string $phone Telefone a normalizar
     * @return string Telefone normalizado
     */
    private function normalizePhone(string $phone): string
    {
        return preg_replace('/[^0-9+]/', '', $phone);
    }

    /**
     * Processa um evento de lead do Facebook webhook
     * 
     * Orquestra:
     * 1. Busca de dados no Graph API
     * 2. Extração e validação de campos
     * 3. Busca de lead existente na BD
     * 4. Inserção ou atualização conforme necessário
     *
     * @param int $tenantId Tenant dono do webhook
     * @param string $leadgenId ID do lead no Facebook
     * @param string $accessToken Access token do Facebook
     * @param string|null $appSecret App secret (pode ser nulo)
     * @param string $graphVersion Versão da Graph API
     * @return array Resultado do processamento com lead_id, is_new_lead, email, phone, name
     * @throws Exception Se houver erro em qualquer etapa
     */
    private function processValidatedFacebookLead(
        int $tenantId,
        string $leadgenId,
        string $accessToken,
        string $appSecret,
        string $graphVersion
    ): array {
        if ($tenantId <= 0) {
            throw new \RuntimeException('tenant do webhook Facebook não autenticado.');
        }

        $authorizedTenant = $this->authorizeWebhookTenant($tenantId, true);
        if ((int)($authorizedTenant['tenant_id'] ?? 0) !== $tenantId) {
            throw new \RuntimeException('tenant do webhook Facebook não autenticado.');
        }

        if (trim((string)$appSecret) === '') {
            throw new \RuntimeException('app_secret ausente ou vazia para a integração Facebook Leads.');
        }

        $leadData = $this->fetchLeadDataFromGraph($leadgenId, $accessToken, $appSecret, $graphVersion);

        $extracted = $this->extractAndValidateLeadFields($leadData);
        $name = trim($extracted['name']);
        $email = trim($extracted['email']);
        $phone = $this->normalizePhone($extracted['phone']);
        $message = trim($extracted['message']);

        if ($name === '' || $email === '' || $phone === '' || $message === '') {
            throw new Exception('Dados do lead incompletos. Verifique se o formulário do Facebook está retornando name, email, phone e message.');
        }

        $name = \InputValidator::getString($name, 'Nome', true, 100);
        $email = \InputValidator::getEmail($email, 'Email', true);
        $phone = \InputValidator::getString($phone, 'Telefone', true, 30);
        $message = \InputValidator::getText($message, 'Mensagem', true, 5000);

        $now = date('Y-m-d H:i:s');
        $source = 'facebook_leads';

        $existingLead = $this->repository->findExistingLeadForContact($tenantId, $email, $phone);

        if ($existingLead) {
            $leadId = $existingLead->id;
            $updateData = [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'message' => $message,
                'status' => 'new',
                'source' => $source,
            ];
            $this->repository->updateLead($leadId, $tenantId, $updateData);
            $isNewLead = false;
        } else {
            $lead = new Lead([
                'tenant_id' => $tenantId,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'message' => $message,
                'status' => 'new',
                'source' => $source,
                'created_at' => $now,
                'updated_at' => $now,
                'property_id' => null,
                'assigned_user_id' => null,
            ]);
            $leadId = $this->repository->createLead($lead);
            $isNewLead = true;
        }

        return [
            'lead_id' => $leadId,
            'is_new_lead' => $isNewLead,
            'email' => $email,
            'phone' => $phone,
            'name' => $name,
            'tenant_id' => $tenantId,
        ];
    }
}
