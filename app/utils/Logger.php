<?php

/**
 * Logger - Sistema centralizado de logging
 * 
 * Oferece diferentes níveis de log
 * Uso: Logger::info('mensagem', ['contexto' => 'valor'])
 * 
 * ADAPTER: Delega para App\Logging\LoggerFactory para consolidação arquitetural
 */

namespace App\Utils;

use App\Logging\LoggerFactory;

class Logger
{

    const LEVEL_DEBUG = 'DEBUG';
    const LEVEL_INFO = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR = 'ERROR';
    const LEVEL_CRITICAL = 'CRITICAL';

    private static string $logDir = __DIR__ . '/../../logs';
    private static string $logFile = '';

    /**
     * Inicializar logger com arquivo de log
     * @deprecated Use App\Logging\LoggerFactory::make() diretamente
     */
    public static function init(string $logDir = ''): void
    {
        if (!empty($logDir)) {
            self::$logDir = $logDir;
        }

        if (!is_dir(self::$logDir)) {
            mkdir(self::$logDir, 0755, true);
        }

        self::$logFile = self::$logDir . '/' . date('Y-m-d') . '.log';
    }

    /**
     * Log DEBUG
     */
    public static function debug(string $message, array $context = []): void
    {
        LoggerFactory::make()->debug($message, $context);
    }

    /**
     * Log INFO
     */
    public static function info(string $message, array $context = []): void
    {
        LoggerFactory::make()->info($message, $context);
    }

    /**
     * Log WARNING
     */
    public static function warning(string $message, array $context = []): void
    {
        LoggerFactory::make()->warning($message, $context);
    }

    /**
     * Log ERROR
     */
    public static function error(string $message, array $context = []): void
    {
        LoggerFactory::make()->error($message, $context);
    }

    /**
     * Log CRITICAL
     */
    public static function critical(string $message, array $context = []): void
    {
        LoggerFactory::make()->critical($message, $context);
    }

    /**
     * Registrar operação (para auditoria)
     */
    public static function audit(string $action, ?int $userId = null, ?string $resource = null, $details = null): void
    {
        $context = [
            'user_id' => $userId,
            'resource' => $resource,
            'details' => $details,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'
        ];

        LoggerFactory::make()->info("[AUDIT] {$action}", $context);
    }

    /**
     * Log com exceção
     */
    public static function exception(\Throwable $e, string $message = ''): void
    {
        $context = [
            'exception' => get_class($e),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ];

        self::error($message ?: $e->getMessage(), $context);
    }

    /**
     * Log genérico
     */
    private static function log(string $level, string $message, array $context = []): void
    {
        if (getenv('ENVIRONMENT') !== 'development' && $level === self::LEVEL_DEBUG) {
            return; // Não registrar debug em produção
        }

        if (empty(self::$logFile)) {
            self::init();
        }

        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | ' . json_encode($context) : '';
        $logMessage = "[{$timestamp}] [{$level}] {$message}{$contextStr}" . PHP_EOL;

        // Escrever no arquivo de log
        @file_put_contents(self::$logFile, $logMessage, FILE_APPEND);

        // Em desenvolvimento, também exibir no error_log
        if (getenv('ENVIRONMENT') === 'development') {
            error_log("[{$level}] {$message}{$contextStr}");
        }
    }

    /**
     * Obter conteúdo do log atual
     */
    public static function getToday(int $limit = 100): array
    {
        if (empty(self::$logFile)) {
            self::init();
        }

        if (!file_exists(self::$logFile)) {
            return [];
        }

        $lines = file(self::$logFile, FILE_IGNORE_NEW_LINES);
        return array_slice($lines, -$limit);
    }

    /**
     * Obter logs de um período
     * 
     * Exemplo: Logger::getFromDate('2026-01-01', '2026-01-08')
     */
    public static function getFromDate(string $startDate, string $endDate): array
    {
        if (empty(self::$logDir)) {
            self::init();
        }

        $logs = [];
        $current = \DateTime::createFromFormat('Y-m-d', $startDate);
        $end = \DateTime::createFromFormat('Y-m-d', $endDate);

        while ($current <= $end) {
            $file = self::$logDir . '/' . $current->format('Y-m-d') . '.log';

            if (file_exists($file)) {
                $lines = file($file, FILE_IGNORE_NEW_LINES);
                $logs = array_merge($logs, $lines);
            }

            $current->modify('+1 day');
        }

        return $logs;
    }

    /**
     * Obter logs por nível
     * 
     * Exemplo: Logger::getByLevel('ERROR')
     */
    public static function getByLevel(string $level, int $limit = 100): array
    {
        $logs = self::getToday($limit * 2);
        $filtered = [];

        foreach ($logs as $log) {
            if (strpos($log, "[{$level}]") !== false) {
                $filtered[] = $log;
            }
        }

        return array_slice($filtered, -$limit);
    }

    /**
     * Obter logs por recurso (auditoria)
     * 
     * Exemplo: Logger::getAuditByResource('user', 100)
     */
    public static function getAuditByResource(string $resource, int $limit = 100): array
    {
        $logs = self::getToday($limit * 3);
        $filtered = [];

        foreach ($logs as $log) {
            if (strpos($log, $resource) !== false && strpos($log, '[AUDIT]') !== false) {
                $filtered[] = $log;
            }
        }

        return array_slice($filtered, -$limit);
    }

    /**
     * Obter estatísticas do log atual
     */
    public static function getStats(): array
    {
        if (empty(self::$logFile)) {
            self::init();
        }

        if (!file_exists(self::$logFile)) {
            return [];
        }

        $logs = file(self::$logFile, FILE_IGNORE_NEW_LINES);
        $stats = [
            'total' => count($logs),
            'DEBUG' => 0,
            'INFO' => 0,
            'WARNING' => 0,
            'ERROR' => 0,
            'CRITICAL' => 0,
            'AUDIT' => 0
        ];

        foreach ($logs as $log) {
            foreach ($stats as $level => &$count) {
                if ($level !== 'total' && strpos($log, "[{$level}]") !== false) {
                    $count++;
                }
            }
        }

        return $stats;
    }

    /**
     * Limpar log antigos (mais antigos que N dias)
     */
    public static function cleanup(int $daysKeep = 30): int
    {
        if (empty(self::$logDir)) {
            self::init();
        }

        $deleted = 0;
        $cutoff = strtotime("-{$daysKeep} days");

        foreach (glob(self::$logDir . '/*.log') as $file) {
            if (filemtime($file) < $cutoff) {
                if (@unlink($file)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    /**
     * Exportar logs em formato CSV
     */
    public static function exportCsv(string $outputFile, ?string $level = null): bool
    {
        if (empty(self::$logFile)) {
            self::init();
        }

        $logs = self::getToday(PHP_INT_MAX);

        if (!empty($level)) {
            $logs = array_filter($logs, function ($log) use ($level) {
                return strpos($log, "[{$level}]") !== false;
            });
        }

        $fp = fopen($outputFile, 'w');
        if (!$fp) return false;

        fputcsv($fp, ['Timestamp', 'Level', 'Message', 'Context']);

        foreach ($logs as $log) {
            // Parse: [timestamp] [level] message | context
            if (preg_match('/\[(.*?)\] \[(.*?)\] (.*)/', $log, $matches)) {
                fputcsv($fp, [
                    $matches[1],
                    $matches[2],
                    preg_replace('/\s*\|.*/', '', $matches[3]),
                    preg_replace('/.*?\|\s*/', '', $matches[3]) ?: ''
                ]);
            }
        }

        fclose($fp);
        return true;
    }
}
