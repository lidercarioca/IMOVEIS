<?php
// portalFeed.php - agora exige API Key (X-API-Key) e resolve tenant pelo registro da chave
// Não aceita tenant_id via request

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/integrations.php';
require_once __DIR__ . '/../../../src/Repository/ApiKeyRepository.php';
require_once __DIR__ . '/../../../src/Service/ApiKeyService.php';
require_once __DIR__ . '/../../../app/integrations/PortalManager.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Parâmetros obrigatórios da request
    $portal = strtolower(trim($_GET['portal'] ?? ''));
    $format = strtolower(trim($_GET['format'] ?? 'xml'));

    if (empty($portal)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Portal inválido']);
        exit;
    }

    $allowedFormats = ['xml', 'json'];
    if (!in_array($format, $allowedFormats, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Formato inválido']);
        exit;
    }

    // Validação de API Key via service/repositório
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? null;
    if (!$apiKey) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'API Key inválida.']);
        exit;
    }

    $apiKeyService = new \App\Service\ApiKeyService($GLOBALS['pdo']);
    $apiKeyRecord = $apiKeyService->validateKey($apiKey);

    if (!$apiKeyRecord) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'API Key inválida.']);
        exit;
    }

    $tenantId = isset($apiKeyRecord['tenant_id']) ? intval($apiKeyRecord['tenant_id']) : 0;
    if ($tenantId <= 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Tenant não definido para esta chave de API']);
        exit;
    }

    // Gerar payload usando PortalManager — tenant vem exclusivamente da api_keys
    $cfg = require __DIR__ . '/../../../config/integrations.php';
    $manager = new PortalManager($pdo, $cfg, $tenantId);
    $payload = $manager->generatePayload($portal, $format, 500);
    $responseIsJson = in_array(strtolower($portal), ['adopt', 'meta_anuncios', 'google_tag_manager', 'active_campaign', 'mailchimp'], true) || $format === 'json';

    if ($responseIsJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo $payload;
        exit;
    }

    header('Content-Type: application/xml; charset=utf-8');
    echo $payload;
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
