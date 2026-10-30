<?php
header('Content-Type: application/json');
require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/session_manager.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';

use App\Service\PropertyService;

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id) {
    echo json_encode([]);
    exit;
}

// Resolver tenant a partir do contexto do servidor (autoritativo).
// Para usuários autenticados, manter o comportamento atual.
// Para públicos, tentar resolver o tenant pelo mecanismo existente
// (host/subdomínio / chave de API / rota já implementada). Se não
// for possível resolver, negar acesso — NÃO usar tenant vindo do cliente.
initializeTenantMiddleware($pdo);
$tenantId = null;
if (isLoggedIn()) {
    // usuário autenticado: contexto da sessão é autoritativo
    $tenantId = getCurrentTenantId();
} else {
    // público: usar o resolvedor de request (host/subdomínio/API key)
    $tenantId = getRequestTenantId();
    $resolutionSource = class_exists('TenantContext') ? \TenantContext::getResolutionSource() : null;

    if (
        $resolutionSource === \App\Tenant\TenantResolver::SOURCE_FALLBACK ||
        !$tenantId ||
        $tenantId <= 0
    ) {
        // Não revelar informação — negar com 403 e resposta vazia JSON
        http_response_code(403);
        echo json_encode([]);
        exit;
    }
}

try {
    $service = new PropertyService($pdo);
    $images = $service->getPropertyImages($id, $tenantId);
    echo json_encode($images);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([]);
}
