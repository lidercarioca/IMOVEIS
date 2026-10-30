<?php
namespace App\Cache;

/**
 * CacheManager - Gerenciador de cache com fallback automático
 *
 * Escolhe automaticamente o melhor driver disponível:
 * 1. RedisCache (se Redis estiver disponível)
 * 2. FileCache (fallback)
 */
class CacheManager
{
    private CacheInterface $cache;

    public function __construct()
    {
        $this->cache = $this->createCacheDriver();
    }

    /**
     * Cria o driver de cache mais apropriado disponível
     */
    private function createCacheDriver(): CacheInterface
    {
        // Tentar Redis primeiro
        try {
            return new RedisCache();
        } catch (\Exception $e) {
            // Fallback para FileCache
            return new FileCache();
        }
    }

    /**
     * Delega para o driver ativo
     */
    public function get(string $key)
    {
        return $this->cache->get($key);
    }

    public function set(string $key, $value, int $ttl = 3600): void
    {
        $this->cache->set($key, $value, $ttl);
    }

    public function forget(string $key): void
    {
        $this->cache->forget($key);
    }

    public function has(string $key): bool
    {
        return $this->cache->has($key);
    }

    /**
     * Retorna o nome do driver ativo (para debug)
     */
    public function getDriverName(): string
    {
        return get_class($this->cache);
    }
}
