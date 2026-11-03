<?php

namespace Tests\Unit;

use App\Tenant\PublicTenantResolver;
use PHPUnit\Framework\TestCase;

class PublicTenantResolverTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT, slug TEXT, company_subdomain TEXT, status TEXT DEFAULT "active")');
        $this->pdo->exec("INSERT INTO tenants (id, name, slug, company_subdomain, status) VALUES (1, 'Local', 'default', 'default', 'active')");
        $this->pdo->exec("INSERT INTO tenants (id, name, slug, company_subdomain, status) VALUES (2, 'Empresa', 'empresa', 'empresa', 'active')");
        $this->pdo->exec("INSERT INTO tenants (id, name, slug, company_subdomain, status) VALUES (42, 'Tenant 42', 'tenant42', 'tenant42', 'inactive')");
        $this->pdo->exec("INSERT INTO tenants (id, name, slug, company_subdomain, status) VALUES (99, 'Default Inativo', 'default-inativo', 'default-inativo', 'inactive')");
        $this->pdo->exec("INSERT INTO tenants (id, name, slug, company_subdomain, status) VALUES (100, 'Tenant 100', 'tenant100', 'tenant100', 'active')");
    }

    public function testLocalhostResolvesToDefaultTenant(): void
    {
        $resolver = new PublicTenantResolver($this->pdo, ['localhost'], 1);

        $result = $resolver->resolve('localhost');

        $this->assertSame('resolved', $result['status']);
        $this->assertSame(1, $result['tenant_id']);
    }

    public function testLocalhostWithInactiveDefaultTenantReturnsTenantInactive(): void
    {
        $this->pdo->exec("UPDATE tenants SET status = 'inactive' WHERE id = 1");
        $resolver = new PublicTenantResolver($this->pdo, ['localhost'], 1);

        $result = $resolver->resolve('localhost');

        $this->assertSame('tenant_inactive', $result['status']);
        $this->assertSame(1, $result['tenant_id']);
    }

    public function testLocalhostWithoutDefaultTenantReturnsTenantNotFound(): void
    {
        $this->pdo->exec("DELETE FROM tenants WHERE id = 1");
        $resolver = new PublicTenantResolver($this->pdo, ['localhost'], 1);

        $result = $resolver->resolve('localhost');

        $this->assertSame('tenant_not_found', $result['status']);
        $this->assertNull($result['tenant_id']);
    }

    public function testLocalhostSubdomainWithExistingActiveTenantResolves(): void
    {
        $this->pdo->exec("UPDATE tenants SET slug = 'riodemansoesabsolute', company_subdomain = 'riodemansoesabsolute', status = 'active' WHERE id = 42");
        $resolver = new PublicTenantResolver($this->pdo, ['localhost'], 1);

        $result = $resolver->resolve('riodemansoesabsolute.localhost');

        $this->assertSame('resolved', $result['status']);
        $this->assertSame(42, $result['tenant_id']);
        $this->assertSame('riodemansoesabsolute', $result['subdomain']);
    }

    public function testLocalhostSubdomainWithInactiveTenantReturnsTenantInactive(): void
    {
        $this->pdo->exec("UPDATE tenants SET slug = 'riodemansoesabsolute', company_subdomain = 'riodemansoesabsolute', status = 'inactive' WHERE id = 42");
        $resolver = new PublicTenantResolver($this->pdo, ['localhost'], 1);

        $result = $resolver->resolve('riodemansoesabsolute.localhost');

        $this->assertSame('tenant_inactive', $result['status']);
        $this->assertSame(42, $result['tenant_id']);
    }

    public function testLocalhostSubdomainWithUnknownTenantReturnsTenantNotFound(): void
    {
        $resolver = new PublicTenantResolver($this->pdo, ['localhost'], 1);

        $result = $resolver->resolve('cliente.localhost');

        $this->assertSame('tenant_not_found', $result['status']);
        $this->assertNull($result['tenant_id']);
    }

    public function testLocalhostSubdomainWithMultipleLevelsIsRejected(): void
    {
        $resolver = new PublicTenantResolver($this->pdo, ['localhost'], 1);

        $result = $resolver->resolve('a.b.localhost');

        $this->assertSame('invalid_host', $result['status']);
        $this->assertNull($result['tenant_id']);
    }

    public function testMalformedLocalhostSubdomainIsRejected(): void
    {
        $resolver = new PublicTenantResolver($this->pdo, ['localhost'], 1);

        $result = $resolver->resolve('sub_domínio.localhost');

        $this->assertSame('invalid_host', $result['status']);
        $this->assertNull($result['tenant_id']);
    }

    public function testLoopbackIpv4DoesNotReceiveDefaultTenant(): void
    {
        $resolver = new PublicTenantResolver($this->pdo, ['localhost'], 1);

        $result = $resolver->resolve('127.0.0.1');

        $this->assertSame('invalid_host', $result['status']);
        $this->assertNull($result['tenant_id']);
    }

    public function testLoopbackIpv6DoesNotReceiveDefaultTenant(): void
    {
        $resolver = new PublicTenantResolver($this->pdo, ['localhost'], 1);

        $result = $resolver->resolve('::1');

        $this->assertSame('invalid_host', $result['status']);
        $this->assertNull($result['tenant_id']);
    }

    public function testZeroAddressDoesNotReceiveDefaultTenant(): void
    {
        $resolver = new PublicTenantResolver($this->pdo, ['localhost'], 1);

        $result = $resolver->resolve('0.0.0.0');

        $this->assertSame('invalid_host', $result['status']);
        $this->assertNull($result['tenant_id']);
    }

    public function testExternalBaseWithoutAuthorizationIsRejected(): void
    {
        $resolver = new PublicTenantResolver($this->pdo, [], 1);

        $result = $resolver->resolve('empresa.evil.com');

        $this->assertSame('invalid_host', $result['status']);
        $this->assertNull($result['tenant_id']);
    }

    public function testAuthorizedBaseResolvesValidSubdomain(): void
    {
        $resolver = new PublicTenantResolver($this->pdo, ['example.com'], 1);

        $result = $resolver->resolve('empresa.example.com');

        $this->assertSame('resolved', $result['status']);
        $this->assertSame(2, $result['tenant_id']);
        $this->assertSame('empresa', $result['subdomain']);
    }

    public function testTwoLevelSubdomainIsRejected(): void
    {
        $resolver = new PublicTenantResolver($this->pdo, ['example.com'], 1);

        $result = $resolver->resolve('a.b.example.com');

        $this->assertSame('invalid_host', $result['status']);
        $this->assertNull($result['tenant_id']);
    }

    public function testHostnameWithTrailingDotIsNormalized(): void
    {
        $resolver = new PublicTenantResolver($this->pdo, ['example.com'], 1);

        $result = $resolver->resolve('EMPRESA.example.com.');

        $this->assertSame('resolved', $result['status']);
        $this->assertSame('empresa', $result['subdomain']);
    }

    public function testHostnameWithUppercaseIsNormalized(): void
    {
        $resolver = new PublicTenantResolver($this->pdo, ['example.com'], 1);

        $result = $resolver->resolve('Empresa.Example.Com');

        $this->assertSame('resolved', $result['status']);
        $this->assertSame('empresa', $result['subdomain']);
    }

    public function testIpv4IsSupportedWhenAuthorized(): void
    {
        $resolver = new PublicTenantResolver($this->pdo, ['127.0.0.1'], 1);

        $result = $resolver->resolve('127.0.0.1');

        $this->assertSame('resolved', $result['status']);
        $this->assertSame(1, $result['tenant_id']);
    }

    public function testIpv6IsSupportedWhenAuthorized(): void
    {
        $resolver = new PublicTenantResolver($this->pdo, ['::1'], 1);

        $result = $resolver->resolve('[::1]');

        $this->assertSame('resolved', $result['status']);
        $this->assertSame(1, $result['tenant_id']);
    }

    public function testValidPortIsAccepted(): void
    {
        $resolver = new PublicTenantResolver($this->pdo, ['example.com'], 1);

        $result = $resolver->resolve('empresa.example.com:443');

        $this->assertSame('resolved', $result['status']);
        $this->assertSame(2, $result['tenant_id']);
    }

    public function testInvalidPortIsRejected(): void
    {
        $resolver = new PublicTenantResolver($this->pdo, ['example.com'], 1);

        $result = $resolver->resolve('empresa.example.com:70000');

        $this->assertSame('invalid_host', $result['status']);
        $this->assertNull($result['tenant_id']);
    }

    public function testActiveTenantReturnsResolvedStatus(): void
    {
        $resolver = new PublicTenantResolver($this->pdo, ['example.com'], 1);

        $result = $resolver->resolve('tenant100.example.com');

        $this->assertSame('resolved', $result['status']);
        $this->assertSame(100, $result['tenant_id']);
    }

    public function testInactiveTenantReturnsTenantInactive(): void
    {
        $resolver = new PublicTenantResolver($this->pdo, ['example.com'], 1);

        $result = $resolver->resolve('tenant42.example.com');

        $this->assertSame('tenant_inactive', $result['status']);
        $this->assertSame(42, $result['tenant_id']);
    }

    public function testAuthorizedHostnameWithoutMatchingTenantReturnsTenantNotFound(): void
    {
        $resolver = new PublicTenantResolver($this->pdo, ['example.com'], 1);

        $result = $resolver->resolve('naoexiste.example.com');

        $this->assertSame('tenant_not_found', $result['status']);
        $this->assertNull($result['tenant_id']);
    }

    public function testUnknownHostnameDoesNotFallbackToTenantOne(): void
    {
        $resolver = new PublicTenantResolver($this->pdo, ['example.com'], 1);

        $result = $resolver->resolve('desconhecido.example.com');

        $this->assertNotSame('resolved', $result['status']);
        $this->assertNotSame(1, $result['tenant_id']);
    }

    public function testDefaultTenantInactiveIsDetected(): void
    {
        $resolver = new PublicTenantResolver($this->pdo, ['example.com'], 1);

        $this->pdo->exec("UPDATE tenants SET status = 'inactive' WHERE id = 1");

        $result = $resolver->resolve('example.com');

        $this->assertSame('tenant_inactive', $result['status']);
        $this->assertSame(1, $result['tenant_id']);
    }

    public function testDefaultTenantMissingReturnsTenantNotFound(): void
    {
        $resolver = new PublicTenantResolver($this->pdo, ['example.com'], 1);

        $this->pdo->exec("DELETE FROM tenants WHERE slug = 'default' OR company_subdomain = 'default'");

        $result = $resolver->resolve('example.com');

        $this->assertSame('tenant_not_found', $result['status']);
        $this->assertNull($result['tenant_id']);
    }

    public function testMissingConfigRejectsExternalDomains(): void
    {
        $resolver = new PublicTenantResolver($this->pdo, [], 1);

        $result = $resolver->resolve('empresa.example.com');

        $this->assertSame('invalid_host', $result['status']);
        $this->assertNull($result['tenant_id']);
    }

    public function testAmbiguousBaseConfigurationIsRejected(): void
    {
        $resolver = new PublicTenantResolver($this->pdo, ['example.com', 'com'], 1);

        $result = $resolver->resolve('empresa.example.com');

        $this->assertSame('config_error', $result['status']);
        $this->assertNull($result['tenant_id']);
    }
}
