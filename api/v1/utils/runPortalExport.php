<?php
/**
 * Arquivo de compatibilidade: encaminha para o endpoint real em auth
 * Chamadas antigas usavam /api/v1/utils/runPortalExport.php
 */

$target = __DIR__ . '/../auth/runPortalExport.php';
if (file_exists($target)) {
    require_once $target;
    exit;
}

// Fallback: tenta localizar em outras posições
$alt = dirname(dirname(__DIR__)) . '/auth/runPortalExport.php';
if (file_exists($alt)) {
    require_once $alt;
    exit;
}

// Se nenhum arquivo foi encontrado, retorna erro
http_response_code(500);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => false,
    'message' => 'Endpoint runPortalExport não encontrado',
    'error' => 'The runPortalExport endpoint is not available'
], JSON_UNESCAPED_UNICODE);
exit;


