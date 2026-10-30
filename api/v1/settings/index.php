<?php
/**
 * Fallback para /api/v1/settings/ (tratamento de requests com URL sem arquivo)
 *
 * Redireciona para endpoints específicos:
 * - GET  -> getCompanySettings.php
 * - POST -> saveCompanySettings.php
 */

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        require_once __DIR__ . '/getCompanySettings.php';
        break;
    case 'POST':
        require_once __DIR__ . '/saveCompanySettings.php';
        break;
    case 'OPTIONS':
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        http_response_code(204);
        break;
    default:
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método não permitido. Use GET ou POST.']);
        break;
}
