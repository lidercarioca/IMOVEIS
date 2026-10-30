<?php

namespace Tests\Integration;

use App\Infrastructure\Billing\PaymentRepository;
use App\Models\BillingHistoryModel;
use PHPUnit\Framework\TestCase;
use PDO;

class PaymentRepositoryBillingOverviewTest extends TestCase
{
    private PDO $pdo;
    private PaymentRepository $repo;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../config/database.php';
        global $pdo;
        $this->pdo = $pdo;
        $this->pdo->beginTransaction();

        $model = new BillingHistoryModel($this->pdo);
        $this->repo = new PaymentRepository($model);
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    /** @test */
    public function tenant_inexistente_returns_false(): void
    {
        $nonexistentId = 999999999;
        $psr4 = $this->repo->getBillingOverview($nonexistentId);
        $this->assertFalse($psr4);
    }

    /** @test */
    public function tenant_sem_pagamentos(): void
    {
        $tenantId = $this->createTenant('tenant-no-payments');

        $legacy = $this->getExpectedLegacyBillingOverview($tenantId);
        $psr4 = $this->repo->getBillingOverview($tenantId);

        $this->assertIsArray($legacy);
        $this->assertIsArray($psr4);
        $this->assertSame((int)$legacy['total_pagamentos'], $psr4['total_pagamentos']);
        $this->assertSame((float)$legacy['total_pago'], $psr4['total_pago']);
        $this->assertSame($legacy['ultimo_pagamento'], $psr4['ultimo_pagamento']);
    }

    /** @test */
    public function somente_pagamentos_pago(): void
    {
        $tenantId = $this->createTenant('tenant-paid-only');

        $this->insertPayment($tenantId, 100.00, 'pago', '2026-08-01 10:00:00');
        $this->insertPayment($tenantId, 50.50, 'pago', '2026-08-05 12:00:00');

        $legacy = $this->getExpectedLegacyBillingOverview($tenantId);
        $psr4 = $this->repo->getBillingOverview($tenantId);

        $this->assertSame((int)$legacy['total_pagamentos'], $psr4['total_pagamentos']);
        $this->assertSame((float)$legacy['total_pago'], $psr4['total_pago']);
        $this->assertSame($legacy['ultimo_pagamento'], $psr4['ultimo_pagamento']);
    }

    /** @test */
    public function somente_pagamentos_pendente(): void
    {
        $tenantId = $this->createTenant('tenant-pending-only');

        $this->insertPayment($tenantId, 80.00, 'pendente', '2026-08-02 09:00:00');
        $this->insertPayment($tenantId, 20.00, 'pendente', null);

        $legacy = $this->getExpectedLegacyBillingOverview($tenantId);
        $psr4 = $this->repo->getBillingOverview($tenantId);

        $this->assertSame((int)$legacy['total_pagamentos'], $psr4['total_pagamentos']);
        $this->assertSame((float)$legacy['total_pago'], $psr4['total_pago']);
        $this->assertSame($legacy['ultimo_pagamento'], $psr4['ultimo_pagamento']);
    }

    /** @test */
    public function pagamentos_mistos_incluindo_data_null(): void
    {
        $tenantId = $this->createTenant('tenant-mixed');

        $this->insertPayment($tenantId, 100.00, 'pago', '2026-08-03 08:00:00');
        $this->insertPayment($tenantId, 10.00, 'pago', null);
        $this->insertPayment($tenantId, 50.00, 'pendente', '2026-08-04 08:00:00');

        $legacy = $this->getExpectedLegacyBillingOverview($tenantId);
        $psr4 = $this->repo->getBillingOverview($tenantId);

        $this->assertSame((int)$legacy['total_pagamentos'], $psr4['total_pagamentos']);
        $this->assertSame((float)$legacy['total_pago'], $psr4['total_pago']);
        $this->assertSame($legacy['ultimo_pagamento'], $psr4['ultimo_pagamento']);
    }

    /** @test */
    public function isolamento_multi_tenant(): void
    {
        $a = $this->createTenant('tenant-A');
        $b = $this->createTenant('tenant-B');

        $this->insertPayment($a, 10.00, 'pago', '2026-08-01 01:00:00');
        $this->insertPayment($b, 200.00, 'pago', '2026-08-02 02:00:00');

        $psrA = $this->repo->getBillingOverview($a);
        $psrB = $this->repo->getBillingOverview($b);

        $this->assertSame(1, $psrA['total_pagamentos']);
        $this->assertSame(10.0, $psrA['total_pago']);
        $this->assertSame('2026-08-01 01:00:00', $psrA['ultimo_pagamento']);

        $this->assertSame(1, $psrB['total_pagamentos']);
        $this->assertSame(200.0, $psrB['total_pago']);
        $this->assertSame('2026-08-02 02:00:00', $psrB['ultimo_pagamento']);
    }

    // ------------------------
    // Helpers
    // ------------------------

    private function getExpectedLegacyBillingOverview(int $tenantId): array|false
    {
        $sql = "SELECT
                    t.id,
                    t.name,
                    t.status,
                    t.plano,
                    t.limite_usuarios,
                    t.data_expiracao,
                    t.created_at,
                    COUNT(p.id) AS total_pagamentos,
                    SUM(CASE WHEN p.status = 'pago' THEN p.valor ELSE 0 END) AS total_pago,
                    MAX(p.data_pagamento) AS ultimo_pagamento
                FROM tenants t
                LEFT JOIN pagamentos p ON p.tenant_id = t.id
                WHERE t.id = :tenant_id
                GROUP BY t.id
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':tenant_id' => $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return false;
        }

        $row['total_pagamentos'] = isset($row['total_pagamentos']) ? (int)$row['total_pagamentos'] : 0;
        $row['total_pago'] = isset($row['total_pago']) ? (float)$row['total_pago'] : 0.0;
        $row['ultimo_pagamento'] = $row['ultimo_pagamento'] !== null ? (string)$row['ultimo_pagamento'] : null;

        return $row;
    }

    private function createTenant(string $slugPrefix): int
    {
        $slug = $slugPrefix . '-' . substr(uniqid(), -6);
        $stmt = $this->pdo->prepare("INSERT INTO tenants (name, slug) VALUES (?, ?)");
        $stmt->execute(['Test ' . $slugPrefix, $slug]);
        return (int)$this->pdo->lastInsertId();
    }

    private function insertPayment(int $tenantId, float $valor, string $status, ?string $dataPagamento): int
    {
        $sql = "INSERT INTO pagamentos (tenant_id, valor, status, data_pagamento, plano) VALUES (:tenant_id, :valor, :status, :data_pagamento, :plano)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':valor' => $valor,
            ':status' => $status,
            ':data_pagamento' => $dataPagamento,
            ':plano' => 'basico'
        ]);
        return (int)$this->pdo->lastInsertId();
    }
}
