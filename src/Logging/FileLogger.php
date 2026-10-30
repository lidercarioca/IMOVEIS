<?php
namespace App\Logging;

/**
 * FileLogger - Driver de logging baseado em arquivos
 *
 * Escreve logs estruturados em arquivos JSON para análise posterior.
 * Sem dependências externas (alternativa ao Monolog).
 *
 * Funcionalidades:
 * - Logs estruturados em JSON
 * - Rotação por data
 * - Contexto estruturado
 * - Preservação de stack traces
 */
class FileLogger implements LoggerInterface
{
    private string $logDir;
    private string $appLogFile;
    private string $errorLogFile;
    private string $auditLogFile;

    // Mapeamento de níveis para prioridades
    private const LEVEL_PRIORITIES = [
        self::LEVEL_DEBUG => 7,
        self::LEVEL_INFO => 6,
        self::LEVEL_WARNING => 4,
        self::LEVEL_ERROR => 3,
        self::LEVEL_CRITICAL => 2,
    ];

    public function __construct(string $logDir = null)
    {
        $this->logDir = $logDir ?? (sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'imoveis_logs');

        // Criar diretório se necessário
        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0755, true);
        }

        // Inicializar caminhos dos arquivos com data
        $date = date('Y-m-d');
        $this->appLogFile = $this->logDir . DIRECTORY_SEPARATOR . "app-{$date}.log";
        $this->errorLogFile = $this->logDir . DIRECTORY_SEPARATOR . "error-{$date}.log";
        $this->auditLogFile = $this->logDir . DIRECTORY_SEPARATOR . "audit-{$date}.log";
    }

    /**
     * {@inheritdoc}
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_DEBUG, $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function info(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_INFO, $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function error(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_CRITICAL, $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function log(string $level, string $message, array $context = []): void
    {
        // Validar nível
        if (!isset(self::LEVEL_PRIORITIES[$level])) {
            $level = self::LEVEL_INFO;
        }

        // Construir entrada de log estruturada
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s.u'),
            'level' => strtoupper($level),
            'message' => $message,
            'request_id' => $this->getRequestId(),
            'context' => $context,
        ];

        // Adicionar informações da stack trace se for erro
        if (in_array($level, [self::LEVEL_ERROR, self::LEVEL_CRITICAL], true)) {
            $logEntry['trace'] = $this->getStackTrace();
        }

        // Determinar arquivo de destino
        $targetFile = $this->appLogFile;
        if (in_array($level, [self::LEVEL_ERROR, self::LEVEL_CRITICAL], true)) {
            $targetFile = $this->errorLogFile;
        }

        // Escrever em arquivo (não-bloqueante)
        $this->writeToFile($targetFile, $logEntry);
    }

    /**
     * Log de auditoria - rastreamento de ações
     *
     * @param string $action
     * @param string $entity
     * @param mixed $entityId
     * @param array $metadata
     * @param int|null $userId
     * @return void
     */
    public function audit(
        string $action,
        string $entity,
        $entityId,
        array $metadata = [],
        ?int $userId = null
    ): void {
        $auditEntry = [
            'timestamp' => date('Y-m-d H:i:s.u'),
            'request_id' => $this->getRequestId(),
            'user_id' => $userId,
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId,
            'metadata' => $metadata,
        ];

        $this->writeToFile($this->auditLogFile, $auditEntry);
    }

    /**
     * Escrever entrada estruturada no arquivo
     *
     * @param string $filePath
     * @param array $entry
     * @return void
     */
    private function writeToFile(string $filePath, array $entry): void
    {
        try {
            $json = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
            @file_put_contents($filePath, $json, FILE_APPEND | LOCK_EX);
        } catch (\Exception $e) {
            // Falha silenciosa - logging não deve quebrar o sistema
            error_log("Falha ao escrever log: " . $e->getMessage());
        }
    }

    /**
     * Obter request_id global (se disponível)
     *
     * @return string|null
     */
    private function getRequestId(): ?string
    {
        return $_SERVER['HTTP_X_REQUEST_ID'] 
            ?? $_SERVER['REQUEST_ID'] 
            ?? null;
    }

    /**
     * Obter stack trace simplificado
     *
     * @return array
     */
    private function getStackTrace(): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        
        $simplified = [];
        foreach ($trace as $frame) {
            $simplified[] = [
                'file' => $frame['file'] ?? 'unknown',
                'line' => $frame['line'] ?? 0,
                'function' => $frame['function'] ?? 'unknown',
                'class' => $frame['class'] ?? null,
            ];
        }

        return $simplified;
    }

    /**
     * Limpar logs antigos (> 30 dias)
     *
     * @param int $daysOld
     * @return void
     */
    public function cleanup(int $daysOld = 30): void
    {
        $cutoffTime = time() - ($daysOld * 86400);

        $files = @scandir($this->logDir);
        if (!$files) {
            return;
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filePath = $this->logDir . DIRECTORY_SEPARATOR . $file;
            if (is_file($filePath) && filemtime($filePath) < $cutoffTime) {
                @unlink($filePath);
            }
        }
    }
}
