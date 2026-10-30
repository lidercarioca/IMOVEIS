<?php
namespace App\Cache;

/**
 * RedisCache - Driver de cache baseado em Redis
 *
 * Este é o driver preferencial para produção.
 * Oferece performance superior e suporte nativo a TTL.
 */
class RedisCache implements CacheInterface
{
    private $redis;
    private bool $available = false;

    public function __construct()
    {
        try {
            // Tentar usar extensão Redis nativa
            if (extension_loaded('redis')) {
                $this->redis = new \Redis();
                $this->redis->connect('127.0.0.1', 6379, 1);
                $this->available = true;
            }
        } catch (\Exception $e) {
            error_log("Redis não disponível: " . $e->getMessage());
            $this->available = false;
        }
    }

    public function get(string $key)
    {
        if (!$this->available || $this->redis === null) {
            return null;
        }

        try {
            $value = $this->redis->get($key);
            return $value !== false ? @unserialize($value) : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function set(string $key, $value, int $ttl = 3600): void
    {
        if (!$this->available || $this->redis === null) {
            return;
        }

        try {
            $serialized = serialize($value);
            if ($ttl > 0) {
                $this->redis->setex($key, $ttl, $serialized);
            } else {
                $this->redis->set($key, $serialized);
            }
        } catch (\Exception $e) {
            // Silently fail - cache não deve quebrar aplicação
        }
    }

    public function forget(string $key): void
    {
        if (!$this->available || $this->redis === null) {
            return;
        }

        try {
            $this->redis->del($key);
        } catch (\Exception $e) {
            // Silently fail
        }
    }

    public function has(string $key): bool
    {
        if (!$this->available || $this->redis === null) {
            return false;
        }

        try {
            return $this->redis->exists($key) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }
}

