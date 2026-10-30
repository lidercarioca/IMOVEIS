<?php
require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/session_manager.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';

checkAuth();

header('Content-Type: application/json');

try {
    $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
    if (!$id || $id <= 0) {
        throw new Exception('ID inválido ou não fornecido');
    }

    $tenantId = getRequestTenantId();
    $bannerService = new \App\Service\BannerService($pdo);
    $bannerService->deleteBanner($tenantId, $id);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log("Erro ao excluir banner: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
