<?php
require_once dirname(dirname(dirname(__DIR__))) . '/api/check_auth.php';
checkApiAuth();

require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';

use App\Service\PropertyService;

header('Content-Type: application/json; charset=utf-8');

try {
    // Inicializar isolamento de tenant
    initializeTenantMiddleware($pdo);
    $tenantId = getCurrentTenantId();

    // Instanciar novo service (src/Service)
    $service = new PropertyService($pdo);

    $categories = $service->getPropertyCategories($tenantId);

    echo json_encode([
        'success' => true,
        'data' => $categories
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar categorias de imóveis: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
