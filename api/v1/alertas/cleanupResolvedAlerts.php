<?php

/**
 * POST /api/v1/alertas/cleanupResolvedAlerts.php
 * Remove alertas marcados como RESOLVIDOS do banco de dados
 */

// PRIMEIRA COISA: Limpar buffers E colocar headers JSON
while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Iniciar buffer para capturar qualquer output dos includes
ob_start();

try {
    $basePath = dirname(dirname(dirname(dirname(__FILE__))));

    require_once $basePath . '/vendor/autoload.php';
    require_once $basePath . '/app/bootstrap.php';

    $includeOutput = ob_get_clean();
    if (!empty(trim($includeOutput))) {
        error_log("[CLEANUP] Output dos includes: " . substr($includeOutput, 0, 100));
    }

    \Api\V1\Middleware\AuthMiddleware::handle();

    $pdo = $GLOBALS['pdo'] ?? null;
    if (!$pdo) {
        throw new Exception('Conexão PDO não disponível');
    }

    $tenantId = \Api\V1\Middleware\TenantMiddleware::resolveTenantId();

    $authContext = \App\Auth\AuthContext::getInstance();
    $isAdmin = $authContext->isAdmin();
    if (!$isAdmin) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Permissão negada',
            'message' => 'Apenas administradores podem limpar alertas resolvidos'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $service = new \App\Services\AlertService($pdo);
    $result = $service->cleanupResolvedAlerts($tenantId);

    http_response_code($result['success'] ? 200 : 500);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    error_log("[CLEANUP ERROR] " . $e->getMessage() . " [" . $e->getFile() . ":" . $e->getLine() . "]");

    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getFile() . ':' . $e->getLine(),
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
