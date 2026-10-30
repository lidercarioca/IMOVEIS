require_once dirname(dirname(dirname(__DIR__))) . '/src/Observability/RequestTracker.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Security/RateLimiter.php';
$securityLimits = require dirname(dirname(dirname(__DIR__))) . '/config/security.php';
use App\Observability\RequestTracker;
use App\Security\RateLimiter;
$ip = RequestTracker::resolveClientIp();
if ($ip === null) {
http_response_code(400);
echo json_encode([
'success' => false,
'error' => 'Não foi possível validar sua conexão. Tente novamente.',
], JSON_UNESCAPED_UNICODE);
exit;
}
$rateLimiter = new RateLimiter();
$refreshMax = $securityLimits['refresh_max_requests'] ?? 20;
$refreshDecay = $securityLimits['refresh_decay_seconds'] ?? 60;
if (!$rateLimiter->isAllowed($ip, $refreshMax, $refreshDecay)) {
$headerInfo = $rateLimiter->getHeaderInfo($ip, $refreshMax, $refreshDecay);
http_response_code(429);
header('Retry-After: ' . $headerInfo['reset_in']);
echo json_encode([
'success' => false,
'error' => 'Muitas requisições de refresh. Tente novamente em ' . $headerInfo['reset_in'] . ' segundos.',
'limit' => $headerInfo['limit'],
'remaining' => $headerInfo['remaining'],
'reset_in' => $headerInfo['reset_in'],
], JSON_UNESCAPED_UNICODE);
exit;
}
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
$refreshToken = trim($body['refresh_token'] ?? '');
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

if ($refreshToken === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'refresh_token é obrigatório'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $tokens = $jwtService->refreshTokenPair($refreshToken);
    $userId = $jwtService->getUserId($tokens['access']) ?? 0;

    $auditLogger->log(
        AuditLogger::ACTION_UPDATE,
        'Auth',
        $userId,
        ['action' => 'refresh_token', 'ip' => $clientIp],
        $userId
    );

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'access_token' => $tokens['access'],
        'refresh_token' => $tokens['refresh'],
        'expires_in' => JwtService::getDurations()['access'],
        'refresh_expires_in' => JwtService::getDurations()['refresh'],
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    $logger->warning('Refresh token inválido', ['request_id' => RequestTracker::getId(), 'error' => $e->getMessage()]);
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Refresh token inválido ou expirado'], JSON_UNESCAPED_UNICODE);
}
