<?php

require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config/session_manager.php';

/**
 * POST /api/v1/alertas/processAlerts.php
 *
 * Processa alertas inteligentes (requer autenticação admin)
 * Pode ser executado manualmente ou via cron job
 *
 * Exemplo de uso em cron:
 * 0 2 * * * /usr/bin/php /var/www/htdocs/api/v1/alertas/processAlerts.php
 */

header('Content-Type: application/json');

try {
    require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/vendor/autoload.php';
    require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config/database.php';
    require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/app/bootstrap.php';
    require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/app/helpers/tenant_isolation_helpers.php';

    if (!isLoggedIn()) {
        $apiKey = $_GET['api_key'] ?? $_POST['api_key'] ?? '';
        if ($apiKey !== getenv('ALERTS_CRON_KEY') || !getenv('ALERTS_CRON_KEY')) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Não autenticado']);
            exit;
        }
    } else {
        if (!isAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Permissão negada']);
            exit;
        }
    }

    $tenantId = getCurrentTenantId();
    $userId = $_SESSION['user_id'] ?? null;
    $isAdmin = isLoggedIn() && isAdmin();

    $service = new \App\Services\AlertService($pdo);
    $result = $service->processAllAlerts($tenantId, $userId, $isAdmin);

    http_response_code($result['success'] ? 200 : 500);
    echo json_encode($result);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao processar alertas',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
