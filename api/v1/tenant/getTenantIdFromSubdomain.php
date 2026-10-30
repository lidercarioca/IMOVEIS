<?php

/**
 * API Endpoint: Retorna o TENANT_ID baseado no subdomínio
 * 
 * Usado como fallback quando o servidor não injeta a variável
 * ou para o front-end validar/confirmar o tenant_id correto
 */

require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/app/bootstrap.php';

use App\Application\Tenant\TenantResolutionService;

header('Content-Type: application/json; charset=UTF-8');

try {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $service = new TenantResolutionService($GLOBALS['pdo']);
    $result = $service->resolveFromHost($host);

    http_response_code($result['success'] ? 200 : 404);
    echo json_encode([
        'success' => $result['success'],
        'tenant_id' => $result['tenant_id'],
        'subdomain' => $result['subdomain'],
        'host' => $result['host'],
        'message' => $result['message'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'tenant_id' => null,
        'message' => 'Erro ao detectar tenant_id sem fallback legado.'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
