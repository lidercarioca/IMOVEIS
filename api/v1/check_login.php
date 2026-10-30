<?php

/**
 * Verificar quem está logado no navegador
 */
require_once __DIR__ . '/../../config/session_manager.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../app/helpers/tenant_isolation_helpers.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['error' => 'Não autenticado']);
        exit;
    }

    $pdo = getConnection();
    initializeTenantMiddleware($pdo);
    $tenantId = (int) getCurrentTenantId();
    $userId = (int) $_SESSION['user_id'];

    if ($tenantId <= 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Contexto de tenant inválido']);
        exit;
    }

    $isAdmin = isAdmin();
    $service = new \App\Service\CheckLoginService($pdo);

    try {
        $payload = $service->getLoginStatus($userId, $tenantId, $isAdmin);
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(403);
        echo json_encode(['error' => $e->getMessage()]);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
