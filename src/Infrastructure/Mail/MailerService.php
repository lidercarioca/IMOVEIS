<?php

namespace App\Infrastructure\Mail;

use Throwable;

/**
 * MailerService - encapsula envio de e-mail via PHPMailer ou fallback mail().
 */
class MailerService
{
    private array $config;

    public function __construct(array $config = null)
    {
        $this->config = $config ?? $this->loadConfig();
    }

    public function send(string $to, string $subject, string $bodyText, ?string $bodyHtml = null): bool
    {
        if ($this->hasPhpMailer()) {
            return $this->sendWithPhpMailer($to, $subject, $bodyText, $bodyHtml);
        }

        return $this->sendWithMail($to, $subject, $bodyText, $bodyHtml);
    }

    private function loadConfig(): array
    {
        $configPath = realpath(__DIR__ . '/../../../config/smtp.php');

        if ($configPath === false || !is_file($configPath)) {
            throw new \RuntimeException('SMTP configuration file not found: ' . __DIR__ . '/../../../config/smtp.php');
        }

        $config = require $configPath;

        if (!is_array($config)) {
            throw new \RuntimeException('SMTP configuration must return an array.');
        }

        return $config;
    }

    private function hasPhpMailer(): bool
    {
        return class_exists('\PHPMailer\PHPMailer\PHPMailer');
    }

    private function sendWithPhpMailer(string $to, string $subject, string $bodyText, ?string $bodyHtml = null): bool
    {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $this->config['host'] ?? '';
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['username'] ?? '';
            $mail->Password = $this->config['password'] ?? '';
            $mail->SMTPSecure = $this->config['secure'] ?? '';
            $mail->Port = (int)($this->config['port'] ?? 587);
            $mail->SMTPDebug = 0;
            $mail->Timeout = 10;
            $mail->setFrom($this->config['from_email'] ?? '', $this->config['from_name'] ?? '');
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->CharSet = 'UTF-8';
            $mail->isHTML(!empty($bodyHtml));

            if (!empty($bodyHtml)) {
                $mail->Body = $bodyHtml;
                $mail->AltBody = $bodyText;
            } else {
                $mail->Body = nl2br(htmlspecialchars($bodyText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
                $mail->AltBody = $bodyText;
            }

            $mail->send();
            return true;
        } catch (Throwable $e) {
            error_log('MailerService PHPMailer error: ' . $e->getMessage());
            return false;
        }
    }

    private function sendWithMail(string $to, string $subject, string $bodyText, ?string $bodyHtml = null): bool
    {
        $fromName = $this->config['from_name'] ?? '';
        $fromEmail = $this->config['from_email'] ?? '';

        $headers = 'From: ' . $fromName . ' <' . $fromEmail . ">\r\n";
        $headers .= 'Reply-To: ' . $fromEmail . "\r\n";
        $headers .= 'MIME-Version: 1.0' . "\r\n";

        if (!empty($bodyHtml)) {
            $boundary = md5((string) time());
            $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
            $message = "--{$boundary}\r\n";
            $message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
            $message .= $bodyText . "\r\n";
            $message .= "--{$boundary}\r\n";
            $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
            $message .= $bodyHtml . "\r\n";
            $message .= "--{$boundary}--";

            return @mail($to, $subject, $message, $headers);
        }

        $headers .= 'Content-Type: text/plain; charset=UTF-8' . "\r\n";
        return @mail($to, $subject, $bodyText, $headers);
    }
}
