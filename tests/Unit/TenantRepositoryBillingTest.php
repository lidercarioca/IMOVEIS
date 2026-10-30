<?php

namespace Tests\Unit;

use App\Infrastructure\Billing\TenantRepository;
use PDO;
use PHPUnit\Framework\TestCase;

class TenantRepositoryBillingTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE tenants (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT,
                status TEXT,
                plano TEXT,
                limite_usuarios INTEGER,
                data_expiracao TEXT,
                stripe_customer_id TEXT,
                stripe_subscription_id TEXT,
                created_at TEXT,
                updated_at TEXT
            )'
        );
        $this->pdo->exec(
            'CREATE TABLE plans (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code TEXT,
                name TEXT,
                description TEXT,
                max_users INTEGER,
                max_properties INTEGER,
                monthly_price TEXT,
                trial_days INTEGER,
                is_trial INTEGER,
                is_active INTEGER,
                display_order INTEGER,
                stripe_price_id TEXT,
                created_at TEXT,
                updated_at TEXT
            )'
        );
        $this->pdo->exec("INSERT INTO plans (code, name, max_users, is_active) VALUES ('trial', 'Trial', 2, 1)");
    }

    public function test_shouldShowExpiryWarning_uses_tenant_deadline_window(): void
    {
        $repo = new TenantRepository($this->pdo);

        $this->pdo->exec("INSERT INTO tenants (name, status, plano, limite_usuarios, data_expiracao) VALUES ('Acme', 'active', 'basico', 5, datetime('now', '+2 days'))");

        $this->assertTrue($repo->shouldShowExpiryWarning(1));
        $daysRemaining = $repo->getDaysRemaining(1);
        $this->assertNotNull($daysRemaining);
        $this->assertGreaterThan(0, $daysRemaining);
        $this->assertLessThanOrEqual(3, $daysRemaining);
    }

    public function test_activateTrial_sets_active_status_and_expiration_window(): void
    {
        $repo = new TenantRepository($this->pdo);

        $this->pdo->exec("INSERT INTO tenants (name, status, plano, limite_usuarios, data_expiracao) VALUES ('Acme', 'inactive', 'basico', 2, datetime('now', '-1 day'))");

        $this->assertTrue($repo->activateTrial(1, 7));

        $tenant = $this->pdo->query("SELECT status, plano, limite_usuarios, data_expiracao FROM tenants WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('active', $tenant['status']);
        $this->assertSame('trial', $tenant['plano']);
        $this->assertSame(2, (int)$tenant['limite_usuarios']);
        $this->assertNotNull($tenant['data_expiracao']);
    }

    public function test_checkTenantActiveStatus_is_read_only_and_does_not_mutate_status(): void
    {
        $repo = new TenantRepository($this->pdo);

        $this->pdo->exec("INSERT INTO tenants (name, status, plano, limite_usuarios, data_expiracao) VALUES ('Acme', 'trial', 'trial', 2, datetime('now', '+2 days'))");

        $result = $repo->checkTenantActiveStatus(1);

        $this->assertTrue($result['ativo']);
        $tenant = $this->pdo->query("SELECT status FROM tenants WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('trial', $tenant['status']);
    }

    public function test_normalizeTenantStatus_applies_explicit_write_when_requested(): void
    {
        $repo = new TenantRepository($this->pdo);

        $this->pdo->exec("INSERT INTO tenants (name, status, plano, limite_usuarios, data_expiracao) VALUES ('Acme', 'trial', 'trial', 2, datetime('now', '+2 days'))");

        $result = $repo->normalizeTenantStatus(1);

        $this->assertTrue($result['ativo']);
        $tenant = $this->pdo->query("SELECT status FROM tenants WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('active', $tenant['status']);
    }
}
