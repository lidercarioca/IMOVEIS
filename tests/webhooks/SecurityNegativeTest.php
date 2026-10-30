<?php

namespace Tests\Webhooks;

use App\Cache\FileCache;
use App\Middleware\RateLimitMiddleware;
use App\Security\RateLimitPolicy;
use App\Security\RateLimiter;
use PHPUnit\Framework\TestCase;

/**
 * Integration-style negative security checks.
 * Kept as a PHPUnit test but skipped by default so the full suite remains canonical.
 */
class SecurityNegativeTest extends TestCase
{
    public function testSecurityNegativeScenarios(): void
    {
        if (getenv('RUN_INTEGRATION_TESTS') !== '1') {
            $this->markTestSkipped('Integration negative security checks are skipped by default');
            return;
        }

        $this->assertTrue(class_exists('App\\Security\\WebhookAuthenticator'));
        $this->assertTrue(class_exists('App\\Application\\Integration\\OLXWebhookService'));

        $cacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rate_limit_negative_' . uniqid('', true);
        @mkdir($cacheDir, 0777, true);

        try {
            $cache = new FileCache($cacheDir);
            $rateLimiter = new RateLimiter($cache);
            $endpoint = 'password_reset';
            $identifier = 'user-negative';
            $resolvedIdentifier = $endpoint . ':' . $identifier;
            $config = RateLimitPolicy::getConfig($endpoint);
            $limit = (int) $config['limit'];
            $window = (int) $config['window'];

            $rateLimiter->reset($resolvedIdentifier, $window);

            for ($i = 0; $i < $limit; $i++) {
                $result = $rateLimiter->isAllowed($resolvedIdentifier, $limit, $window);
                $this->assertTrue($result);
            }

            $blocked = $rateLimiter->isAllowed($resolvedIdentifier, $limit, $window);
            $headerInfo = $rateLimiter->getHeaderInfo($resolvedIdentifier, $limit, $window);
            $this->assertFalse($blocked);
            $this->assertSame(0, $headerInfo['remaining']);
            $this->assertGreaterThan(0, $headerInfo['reset_in']);

            $middleware = new RateLimitMiddleware($rateLimiter);
            $middleware->setLimit('api', 2);

            $allowed = $middleware->handle(['ip' => '10.0.0.1', 'action' => 'api'], static fn(): array => ['status' => 200]);
            $this->assertSame(200, $allowed['status']);

            $allowedSecond = $middleware->handle(['ip' => '10.0.0.1', 'action' => 'api'], static fn(): array => ['status' => 200]);
            $this->assertSame(200, $allowedSecond['status']);

            $blockedResponse = $middleware->handle(['ip' => '10.0.0.1', 'action' => 'api'], static fn(): array => ['status' => 200]);
            $this->assertSame(429, $blockedResponse['status']);
            $this->assertSame('0', $blockedResponse['headers']['X-RateLimit-Remaining']);
            $this->assertArrayHasKey('Retry-After', $blockedResponse['headers']);
            $this->assertGreaterThan(0, (int) $blockedResponse['headers']['Retry-After']);

            $tenantAllowed = $middleware->handle(['ip' => 'tenant_2', 'action' => 'api'], static fn(): array => ['status' => 200]);
            $this->assertSame(200, $tenantAllowed['status']);

            $this->assertSame(20, RateLimitPolicy::getConfig('olx_pre_auth')['limit']);
            $this->assertSame(100, RateLimitPolicy::getConfig('olx_tenant')['limit']);
        } finally {
            if (is_dir($cacheDir)) {
                foreach (new \DirectoryIterator($cacheDir) as $item) {
                    if ($item->isDot()) {
                        continue;
                    }

                    @unlink($item->getPathname());
                }

                @rmdir($cacheDir);
            }
        }
    }
}
