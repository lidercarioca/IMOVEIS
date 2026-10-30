<?php
/**
 * Endpoint de teste - Verificar se API está respondendo
 */

require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';

use App\Http\ApiResponse;

// Detectar se quer resposta padronizada
$useStandardResponse = isset($_GET['standard_response']) && $_GET['standard_response'] === '1';

// Preparar dados de status
$statusData = [
    'status' => 'ok',
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => PHP_VERSION,
    'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
];

// Usar resposta padronizada se solicitado
if ($useStandardResponse) {
    ApiResponse::success(
        $statusData,
        'API respondendo normalmente',
        ['uptime' => 'OK', 'environment' => 'production']
    );
}

// Resposta legada (padrão)
header('Content-Type: application/json');
http_response_code(200);

echo json_encode($statusData, JSON_PRETTY_PRINT);
