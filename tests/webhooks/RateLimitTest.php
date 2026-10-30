<?php

namespace Tests\Webhooks;

use App\Security\RateLimitPolicy;
use App\Security\RateLimiter;
use PHPUnit\Framework\TestCase;

class RateLimitTest extends TestCase
{
    public function test_pre_auth_limit_behavior(): void
    {
        $endpoint = 'olx_pre_auth';
        $identifier = 'test_ip_1';
        $resolvedIdentifier = $endpoint . ':' . $identifier;
        $config = RateLimitPolicy::getConfig($endpoint);
        $limit = $config['limit'];
        $window = $config['window'];
        $rateLimiter = new RateLimiter();

        $rateLimiter->reset($resolvedIdentifier, $window);

        for ($i = 0; $i < $limit; $i++) {
            $allowed = $rateLimiter->isAllowed($resolvedIdentifier, $limit, $window);
            $this->assertTrue($allowed, "Expected allowed on iteration $i");
        }

        $blocked = $rateLimiter->isAllowed($resolvedIdentifier, $limit, $window);
        $this->assertFalse($blocked, "Expected blocked after reaching limit for $endpoint");
        $this->assertSame(RateLimitPolicy::getConfig($endpoint), $config);
    }

    public function test_tenant_isolation_behavior(): void
    {
        $endpointTenant = 'olx_tenant';
        $idA = 'tenant_1';
        $idB = 'tenant_2';
        $resolvedA = $endpointTenant . ':' . $idA;
        $resolvedB = $endpointTenant . ':' . $idB;
        $configT = RateLimitPolicy::getConfig($endpointTenant);
        $limitT = $configT['limit'];
        $windowT = $configT['window'];
        $rateLimiter = new RateLimiter();

        $rateLimiter->reset($resolvedA, $windowT);
        $rateLimiter->reset($resolvedB, $windowT);

        for ($i = 0; $i < $limitT; $i++) {
            $ra = $rateLimiter->isAllowed($resolvedA, $limitT, $windowT);
            $rb = $rateLimiter->isAllowed($resolvedB, $limitT, $windowT);
            $this->assertTrue($ra === true, "Tenant A should be allowed at iteration $i");
            $this->assertTrue($rb === true, "Tenant B should be allowed at iteration $i");
        }

        $ra2 = $rateLimiter->isAllowed($resolvedA, $limitT, $windowT);
        $rb2 = $rateLimiter->isAllowed($resolvedB, $limitT, $windowT);
        $this->assertFalse($ra2, "Tenant A expected blocked after limit");
        $this->assertFalse($rb2, "Tenant B expected blocked after limit");
        $this->assertSame(RateLimitPolicy::getConfig($endpointTenant), $configT);
    }
}
