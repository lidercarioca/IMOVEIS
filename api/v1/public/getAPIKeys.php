<?php

/**
 * Endpoint para recuperar todas as chaves de API do usuário ou admin
 * GET /api/v1/public/getAPIKeys.php
 */

// Usar __DIR__ para caminho absoluto (funciona em CLI e HTTP)
$rootPath = dirname(dirname(dirname(dirname(__FILE__))));
require_once $rootPath . '/auth.php';
require_once $rootPath . '/config/database.php';
require_once $rootPath . '/src/Repository/ApiKeyRepository.php';
require_once $rootPath . '/src/Service/ApiKeyService.php';
require_once $rootPath . '/app/helpers/tenant_isolation_helpers.php';

checkAuth();
initializeTenantMiddleware($pdo);
$tenantId = getCurrentTenantId();

header('Content-Type: application/json');

try {
    global $pdo;

    $authContext = \App\Auth\AuthContext::getInstance();
    $userId = (int) $authContext->getUserId();

    $apiKeyService = new \App\Service\ApiKeyService($pdo);
    $keys = $apiKeyService->listKeysForUser($tenantId, $userId);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'keys' => $keys,
        'total' => count($keys)
    ]);
} catch (Exception $e) {
    error_log('Erro em getAPIKeys.php: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao recuperar chaves de API'
    ]);
}
