<?php
// Inicializar sessão com configurações de segurança
require_once dirname(dirname(__DIR__)) . '/config/session_manager.php';
require_once dirname(dirname(__DIR__)) . '/api/check_auth.php';
checkApiAuth();

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/config/smtp.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/tenant_isolation_helpers.php';
require_once dirname(dirname(__DIR__)) . '/app/middleware/api_auth.php';

checkApiAuth();

// Initialize tenant isolation
global $pdo;
initializeTenantMiddleware($pdo);
$tenantId = getCurrentTenantId();

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? null;

if ($action === 'configure') {
    $actorId = \App\Auth\AuthContext::getInstance()->getUserId();
    if (!$actorId) {
        http_response_code(401);
        die(json_encode(['success' => false, 'error' => 'Não autenticado', 'debug' => 'AuthContext user_id: ' . ($actorId ?? 'none')]));
    }

    $userId = (int) $actorId;
    $alertType = $_POST['alert_type'] ?? null;
    $threshold = isset($_POST['threshold']) ? (int) $_POST['threshold'] : 0;
    $enabled = isset($_POST['enabled']) ? (bool) $_POST['enabled'] : false;

    if (!$alertType) {
        http_response_code(400);
        die(json_encode(['success' => false, 'error' => 'alert_type é obrigatório']));
    }

    try {
        $service = new \App\Services\AlertConfigService($pdo);
        $saved = $service->saveConfig($tenantId, $userId, $alertType, $threshold, $enabled);

        if ($saved) {
            echo json_encode(['success' => true, 'message' => 'Alerta configurado com sucesso']);
            return;
        }

        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro ao salvar configuração de alerta']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} elseif ($action === 'get_config') {
    $actorId = \App\Auth\AuthContext::getInstance()->getUserId();
    if (!$actorId) {
        echo json_encode(['success' => true, 'data' => []]);
        exit;
    }

    try {
        $service = new \App\Services\AlertConfigService($pdo);
        $configs = $service->getUserConfigs($tenantId, (int) $actorId);

        echo json_encode(['success' => true, 'data' => array_map(static function (array $config): array {
            return [
                'id' => $config['id'] ?? null,
                'user_id' => $config['user_id'] ?? null,
                'alert_type' => $config['alert_type'] ?? null,
                'threshold' => isset($config['threshold']) ? (int) $config['threshold'] : 0,
                'enabled' => isset($config['enabled']) ? (bool) $config['enabled'] : false,
                'created_at' => $config['created_at'] ?? null,
                'updated_at' => $config['updated_at'] ?? null,
            ];
        }, $configs)]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ação não especificada']);
}
