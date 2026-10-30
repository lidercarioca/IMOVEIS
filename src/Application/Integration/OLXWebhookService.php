<?php

namespace App\Application\Integration;

use PDO;
use App\Security\WebhookAuthenticator;
use App\Security\WebhookAuditLogger;
use App\Security\RateLimitPolicy;
use App\Security\RateLimiter;
use App\Infrastructure\PortalIntegration\PortalIntegrationRepository;
use App\Infrastructure\Lead\LeadRepository;
use Exception;

/**
 * OLXWebhookService
 * 
 * Orquestra processamento seguro de webhooks da OLX.
 * 
 * Fluxo:
 * 1. Autenticar webhook via HMAC
 * 2. Obter tenant da integração (não do payload)
 * 3. Validar property_id pertence ao tenant
 * 4. Proteger contra replay
 * 5. Gravar lead via LeadRepository
 */
class OLXWebhookService
{
    private PDO $pdo;
    private PortalIntegrationRepository $integrationRepo;
    private LeadRepository $leadRepo;
    private WebhookAuditLogger $auditLogger;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->integrationRepo = new PortalIntegrationRepository($pdo);
        $this->leadRepo = new LeadRepository($pdo);
        $this->auditLogger = new WebhookAuditLogger($pdo);
    }

    /**
     * Processar webhook OLX recebido
     * 
     * @param string $payloadRaw Raw payload (não decodificado)
     * @param string $signature Header X-Webhook-Signature
     * @param string $timestamp Header X-Webhook-Timestamp
     * @return array ['success' => bool, 'lead_id' => ?int, 'error' => ?string, 'message' => string]
     * @throws Exception
     */
    public function processWebhook(
        string $payloadRaw,
        string $signature,
        string $timestamp
    ): array {
        try {
            // ETAPA 1: Autenticar webhook
            $integration = $this->authenticateWebhook($payloadRaw, $signature, $timestamp);

            // ETAPA 2: Decodificar payload
            $payload = json_decode($payloadRaw, true);
            if (!is_array($payload)) {
                throw new Exception('Payload JSON inválido');
            }

            // ETAPA 3: Extrair dados do lead
            $propertyId = isset($payload['property_id']) ? intval($payload['property_id']) : null;
            if (!$propertyId || $propertyId <= 0) {
                throw new Exception('property_id ausente ou inválido');
            }

            $tenantId = $integration['tenant_id'];

            // Aplicar rate limit por tenant (após autenticação)
            $endpoint = 'olx_tenant';
            $config = RateLimitPolicy::getConfig($endpoint);
            $limit = (int) $config['limit'];
            $window = (int) $config['window'];
            $resolvedIdentifier = $endpoint . ':tenant_' . $tenantId;
            $rateLimiter = new RateLimiter();
            if (!$rateLimiter->isAllowed($resolvedIdentifier, $limit, $window)) {
                $headerInfo = $rateLimiter->getHeaderInfo($resolvedIdentifier, $limit, $window);
                throw new Exception(sprintf(
                    'RATE_LIMIT_EXCEEDED:%d',
                    $headerInfo['reset_in']
                ));
            }

            // ETAPA 4: Validar ownership do property_id
            $this->validatePropertyOwnership($propertyId, $tenantId);

            // ETAPA 5: Proteção contra replay
            try {
                $this->checkReplay($payloadRaw, $tenantId);
            } catch (Exception $e) {
                // Log de auditoria: replay bloqueado
                $this->auditLogger->logEvent($tenantId, 'olx', 'lead_received', 'REPLAY_BLOCKED', $_SERVER['REMOTE_ADDR'] ?? null);
                throw $e;
            }

            // ETAPA 6: Validar e sanitizar dados do lead
            $leadData = $this->extractLeadData($payload);

            // ETAPA 7: Gravar lead via LeadRepository
            $leadId = $this->createOrUpdateLead($tenantId, $propertyId, $leadData);

            // ETAPA 8: Registrar evento processado (replay protection)
            $this->recordEvent($payloadRaw, $tenantId, $leadId);

            // Auditoria: sucesso
            $this->auditLogger->logEvent($tenantId, 'olx', 'lead_received', 'SUCCESS', $_SERVER['REMOTE_ADDR'] ?? null);

            return [
                'success' => true,
                'lead_id' => $leadId,
                'message' => 'Lead processado com sucesso',
                'error' => null,
            ];
        } catch (Exception $e) {
            $message = $e->getMessage();
            $errorCode = strpos($message, 'RATE_LIMIT_EXCEEDED:') === 0 ? 'RATE_LIMIT_EXCEEDED' : $message;
            return [
                'success' => false,
                'lead_id' => null,
                'message' => strpos($message, 'RATE_LIMIT_EXCEEDED:') === 0 ? 'Muitas requisições. Aguarde alguns segundos.' : 'Erro ao processar webhook',
                'error' => $errorCode,
                'retry_after' => strpos($message, 'RATE_LIMIT_EXCEEDED:') === 0 ? (int) substr($message, strlen('RATE_LIMIT_EXCEEDED:')) : null,
            ];
        }
    }

    /**
     * ETAPA 1: Autenticar webhook usando HMAC-SHA256
     * 
     * @param string $payloadRaw
     * @param string $signature
     * @param string $timestamp
     * @return array Integração encontrada
     * @throws Exception
     */
    private function authenticateWebhook(
        string $payloadRaw,
        string $signature,
        string $timestamp
    ): array {
        // Validar headers obrigatórios
        if (empty($signature) || empty($timestamp)) {
            // Auditoria: assinatura/timestamp ausentes
            if (isset($this->auditLogger)) {
                $this->auditLogger->logEvent(null, 'olx', 'authentication', 'INVALID_SIGNATURE', $_SERVER['REMOTE_ADDR'] ?? null);
            }
            throw new Exception('Headers X-Webhook-Signature e X-Webhook-Timestamp obrigatórios');
        }

        // Obter configuração do webhook OLX (toda integração OLX)
        // Para Fase 2, usamos apenas UMA integração OLX por tenant
        $stmt = $this->pdo->prepare(
            'SELECT id, tenant_id, credentials FROM portal_integrations 
             WHERE portal_slug = :slug AND enabled = 1'
        );
        $stmt->execute([':slug' => 'olx']);
        $integration = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$integration) {
            // Auditoria: integração não configurada
            if (isset($this->auditLogger)) {
                $this->auditLogger->logEvent(null, 'olx', 'authentication', 'NO_INTEGRATION', $_SERVER['REMOTE_ADDR'] ?? null);
            }
            throw new Exception('Integração OLX não configurada');
        }

        // Decodificar credenciais
        $credentials = json_decode($integration['credentials'] ?? '{}', true);
        $secret = $credentials['webhook_secret'] ?? null;

        if (empty($secret)) {
            if (isset($this->auditLogger)) {
                $this->auditLogger->logEvent($integration['tenant_id'] ?? null, 'olx', 'authentication', 'NO_SECRET', $_SERVER['REMOTE_ADDR'] ?? null);
            }
            throw new Exception('Webhook secret não configurado para OLX');
        }

        // Validar assinatura
        if (!WebhookAuthenticator::validate($payloadRaw, $signature, $timestamp, $secret)) {
            if (isset($this->auditLogger)) {
                $this->auditLogger->logEvent($integration['tenant_id'] ?? null, 'olx', 'authentication', 'INVALID_SIGNATURE', $_SERVER['REMOTE_ADDR'] ?? null);
            }
            throw new Exception('Assinatura de webhook inválida');
        }

        return $integration;
    }

    /**
     * ETAPA 4: Validar se property_id pertence ao tenant
     * 
     * Garante isolamento multi-tenant.
     * 
     * @param int $propertyId
     * @param int $tenantId
     * @throws Exception
     */
    private function validatePropertyOwnership(int $propertyId, int $tenantId): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM properties WHERE id = :id AND tenant_id = :tenant_id LIMIT 1'
        );
        $stmt->execute([
            ':id' => $propertyId,
            ':tenant_id' => $tenantId,
        ]);

        $property = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$property) {
            throw new Exception('Property não encontrada ou não pertence ao tenant');
        }
    }

    /**
     * ETAPA 5: Proteção contra replay attacks
     * 
     * Verifica se evento já foi processado usando webhook_events.
     * 
     * @param string $payloadRaw
     * @param int $tenantId
     * @throws Exception
     */
    private function checkReplay(string $payloadRaw, int $tenantId): void
    {
        $eventHash = hash('sha256', $payloadRaw);

        $stmt = $this->pdo->prepare(
            'SELECT id FROM webhook_events 
             WHERE tenant_id = :tenant_id 
             AND event_type = :type 
             AND entity_type = :entity 
             AND event_hash = :hash 
             LIMIT 1'
        );

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':type' => 'lead_received',
            ':entity' => 'lead',
            ':hash' => $eventHash,
        ]);

        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        // Se evento já existe, é replay — retorna silenciosamente (idempotente)
        if ($existing) {
            throw new Exception('Webhook já processado (replay detected)');
        }
    }

    /**
     * ETAPA 6: Extrair e validar dados do lead
     * 
     * @param array $payload
     * @return array
     * @throws Exception
     */
    private function extractLeadData(array $payload): array
    {
        require_once __DIR__ . '/../../app/utils/InputValidator.php';

        $name = trim((string)($payload['name'] ?? $payload['full_name'] ?? $payload['nome'] ?? ''));
        $email = trim((string)($payload['email'] ?? $payload['email_address'] ?? ''));
        $phone = trim((string)($payload['phone'] ?? $payload['mobile'] ?? $payload['telefone'] ?? ''));
        $message = trim((string)($payload['message'] ?? $payload['comments'] ?? $payload['observations'] ?? ''));

        if ($name === '' || $email === '' || $phone === '' || $message === '') {
            throw new Exception('Campos obrigatórios ausentes: name, email, phone, message');
        }

        // Validar usando InputValidator existente
        $name = \InputValidator::getString($name, 'Nome', true, 100);
        $email = \InputValidator::getEmail($email, 'Email', true);
        $phone = \InputValidator::getString($phone, 'Telefone', true, 30);
        $message = \InputValidator::getText($message, 'Mensagem', true, 5000);

        return [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'message' => $message,
        ];
    }

    /**
     * ETAPA 7: Criar ou atualizar lead via LeadRepository
     * 
     * @param int $tenantId
     * @param int $propertyId
     * @param array $leadData
     * @return int Lead ID
     * @throws Exception
     */
    private function createOrUpdateLead(int $tenantId, int $propertyId, array $leadData): int
    {
        $now = date('Y-m-d H:i:s');

        // Verificar se lead com mesmo email/phone já existe
        $stmt = $this->pdo->prepare(
            'SELECT id FROM leads 
             WHERE tenant_id = :tenant_id 
             AND (email = :email OR phone = :phone) 
             ORDER BY updated_at DESC 
             LIMIT 1'
        );
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':email' => $leadData['email'],
            ':phone' => $leadData['phone'],
        ]);

        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // UPDATE
            $leadId = $existing['id'];
            $stmt = $this->pdo->prepare(
                'UPDATE leads 
                 SET name = :name, 
                     email = :email, 
                     phone = :phone, 
                     property_id = :property_id, 
                     message = :message, 
                     status = :status, 
                     updated_at = :updated_at, 
                     source = :source 
                 WHERE id = :id AND tenant_id = :tenant_id'
            );
            $stmt->execute([
                ':name' => $leadData['name'],
                ':email' => $leadData['email'],
                ':phone' => $leadData['phone'],
                ':property_id' => $propertyId,
                ':message' => $leadData['message'],
                ':status' => 'new',
                ':updated_at' => $now,
                ':source' => 'olx',
                ':id' => $leadId,
                ':tenant_id' => $tenantId,
            ]);
        } else {
            // INSERT
            $stmt = $this->pdo->prepare(
                'INSERT INTO leads 
                (tenant_id, name, email, phone, property_id, message, status, created_at, updated_at, source) 
                VALUES (:tenant_id, :name, :email, :phone, :property_id, :message, :status, :created_at, :updated_at, :source)'
            );
            $stmt->execute([
                ':tenant_id' => $tenantId,
                ':name' => $leadData['name'],
                ':email' => $leadData['email'],
                ':phone' => $leadData['phone'],
                ':property_id' => $propertyId,
                ':message' => $leadData['message'],
                ':status' => 'new',
                ':created_at' => $now,
                ':updated_at' => $now,
                ':source' => 'olx',
            ]);
            $leadId = intval($this->pdo->lastInsertId());
        }

        return $leadId;
    }

    /**
     * ETAPA 8: Registrar evento processado (para replay protection)
     * 
     * @param string $payloadRaw
     * @param int $tenantId
     * @param int $leadId
     */
    private function recordEvent(string $payloadRaw, int $tenantId, int $leadId): void
    {
        $eventHash = hash('sha256', $payloadRaw);

        $stmt = $this->pdo->prepare(
            'INSERT INTO webhook_events 
            (tenant_id, event_type, entity_type, entity_id, event_hash, data) 
            VALUES (:tenant_id, :event_type, :entity_type, :entity_id, :event_hash, :data)'
        );

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':event_type' => 'lead_received',
            ':entity_type' => 'lead',
            ':entity_id' => $leadId,
            ':event_hash' => $eventHash,
            ':data' => json_encode(['event_hash' => $eventHash, 'provider' => 'olx']),
        ]);
    }
}
