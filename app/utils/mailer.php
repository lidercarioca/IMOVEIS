<?php
// Helper de envio de e-mail. Usa PHPMailer se instalado via Composer, caso contrário cai para mail().

function sendAppEmail($to, $subject, $bodyPlainText, $bodyHtml = null) {
    $cfg = require __DIR__ . '/../../config/smtp.php';

    // Tenta usar PHPMailer
    $composerAutoload = __DIR__ . '/../../vendor/autoload.php';
    if (file_exists($composerAutoload)) {
        require_once $composerAutoload;
        // Em alguns ambientes o `vendor/autoload.php` foi substituído
        // por um stub que não registra todas as classes. Força o
        // carregamento direto das classes do PHPMailer se necessário.
        if (!class_exists('\\PHPMailer\\PHPMailer\\PHPMailer') && file_exists(__DIR__ . '/../../vendor/phpmailer/phpmailer/src/PHPMailer.php')) {
            require_once __DIR__ . '/../../vendor/phpmailer/phpmailer/src/Exception.php';
            require_once __DIR__ . '/../../vendor/phpmailer/phpmailer/src/PHPMailer.php';
            require_once __DIR__ . '/../../vendor/phpmailer/phpmailer/src/SMTP.php';
        }
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            // Server settings
            $mail->isSMTP();
            $mail->Host = $cfg['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $cfg['username'];
            $mail->Password = $cfg['password'];
            $mail->SMTPSecure = $cfg['secure'];
            $mail->Port = $cfg['port'];
            
            // Configurações adicionais para troubleshooting
            $mail->SMTPDebug = 0; // Mude para 2 para ver detalhes da conexão
            $mail->Timeout = 10;
            
            // From
            $mail->setFrom($cfg['from_email'], $cfg['from_name']);
            $mail->addAddress($to);

            // Content
            $mail->isHTML(!empty($bodyHtml));
            if (!empty($bodyHtml)) {
                $mail->Body = $bodyHtml;
                $mail->AltBody = $bodyPlainText;
            } else {
                $mail->Body = nl2br(htmlspecialchars($bodyPlainText));
                $mail->AltBody = $bodyPlainText;
            }

            $mail->Subject = $subject;
            $mail->CharSet = 'UTF-8';

            $mail->send();
            error_log('E-mail enviado com sucesso para: ' . $to);
            return true;
        } catch (Exception $e) {
            error_log('PHPMailer error: ' . $e->getMessage());
            return false;
        }
    }

    // Fallback simples usando mail()
    $headers = 'From: ' . $cfg['from_name'] . ' <' . $cfg['from_email'] . "\r\n";
    $headers .= 'Reply-To: ' . $cfg['from_email'] . "\r\n";
    $headers .= 'MIME-Version: 1.0' . "\r\n";
    if (!empty($bodyHtml)) {
        $boundary = md5(time());
        $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
        $message = "--{$boundary}\r\n";
        $message .= "Content-Type: text/plain; charset=ISO-8859-1\r\n\r\n";
        $message .= $bodyPlainText . "\r\n";
        $message .= "--{$boundary}\r\n";
        $message .= "Content-Type: text/html; charset=ISO-8859-1\r\n\r\n";
        $message .= $bodyHtml . "\r\n";
        $message .= "--{$boundary}--";
        return @mail($to, $subject, $message, $headers);
    } else {
        $headers .= 'Content-Type: text/plain; charset=UTF-8' . "\r\n";
        return @mail($to, $subject, $bodyPlainText, $headers);
    }
}
