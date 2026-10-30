<?php

namespace Tests\Unit;

use App\Models\BillingHistoryModel;
use App\Infrastructure\Billing\PaymentRepository;
use App\Infrastructure\Billing\TenantRepository;
use App\Services\Billing\TenantBillingService;
use PDO;
use PHPUnit\Framework\TestCase;

class TenantBillingServiceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT NULL
            )'
        );
        $this->pdo->exec('CREATE UNIQUE INDEX uq_pagamentos_tenant_stripe_reference ON pagamentos(tenant_id, stripe_reference)');
    }

    private function countPayments(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM pagamentos');
        return (int)$stmt->fetchColumn();
    }

    public function test_registerPayment_commits_when_tenant_update_succeeds(): void
    {
        $tenantRepo = $this->createMock(TenantRepository::class);
        $tenantRepo->method('updateStripeIdentifiers')->willReturn(true);
        $tenantRepo->method('updateUserLimitFromPlan')->willReturn(true);
        $tenantRepo->expects($this->once())
            ->method('updateBillingFields')
            ->with(1, $this->arrayHasKey('plano'))
            ->willReturn(true);

        $service = new TenantBillingService(
            new PaymentRepository(new BillingHistoryModel($this->pdo)),
            $tenantRepo,
            null,
            $this->pdo
        );

        $result = $service->registerPayment(1, [
            'valor' => 100.0,
            'plano' => 'basico',
            'status' => 'pago',
            'data_pagamento' => date('Y-m-d H:i:s'),
            'stripe_reference' => 'cs_tenant_ok_1',
            'stripe_reference_type' => 'checkout_session',
            'stripe_event_id' => 'evt_tenant_ok_1',
        ], ['plano' => 'basico']);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $this->countPayments());
    }

    public function test_registerPayment_rolls_back_when_tenant_update_fails(): void
    {
        $tenantRepo = $this->createMock(TenantRepository::class);
        $tenantRepo->method('updateStripeIdentifiers')->willReturn(true);
        $tenantRepo->method('updateUserLimitFromPlan')->willReturn(true);
        $tenantRepo->expects($this->once())
            ->method('updateBillingFields')
            ->with(1, $this->arrayHasKey('plano'))
            ->willReturn(false);

        $service = new TenantBillingService(
            new PaymentRepository(new BillingHistoryModel($this->pdo)),
            $tenantRepo,
            null,
            $this->pdo
        );

        try {
            $service->registerPayment(1, [
                'valor' => 100.0,
                'plano' => 'basico',
                'status' => 'pago',
                'data_pagamento' => date('Y-m-d H:i:s'),
                'stripe_reference' => 'cs_tenant_fail_1',
                'stripe_reference_type' => 'checkout_session',
                'stripe_event_id' => 'evt_tenant_fail_1',
            ], ['plano' => 'basico']);
            $this->fail('Expected RuntimeException when tenant update fails');
        } catch (\Throwable $e) {
            $this->assertSame(0, $this->countPayments());
            $this->assertStringContainsString('Failed to update tenant billing fields', $e->getMessage());
        }
    }
}
