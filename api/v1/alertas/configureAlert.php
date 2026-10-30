<?php

use App\Services\AlertConfigService;

header('Content-Type: application/json');

try {
    require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/vendor/autoload.php';
    require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config/database.php';
    require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/app/helpers/tenant_isolation_helpers.php';
    require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/auth.php';
    require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/src/Services/AlertConfigService.php';

    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Não autenticado']);
        exit;
    }

    if (!function_exists('initializeTenantMiddleware') || !function_exists('getCurrentTenantId')) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'TenantId indisponível para AlertConfig neste endpoint. Requer etapa específica de ajuste do chamador.'
        ]);
        exit;
    }

    initializeTenantMiddleware($pdo);
    $tenantId = (int)getCurrentTenantId();
    if ($tenantId <= 0) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'TenantId inválido para AlertConfig neste endpoint. Requer etapa específica de ajuste do chamador.'
        ]);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['alert_type'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'alert_type é obrigatório']);
        exit;
    }

    $threshold = isset($data['threshold']) ? (int)$data['threshold'] : 0;
    $enabled = isset($data['enabled']) ? (bool)$data['enabled'] : true;

    $service = new AlertConfigService($pdo);
    $saved = $service->saveConfig($tenantId, (int)$_SESSION['user_id'], $data['alert_type'], $threshold, $enabled);

    if ($saved) {
        echo json_encode([
            'success' => true,
            'message' => 'Configuração de alerta salva com sucesso'
        ]);
        return;
    }

    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao salvar configuração']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao processar requisição',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
