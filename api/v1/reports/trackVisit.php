<?php
header("Content-Type: application/json");

require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/utils/logger_functions.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/utils/InputValidator.php';
require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';

use App\Observability\RequestTracker;
use App\Security\RateLimitPolicy;
use App\Security\RateLimiter;
use App\Security\TenantContext;

try {
    $tenantId = null;
    if (class_exists(TenantContext::class)) {
        TenantContext::initialize();
        $tenantId = TenantContext::getTenantId();
    } elseif (class_exists('TenantContext')) {
        \TenantContext::initialize();
        $tenantId = \TenantContext::getTenantId();
    }

    $clientIp = RequestTracker::resolveClientIp();
    if ($clientIp === null) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Não foi possível validar sua conexão. Tente novamente.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $rateIdentifier = $clientIp;
    if ($tenantId !== null && (int)$tenantId > 0) {
        $rateIdentifier = 'tenant_' . (int)$tenantId . ':api_call';
    }

    $endpoint = 'api_call';
    $config = RateLimitPolicy::getConfig($endpoint);
    $limit = (int) $config['limit'];
    $window = (int) $config['window'];
    $resolvedIdentifier = $endpoint . ':' . $rateIdentifier;
    $rateLimiter = new RateLimiter();
    $headerInfo = $rateLimiter->getHeaderInfo($resolvedIdentifier, $limit, $window);
    if (!$rateLimiter->isAllowed($resolvedIdentifier, $limit, $window)) {
        http_response_code(429);
        echo json_encode([
            "success" => false,
            "message" => "Muitas requisições. Aguarde alguns segundos.",
            "retry_after" => $headerInfo['reset_in']
        ]);
        exit;
    }

    // Receber os dados
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['property_id'])) {
        throw new Exception("ID da propriedade é obrigatório");
    }

    $propertyId = InputValidator::getInt($data['property_id'], 'ID da propriedade', 1);
    $source = InputValidator::getString($data['source'] ?? 'unknown', 'Fonte', false, 50);
    $userId = isset($data['user_id']) && $data['user_id'] ? InputValidator::getInt($data['user_id'], 'ID do usuário', 1) : null;
    $visitedAt = date('Y-m-d H:i:s');

    $reportService = new \App\Service\ReportService($pdo);
    $result = $reportService->trackVisit([
        'property_id' => $propertyId,
        'source' => $source,
        'user_id' => $userId,
        'visited_at' => $visitedAt,
    ]);

    http_response_code(200);
    echo json_encode($result);
} catch (Exception $e) {
    error_log("Erro ao registrar visita: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erro ao registrar visita",
        "error" => $e->getMessage()
    ]);
}
