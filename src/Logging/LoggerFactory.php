<?php
namespace App\Logging;

/**
 * LoggerFactory - Factory para seleção automática do Logger
 *
 * Implementa singleton pattern para garantir única instância
 * do logger na aplicação.
 *
 * Uso:
 *   $logger = LoggerFactory::make();
 *   $logger->info('Mensagem', ['context' => 'value']);
 */
class LoggerFactory
{
    private static ?LoggerInterface $instance = null;

    /**
     * Obter instância singleton do Logger
     *
     * @return LoggerInterface
     */
    public static function make(): LoggerInterface
    {
        if (self::$instance === null) {
            self::$instance = self::createDriver();
        }

        return self::$instance;
    }

    /**
     * Criar driver apropriado
     *
     * Estratégia:
     * 1. Se ambiente de produção e extensão syslog disponível → usar syslog
     * 2. Caso contrário → usar FileLogger
     *
     * @return LoggerInterface
     */
    private static function createDriver(): LoggerInterface
    {
        // Por enquanto, usar FileLogger como padrão
        // (pode ser estendido para Monolog depois)
        $logDir = $_ENV['LOG_DIR'] ?? (sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'imoveis_logs');
        
        error_log("[Logger] Iniciando FileLogger em: {$logDir}");
        
        return new FileLogger($logDir);
    }

    /**
     * Definir instância customizada (útil para testes)
     *
     * @param LoggerInterface $logger
     * @return void
     */
    public static function setInstance(LoggerInterface $logger): void
    {
        self::$instance = $logger;
    }

    /**
     * Resetar singleton (útil para testes)
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
