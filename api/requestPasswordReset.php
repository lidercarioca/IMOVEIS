<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/utils/mailer.php';

// Cria tabela de tokens se não existir
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
      id INT AUTO_INCREMENT PRIMARY KEY,
      user_id INT NOT NULL,
      token VARCHAR(128) NOT NULL,
      expires_at DATETIME NOT NULL,
      used TINYINT(1) DEFAULT 0,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      INDEX (token(64)),
      INDEX (user_id)
    )");
} catch (Exception $e) {
    error_log('Erro ao criar tabela password_resets: ' . $e->getMessage());
}

try {
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

    // Busca usuário
    $stmt = $pdo->prepare('SELECT id, username FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Resposta genérica para evitar enumeração de usuários
    if (!$user) {
        echo json_encode(['success' => true, 'message' => 'Se o e-mail estiver cadastrado, você receberá instruções para redefinir a senha.']);
        exit;
    }

    $userId = $user['id'];

    // Rate limiting: verifica se há uma solicitação recente não utilizada (15 minutos)
    $stmt = $pdo->prepare('SELECT created_at FROM password_resets WHERE user_id = :uid AND used = 0 ORDER BY created_at DESC LIMIT 1');
    $stmt->execute([':uid' => $userId]);
    $last = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($last) {
        $lastTime = strtotime($last['created_at']);
        if (time() - $lastTime < 900) { // 900 segundos = 15 minutos
            echo json_encode(['success' => true, 'message' => 'Se o e-mail estiver cadastrado, você receberá instruções para redefinir a senha.']);
            exit;
        }
    }

    // Gera token seguro (64 caracteres hexadecimais = 32 bytes)
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hora

    // Insere registro de reset na tabela
    $ins = $pdo->prepare('INSERT INTO password_resets (user_id, token, expires_at) VALUES (:uid, :token, :exp)');
    $ins->execute([':uid' => $userId, ':token' => $token, ':exp' => $expires]);

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
    error_log("Password reset solicitado para user_id: {$userId}, email: {$email}, email_sent: {$sent}");

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
