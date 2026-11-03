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
        $this->pdo->exec(
            'CREATE TABLE pagamentos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tenant_id INTEGER NOT NULL,
                valor TEXT,
                status TEXT,
                data_pagamento TEXT,
                created_at TEXT,
                plano TEXT,
                stripe_reference TEXT,
                stripe_reference_type TEXT,
                stripe_event_id TEXT
            )'
        );
        $this->pdo->exec(
            'CREATE TABLE tenant_trial_usage (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tenant_id INTEGER NOT NULL UNIQUE,
                plan_code TEXT NOT NULL DEFAULT "trial",
                started_at TEXT NOT NULL,
                expires_at TEXT,
                used_at TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
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

    public function test_expired_trial_is_not_in_warning_window_and_is_marked_expired(): void
    {
        $repo = new TenantRepository($this->pdo);

        $this->pdo->exec("INSERT INTO tenants (name, status, plano, limite_usuarios, data_expiracao) VALUES ('Acme', 'trial', 'trial', 2, datetime('now', '-2 days'))");

        $this->assertTrue($repo->isExpired(1));
        $this->assertFalse($repo->shouldShowExpiryWarning(1));
        $this->assertLessThan(0, $repo->getDaysRemaining(1));
    }

    public function test_paid_plan_renewal_is_preserved_for_active_paid_tenant(): void
    {
        $repo = new TenantRepository($this->pdo);
        $this->pdo->exec("INSERT INTO tenants (name, status, plano, limite_usuarios, data_expiracao) VALUES ('Acme', 'active', 'basico', 5, datetime('now', '+2 days'))");

        $tenantInfo = $repo->getTenantById(1);
        $isCurrent = ($tenantInfo['plano'] ?? '') === 'basico';
        $planoExpirado = $repo->isExpired(1);
        $warning = $repo->shouldShowExpiryWarning(1);
        $renewalAllowed = $planoExpirado || $warning;

        $this->assertTrue($isCurrent);
        $this->assertFalse($planoExpirado);
        $this->assertTrue($warning);
        $this->assertTrue($renewalAllowed);
    }

    public function test_hasTrialHistory_requires_persisted_trial_usage_record(): void
    {
        $repo = new TenantRepository($this->pdo);
        $this->pdo->exec("INSERT INTO tenants (name, status, plano, limite_usuarios, data_expiracao) VALUES ('Acme', 'active', 'basico', 5, datetime('now', '+2 days'))");
        $this->pdo->exec("INSERT INTO pagamentos (tenant_id, valor, status, data_pagamento, plano) VALUES (1, 99.90, 'pago', datetime('now'), 'basico')");

        $this->assertFalse($repo->hasTrialHistory(1));

        $this->pdo->exec("INSERT INTO tenant_trial_usage (tenant_id, plan_code, started_at, expires_at, used_at) VALUES (1, 'trial', datetime('now'), datetime('now', '+7 days'), datetime('now'))");
        $this->assertTrue($repo->hasTrialHistory(1));
    }

    public function test_activateTrial_persists_trial_usage_record_without_finance_contamination(): void
    {
        $repo = new TenantRepository($this->pdo);
        $this->pdo->exec("INSERT INTO tenants (name, status, plano, limite_usuarios, data_expiracao) VALUES ('Acme', 'inactive', 'basico', 2, datetime('now', '-1 day'))");

        $this->assertTrue($repo->activateTrial(1, 7));

        $usageCount = $this->pdo->query("SELECT COUNT(*) FROM tenant_trial_usage WHERE tenant_id = 1 AND plan_code = 'trial'")->fetchColumn();
        $paymentCount = $this->pdo->query("SELECT COUNT(*) FROM pagamentos WHERE tenant_id = 1 AND plano = 'trial'")->fetchColumn();

        $this->assertSame('1', (string)$usageCount);
        $this->assertSame('0', (string)$paymentCount);
        $this->assertTrue($repo->hasTrialHistory(1));
    }

    public function test_second_trial_attempt_is_blocked_for_the_same_tenant(): void
    {
        $repo = new TenantRepository($this->pdo);
        $this->pdo->exec("INSERT INTO tenants (name, status, plano, limite_usuarios, data_expiracao) VALUES ('Acme', 'inactive', 'basico', 2, datetime('now', '-1 day'))");

        $this->assertTrue($repo->activateTrial(1, 7));
        $this->assertFalse($repo->activateTrial(1, 7));
        $this->assertSame(1, (int)$this->pdo->query("SELECT COUNT(*) FROM tenant_trial_usage WHERE tenant_id = 1")->fetchColumn());
    }

    public function test_trial_usage_is_isolated_per_tenant(): void
    {
        $repo = new TenantRepository($this->pdo);
        $this->pdo->exec("INSERT INTO tenants (name, status, plano, limite_usuarios, data_expiracao) VALUES ('Acme', 'inactive', 'basico', 2, datetime('now', '-1 day'))");
        $this->pdo->exec("INSERT INTO tenants (name, status, plano, limite_usuarios, data_expiracao) VALUES ('Beta', 'inactive', 'basico', 2, datetime('now', '-1 day'))");

        $this->assertTrue($repo->activateTrial(1, 7));
        $this->assertFalse($repo->hasTrialHistory(2));
        $this->assertTrue($repo->activateTrial(2, 7));
        $this->assertSame(2, (int)$this->pdo->query("SELECT COUNT(*) FROM tenant_trial_usage")->fetchColumn());
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

    public function test_trial_migration_sql_is_idempotent_and_non_destructive(): void
    {
        $createSql = file_get_contents(__DIR__ . '/../../migrations/2026_11_03_create_tenant_trial_usage_table.sql');
        $backfillSql = file_get_contents(__DIR__ . '/../../migrations/2026_11_03_backfill_tenant_trial_usage.sql');

        $this->assertIsString($createSql);
        $this->assertIsString($backfillSql);
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS', $createSql);
        $this->assertStringContainsString('INSERT IGNORE INTO', $backfillSql);
        $this->assertStringContainsString('LEFT JOIN `tenant_trial_usage`', $backfillSql);
    }

    public function test_two_independent_connections_cannot_create_duplicate_trial_usage_records(): void
    {
        $databaseFile = tempnam(sys_get_temp_dir(), 'trial_db_');
        if ($databaseFile === false) {
            $this->markTestSkipped('Unable to create temporary SQLite database for the connection test.');
        }

        unlink($databaseFile);
        $databasePath = $databaseFile;

        $schema = [
            'CREATE TABLE tenants (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, status TEXT, plano TEXT, limite_usuarios INTEGER, data_expiracao TEXT, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE plans (id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT, name TEXT, max_users INTEGER, is_active INTEGER)',
            'CREATE TABLE tenant_trial_usage (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL UNIQUE, plan_code TEXT NOT NULL DEFAULT "trial", started_at TEXT NOT NULL, expires_at TEXT, used_at TEXT NOT NULL, created_at TEXT, updated_at TEXT)',
        ];

        $primary = new PDO('sqlite:' . $databasePath);
        $primary->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $secondary = new PDO('sqlite:' . $databasePath);
        $secondary->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        foreach ($schema as $statement) {
            $primary->exec($statement);
        }

        $primary->exec("INSERT INTO plans (code, name, max_users, is_active) VALUES ('trial', 'Trial', 2, 1)");
        $primary->exec("INSERT INTO tenants (name, status, plano, limite_usuarios, data_expiracao) VALUES ('Acme', 'inactive', 'basico', 2, datetime('now', '-1 day'))");

        $firstRepo = new TenantRepository($primary);
        $secondRepo = new TenantRepository($secondary);

        $this->assertTrue($firstRepo->activateTrial(1, 7));
        $this->assertFalse($secondRepo->activateTrial(1, 7));
        $this->assertSame(1, (int)$primary->query('SELECT COUNT(*) FROM tenant_trial_usage WHERE tenant_id = 1')->fetchColumn());
    }
}
