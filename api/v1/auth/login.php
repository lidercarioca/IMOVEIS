<?php
require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Infrastructure/User/UserRepository.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Application/User/UserService.php';

use App\Infrastructure\User\UserRepository;
use App\Application\User\UserService;
use App\Observability\RequestTracker;
use App\Security\RateLimiter;

header('Content-Type: application/json; charset=utf-8');

// Carregar limites de segurança
$securityLimits = require dirname(dirname(dirname(__DIR__))) . '/config/security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método inválido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
error_log('AUTH_LOGIN payload keys: ' . implode(',', array_keys($body)));
$username = trim($body['username'] ?? $body['email'] ?? $body['login'] ?? '');
$password = $body['password'] ?? $body['senha'] ?? '';

$ip = RequestTracker::resolveClientIp();
if ($ip === null) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Não foi possível validar sua conexão. Tente novamente.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Identificador para rate limit: IP + username/email (protege contra brute force distribuído)
$rateId = $ip . '|' . strtolower($username);

// Inicializar RateLimiter
$rateLimiter = new RateLimiter();
$loginMaxAttempts = $securityLimits['login_max_attempts'] ?? 5;
$loginDecay = $securityLimits['login_decay_seconds'] ?? 60;

// Checar limite antes de autenticar
if (!$rateLimiter->isAllowed($rateId, $loginMaxAttempts, $loginDecay)) {
    http_response_code(429);
    $headerInfo = $rateLimiter->getHeaderInfo($rateId, $loginMaxAttempts, $loginDecay);
    header('Retry-After: ' . $headerInfo['reset_in']);
    echo json_encode([
        'success' => false,
        'error' => 'Muitas tentativas de login. Tente novamente em ' . $headerInfo['reset_in'] . ' segundos.',
        'limit' => $headerInfo['limit'],
        'remaining' => $headerInfo['remaining'],
        'reset_in' => $headerInfo['reset_in'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($username === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Usuário e senha são obrigatórios'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    global $pdo; // $pdo é definido globalmente no database.php
    $userRepository = new UserRepository($pdo);
    $userService = new UserService($userRepository);
    $user = $userService->authenticateUser($username, $password);

    error_log('AUTH_LOGIN user found: ' . ($user ? 'true' : 'false'));
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Usuário ou senha inválidos'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    error_log('AUTH_LOGIN password validated: true');

    // Tentar gerar tokens JWT se disponível

    // Tentar gerar tokens JWT se disponível
    $tokens = null;
    $jwt_error = null;
    try {
        require_once dirname(dirname(dirname(__DIR__))) . '/src/Auth/JwtService.php';
        $jwtService = new App\Auth\JwtService();
        $tokens = $jwtService->generateTokenPair((int)$user['id'], $user['role']);
    } catch (\Throwable $jwtError) {
        $jwt_error = $jwtError->getMessage();
        error_log("JWT generation failed: " . $jwt_error);
    }

    http_response_code(200);
    $response = [
        'success' => true,
        'user' => [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
        ],
    ];

    // Adiciona tokens JWT se disponíveis
    $generatedTokens = false;
    if (is_array($tokens) && !empty($tokens['access']) && !empty($tokens['refresh'])) {
        $response['access_token'] = $tokens['access'];
        $response['refresh_token'] = $tokens['refresh'];
        $response['expires_in'] = 3600; // 1 hora padrão
        $response['refresh_expires_in'] = 86400; // 24 horas padrão
        $generatedTokens = true;
    } else if ($jwt_error) {
        $response['jwt_error'] = $jwt_error;
    }
    error_log('AUTH_LOGIN tokens generated: ' . ($generatedTokens ? 'true' : 'false'));
    error_log('AUTH_LOGIN response contains access_token: ' . (!empty($response['access_token']) ? 'true' : 'false'));
    error_log('AUTH_LOGIN response contains refresh_token: ' . (!empty($response['refresh_token']) ? 'true' : 'false'));

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log("Auth login error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno do servidor'], JSON_UNESCAPED_UNICODE);
}
