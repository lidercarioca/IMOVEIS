<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class UserTenantLegacyCompatibilityTest extends TestCase
{
    public function testLegacyUserModelDelegatesToModernServiceAndRepository(): void
    {
        $source = file_get_contents(__DIR__ . '/../../app/legacy/UserModel.php');

        $this->assertIsString($source);
        $this->assertStringContainsString('App\\Application\\User\\UserService', $source);
        $this->assertStringContainsString('App\\Infrastructure\\User\\UserRepository', $source);
        $this->assertStringContainsString('App\\Application\\User\\UserAuditService', $source);
        $this->assertStringNotContainsString('SELECT * FROM {$this->table}', $source);
        $this->assertStringNotContainsString('INSERT INTO audit_logs', $source);
    }

    public function testLegacyTenantModelDelegatesToModernRepository(): void
    {
        $source = file_get_contents(__DIR__ . '/../../app/models/TenantModel.php');

        $this->assertIsString($source);
        $this->assertStringContainsString('App\\Infrastructure\\Tenant\\TenantRepository', $source);
        $this->assertStringNotContainsString('SELECT * FROM {$this->table} WHERE status = \'active\'', $source);
        $this->assertStringNotContainsString('SELECT * FROM users WHERE tenant_id = :tenant_id', $source);
    }

    public function testModernTenantRepositoryProvidesLegacyCompatibilityMethods(): void
    {
        $this->assertTrue(method_exists(\App\Infrastructure\Tenant\TenantRepository::class, 'listActiveTenants'));
        $this->assertTrue(method_exists(\App\Infrastructure\Tenant\TenantRepository::class, 'getTenantStats'));
        $this->assertTrue(method_exists(\App\Infrastructure\Tenant\TenantRepository::class, 'getTenantUsers'));
        $this->assertTrue(method_exists(\App\Infrastructure\Tenant\TenantRepository::class, 'userBelongsToTenant'));
        $this->assertTrue(method_exists(\App\Infrastructure\Tenant\TenantRepository::class, 'updateTenant'));
    }
}
