<?php
require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Observability/RequestTracker.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Auth/JwtService.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Audit/AuditLogger.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Logging/LoggerFactory.php';

use App\Auth\JwtService;
use App\Audit\AuditLogger;
use App\Logging\LoggerFactory;
use App\Observability\RequestTracker;

// RequestTracker::start($_SERVER['HTTP_X_REQUEST_ID'] ?? null); // Removido - não existe
header('Content-Type: application/json; charset=utf-8');

$logger = LoggerFactory::make();
$auditLogger = new AuditLogger($logger);
$jwtService = new JwtService();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método inválido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$accessToken = trim($body['access_token'] ?? '');
$refreshToken = trim($body['refresh_token'] ?? '');
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

if ($accessToken === '' && $refreshToken === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'access_token ou refresh_token é obrigatório'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $userId = null;

    if ($accessToken !== '') {
        $userId = $jwtService->getUserId($accessToken);
        $jwtService->revokeToken($accessToken);
    }

    if ($refreshToken !== '') {
        $userId = $jwtService->getUserId($refreshToken) ?? $userId;
        $jwtService->revokeToken($refreshToken);
    }

    if ($userId === null) {
        $userId = 0;
    }

    $auditLogger->logLogout($userId, $clientIp);

    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Logout concluído'], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    $logger->warning('Logout error', ['request_id' => RequestTracker::getId(), 'error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao efetuar logout'], JSON_UNESCAPED_UNICODE);
}
