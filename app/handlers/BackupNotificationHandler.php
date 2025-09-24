<?php
class BackupNotificationHandler {
    private $errorLog;
    private $notifyEmail;
    private $fromEmail;
    private $smtpConfig;

    public function __construct($notifyEmail, $fromEmail = null, $smtpConfig = null) {
        $this->errorLog = __DIR__ . '/../../backup_error.log';
        $this->notifyEmail = $notifyEmail;
        $this->fromEmail = $fromEmail ?: 'noreply@backup.sistema';
        $this->smtpConfig = $smtpConfig;
    }

    /**
     * Envia notificação de erro
     */
    public function notifyError($error, $context = []) {
        try {
            $timestamp = date('Y-m-d H:i:s');
            $subject = "[BACKUP ERRO] Falha no backup do sistema - {$timestamp}";
            
            $message = "Ocorreu um erro durante o processo de backup:\n\n";
            $message .= "Erro: " . $error . "\n\n";
            
            if (!empty($context)) {
                $message .= "Contexto adicional:\n";
                foreach ($context as $key => $value) {
                    $message .= "- {$key}: {$value}\n";
                }
                $message .= "\n";
            }

            $message .= "Data/Hora: {$timestamp}\n";
            $message .= "Servidor: " . $_SERVER['SERVER_NAME'] . "\n";
            
            // Adiciona logs recentes se disponíveis
            $recentLogs = $this->getRecentLogs();
            if ($recentLogs) {
                $message .= "\nLogs recentes:\n" . $recentLogs;
            }

            // Configurações dos headers do email
            $headers = [
                'From: ' . $this->fromEmail,
                'X-Mailer: PHP/' . phpversion(),
                'Content-Type: text/plain; charset=UTF-8',
                'X-Priority: 1', // Alta prioridade
                'X-MSMail-Priority: High'
            ];

            // Usa SMTP se configurado, caso contrário usa mail() padrão do PHP
            if ($this->smtpConfig) {
                $this->sendSmtpEmail($subject, $message);
            } else {
                if (!mail($this->notifyEmail, $subject, $message, implode("\r\n", $headers))) {
                    throw new Exception("Falha ao enviar email de notificação");
                }
            }

            // Registra a notificação no log
            error_log(
                date('Y-m-d H:i:s') . " - Notificação de erro enviada para {$this->notifyEmail}\n" .
                "Assunto: {$subject}\n" .
                "Mensagem: {$error}\n",
                3,
                $this->errorLog
            );

            return true;
        } catch (Exception $e) {
            error_log(
                date('Y-m-d H:i:s') . " - Erro ao enviar notificação: " . $e->getMessage() . "\n",
                3,
                $this->errorLog
            );
            return false;
        }
    }

    /**
     * Envia notificação de sucesso (opcional, para backups críticos)
     */
    public function notifySuccess($details) {
        try {
            $timestamp = date('Y-m-d H:i:s');
            $subject = "[BACKUP SUCESSO] Backup concluído com sucesso - {$timestamp}";
            
            $message = "O backup do sistema foi concluído com sucesso!\n\n";
            $message .= "Detalhes do backup:\n";
            
            foreach ($details as $key => $value) {
                if (is_array($value)) {
                    $message .= "\n{$key}:\n";
                    foreach ($value as $subKey => $subValue) {
                        $message .= "  - {$subKey}: {$subValue}\n";
                    }
                } else {
                    $message .= "- {$key}: {$value}\n";
                }
            }

            $message .= "\nData/Hora: {$timestamp}\n";
            $message .= "Servidor: " . php_uname('n') . "\n";

            $headers = [
                'From: ' . $this->fromEmail,
                'X-Mailer: PHP/' . phpversion(),
                'Content-Type: text/plain; charset=UTF-8'
            ];

            if ($this->smtpConfig) {
                $this->sendSmtpEmail($subject, $message);
            } else {
                mail($this->notifyEmail, $subject, $message, implode("\r\n", $headers));
            }

            return true;
        } catch (Exception $e) {
            error_log(
                date('Y-m-d H:i:s') . " - Erro ao enviar notificação de sucesso: " . $e->getMessage() . "\n",
                3,
                $this->errorLog
            );
            return false;
        }
    }

    /**
     * Obtém logs recentes para incluir na notificação
     */
    private function getRecentLogs($lines = 10) {
        if (!file_exists($this->errorLog)) {
            return null;
        }

        $logs = [];
        $file = new SplFileObject($this->errorLog, 'r');
        $file->seek(PHP_INT_MAX); // Vai para o final do arquivo
        $totalLines = $file->key(); // Obtém o número total de linhas

        // Calcula a partir de qual linha devemos começar a ler
        $start = max(0, $totalLines - $lines);
        
        // Lê as últimas linhas
        $file->seek($start);
        while (!$file->eof()) {
            $logs[] = $file->fgets();
        }

        return implode('', $logs);
    }

    /**
     * Envia email usando SMTP (se configurado)
     */
    private function sendSmtpEmail($subject, $message) {
        if (!$this->smtpConfig) {
            throw new Exception("Configuração SMTP não fornecida");
        }

        // Aqui você pode implementar o envio via SMTP usando PHPMailer ou similar
        // Por enquanto, vamos usar o mail() padrão
        $headers = [
            'From: ' . $this->fromEmail,
            'X-Mailer: PHP/' . phpversion(),
            'Content-Type: text/plain; charset=UTF-8'
        ];

        if (!mail($this->notifyEmail, $subject, $message, implode("\r\n", $headers))) {
            throw new Exception("Falha ao enviar email via SMTP");
        }

        return true;
    }
}
