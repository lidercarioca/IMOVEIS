<?php
header('Content-Type: application/json; charset=utf-8');
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/utils/mailer.php';
require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Infrastructure/User/UserRepository.php';

use App\Infrastructure\User\UserRepository;
use App\Observability\RequestTracker;
use App\Security\RateLimitPolicy;
use App\Security\RateLimiter;

// Cria tabela de tokens se não existir
try {
    $userRepository = new UserRepository($pdo);
    $userRepository->ensurePasswordResetTableExists();
} catch (Exception $e) {
    error_log('Erro ao criar tabela password_resets: ' . $e->getMessage());
}

try {
    // Rate limiting por IP
    $endpoint = 'password_reset';
    $config = RateLimitPolicy::getConfig($endpoint);
    $limit = (int) $config['limit'];
    $window = (int) $config['window'];
    $clientIp = RequestTracker::resolveClientIp();
    if ($clientIp === null) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Não foi possível validar sua conexão. Tente novamente.'
        ]);
        exit;
    }
    $resolvedIdentifier = $endpoint . ':' . $clientIp;
    $rateLimiter = new RateLimiter();
    $headerInfo = $rateLimiter->getHeaderInfo($resolvedIdentifier, $limit, $window);
    if (!$rateLimiter->isAllowed($resolvedIdentifier, $limit, $window)) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => sprintf('Muitas tentativas. Aguarde %d segundos.', $headerInfo['reset_in']),
            'retry_after' => $headerInfo['reset_in']
        ]);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || empty($input['email'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'E-mail obrigatório']);
        exit;
    }

    $email = filter_var(trim($input['email']), FILTER_VALIDATE_EMAIL);
    if (!$email) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'E-mail inválido']);
        exit;
    }

    // Busca usuário e gera token via service
    $userRepository = new UserRepository($pdo);
    $userService = new \App\Application\User\UserService($userRepository);

    // Resposta genérica para evitar enumeração de usuários
    $resetData = $userService->requestPasswordReset($email);
    if ($resetData === null) {
        echo json_encode(['success' => true, 'message' => 'Se o e-mail estiver cadastrado, você receberá instruções para redefinir a senha.']);
        exit;
    }

    $token = $resetData['token'];
    $expires = $resetData['expires_at'];

    // Monta URL de reset
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $resetUrl = sprintf('%s://%s/reset-password.php?token=%s', $scheme, $host, urlencode($token));

    // Monta corpo do e-mail
    $subject = 'Redefinição de senha';
    $message = "Olá,\n\nRecebemos uma solicitação para redefinir a senha da sua conta. "
        . "Para redefinir, acesse o link abaixo:\n\n"
        . $resetUrl . "\n\n"
        . "Esse link expira em 1 hora.\n\n"
        . "Se você não solicitou esta ação, ignore esta mensagem e sua senha permanecerá segura.\n\n"
        . "Atenciosamente,\nEquipe de Suporte";

    // Envia usando o helper (PHPMailer se disponível)
    $sent = sendAppEmail($email, $subject, $message, null);

    // Log da solicitação
    error_log("Password reset solicitado para user_id: {$resetData['user_id']}, email: {$email}, email_sent: {$sent}");

    // Resposta genérica
    echo json_encode([
        'success' => true,
        'message' => 'Se o e-mail estiver cadastrado, você receberá instruções para redefinir a senha.',
        'email_sent' => $sent
    ]);
} catch (Exception $e) {
    http_response_code(500);
    error_log('requestPasswordReset error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno ao processar a solicitação']);
}
