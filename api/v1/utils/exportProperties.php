<?php
require dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require dirname(dirname(dirname(__DIR__))) . '/app/MvcBootstrap.php';

use App\Service\PropertyService;

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="properties.csv"');

try {
    if (!\App\Security\Auth::check()) {
        // Permitir acesso via API key (FEED_API_KEY) se fornecida
        $apiKey = $_GET['api_key'] ?? $_POST['api_key'] ?? null;
        $feedApiKey = $_ENV['FEED_API_KEY'] ?? null;

        if (empty($apiKey) || empty($feedApiKey) || $apiKey !== $feedApiKey) {
            http_response_code(401);
            die('Unauthorized');
        }
    }

    $db = \App\MvcServices::getDatabase();
    $service = new PropertyService($db);
    $properties = $service->getExportProperties();
    if (!empty($properties)) {
        $columns = array_keys($properties[0]);
        echo implode(',', $columns) . "\n";
        foreach ($properties as $property) {
            echo implode(',', array_map(function ($v) {
                return '"' . addslashes($v) . '"';
            }, $property)) . "\n";
        }
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo 'Error: ' . $e->getMessage();
}
