<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Cache\FileCache;
use App\Cache\RedisCache;
use App\Cache\CacheFactory;

/**
 * CacheTest - Testes para camada de cache (PHASE 10)
 *
 * Testes:
 * - FileCache: get, set, forget, has com TTL expirado
 * - Cache hit / miss
 * - Invalidação após update/delete
 * - Fallback behavior
 */
class CacheTest extends TestCase
{
    private FileCache $fileCache;
    private string $cacheDir;

    protected function setUp(): void
    {
        // Criar diretório de cache temporário único para cada teste
        $this->cacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'imoveis_cache_test_' . uniqid();
        $this->fileCache = new FileCache($this->cacheDir);
    }

    protected function tearDown(): void
    {
        // Limpar diretório de cache após cada teste
        if (is_dir($this->cacheDir)) {
            $files = array_diff(scandir($this->cacheDir), ['.', '..']);
            foreach ($files as $file) {
                @unlink($this->cacheDir . DIRECTORY_SEPARATOR . $file);
            }
            @rmdir($this->cacheDir);
        }
    }

    // ================================================================
    // FileCache Tests
    // ================================================================

    /**
     * Testa set e get básicos
     *
     * @test
     */
    public function testFileCacheSetAndGet()
    {
        $key = 'test_key';
        $value = ['id' => 1, 'name' => 'Test Property'];

        // Set
        $this->fileCache->set($key, $value, 3600);

        // Get - deve retornar o valor
        $result = $this->fileCache->get($key);
        $this->assertNotNull($result);
        $this->assertEquals($value, $result);
    }

    /**
     * Testa get com chave não existente
     *
     * @test
     */
    public function testFileCacheGetNonExistent()
    {
        $result = $this->fileCache->get('non_existent_key');
        $this->assertNull($result);
    }

    /**
     * Testa has com chave existente
     *
     * @test
     */
    public function testFileCacheHasExistent()
    {
        $key = 'test_key';
        $this->fileCache->set($key, 'value', 3600);

        $this->assertTrue($this->fileCache->has($key));
    }

    /**
     * Testa has com chave não existente
     *
     * @test
     */
    public function testFileCacheHasNonExistent()
    {
        $this->assertFalse($this->fileCache->has('non_existent_key'));
    }

    /**
     * Testa forget remove uma chave
     *
     * @test
     */
    public function testFileCacheForget()
    {
        $key = 'test_key';
        $this->fileCache->set($key, 'value', 3600);

        $this->assertTrue($this->fileCache->has($key));

        $this->fileCache->forget($key);

        $this->assertFalse($this->fileCache->has($key));
    }

    /**
     * Testa TTL expirado retorna null
     *
     * @test
     */
    public function testFileCacheTTLExpired()
    {
        $key = 'test_key_expired';
        $value = 'value';

        // Set com TTL de 1 segundo
        $this->fileCache->set($key, $value, 1);

        // Verificar que foi cached
        $this->assertNotNull($this->fileCache->get($key));

        // Aguardar expiração (2 segundos)
        sleep(2);

        // Get deve retornar null após expiração
        $result = $this->fileCache->get($key);
        $this->assertNull($result);

        // Has deve retornar false
        $this->assertFalse($this->fileCache->has($key));
    }

    /**
     * Testa serialização de arrays complexos
     *
     * @test
     */
    public function testFileCacheComplexDataTypes()
    {
        $key = 'complex_data';
        $value = [
            'id' => 1,
            'name' => 'Property',
            'features' => ['pool', 'garage', 'garden'],
            'price' => 150000.50,
            'available' => true,
            'metadata' => ['created_at' => '2026-05-06', 'tags' => ['novo', 'luxo']],
        ];

        $this->fileCache->set($key, $value, 3600);
        $result = $this->fileCache->get($key);

        $this->assertEquals($value, $result);
        $this->assertIsArray($result);
        $this->assertCount(6, $result);
    }

    /**
     * Testa cache com TTL 0 (sem expiração)
     *
     * @test
     */
    public function testFileCacheNoExpiration()
    {
        $key = 'no_expiry';
        $value = 'permanent value';

        // TTL 0 = sem expiração
        $this->fileCache->set($key, $value, 0);

        $this->assertTrue($this->fileCache->has($key));
        $this->assertEquals($value, $this->fileCache->get($key));
    }

    /**
     * Testa múltiplas chaves independentes
     *
     * @test
     */
    public function testFileCacheMultipleKeys()
    {
        $keys = ['key1', 'key2', 'key3'];
        $values = ['value1', 'value2', 'value3'];

        // Set todas
        foreach ($keys as $i => $key) {
            $this->fileCache->set($key, $values[$i], 3600);
        }

        // Verificar todas
        foreach ($keys as $i => $key) {
            $this->assertEquals($values[$i], $this->fileCache->get($key));
        }

        // Remover uma
        $this->fileCache->forget('key2');

        $this->assertTrue($this->fileCache->has('key1'));
        $this->assertFalse($this->fileCache->has('key2'));
        $this->assertTrue($this->fileCache->has('key3'));
    }

    // ================================================================
    // CacheFactory Tests
    // ================================================================

    /**
     * Testa que CacheFactory retorna singleton
     *
     * @test
     */
    public function testCacheFactoryReturnsSingleton()
    {
        CacheFactory::reset();
        
        $cache1 = CacheFactory::make();
        $cache2 = CacheFactory::make();

        $this->assertSame($cache1, $cache2);
    }

    /**
     * Testa que CacheFactory sempre retorna CacheInterface
     *
     * @test
     */
    public function testCacheFactoryReturnsCacheInterface()
    {
        CacheFactory::reset();
        
        $cache = CacheFactory::make();

        $this->assertInstanceOf(\App\Cache\CacheInterface::class, $cache);
    }

    // ================================================================
    // Integration Tests
    // ================================================================

    /**
     * Testa cache hit / miss pattern
     *
     * @test
     */
    public function testCacheHitMissPattern()
    {
        $cache = $this->fileCache;
        $key = 'properties_page_1';
        $data = [
            ['id' => 1, 'name' => 'House 1'],
            ['id' => 2, 'name' => 'House 2'],
        ];

        // Miss na primeira vez
        $this->assertNull($cache->get($key));

        // Set (como se tivesse buscado do DB)
        $cache->set($key, $data, 3600);

        // Hit na segunda vez
        $result = $cache->get($key);
        $this->assertNotNull($result);
        $this->assertEquals($data, $result);
    }

    /**
     * Testa invalidação de cache
     *
     * @test
     */
    public function testCacheInvalidation()
    {
        $cache = $this->fileCache;
        $key = 'properties_tenant_1';
        $data = [['id' => 1, 'name' => 'Property']];

        // Cache hit
        $cache->set($key, $data, 3600);
        $this->assertNotNull($cache->get($key));

        // Invalidate
        $cache->forget($key);

        // Cache miss após invalidação
        $this->assertNull($cache->get($key));
    }

    /**
     * Testa que chaves diferentes não colidem
     *
     * @test
     */
    public function testCacheKeyIsolation()
    {
        $cache = $this->fileCache;
        
        $cache->set('properties_user_1', ['data' => 'user1']);
        $cache->set('properties_user_2', ['data' => 'user2']);

        $user1Data = $cache->get('properties_user_1');
        $user2Data = $cache->get('properties_user_2');

        $this->assertEquals(['data' => 'user1'], $user1Data);
        $this->assertEquals(['data' => 'user2'], $user2Data);
        $this->assertNotEquals($user1Data, $user2Data);
    }
}
