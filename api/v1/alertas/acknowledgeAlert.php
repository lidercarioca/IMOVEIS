<?php

use Api\V1\Middleware\AuthMiddleware;
use Api\V1\Middleware\TenantMiddleware;
use App\Services\AlertService;

// Só definir header se não estiver sendo incluído em teste
if (!defined('TESTING_ENDPOINT')) {
    header('Content-Type: application/json');
}

// Só incluir bootstrap se não estiver sendo testado
if (!defined('TESTING_ENDPOINT')) {
    require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/app/bootstrap.php';
    require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/src/Services/AlertService.php';
} else {
    require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config/database.php';
    require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/src/Services/AlertService.php';
    $GLOBALS['pdo'] = $GLOBALS['pdo'] ?? getConnection();
}

try {
    AuthMiddleware::handle();

    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['alert_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID do alerta obrigatório']);
        exit;
    }

    $pdo = $GLOBALS['pdo'];
    $tenantId = TenantMiddleware::resolveTenantId();

    $service = new AlertService($pdo);
    $result = $service->acknowledgeAlert($tenantId, (int)$data['alert_id']);

    if ($result['success']) {
        echo json_encode($result);
    } else {
        http_response_code(400);
        echo json_encode($result);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro interno do servidor',
        'message' => $e->getMessage()
    ]);
}
