<?php
// Endpoint para gerenciar as configurações de integração com portais imobiliários
// GET: lista integrações
// POST: salva/ativa ou desativa integração

require_once dirname(dirname(dirname(__DIR__))) . '/config/session_manager.php';
require_once dirname(dirname(dirname(__DIR__))) . '/auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/security/TenantContext.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/integrations.php';
require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';

use App\Infrastructure\PortalIntegration\PortalIntegrationRepository;
use App\Application\Integration\PortalIntegrationService;

header('Content-Type: application/json; charset=utf-8');

require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
$hasBearer = is_string($authHeader) && str_starts_with(trim($authHeader), 'Bearer ');
$authContext = \App\Auth\AuthContext::getInstance();

if ($hasBearer) {
    $request = [
        'headers' => ['Authorization' => $authHeader],
        'query' => $_GET,
        'path' => $_SERVER['REQUEST_URI'] ?? ''
    ];

    $middleware = new \App\Middleware\AuthMiddleware();
    $res = $middleware->handle($request, function ($r) {
        return ['ok' => true];
    });

    if (isset($res['status']) && $res['status'] !== 200 && isset($res['error'])) {
        http_response_code((int)$res['status']);
        echo json_encode(['success' => false, 'error' => $res['error']]);
        exit;
    }
} else {
    checkAuth();
}

if (!$authContext->isAuthenticated() && !empty($_SESSION['user_id'] ?? '')) {
    $authContext->authenticate((int)$_SESSION['user_id'], $_SESSION['role'] ?? 'user');
}

if (!$authContext->isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

TenantContext::initialize();

if (!$authContext->isAdmin() && !$authContext->isSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acesso negado']);
    exit;
}

$tenantId = TenantContext::getTenantId();
if (!$tenantId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Tenant não selecionado']);
    exit;
}

$pdo = getConnection();

// Inicializar Repository e Service
$repository = new PortalIntegrationRepository($pdo);
$repository->ensureTableExists();
$service = new PortalIntegrationService($repository);

$method = $_SERVER['REQUEST_METHOD'];

$availableConfig = require dirname(dirname(dirname(__DIR__))) . '/config/integrations.php';

function buildPortalList(array $config, array $savedSettings): array
{
    $portals = [];
    foreach ($config as $portalSlug => $portalConfig) {
        if ($portalSlug === 'base_url' || $portalSlug === 'defaults') {
            continue;
        }

        if (!is_array($portalConfig) || empty($portalConfig['name'])) {
            continue;
        }

        $saved = $savedSettings[$portalSlug] ?? null;
        $credentialsTemplate = $portalConfig['credentials'] ?? [];
        $storedCredentials = [];
        foreach ($credentialsTemplate as $credentialKey => $defaultValue) {
            $storedCredentials[$credentialKey] = $saved && is_array($saved['credentials']) && !empty($saved['credentials'][$credentialKey]);
        }

        $credentialFieldsMeta = [];
        if (!empty($portalConfig['credential_fields_meta']) && is_array($portalConfig['credential_fields_meta'])) {
            foreach ($portalConfig['credential_fields_meta'] as $meta) {
                if (is_array($meta) && !empty($meta['name'])) {
                    $credentialFieldsMeta[] = $meta;
                }
            }
        }

        $portals[] = [
            'portal' => $portalSlug,
            'name' => $portalConfig['name'],
            'description' => $portalConfig['description'] ?? '',
            'icon' => $portalConfig['icon'] ?? 'fas fa-plug',
            'iconColor' => $portalConfig['icon_color'] ?? 'text-blue-600',
            'category' => $portalConfig['category'] ?? 'Portais Imobiliários',
            'status' => $portalConfig['status'] ?? 'available',
            'comingSoon' => isset($portalConfig['status']) && $portalConfig['status'] === 'coming_soon',
            'enabled' => $saved ? $saved['enabled'] : (!empty($portalConfig['enabled'])),
            'endpoint' => $saved ? $saved['endpoint'] : ($portalConfig['endpoint'] ?? ''),
            'hide_endpoint' => !empty($portalConfig['hide_endpoint']),
            'credential_fields' => array_keys($credentialsTemplate),
            'credential_fields_meta' => $credentialFieldsMeta,
            'credentials' => $saved && is_array($saved['credentials']) ? $saved['credentials'] : [],
            'stored_credentials' => $storedCredentials,
        ];
    }
    return $portals;
}

if ($method === 'GET') {
    $savedSettings = $service->getPortalIntegrationsForTenant($tenantId);
    $portals = buildPortalList($availableConfig, $savedSettings);

    echo json_encode(['success' => true, 'data' => ['integrations' => $portals]]);
    exit;
}

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $portalSlug = strtolower(trim($input['portal'] ?? ''));
    if (empty($portalSlug) || !isset($availableConfig[$portalSlug])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Portal inválido']);
        exit;
    }

    $portalConfig = $availableConfig[$portalSlug];
    $enabled = isset($input['enabled']) ? boolval($input['enabled']) : false;
    $endpoint = trim($input['endpoint'] ?? '');
    $credentials = [];
    $credentialsKeys = array_keys($portalConfig['credentials'] ?? []);
    $submittedCredentials = is_array($input['credentials']) ? $input['credentials'] : [];
    $existingIntegration = $service->getPortalIntegration($tenantId, $portalSlug);

    foreach ($credentialsKeys as $key) {
        $value = trim((string)($submittedCredentials[$key] ?? ''));
        if ($value === '' && $existingIntegration && isset($existingIntegration['credentials'][$key]) && $existingIntegration['credentials'][$key] !== '') {
            $credentials[$key] = $existingIntegration['credentials'][$key];
            continue;
        }
        $credentials[$key] = $value;
    }

    try {
        $service->savePortalIntegration($tenantId, $portalSlug, $enabled, $endpoint, $credentials);
        echo json_encode(['success' => true, 'message' => 'Configuração salva com sucesso']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Falha ao salvar configuração', 'details' => $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Método não suportado']);
exit;
