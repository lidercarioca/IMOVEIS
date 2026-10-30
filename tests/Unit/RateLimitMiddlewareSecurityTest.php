<?php

namespace Tests\Unit;

use App\Auth\AuthContext;
use App\Auth\Roles;
use App\Cache\FileCache;
use App\Middleware\RateLimitMiddleware;
use App\Security\RateLimitPolicy;
use App\Security\RateLimiter;
use PHPUnit\Framework\TestCase;

class RateLimitMiddlewareSecurityTest extends TestCase
{
    private string $cacheDir;
    private RateLimiter $rateLimiter;
    private RateLimitMiddleware $middleware;

    protected function setUp(): void
    {
        AuthContext::reset();

        $this->cacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rate_limit_middleware_security_' . uniqid('', true);
        @mkdir($this->cacheDir, 0777, true);

        $this->rateLimiter = new RateLimiter(new FileCache($this->cacheDir));
        $this->middleware = new RateLimitMiddleware($this->rateLimiter);
    }

    protected function tearDown(): void
    {
        AuthContext::reset();

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

    public function testAuthenticatedUserIdentifierOverridesRequestIp(): void
    {
        AuthContext::getInstance()->authenticate(42, Roles::USER, 'token-abc');
        $this->middleware->setLimit('api', 1);

        $first = $this->middleware->handle([
            'action' => 'api',
            'ip' => '10.0.0.1',
            'path' => '/api/first',
        ], static fn(): array => ['status' => 200, 'data' => 'ok']);

        $this->assertSame(200, $first['status']);

        $second = $this->middleware->handle([
            'action' => 'api',
            'ip' => '203.0.113.45',
            'path' => '/api/second',
        ], static fn(): array => ['status' => 200, 'data' => 'ok']);

        $this->assertSame(429, $second['status']);
        $this->assertSame('0', $second['headers']['X-RateLimit-Remaining']);
    }

    public function testUnauthenticatedRequestUsesValidIpIdentifier(): void
    {
        $this->middleware->setLimit('api', 1);

        $first = $this->middleware->handle([
            'action' => 'api',
            'ip' => '198.51.100.10',
            'path' => '/api/ip-ok',
        ], static fn(): array => ['status' => 200, 'data' => 'ok']);

        $this->assertSame(200, $first['status']);

        $second = $this->middleware->handle([
            'action' => 'api',
            'ip' => '198.51.100.10',
            'path' => '/api/ip-ok-again',
        ], static fn(): array => ['status' => 200, 'data' => 'ok']);

        $this->assertSame(429, $second['status']);
        $this->assertSame('0', $second['headers']['X-RateLimit-Remaining']);
    }

    public function testAnonymousRequestWithMissingIpIsRejectedBeforeRateLimitBucket(): void
    {
        $this->middleware->setLimit('api', 1);

        $first = $this->middleware->handle([
            'action' => 'api',
            'path' => '/api/no-ip',
        ], static fn(): array => ['status' => 200, 'data' => 'ok']);

        $this->assertSame(400, $first['status']);
        $this->assertSame('INVALID_IP', $first['error']);

        $second = $this->middleware->handle([
            'action' => 'api',
            'path' => '/api/no-ip-second',
        ], static fn(): array => ['status' => 200, 'data' => 'ok']);

        $this->assertSame(400, $second['status']);
        $this->assertSame('INVALID_IP', $second['error']);
    }

    public function testAnonymousRequestWithEmptyIpIsRejectedBeforeRateLimitBucket(): void
    {
        $this->middleware->setLimit('api', 1);

        $response = $this->middleware->handle([
            'action' => 'api',
            'ip' => '',
            'path' => '/api/empty-ip',
        ], static fn(): array => ['status' => 200, 'data' => 'ok']);

        $this->assertSame(400, $response['status']);
        $this->assertSame('INVALID_IP', $response['error']);
    }

    public function testAnonymousRequestWithMalformedIpIsRejectedBeforeRateLimitBucket(): void
    {
        $this->middleware->setLimit('api', 1);

        $response = $this->middleware->handle([
            'action' => 'api',
            'ip' => 'not-an-ip',
            'path' => '/api/invalid-ip',
        ], static fn(): array => ['status' => 200, 'data' => 'ok']);

        $this->assertSame(400, $response['status']);
        $this->assertSame('INVALID_IP', $response['error']);
    }

    public function testAnonymousRequestWithForwardedHeadersStillRequiresValidIp(): void
    {
        $original = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null;
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.77';

        try {
            $response = $this->middleware->handle([
                'action' => 'api',
                'path' => '/api/forwarded-only',
            ], static fn(): array => ['status' => 200, 'data' => 'ok']);

            $this->assertSame(400, $response['status']);
            $this->assertSame('INVALID_IP', $response['error']);
        } finally {
            if ($original === null) {
                unset($_SERVER['HTTP_X_FORWARDED_FOR']);
            } else {
                $_SERVER['HTTP_X_FORWARDED_FOR'] = $original;
            }
        }
    }

    public function testAuthenticatedIdentityWithoutValidUserIdDoesNotFallbackToIp(): void
    {
        AuthContext::getInstance()->authenticate(0, Roles::USER, 'token-abc');
        $this->middleware->setLimit('api', 1);

        $response = $this->middleware->handle([
            'action' => 'api',
            'ip' => '198.51.100.88',
            'path' => '/api/invalid-auth-user',
        ], static fn(): array => ['status' => 200, 'data' => 'ok']);

        $this->assertSame(401, $response['status']);
        $this->assertSame('Identidade não materializável', $response['error']);
    }

    public function testValidIpv6RequestUsesStableBucketPerSource(): void
    {
        $this->middleware->setLimit('api', 1);

        $first = $this->middleware->handle([
            'action' => 'api',
            'ip' => '2001:db8::1',
            'path' => '/api/ipv6-ok',
        ], static fn(): array => ['status' => 200, 'data' => 'ok']);

        $this->assertSame(200, $first['status']);

        $second = $this->middleware->handle([
            'action' => 'api',
            'ip' => '2001:db8::1',
            'path' => '/api/ipv6-ok-again',
        ], static fn(): array => ['status' => 200, 'data' => 'ok']);

        $this->assertSame(429, $second['status']);
        $this->assertSame('0', $second['headers']['X-RateLimit-Remaining']);
    }

    public function testLimitExceededReturnsExpected429ResponseContract(): void
    {
        $this->middleware->setLimit('api', 1);

        $allowed = $this->middleware->handle([
            'action' => 'api',
            'ip' => '198.51.100.20',
        ], static fn(): array => ['status' => 200, 'data' => 'ok']);

        $this->assertSame(200, $allowed['status']);

        $blocked = $this->middleware->handle([
            'action' => 'api',
            'ip' => '198.51.100.20',
        ], static fn(): array => ['status' => 200, 'data' => 'ok']);

        $this->assertSame(429, $blocked['status']);
        $this->assertArrayHasKey('X-RateLimit-Limit', $blocked['headers']);
        $this->assertArrayHasKey('X-RateLimit-Remaining', $blocked['headers']);
        $this->assertArrayHasKey('X-RateLimit-Reset', $blocked['headers']);
        $this->assertArrayHasKey('Retry-After', $blocked['headers']);
        $this->assertSame('0', $blocked['headers']['X-RateLimit-Remaining']);
        $this->assertGreaterThan(0, (int) $blocked['headers']['Retry-After']);
    }

    public function testMiddlewareUsesConfiguredPolicyValuesWithoutMutatingThem(): void
    {
        $config = RateLimitPolicy::getConfig('api_call');

        $this->assertSame(100, $config['limit']);
        $this->assertSame(60, $config['window']);

        $this->middleware->setLimit('api_call', $config['limit']);

        for ($i = 0; $i < $config['limit']; $i++) {
            $response = $this->middleware->handle([
                'action' => 'api_call',
                'ip' => '198.51.100.30',
            ], static fn(): array => ['status' => 200, 'data' => 'ok']);

            $this->assertSame(200, $response['status']);
        }

        $blocked = $this->middleware->handle([
            'action' => 'api_call',
            'ip' => '198.51.100.30',
        ], static fn(): array => ['status' => 200, 'data' => 'ok']);

        $this->assertSame(429, $blocked['status']);
        $this->assertSame(RateLimitPolicy::getConfig('api_call')['limit'], 100);
    }

    public function testMiddlewareIsNotActivatedInBootstrapOrFrontController(): void
    {
        $files = [
            __DIR__ . '/../../index.php',
            __DIR__ . '/../../app/bootstrap.php',
            __DIR__ . '/../../app/MvcBootstrap.php',
        ];

        foreach ($files as $file) {
            $this->assertFileExists($file);
            $contents = file_get_contents($file);
            $this->assertNotFalse($contents);
            $this->assertStringNotContainsString('new RateLimitMiddleware', $contents, sprintf('%s should not instantiate RateLimitMiddleware', $file));
            $this->assertStringNotContainsString('RateLimitMiddleware', $contents, sprintf('%s should not reference the middleware class', $file));
        }
    }
}
