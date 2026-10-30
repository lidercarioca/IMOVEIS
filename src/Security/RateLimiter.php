<?php

namespace App\Security;

use App\Cache\CacheInterface;
use App\Cache\CacheFactory;

/**
 * RateLimiter - Controle de taxa de requisições
 *
 * Implementa rate limiting baseado em:
 * - IP
 * - Usuário autenticado
 * - Rota/ação
 *
 * Usa cache (Redis/FileCache da PHASE 10) para armazenar contadores.
 *
 * Exemplo:
 * - login: 5 tentativas/minuto
 * - API geral: 100 req/minuto
 * - Rota específica: limite customizado
 */
class RateLimiter
{
    // Limites padrão (requisições por minuto)
    private const LIMIT_LOGIN = 5;
    private const LIMIT_API = 100;
    private const LIMIT_AGGRESSIVE = 10;

    // Window de tempo (em segundos)
    private const WINDOW = 60; // 1 minuto

    private CacheInterface $cache;

    public function __construct(?CacheInterface $cache = null)
    {
        $this->cache = $cache ?? CacheFactory::make();
    }

    /**
     * Verificar se requisição é permitida
     *
     * @param string $identifier IP ou usuário ID
     * @param int $limit Limite de requisições
     * @param int $window Janela de tempo (segundos)
     * @return bool True se permitido, false se bloqueado
     */
    public function isAllowed(
        string $identifier,
        int $limit = self::LIMIT_API,
        int $window = self::WINDOW
    ): bool {
        $key = $this->generateKey($identifier, $window);
        $current = (int)$this->cache->get($key);

        if ($current >= $limit) {
            return false; // Bloqueado
        }

        // Incrementar contador
        $this->cache->set($key, $current + 1, $window);
        $this->rememberTrackedKey($key);
        return true;
    }

    /**
     * Obter número de requisições atuais
     *
     * @param string $identifier
     * @param int $window
     * @return int
     */
    public function getAttempts(string $identifier, int $window = self::WINDOW): int
    {
        $key = $this->generateKey($identifier, $window);
        return (int)$this->cache->get($key);
    }

    /**
     * Resetar contador para um identificador
     *
     * @param string $identifier
     * @param int $window
     * @return void
     */
    public function reset(string $identifier, int $window = self::WINDOW): void
    {
        $key = $this->generateKey($identifier, $window);
        $this->cache->forget($key);
        $this->removeTrackedKey($key);
    }

    /**
     * Reset administrativo para um endpoint específico.
     *
     * Mantém a semântica legacy de "reset por endpoint + identificador" usando
     * o identificador resolvido da mesma forma que o adapter de compatibilidade.
     *
     * @param string $endpoint
     * @param string $identifier
     * @param int|null $window
     * @return bool
     */
    public function resetForEndpoint(string $endpoint, string $identifier, ?int $window = null): bool
    {
        $config = RateLimitPolicy::getConfig($endpoint);
        $effectiveWindow = $window ?? (int)$config['window'];
        $resolvedIdentifier = $endpoint . ':' . $identifier;
        $key = $this->generateKey($resolvedIdentifier, $effectiveWindow);

        $hadEntry = $this->cache->has($key);
        $this->cache->forget($key);
        $this->removeTrackedKey($key);

        return $hadEntry;
    }

    /**
     * Remove apenas buckets de rate limit rastreados pela política canônica.
     *
     * @return int
     */
    public function clearAllBuckets(): int
    {
        $trackedKeys = $this->getTrackedKeys('ratelimit:index:all');
        $removed = 0;

        foreach ($trackedKeys as $key) {
            if ($this->cache->has($key) || $this->cache->get($key) !== null) {
                $this->cache->forget($key);
                $removed++;
            }
        }

        $this->cache->forget('ratelimit:index:all');

        return $removed;
    }

    /**
     * Remove buckets expirados que ainda permanecem no índice de rastreio.
     *
     * @param int $maxAgeSeconds
     * @return int
     */
    public function cleanupExpiredBuckets(int $maxAgeSeconds = 86400): int
    {
        $trackedKeys = $this->getTrackedKeys('ratelimit:index:all');
        $removed = 0;

        foreach ($trackedKeys as $key) {
            $value = $this->cache->get($key);
            if ($value === null) {
                $this->cache->forget($key);
                $this->removeTrackedKey($key);
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * Lista os buckets de rate limit atualmente rastreados.
     *
     * @return array<int, array{key:string, value:mixed}>
     */
    public function listTrackedBuckets(): array
    {
        $trackedKeys = $this->getTrackedKeys('ratelimit:index:all');
        $buckets = [];

        foreach ($trackedKeys as $key) {
            $value = $this->cache->get($key);
            if ($value === null) {
                continue;
            }

            $buckets[] = [
                'key' => $key,
                'value' => $value,
            ];
        }

        return $buckets;
    }

    /**
     * Limites pré-configurados para ações comuns
     */

    /**
     * Verificar limite de login
     *
     * @param string $ipOrEmail IP ou email do usuário
     * @return bool
     */
    public function checkLoginLimit(string $ipOrEmail): bool
    {
        return $this->isAllowed($ipOrEmail, self::LIMIT_LOGIN);
    }

    /**
     * Verificar limite de API geral
     *
     * @param string $ipOrUserId
     * @return bool
     */
    public function checkApiLimit(string $ipOrUserId): bool
    {
        return $this->isAllowed($ipOrUserId, self::LIMIT_API);
    }

    /**
     * Verificar limite agressivo (para ações críticas)
     *
     * @param string $identifier
     * @return bool
     */
    public function checkAggressiveLimit(string $identifier): bool
    {
        return $this->isAllowed($identifier, self::LIMIT_AGGRESSIVE);
    }

    /**
     * Verificar limite customizado
     *
     * @param string $identifier
     * @param string $action Identifica a ação
     * @param int $limit
     * @param int $window
     * @return bool
     */
    public function checkCustomLimit(
        string $identifier,
        string $action,
        int $limit = self::LIMIT_API,
        int $window = self::WINDOW
    ): bool {
        $key = $this->generateKey($identifier, $window, $action);
        $current = (int)$this->cache->get($key);

        if ($current >= $limit) {
            return false;
        }

        $this->cache->set($key, $current + 1, $window);
        $this->rememberTrackedKey($key);
        return true;
    }

    /**
     * Rastreia buckets de rate limit para limpeza administrativa segura.
     */
    private function rememberTrackedKey(string $key): void
    {
        $trackedKeys = $this->getTrackedKeys('ratelimit:index:all');

        if (!in_array($key, $trackedKeys, true)) {
            $trackedKeys[] = $key;
            $this->cache->set('ratelimit:index:all', $trackedKeys, 86400);
        }
    }

    /**
     * Remove uma chave do índice de rastreio sem afetar o restante do cache.
     */
    private function removeTrackedKey(string $key): void
    {
        $trackedKeys = $this->getTrackedKeys('ratelimit:index:all');
        $filtered = array_values(array_filter($trackedKeys, static fn(string $trackedKey) => $trackedKey !== $key));
        $this->cache->set('ratelimit:index:all', $filtered, 86400);
    }

    /**
     * Retorna uma lista de chaves rastreadas armazenadas no cache.
     *
     * @param string $indexKey
     * @return array<int, string>
     */
    private function getTrackedKeys(string $indexKey): array
    {
        $keys = $this->cache->get($indexKey);
        return is_array($keys) ? array_values($keys) : [];
    }

    /**
     * Gerar chave de cache
     *
     * @param string $identifier
     * @param int $window
     * @param string|null $action
     * @return string
     */
    private function generateKey(string $identifier, int $window, ?string $action = null): string
    {
        // Usar timestamp arredondado para janela
        $windowKey = (int)(time() / $window);

        if ($action) {
            return "ratelimit:{$action}:{$identifier}:{$windowKey}";
        }

        return "ratelimit:{$identifier}:{$windowKey}";
    }

    /**
     * Obter informações de rate limit para resposta
     *
     * @param string $identifier
     * @param int $limit
     * @param int $window
     * @return array ['remaining' => int, 'reset' => int]
     */
    public function getHeaderInfo(
        string $identifier,
        int $limit = self::LIMIT_API,
        int $window = self::WINDOW
    ): array {
        $attempts = $this->getAttempts($identifier, $window);
        $remaining = max(0, $limit - $attempts);

        // Tempo até reset (próximo window)
        $now = time();
        $windowKey = (int)($now / $window);
        $resetTime = ($windowKey + 1) * $window;

        return [
            'limit' => $limit,
            'remaining' => $remaining,
            'reset' => $resetTime,
            'reset_in' => max(0, $resetTime - $now),
        ];
    }
}
