<?php

/**
 * Endpoint para criar uma nova chave de API
 * POST /api/v1/public/createAPIKey.php
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/Repository/ApiKeyRepository.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/Service/ApiKeyService.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/helpers/tenant_isolation_helpers.php';

checkAuth();
initializeTenantMiddleware($GLOBALS['pdo']);
$tenantId = getCurrentTenantId();

header('Content-Type: application/json');

try {
    $authContext = \App\Auth\AuthContext::getInstance();
    $userId = (int) $authContext->getUserId();

    if (!$userId) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Usuário não autenticado'
        ]);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    $name = isset($input['name']) ? trim((string) $input['name']) : null;
    $expiresAt = isset($input['expires_at']) ? $input['expires_at'] : null;

    if (!$name || empty($name)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Nome da chave é obrigatório'
        ]);
        exit;
    }

    if (strlen($name) > 100) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Nome da chave não pode ter mais de 100 caracteres'
        ]);
        exit;
    }

    $apiKeyService = new \App\Service\ApiKeyService($GLOBALS['pdo']);
    $createdKey = $apiKeyService->createKeyForTenant($tenantId, $userId, $name, $expiresAt);

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Chave de API criada com sucesso',
        'id' => $createdKey['id'],
        'key' => $createdKey['key'],
        'name' => $createdKey['name']
    ]);
} catch (Exception $e) {
    error_log('Erro em createAPIKey.php: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao criar chave de API'
    ]);
}
