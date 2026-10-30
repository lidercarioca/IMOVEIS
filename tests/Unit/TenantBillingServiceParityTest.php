<?php

namespace Tests\Unit;

use App\Models\BillingHistoryModel;
use App\Infrastructure\Billing\PaymentRepository;
use App\Infrastructure\Billing\TenantRepository;
use App\Services\Billing\TenantBillingService;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Testes de paridade entre legacy registrarPagamento() e fluxo PSR-4
 * Esses testes demonstram os gaps: sincronização de limite_usuarios e
 * semântica de data_expiracao para pagamentos manuais.
 *
 * Regras: não alteram código de produção; usam SQLite in-memory.
 */
class TenantBillingServiceParityTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Criar tabelas mínimas necessárias
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

        $this->pdo->exec(
            'CREATE TABLE pagamentos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tenant_id INTEGER NOT NULL,
                valor REAL NOT NULL,
                status TEXT,
                data_pagamento TEXT,
                plano TEXT,
                stripe_reference TEXT,
                stripe_reference_type TEXT,
                stripe_event_id TEXT,
                created_at TEXT,
                updated_at TEXT
            )'
        );

        // Índices de unicidade simulando produção
        $this->pdo->exec('CREATE UNIQUE INDEX uq_pagamentos_tenant_stripe_reference ON pagamentos(tenant_id, stripe_reference)');
        $this->pdo->exec('CREATE UNIQUE INDEX uq_pagamentos_tenant_stripe_event_id ON pagamentos(tenant_id, stripe_event_id)');
    }

    private function countPayments(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM pagamentos');
        return (int)$stmt->fetchColumn();
    }

    public function test_limite_usuarios_not_synchronized_by_registerPayment_should_fail_for_parity(): void
    {
        // Cenário: plano com limite 5, tenant com limite atual 2
        $this->pdo->exec("INSERT INTO plans (code, name, max_users, is_active) VALUES ('basico','Basico', 5, 1)");
        $this->pdo->exec("INSERT INTO tenants (name, status, plano, limite_usuarios) VALUES ('T1', 'inactive', 'old', 2)");

        $paymentModel = new BillingHistoryModel($this->pdo);
        $paymentRepo = new PaymentRepository($paymentModel, $this->pdo);
        $tenantRepo = new TenantRepository($this->pdo);
        $service = new TenantBillingService($paymentRepo, $tenantRepo, null, $this->pdo);

        $result = $service->registerPayment(1, [
            'valor' => 100.0,
            'plano' => 'basico',
            'status' => 'pago',
            'data_pagamento' => date('Y-m-d H:i:s'),
            'stripe_reference' => 'manual_ref_1',
            'stripe_reference_type' => 'manual',
            'stripe_event_id' => null,
        ], ['plano' => 'basico', 'status' => 'active']);

        $this->assertTrue($result['success']);

        $tenant = $this->pdo->query('SELECT limite_usuarios FROM tenants WHERE id = 1')->fetch(PDO::FETCH_ASSOC);

        // Paridade esperada (legacy): limite_usuarios atualizado para 5
        $this->assertSame(5, (int)$tenant['limite_usuarios']);
    }

    public function test_data_expiracao_future_is_extended_like_legacy_should_fail_for_parity(): void
    {
        // Tenant com expiração futura
        $future = date('Y-m-d H:i:s', strtotime('+10 days'));
        $this->pdo->exec("INSERT INTO plans (code, name, max_users, is_active) VALUES ('basico','Basico', 5, 1)");
        $this->pdo->exec("INSERT INTO tenants (name, status, plano, limite_usuarios, data_expiracao) VALUES ('T2', 'active', 'basico', 2, '$future')");

        $paymentModel = new BillingHistoryModel($this->pdo);
        $paymentRepo = new PaymentRepository($paymentModel, $this->pdo);
        $tenantRepo = new TenantRepository($this->pdo);
        $service = new TenantBillingService($paymentRepo, $tenantRepo, null, $this->pdo);

        // Usando fluxo manual real (não existe dias_expiracao no contrato atual)
        $result = $service->registerPayment(1, [
            'valor' => 50.0,
            'plano' => 'basico',
            'status' => 'pago',
            'data_pagamento' => date('Y-m-d H:i:s'),
            'stripe_reference' => 'manual_ref_2',
            'stripe_reference_type' => 'manual',
            'stripe_event_id' => null,
        ], ['plano' => 'basico', 'status' => 'active']);

        $this->assertTrue($result['success']);

        $tenant = $this->pdo->query('SELECT data_expiracao FROM tenants WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        $newExpiry = $tenant['data_expiracao'];

        // Legado esperaria: old + 30 days (default). Comparar e exigir paridade.
        $expected = date('Y-m-d H:i:s', strtotime('+30 days', strtotime($future)));
        $this->assertSame($expected, $newExpiry);
    }

    public function test_data_expiracao_expired_becomes_now_plus_30_days_should_fail_for_parity(): void
    {
        $past = date('Y-m-d H:i:s', strtotime('-5 days'));
        $this->pdo->exec("INSERT INTO plans (code, name, max_users, is_active) VALUES ('basico','Basico', 5, 1)");
        $this->pdo->exec("INSERT INTO tenants (name, status, plano, limite_usuarios, data_expiracao) VALUES ('T3', 'inactive', 'basico', 2, '$past')");

        $paymentModel = new BillingHistoryModel($this->pdo);
        $paymentRepo = new PaymentRepository($paymentModel, $this->pdo);
        $tenantRepo = new TenantRepository($this->pdo);
        $service = new TenantBillingService($paymentRepo, $tenantRepo, null, $this->pdo);

        $result = $service->registerPayment(1, [
            'valor' => 75.0,
            'plano' => 'basico',
            'status' => 'pago',
            'data_pagamento' => date('Y-m-d H:i:s'),
            'stripe_reference' => 'manual_ref_3',
            'stripe_reference_type' => 'manual',
            'stripe_event_id' => null,
        ], ['plano' => 'basico', 'status' => 'active']);

        $this->assertTrue($result['success']);

        $tenant = $this->pdo->query('SELECT data_expiracao FROM tenants WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        $newExpiry = $tenant['data_expiracao'];

        $expected = date('Y-m-d H:i:s', strtotime('+30 days'));
        $this->assertSame($expected, $newExpiry);
    }

    public function test_data_expiracao_null_becomes_now_plus_30_days_should_fail_for_parity(): void
    {
        $this->pdo->exec("INSERT INTO plans (code, name, max_users, is_active) VALUES ('basico','Basico', 5, 1)");
        $this->pdo->exec("INSERT INTO tenants (name, status, plano, limite_usuarios, data_expiracao) VALUES ('T4', 'inactive', 'basico', 2, NULL)");

        $paymentModel = new BillingHistoryModel($this->pdo);
        $paymentRepo = new PaymentRepository($paymentModel, $this->pdo);
        $tenantRepo = new TenantRepository($this->pdo);
        $service = new TenantBillingService($paymentRepo, $tenantRepo, null, $this->pdo);

        $result = $service->registerPayment(1, [
            'valor' => 60.0,
            'plano' => 'basico',
            'status' => 'pago',
            'data_pagamento' => date('Y-m-d H:i:s'),
            'stripe_reference' => 'manual_ref_4',
            'stripe_reference_type' => 'manual',
            'stripe_event_id' => null,
        ], ['plano' => 'basico', 'status' => 'active']);

        $this->assertTrue($result['success']);

        $tenant = $this->pdo->query('SELECT data_expiracao FROM tenants WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        $newExpiry = $tenant['data_expiracao'];

        $expected = date('Y-m-d H:i:s', strtotime('+30 days'));
        $this->assertSame($expected, $newExpiry);
    }

    public function test_atomicity_rolls_back_when_tenant_update_fails(): void
    {
        // Preparar ambiente
        $this->pdo->exec("INSERT INTO plans (code, name, max_users, is_active) VALUES ('basico','Basico', 5, 1)");
        $this->pdo->exec("INSERT INTO tenants (name, status, plano, limite_usuarios) VALUES ('T5', 'inactive', 'old', 2)");

        $paymentModel = new BillingHistoryModel($this->pdo);
        $paymentRepo = new PaymentRepository($paymentModel, $this->pdo);

        // Criar mock de TenantRepository que lança exceção ao atualizar
        $tenantRepoMock = $this->getMockBuilder(TenantRepository::class)
            ->setConstructorArgs([$this->pdo])
            ->onlyMethods(['updateBillingFields'])
            ->getMock();

        $tenantRepoMock->expects($this->once())->method('updateBillingFields')->willThrowException(new \Exception('forced failure'));

        $service = new TenantBillingService($paymentRepo, $tenantRepoMock, null, $this->pdo);

        try {
            $service->registerPayment(1, [
                'valor' => 120.0,
                'plano' => 'basico',
                'status' => 'pago',
                'data_pagamento' => date('Y-m-d H:i:s'),
                'stripe_reference' => 'manual_ref_5',
                'stripe_reference_type' => 'manual',
                'stripe_event_id' => null,
            ], ['plano' => 'basico', 'status' => 'active']);

            $this->fail('Expected exception from tenant update');
        } catch (\Throwable $e) {
            // No transaction: pagamento não deve persistir
            $this->assertSame(0, $this->countPayments());

            $tenant = $this->pdo->query('SELECT limite_usuarios, data_expiracao FROM tenants WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
            $this->assertSame(2, (int)$tenant['limite_usuarios']);
            $this->assertNull($tenant['data_expiracao']);
        }
    }

    public function test_idempotency_prevents_duplicate_payments(): void
    {
        $this->pdo->exec("INSERT INTO plans (code, name, max_users, is_active) VALUES ('basico','Basico', 5, 1)");
        $this->pdo->exec("INSERT INTO tenants (name, status, plano, limite_usuarios) VALUES ('T6', 'inactive', 'old', 2)");

        $paymentModel = new BillingHistoryModel($this->pdo);
        $paymentRepo = new PaymentRepository($paymentModel, $this->pdo);
        $tenantRepo = new TenantRepository($this->pdo);
        $service = new TenantBillingService($paymentRepo, $tenantRepo, null, $this->pdo);

        $res1 = $service->registerPayment(1, [
            'valor' => 150.0,
            'plano' => 'basico',
            'status' => 'pago',
            'data_pagamento' => date('Y-m-d H:i:s'),
            'stripe_reference' => 'dup_ref_1',
            'stripe_reference_type' => 'manual',
            'stripe_event_id' => 'evt_dup_1',
        ], ['plano' => 'basico', 'status' => 'active']);

        $res2 = $service->registerPayment(1, [
            'valor' => 150.0,
            'plano' => 'basico',
            'status' => 'pago',
            'data_pagamento' => date('Y-m-d H:i:s'),
            'stripe_reference' => 'dup_ref_1',
            'stripe_reference_type' => 'manual',
            'stripe_event_id' => 'evt_dup_1',
        ], ['plano' => 'basico', 'status' => 'active']);

        $this->assertTrue($res1['success']);
        $this->assertTrue($res2['success']);
        $this->assertSame(1, $this->countPayments());
        $this->assertArrayHasKey('existing', $res2);
        $this->assertTrue((bool)($res2['existing'] ?? false));
    }

    public function test_isolation_between_tenants(): void
    {
        $this->pdo->exec("INSERT INTO plans (code, name, max_users, is_active) VALUES ('basico','Basico', 5, 1)");
        $this->pdo->exec("INSERT INTO tenants (name, status, plano, limite_usuarios) VALUES ('A', 'inactive', 'old', 2)");
        $this->pdo->exec("INSERT INTO tenants (name, status, plano, limite_usuarios) VALUES ('B', 'inactive', 'old', 3)");

        $paymentModel = new BillingHistoryModel($this->pdo);
        $paymentRepo = new PaymentRepository($paymentModel, $this->pdo);
        $tenantRepo = new TenantRepository($this->pdo);
        $service = new TenantBillingService($paymentRepo, $tenantRepo, null, $this->pdo);

        $res = $service->registerPayment(1, [
            'valor' => 80.0,
            'plano' => 'basico',
            'status' => 'pago',
            'data_pagamento' => date('Y-m-d H:i:s'),
            'stripe_reference' => 'iso_ref_1',
            'stripe_reference_type' => 'manual',
            'stripe_event_id' => null,
        ], ['plano' => 'basico', 'status' => 'active']);

        $this->assertTrue($res['success']);

        $tA = $this->pdo->query('SELECT plano, limite_usuarios FROM tenants WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        $tB = $this->pdo->query('SELECT plano, limite_usuarios FROM tenants WHERE id = 2')->fetch(PDO::FETCH_ASSOC);

        $this->assertSame('basico', $tA['plano']);
        $this->assertSame(3, (int)$tB['limite_usuarios']);
    }
}
