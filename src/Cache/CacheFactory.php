<?php

namespace App\Cache;

/**
 * CacheFactory - Factory para criar instâncias de cache
 *
 * Responsável por:
 * - Selecionar o driver apropriado (Redis preferencial)
 * - Oferecer fallback automático para FileCache
 * - Singleton do driver selecionado
 */
class CacheFactory
{
    private static ?CacheInterface $instance = null;

    /**
     * Obtém a instância singleton do cache
     *
     * Tenta Redis primeiro. Se não disponível, usa FileCache.
     *
     * @return CacheInterface
     */
    public static function make(): CacheInterface
    {
        if (self::$instance === null) {
            self::$instance = self::createDriver();
        }

        return self::$instance;
    }

    /**
     * Cria a instância do driver apropriado
     *
     * @return CacheInterface
     */
    private static function createDriver(): CacheInterface
    {
        // Tentar Redis primeiro
        if (extension_loaded('redis')) {
            try {
                $redis = new RedisCache();
                if ($redis->isAvailable()) {
                    error_log("[Cache] Redis disponível e conectado");
                    return $redis;
                }
            } catch (\Exception $e) {
                error_log("[Cache] Falha ao iniciar Redis: " . $e->getMessage());
            }
        }

        // Fallback para FileCache
        error_log("[Cache] Usando FileCache como fallback");
        return new FileCache();
    }

    /**
     * Reseta a instância (útil para testes)
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
