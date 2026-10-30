<?php

namespace Tests\Unit;

use App\Infrastructure\Billing\TenantRepository;
use PDO;
use PHPUnit\Framework\TestCase;

class TenantRepositoryUpdateUserLimitTest extends TestCase
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
    }

    public function test_updateUserLimitFromPlan_updates_limit_for_valid_tenant_and_plan(): void
    {
        $this->pdo->exec("INSERT INTO plans (code, name, max_users, is_active) VALUES ('basico','Basico', 5, 1)");
        $this->pdo->exec("INSERT INTO tenants (name, status, plano, limite_usuarios) VALUES ('A', 'active', 'basico', 2)");

        $repo = new TenantRepository($this->pdo);
        $this->assertTrue($repo->updateUserLimitFromPlan(1));

        $tenant = $this->pdo->query('SELECT limite_usuarios FROM tenants WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(5, (int)$tenant['limite_usuarios']);
    }

    public function test_updateUserLimitFromPlan_returns_false_if_tenant_not_found(): void
    {
        $repo = new TenantRepository($this->pdo);
        $this->assertFalse($repo->updateUserLimitFromPlan(999));
    }

    public function test_updateUserLimitFromPlan_returns_false_if_tenant_has_no_plan(): void
    {
        $this->pdo->exec("INSERT INTO tenants (name, status, limite_usuarios) VALUES ('B', 'active', 2)");
        $repo = new TenantRepository($this->pdo);
        $this->assertFalse($repo->updateUserLimitFromPlan(1));
    }

    public function test_updateUserLimitFromPlan_returns_false_if_plan_not_exists(): void
    {
        $this->pdo->exec("INSERT INTO tenants (name, status, plano, limite_usuarios) VALUES ('C', 'active', 'nao_existe', 2)");
        $repo = new TenantRepository($this->pdo);
        $this->assertFalse($repo->updateUserLimitFromPlan(1));
    }

    public function test_updateUserLimitFromPlan_only_updates_target_tenant(): void
    {
        $this->pdo->exec("INSERT INTO plans (code, name, max_users, is_active) VALUES ('prof', 'Prof', 10, 1)");
        $this->pdo->exec("INSERT INTO tenants (name, status, plano, limite_usuarios) VALUES ('T1', 'active', 'prof', 2)");
        $this->pdo->exec("INSERT INTO tenants (name, status, plano, limite_usuarios) VALUES ('T2', 'active', 'prof', 3)");

        $repo = new TenantRepository($this->pdo);
        $this->assertTrue($repo->updateUserLimitFromPlan(1));

        $t1 = $this->pdo->query('SELECT limite_usuarios FROM tenants WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        $t2 = $this->pdo->query('SELECT limite_usuarios FROM tenants WHERE id = 2')->fetch(PDO::FETCH_ASSOC);

        $this->assertSame(10, (int)$t1['limite_usuarios']);
        $this->assertSame(3, (int)$t2['limite_usuarios']);
    }
}
