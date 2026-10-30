<?php

namespace App\Observability;

/**
 * RequestTracker - Rastreamento de requisições HTTP
 *
 * Responsável por:
 * - Gerar request_id único para cada requisição
 * - Propagar request_id através de logs e contexto
 * - Capturar metadados da requisição (IP, user agent, método, etc)
 * - Medir duração total da requisição
 *
 * Uso:
 * No bootstrap da aplicação:
 *   RequestTracker::start();
 *
 * Em qualquer lugar do código:
 *   $requestId = RequestTracker::getId();
 *   RequestTracker::addMetadata('key', 'value');
 */
class RequestTracker
{
    private static ?string $requestId = null;
    private static array $metadata = [];
    private static float $startTime = 0;

    /**
     * Iniciar rastreamento da requisição
     *
     * Gera request_id único e armazena metadata inicial.
     * Deve ser chamado no início do bootstrap.
     *
     * @param string|null $requestId Se null, gera UUID
     * @return string O request_id gerado
     */
    public static function start(?string $requestId = null): string
    {
        // Gerar request_id se não fornecido
        if ($requestId === null) {
            $requestId = self::generateUuid();
        }

        self::$requestId = $requestId;
        self::$startTime = microtime(true);

        // Armazenar no ambiente global para propagação
        $_SERVER['REQUEST_ID'] = $requestId;
        $_SERVER['HTTP_X_REQUEST_ID'] = $requestId;

        // Capturar metadata inicial
        self::captureInitialMetadata();

        return $requestId;
    }

    /**
     * Obter request_id atual
     *
     * @return string|null
     */
    public static function getId(): ?string
    {
        return self::$requestId ?? $_SERVER['REQUEST_ID'] ?? null;
    }

    /**
     * Adicionar metadata à requisição
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public static function addMetadata(string $key, $value): void
    {
        self::$metadata[$key] = $value;
    }

    /**
     * Obter metadata armazenada
     *
     * @param string|null $key Se null, retorna tudo
     * @return mixed
     */
    public static function getMetadata(?string $key = null)
    {
        if ($key === null) {
            return self::$metadata;
        }

        return self::$metadata[$key] ?? null;
    }

    /**
     * Obter duração da requisição em ms
     *
     * @return float
     */
    public static function getDuration(): float
    {
        if (self::$startTime === 0) {
            return 0;
        }

        return round((microtime(true) - self::$startTime) * 1000, 2);
    }

    /**
     * Obter resumo da requisição (para logs de conclusão)
     *
     * @return array
     */
    public static function getSummary(): array
    {
        return [
            'request_id' => self::getId(),
            'duration_ms' => self::getDuration(),
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'path' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'ip' => self::getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'metadata' => self::$metadata,
        ];
    }

    /**
     * Resetar tracker (útil para testes)
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$requestId = null;
        self::$metadata = [];
        self::$startTime = 0;

        unset($_SERVER['REQUEST_ID']);
        unset($_SERVER['HTTP_X_REQUEST_ID']);
    }

    /**
     * Capturar metadata inicial da requisição
     *
     * @return void
     */
    private static function captureInitialMetadata(): void
    {
        self::$metadata = [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'path' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'ip' => self::getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'timestamp' => date('Y-m-d H:i:s.u'),
        ];
    }

    /**
     * Obter IP do cliente de forma segura no ambiente direto XAMPP/Apache.
     *
     * Nunca confia em cabeçalhos enviados pelo cliente para rate limiting,
     * logs ou identificadores de sessão quando REMOTE_ADDR estiver disponível.
     *
     * @return string|null
     */
    public static function resolveClientIp(): ?string
    {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
        $remoteAddr = is_string($remoteAddr) ? trim($remoteAddr) : '';

        if ($remoteAddr === '') {
            return null;
        }

        if (filter_var($remoteAddr, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        return $remoteAddr;
    }

    private static function getClientIp(): string
    {
        return self::resolveClientIp() ?? 'unknown';
    }

    /**
     * Gerar UUID v4
     *
     * @return string
     */
    private static function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
