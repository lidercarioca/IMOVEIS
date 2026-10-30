<?php

namespace Tests\Security;

use App\Cache\FileCache;
use App\Security\RateLimitPolicy;
use App\Security\RateLimiter;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class RateLimiterAdminResetTest extends TestCase
{
    private string $cacheDir;
    private FileCache $cache;
    private RateLimiter $rateLimiter;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rate_limiter_admin_test_' . uniqid('', true);
        @mkdir($this->cacheDir, 0777, true);

        $this->cache = new FileCache($this->cacheDir);
        $this->rateLimiter = new RateLimiter($this->cache);
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->cacheDir)) {
            return;
        }

        foreach (new \DirectoryIterator($this->cacheDir) as $item) {
            if ($item->isDot()) {
                continue;
            }

            @unlink($item->getPathname());
        }

        @rmdir($this->cacheDir);
    }

    public function testResetByEndpointClearsSpecificBucket(): void
    {
        $endpoint = 'contact_form';
        $identifier = 'tenant_a';
        $window = RateLimitPolicy::getConfig($endpoint)['window'];
        $resolvedIdentifier = $endpoint . ':' . $identifier;

        $this->assertTrue($this->rateLimiter->isAllowed($resolvedIdentifier, 10, $window));
        $this->assertSame(1, $this->rateLimiter->getAttempts($resolvedIdentifier, $window));

        $this->assertTrue($this->rateLimiter->resetForEndpoint($endpoint, $identifier, $window));
        $this->assertSame(0, $this->rateLimiter->getAttempts($resolvedIdentifier, $window));
    }

    public function testResetByEndpointDoesNotAffectOtherTenant(): void
    {
        $endpoint = 'contact_form';
        $window = RateLimitPolicy::getConfig($endpoint)['window'];
        $tenantA = $endpoint . ':tenant_a';
        $tenantB = $endpoint . ':tenant_b';

        $this->rateLimiter->isAllowed($tenantA, 10, $window);
        $this->rateLimiter->isAllowed($tenantB, 10, $window);

        $this->rateLimiter->resetForEndpoint($endpoint, 'tenant_a', $window);

        $this->assertSame(0, $this->rateLimiter->getAttempts($tenantA, $window));
        $this->assertSame(1, $this->rateLimiter->getAttempts($tenantB, $window));
    }

    public function testCleanupValidBucketIsPreserved(): void
    {
        $endpoint = 'olx_tenant';
        $identifier = 'tenant_valid';
        $window = RateLimitPolicy::getConfig($endpoint)['window'];
        $resolved = $endpoint . ':' . $identifier;

        $this->rateLimiter->isAllowed($resolved, 100, $window);

        $this->assertSame(0, $this->rateLimiter->cleanupExpiredBuckets(86400));
        $this->assertSame(1, $this->rateLimiter->getAttempts($resolved, $window));
    }

    public function testCleanupExpiredBucketRemovesOnlyExpiredKey(): void
    {
        $window = 60;
        $identifier = 'expired_tenant';
        $key = 'ratelimit:' . $identifier . ':' . (int)(time() / $window);
        $indexKey = 'ratelimit:index:all';

        $this->cache->set($indexKey, [$key], 86400);
        $this->writeExpiredPayload($key, 1, 120);

        $removed = $this->rateLimiter->cleanupExpiredBuckets(86400);

        $this->assertSame(1, $removed);
        $this->assertSame(0, $this->rateLimiter->getAttempts($identifier, $window));
    }

    public function testCleanupDoesNotRemoveOtherTenant(): void
    {
        $endpoint = 'olx_tenant';
        $window = RateLimitPolicy::getConfig($endpoint)['window'];
        $tenantA = $endpoint . ':tenant_a';
        $tenantB = $endpoint . ':tenant_b';
        $indexKey = 'ratelimit:index:all';

        $this->rateLimiter->isAllowed($tenantA, 100, $window);
        $this->rateLimiter->isAllowed($tenantB, 100, $window);

        $this->writeExpiredPayload('ratelimit:' . $tenantA . ':' . (int)(time() / $window), 1, 120);
        $tracked = $this->readTrackedKeys();
        $this->cache->set($indexKey, $tracked, 86400);

        $removed = $this->rateLimiter->cleanupExpiredBuckets(86400);

        $this->assertSame(1, $removed);
        $this->assertSame(1, $this->rateLimiter->getAttempts($tenantB, $window));
    }

    public function testClearAllBucketsOnlyRemovesTrackedRateLimitKeys(): void
    {
        $windowA = RateLimitPolicy::getConfig('contact_form')['window'];
        $windowB = RateLimitPolicy::getConfig('api_call')['window'];

        $this->rateLimiter->isAllowed('contact_form:tenant_a', 10, $windowA);
        $this->rateLimiter->isAllowed('api_call:ip_1', 100, $windowB);
        $this->cache->set('app:session:abc', ['user' => 'x'], 3600);

        $removed = $this->rateLimiter->clearAllBuckets();

        $this->assertSame(2, $removed);
        $this->assertSame(0, $this->rateLimiter->getAttempts('contact_form:tenant_a', $windowA));
        $this->assertSame(0, $this->rateLimiter->getAttempts('api_call:ip_1', $windowB));
        $this->assertSame(['user' => 'x'], $this->cache->get('app:session:abc'));
    }

    public function testResetForMissingBucketReturnsFalse(): void
    {
        $this->assertFalse($this->rateLimiter->resetForEndpoint('olx_pre_auth', 'missing_identifier'));
    }

    public function testResetForEndpointOnlyRemovesSpecificBucket(): void
    {
        $endpoint = 'contact_form';
        $window = RateLimitPolicy::getConfig($endpoint)['window'];

        $this->rateLimiter->isAllowed($endpoint . ':tenant_a', 10, $window);
        $this->rateLimiter->isAllowed($endpoint . ':tenant_b', 10, $window);

        $this->assertTrue($this->rateLimiter->resetForEndpoint($endpoint, 'tenant_a', $window));
        $this->assertSame(0, $this->rateLimiter->getAttempts($endpoint . ':tenant_a', $window));
        $this->assertSame(1, $this->rateLimiter->getAttempts($endpoint . ':tenant_b', $window));
    }

    private function readTrackedKeys(): array
    {
        $keys = $this->cache->get('ratelimit:index:all');
        return is_array($keys) ? $keys : [];
    }

    private function writeExpiredPayload(string $key, int $value, int $secondsAgo): void
    {
        $ref = new ReflectionClass(FileCache::class);
        $prop = $ref->getProperty('directory');
        $prop->setAccessible(true);
        $directory = $prop->getValue($this->cache);

        $payload = [
            'expires' => time() - $secondsAgo,
            'value' => $value,
        ];

        file_put_contents($directory . DIRECTORY_SEPARATOR . sha1($key) . '.cache', serialize($payload));
    }
}
