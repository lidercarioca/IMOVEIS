<?php
namespace App\Cache;

interface CacheInterface
{
    /**
     * Recupera um valor do cache.
     *
     * @param string $key
     * @return mixed|null Retorna null se não existir
     */
    public function get(string $key);

    /**
     * Define um valor no cache com TTL em segundos.
     *
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     */
    public function set(string $key, $value, int $ttl = 3600): void;

    /**
     * Remove um valor do cache.
     *
     * @param string $key
     */
    public function forget(string $key): void;

    /**
     * Verifica existência de uma chave de cache válida.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool;
}
