<?php
header("Content-Type: application/json");

require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/session_manager.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/TenantMiddleware.php';

use App\Service\UtmService;

try {
    \Api\V1\Middleware\AuthMiddleware::handle();
    $tenantId = \Api\V1\Middleware\TenantMiddleware::resolveTenantId();

    $days = $_GET['days'] ?? 30;
    $campaignId = isset($_GET['campaign_id']) ? filter_var($_GET['campaign_id'], FILTER_VALIDATE_INT) : null;
    $days = (int) $days;
    if ($days <= 0) {
        $days = 30;
    }

    $service = new \App\Service\UtmService($pdo);
    $data = $service->getAnalytics($tenantId, $days, $campaignId !== false && $campaignId !== null ? $campaignId : null);

    http_response_code(200);
    echo json_encode([
        "success" => true,
        "data" => $data
    ]);
} catch (Throwable $e) {
    error_log("Erro em getAnalytics: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
