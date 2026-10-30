<?php
/**
 * Logger simples para exceções e erros.
 * Escreve logs em /logs/relatorios_errors.log com travamento de arquivo.
 */
function log_exception(Throwable $e, array $context = []) {
    $logDir = __DIR__ . '/../../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $file = $logDir . '/relatorios_errors.log';

    $payload = [
        'timestamp' => date('c'),
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
        'context' => $context,
        'server' => [
            'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? null,
            'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? null,
            'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]
    ];

    $line = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    // Use file locking to avoid corrupção em escritas concorrentes
    $fh = fopen($file, 'a');
    if ($fh) {
        flock($fh, LOCK_EX);
        fwrite($fh, $line);
        fflush($fh);
        flock($fh, LOCK_UN);
        fclose($fh);
    }
}
