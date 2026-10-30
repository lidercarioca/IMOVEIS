<?php
namespace App\Logging;

/**
 * LoggerInterface - Contrato para logging estruturado
 *
 * Define os níveis de log e métodos para registrar mensagens.
 * Implementações podem usar diferentes backends (arquivo, banco, API).
 */
interface LoggerInterface
{
    // Níveis de log (RFC 5424)
    public const LEVEL_DEBUG = 'debug';        // 7
    public const LEVEL_INFO = 'info';          // 6
    public const LEVEL_WARNING = 'warning';    // 4
    public const LEVEL_ERROR = 'error';        // 3
    public const LEVEL_CRITICAL = 'critical';  // 2

    /**
     * Log uma mensagem com nível debug (mais verboso)
     *
     * @param string $message
     * @param array $context Dados adicionais
     * @return void
     */
    public function debug(string $message, array $context = []): void;

    /**
     * Log uma mensagem informativa
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function info(string $message, array $context = []): void;

    /**
     * Log um aviso (algo incomum, mas não erro)
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function warning(string $message, array $context = []): void;

    /**
     * Log um erro (algo deu errado)
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function error(string $message, array $context = []): void;

    /**
     * Log algo crítico (sistema instável, ação necessária)
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function critical(string $message, array $context = []): void;

    /**
     * Log genérico com nível específico
     *
     * @param string $level Um dos LEVEL_* constantes
     * @param string $message
     * @param array $context
     * @return void
     */
    public function log(string $level, string $message, array $context = []): void;
}
