<?php
require dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require dirname(dirname(dirname(__DIR__))) . '/app/MvcBootstrap.php';
require dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';

use App\Http\ApiResponse;
use App\Service\PropertyService;

// Detectar se quer resposta padronizada
$useStandardResponse = isset($_GET['standard_response']) && $_GET['standard_response'] === '1';

// Headers para resposta legada (se não usar padronizada)
if (!$useStandardResponse) {
    header('Content-Type: application/json; charset=utf-8');
}

try {
    $db = \App\MvcServices::getDatabase();
    $service = new PropertyService($db);

    $properties = $service->getFormProperties();

    // Usar resposta padronizada se solicitado
    if ($useStandardResponse) {
        ApiResponse::success(
            $properties,
            'Dados do formulário de imóvel carregados com sucesso',
            ['count' => count($properties), 'limit' => 5]
        );
    }

    // Resposta legada (padrão)
    echo json_encode(['success' => true, 'data' => $properties], JSON_UNESCAPED_UNICODE);
} catch (\Exception $e) {
    // Usar resposta padronizada se solicitado
    if ($useStandardResponse) {
        ApiResponse::error(
            'Erro ao buscar dados do formulário de imóvel',
            500,
            [],
            null
        );
    }

    // Resposta legada (padrão)
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
