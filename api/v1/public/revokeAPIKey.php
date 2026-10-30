<?php

/**
 * Endpoint para revogar uma chave de API
 * POST /api/v1/public/revokeAPIKey.php
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/Repository/ApiKeyRepository.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/Service/ApiKeyService.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/helpers/tenant_isolation_helpers.php';

checkAuth();

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
    $keyId = isset($input['key_id']) ? intval($input['key_id']) : null;

    if (!$keyId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'ID da chave é obrigatório'
        ]);
        exit;
    }

    $pdo = getConnection();
    initializeTenantMiddleware($pdo);
    $tenantId = getCurrentTenantId();

    $apiKeyService = new \App\Service\ApiKeyService($pdo);
    $deleted = $apiKeyService->revokeKeyForUser($tenantId, $userId, $keyId);

    if (!$deleted) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Chave de API não encontrada'
        ]);
        exit;
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Chave de API deletada com sucesso'
    ]);
} catch (Exception $e) {
    error_log('Erro em revokeAPIKey.php: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao revogar chave de API'
    ]);
}
