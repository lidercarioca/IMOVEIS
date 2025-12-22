<?php
class UserActionLogger {
    private static $logFile = __DIR__ . '/../../logs/user_actions.log';

    public static function log($userId, $username, $action, $details = null) {
        $entry = [
            'timestamp' => date('c'),
            'user_id' => $userId,
            'username' => $username,
            'action' => $action,
            'ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null,
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null,
            'details' => $details,
        ];

        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

        // Garantir diretório de logs
        $dir = dirname(self::$logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        // Escrever em append com locking
        $fp = @fopen(self::$logFile, 'a');
        if ($fp) {
            @flock($fp, LOCK_EX);
            @fwrite($fp, $line);
            @flock($fp, LOCK_UN);
            @fclose($fp);
        }
    }
}

?>
