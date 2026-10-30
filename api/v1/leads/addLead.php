<?php
header("Content-Type: application/json");

require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/utils/InputValidator.php';
require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Infrastructure/Lead/LeadRepository.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Application/Lead/LeadService.php';

use App\Observability\RequestTracker;
use App\Security\RateLimitPolicy;
use App\Security\RateLimiter;

try {
    // ============================================================
    // RECEBER DADOS DO FORMULÃRIO (deve vir antes do rate limit)
    // ============================================================
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);

    if (!$data) {
        throw new Exception("Dados JSON invÃ¡lidos");
    }

    // ============================================================
    // RATE LIMITING: usar EMAIL como identificador (mais justo)
    // ============================================================
    // Email Ã© extraÃ­do aqui para usar no rate limit
    $email_raw = $data['email'] ?? '';

    // Rate limiting por email (mÃ¡ximo 3 leads por hora por email)
    // Email Ã© um identificador mais significativo que IP em ambientes compartilhados
    $endpoint = 'contact_form';
    $config = RateLimitPolicy::getConfig($endpoint);
    $limit = (int) $config['limit'];
    $window = (int) $config['window'];
    $identifier = $email_raw !== '' ? $email_raw : RequestTracker::resolveClientIp();
    if ($email_raw === '' && $identifier === null) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Não foi possível validar sua conexão. Tente novamente.'
        ]);
        exit;
    }
    $resolvedIdentifier = $endpoint . ':' . $identifier;
    $rateLimiter = new RateLimiter();
    if (!$rateLimiter->isAllowed($resolvedIdentifier, $limit, $window)) {
        $headerInfo = $rateLimiter->getHeaderInfo($resolvedIdentifier, $limit, $window);
        http_response_code(429);
        throw new Exception(sprintf(
            'Muitos leads foram criados com este email. Aguarde %d segundos antes de tentar novamente.',
            $headerInfo['reset_in']
        ));
    }

    // ValidaÃ§Ã£o completa dos dados obrigatÃ³rios com InputValidator
    $name = InputValidator::getString($data['name'] ?? '', 'Nome', true, 100);
    $email = InputValidator::getEmail($data['email'] ?? '', 'Email', true);
    $phone = InputValidator::getString($data['phone'] ?? '', 'Telefone', true, 20);
    $message = InputValidator::getText($data['message'] ?? '', 'Mensagem', true, 5000);
    $propertyId = isset($data['property_id']) && $data['property_id'] ? InputValidator::getInt($data['property_id'], 'Property ID', 1) : null;
    $source = InputValidator::getString($data['source'] ?? 'site', 'Fonte', false, 50);
    $status = InputValidator::getString($data['status'] ?? 'new', 'Status', false, 50);
    $utmTrackingId = isset($data['utm_tracking_id']) && $data['utm_tracking_id'] ? InputValidator::getInt($data['utm_tracking_id'], 'UTM ID', 1) : null;

    // Determinar status vÃ¡lido
    $validStatuses = ['new', 'contacted', 'closed', 'cancelled', 'negotiating'];
    if (!in_array($status, $validStatuses, true)) {
        $status = 'new';
    }

    $repository = new \App\Infrastructure\Lead\LeadRepository($pdo);
    $leadService = new \App\Application\Lead\LeadService($repository);

    $tenantId = null;
    $submittedTenantId = isset($data['tenant_id']) && $data['tenant_id'] ? intval($data['tenant_id']) : null;
    $tenantContext = $leadService->resolveTenantForLeadSubmission($propertyId, $submittedTenantId);
    $tenantId = $tenantContext['tenant_id'];
    $propertyId = $tenantContext['property_id'];

    $leadPayload = [
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'message' => $message,
        'property_id' => $propertyId,
        'status' => $status,
        'source' => $source,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
        'tenant_id' => $tenantId,
    ];

    $result = $leadService->submitLeadFromContact($leadPayload, $utmTrackingId);
    $leadId = $result['lead_id'];
    $isNewLead = $result['is_new'];
    $propertyTitle = $result['property_title'] ?? null;
    $assignedUserId = $result['assigned_user_id'] ?? null;

    if (!$isNewLead) {
        error_log("[addLead] Lead existente atualizado: id {$leadId} para tenant {$tenantId}");
    }

    http_response_code(200);
    echo json_encode([
        "success" => true,
        "message" => "Lead adicionado com sucesso",
        "data" => [
            "lead_id" => $leadId,
            "name" => $name,
            "email" => $email
        ]
    ]);
} catch (Exception $e) {
    error_log("Erro ao adicionar lead: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erro ao adicionar lead",
        "error" => $e->getMessage()
    ]);
} catch (PDOException $e) {
    error_log("Erro PDO ao adicionar lead: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erro no banco de dados",
        "error" => "Falha ao salvar o lead"
    ]);
}
