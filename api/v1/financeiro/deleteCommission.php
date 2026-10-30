<?php

/**
 * API para deletar comissão (Admin only)
 * DELETE /api/v1/financeiro/deleteCommission.php
 */

// Definir headers JSON e no-cache ANTES de qualquer output
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once dirname(dirname(dirname(__DIR__))) . '/api/check_auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/auth.php';
checkApiAuth();

$authContext = \App\Auth\AuthContext::getInstance();
$isAdmin = $authContext->isAuthenticated() ? $authContext->isAdmin() : (function_exists('isAdmin') ? isAdmin() : false);

// Apenas admin pode deletar
if (!$isAdmin) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Apenas administradores podem deletar comissões'
    ]);
    exit;
}

require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Service/PropertyService.php';

initializeTenantMiddleware($pdo);
$tenantId = getCurrentTenantId();

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['commission_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'ID da comissão não fornecido'
        ]);
        exit;
    }

    $commission_id = intval($input['commission_id']);
    $service = new \App\Services\FinanceService($pdo);
    $response = $service->deleteCommission($commission_id, $isAdmin);

    if (!$response['success']) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $response['error']
        ]);
        exit;
    }

    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
