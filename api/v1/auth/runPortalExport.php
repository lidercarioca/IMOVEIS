<?php
// Endpoint para executar export de propriedades e (opcional) push ao portal
// Recebe JSON { portal, format, limit, endpoint, api_key, push }

require_once dirname(dirname(dirname(__DIR__))) . '/config/session_manager.php';
require_once dirname(dirname(dirname(__DIR__))) . '/auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/security/TenantContext.php';
require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/integrations.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/integrations/PortalManager.php';

header('Content-Type: application/json; charset=utf-8');

TenantContext::initialize();
$tenantId = TenantContext::getTenantId();

if (!$tenantId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Empresa não selecionada']);
    exit;
}

if (!isAdmin()) {
    // Silenciosamente retorna sucesso vazio (sem expor tentativa de export)
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Operação concluída', 'data' => []]);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) $data = $_POST;

$portal = $data['portal'] ?? 'zap';
$format = strtolower($data['format'] ?? 'xml');
$limit = isset($data['limit']) ? intval($data['limit']) : 500;
$endpoint = $data['endpoint'] ?? null;
$apiKey = $data['api_key'] ?? null;
$push = !empty($data['push']) ? true : false;

try {
    $cfg = require __DIR__ . '/../config/integrations.php';
    $manager = new PortalManager($pdo, $cfg, $tenantId);
    $response = $manager->runManualExport($portal, $format, $limit, $endpoint, $apiKey, $push, null, $_SESSION['user']['id'] ?? null);

    echo json_encode($response);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    require_once dirname(dirname(dirname(__DIR__))) . '/app/utils/logger_functions.php';
    log_user_action('export_run_failed', ['portal' => $portal, 'error' => $e->getMessage()]);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
