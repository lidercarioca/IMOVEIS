<?php

namespace Tests\Unit;

use App\Infrastructure\Finance\CommissionRepository;
use App\Infrastructure\Finance\FinancialTransactionRepository;
use App\Services\FinanceService;
use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;

class FinanceContractTest extends TestCase
{
    private PDO $pdo;

    private function ensureTestSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (PHP_SAPI === 'cli' || headers_sent()) {
            $_SESSION = [];
            return;
        }

        session_start();
    }

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }

        $_SESSION = [];
        $this->ensureTestSession();

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->sqliteCreateFunction('NOW', static fn() => date('Y-m-d H:i:s'));
        $this->pdo->sqliteCreateFunction('DATE_FORMAT', static function ($value, $format): ?string {
            if ($value === null || $value === '') {
                return null;
            }

            $timestamp = is_numeric($value) ? (int) $value : strtotime((string) $value);
            if ($timestamp === false) {
                return null;
            }

            return date((string) $format, $timestamp);
        });
        $this->pdo->sqliteCreateFunction('DATE_ADD', static function ($value, $interval): ?string {
            if ($value === null || $value === '') {
                return null;
            }

            $match = preg_match('/INTERVAL\s+([+-]?\d+)\s+(DAY|HOUR|MINUTE|SECOND)/i', (string) $interval, $parts);
            if (!$match) {
                return $value;
            }

            $amount = (int) $parts[1];
            $unit = strtolower($parts[2]);
            $dateTime = new \DateTimeImmutable((string) $value);

            if ($unit === 'day') {
                $dateTime = $dateTime->modify(sprintf('%+d days', $amount));
            } elseif ($unit === 'hour') {
                $dateTime = $dateTime->modify(sprintf('%+d hours', $amount));
            } elseif ($unit === 'minute') {
                $dateTime = $dateTime->modify(sprintf('%+d minutes', $amount));
            } elseif ($unit === 'second') {
                $dateTime = $dateTime->modify(sprintf('%+d seconds', $amount));
            }

            return $dateTime->format('Y-m-d H:i:s');
        });

        $this->pdo->exec('CREATE TABLE properties (id INTEGER PRIMARY KEY, tenant_id INTEGER NOT NULL, status TEXT DEFAULT "active", title TEXT, assigned_user_id INTEGER DEFAULT NULL, price REAL DEFAULT 0, updated_at TEXT DEFAULT NULL)');
        $this->pdo->exec('CREATE TABLE commissions (id INTEGER PRIMARY KEY, tenant_id INTEGER NOT NULL, sale_id INTEGER NOT NULL, user_id INTEGER DEFAULT NULL, property_id INTEGER NOT NULL, sale_value REAL DEFAULT 0, commission_percentage REAL DEFAULT 0, commission_value REAL DEFAULT 0, status TEXT DEFAULT "pendente", approval_notes TEXT DEFAULT NULL, approved_at TEXT DEFAULT NULL, paid_at TEXT DEFAULT NULL, paid_method TEXT DEFAULT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT NULL, deleted_at TEXT DEFAULT NULL)');
        $this->pdo->exec('CREATE TABLE property_sales (id INTEGER PRIMARY KEY, tenant_id INTEGER NOT NULL, property_id INTEGER NOT NULL, status TEXT DEFAULT "completed", created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT NULL, deleted_at TEXT DEFAULT NULL)');
        $this->pdo->exec('CREATE TABLE financeiro (id INTEGER PRIMARY KEY, tenant_id INTEGER NOT NULL, property_id INTEGER NOT NULL, tipo TEXT, valor REAL DEFAULT 0, reference_sale_id INTEGER DEFAULT NULL, reference_commission_id INTEGER DEFAULT NULL, reversal_of_id INTEGER DEFAULT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT NULL, deleted_at TEXT DEFAULT NULL)');
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INTEGER NOT NULL, username TEXT, role TEXT, is_superadmin INTEGER DEFAULT 0, name TEXT, email TEXT, phone TEXT, created_at TEXT, last_login TEXT, active INTEGER DEFAULT 1, force_password_change INTEGER DEFAULT 0, password_changed_at TEXT)');
        $this->pdo->exec("INSERT INTO users (id, tenant_id, username, role, is_superadmin, name, email, phone, created_at, last_login, active, force_password_change, password_changed_at) VALUES (1, 42, 'admin', 'admin', 1, 'Admin', 'admin@example.com', NULL, '2026-01-01 00:00:00', NULL, 1, 0, NULL)");

        $GLOBALS['pdo'] = $this->pdo;
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'admin';
        $_SESSION['tenant_id'] = 42;
        $_SESSION['selected_tenant_id'] = 42;
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }
    }

    public function testDeleteCommission_keeps_active_sale_flow(): void
    {
        $this->pdo->exec("INSERT INTO properties (id, tenant_id, status, title, assigned_user_id, price) VALUES (77, 42, 'sold', 'Imóvel ativo', 1, 250000)");
        $this->pdo->exec("INSERT INTO property_sales (id, tenant_id, property_id, status, deleted_at) VALUES (55, 42, 77, 'completed', NULL)");
        $this->pdo->exec("INSERT INTO financeiro (id, tenant_id, property_id, tipo, valor, reference_sale_id, deleted_at) VALUES (100, 42, 77, 'receita', 250000, 55, NULL)");
        $this->pdo->exec("INSERT INTO commissions (id, tenant_id, sale_id, user_id, property_id, sale_value, commission_percentage, commission_value, status, deleted_at) VALUES (10, 42, 55, 1, 77, 250000, 5, 12500, 'pendente', NULL)");

        $service = $this->createFinanceService();
        $result = $service->deleteCommission(10, true);

        $this->assertTrue($result['success']);
        $this->assertSame('active', $this->fetchScalar('SELECT status FROM properties WHERE id = 77 AND tenant_id = 42'));
        $this->assertNotNull($this->fetchScalar('SELECT deleted_at FROM commissions WHERE id = 10'));
    }

    public function testDeleteCommission_handles_soft_deleted_sale_as_idempotent(): void
    {
        $this->pdo->exec("INSERT INTO properties (id, tenant_id, status, title, assigned_user_id, price) VALUES (77, 42, 'sold', 'Imóvel revertido', 1, 300000)");
        $this->pdo->exec("INSERT INTO property_sales (id, tenant_id, property_id, status, deleted_at) VALUES (55, 42, 77, 'completed', '2026-10-05 12:00:00')");
        $this->pdo->exec("INSERT INTO financeiro (id, tenant_id, property_id, tipo, valor, reference_sale_id, reference_commission_id, deleted_at) VALUES (101, 42, 77, 'receita', 300000, 55, 10, '2026-10-05 12:00:00')");
        $this->pdo->exec("INSERT INTO commissions (id, tenant_id, sale_id, user_id, property_id, sale_value, commission_percentage, commission_value, status, deleted_at) VALUES (10, 42, 55, 1, 77, 300000, 5, 15000, 'pendente', NULL)");

        $service = $this->createFinanceService();
        $result = $service->deleteCommission(10, true);

        $this->assertTrue($result['success']);
        $this->assertSame('sold', $this->fetchScalar('SELECT status FROM properties WHERE id = 77 AND tenant_id = 42'));
        $this->assertSame('2026-10-05 12:00:00', $this->fetchScalar('SELECT deleted_at FROM property_sales WHERE id = 55 AND tenant_id = 42'));
        $this->assertSame('2026-10-05 12:00:00', $this->fetchScalar('SELECT deleted_at FROM financeiro WHERE id = 101 AND tenant_id = 42'));
        $this->assertSame(1, (int) $this->fetchScalar('SELECT COUNT(*) FROM financeiro WHERE reference_sale_id = 55 AND tenant_id = 42'));
        $this->assertStringContainsString('revertida', strtolower((string) $result['message']));
        $this->assertNotNull($this->fetchScalar('SELECT deleted_at FROM commissions WHERE id = 10'));
    }

    public function testDeleteCommission_blocks_cross_tenant_sale(): void
    {
        $this->pdo->exec("INSERT INTO properties (id, tenant_id, status, title, assigned_user_id, price) VALUES (99, 99, 'sold', 'Imóvel de outro tenant', 1, 500000)");
        $this->pdo->exec("INSERT INTO property_sales (id, tenant_id, property_id, status, deleted_at) VALUES (77, 99, 99, 'completed', NULL)");
        $this->pdo->exec("INSERT INTO commissions (id, tenant_id, sale_id, user_id, property_id, sale_value, commission_percentage, commission_value, status, deleted_at) VALUES (30, 42, 77, 1, 99, 500000, 6, 30000, 'pendente', NULL)");

        $service = $this->createFinanceService();
        $result = $service->deleteCommission(30, true);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('tenant', strtolower((string) $result['error']));
    }

    private function createFinanceService(): FinanceService
    {
        return new class($this->pdo) extends FinanceService {
            public function __construct(PDO $pdo)
            {
                parent::__construct($pdo);
                $this->financialRepository = new class($pdo) extends FinancialTransactionRepository {
                    private PDO $sqlitePdo;

                    public function __construct(PDO $pdo)
                    {
                        parent::__construct($pdo);
                        $this->sqlitePdo = $pdo;
                    }

                    public function softDeleteTransactionsBySaleId(int $saleId, int $tenantId): bool
                    {
                        $sql = "UPDATE financeiro
                                SET deleted_at = NOW()
                                WHERE property_id IN (
                                    SELECT property_id FROM property_sales WHERE id = :sale_id AND tenant_id = :sale_tenant_id
                                )
                                AND tipo IN ('receita', 'comissao')
                                AND tenant_id = :finance_tenant_id";

                        $stmt = $this->sqlitePdo->prepare($sql);
                        return $stmt->execute([
                            ':sale_id' => $saleId,
                            ':sale_tenant_id' => $tenantId,
                            ':finance_tenant_id' => $tenantId,
                        ]);
                    }
                };
            }
        };
    }

    private function fetchScalar(string $sql)
    {
        $stmt = $this->pdo->query($sql);
        $value = $stmt->fetchColumn();
        return $value === false ? null : $value;
    }
    public function testAsyncJobQueueDoesNotFallbackToTenantOneInFinancialRecords(): void
    {
        $source = file_get_contents(__DIR__ . '/../../app/utils/AsyncJobQueue.php');

        $this->assertNotFalse($source);
        $this->assertStringContainsString('getTenantIdForProperty((int)$propertyId)', $source);
        $this->assertStringNotContainsString('getTenantIdForProperty((int)$propertyId) ?: 1', $source);
        $this->assertStringContainsString('if ($tenantId === null || $tenantId <= 0)', $source);
    }

    public function testCommissionRepositoryGetCommissionStatsUsesTenantFirst(): void
    {
        $pdo = $this->createMock(PDO::class);
        $statement = $this->createMock(PDOStatement::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('AND c.tenant_id = :tenant_id'))
            ->willReturn($statement);

        $statement->expects($this->once())
            ->method('execute')
            ->with([
                ':tenant_id' => 42,
                ':user_id' => 7,
                ':start_date' => '2024-01-01',
                ':end_date' => '2024-01-31',
            ])
            ->willReturn(true);

        $statement->method('fetchAll')->willReturn([]);

        $repository = new CommissionRepository($pdo);
        $repository->getCommissionStats(42, 7, '2024-01-01', '2024-01-31');
    }

    public function testCommissionRepositoryApproveCommissionUsesTenantIdBeforeNotes(): void
    {
        $pdo = $this->createMock(PDO::class);
        $statement = $this->createMock(PDOStatement::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($statement);

        $statement->expects($this->once())
            ->method('execute')
            ->with([
                ':id' => 10,
                ':tenant_id' => 42,
                ':approved_by' => 7,
                ':notes' => 'Aprovado',
            ])
            ->willReturn(true);

        $repository = new CommissionRepository($pdo);
        $repository->approveCommission(10, 7, 42, 'Aprovado');
    }

    public function testCommissionRepositoryPayCommissionUsesTenantIdBeforeMethod(): void
    {
        $pdo = $this->createMock(PDO::class);
        $statement = $this->createMock(PDOStatement::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($statement);

        $statement->expects($this->once())
            ->method('execute')
            ->with([
                ':id' => 10,
                ':tenant_id' => 42,
                ':method' => 'transferência',
            ])
            ->willReturn(true);

        $repository = new CommissionRepository($pdo);
        $repository->payCommission(10, 42, 'transferência');
    }

    public function testCommissionRepositoryCancelCommissionUsesTenantIdBeforeReason(): void
    {
        $pdo = $this->createMock(PDO::class);
        $statement = $this->createMock(PDOStatement::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($statement);

        $statement->expects($this->once())
            ->method('execute')
            ->with([
                ':id' => 10,
                ':tenant_id' => 42,
                ':reason' => 'Pedido cancelado',
            ])
            ->willReturn(true);

        $repository = new CommissionRepository($pdo);
        $repository->cancelCommission(10, 42, 'Pedido cancelado');
    }

    public function testCommissionRepositoryGetPayrollSummaryUsesTenantFirst(): void
    {
        $pdo = $this->createMock(PDO::class);
        $statement = $this->createMock(PDOStatement::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($statement);

        $statement->expects($this->once())
            ->method('execute')
            ->with([
                ':month' => '2024-01',
                ':tenant_id' => 42,
                ':user_id' => 7,
            ])
            ->willReturn(true);

        $statement->method('fetchAll')->willReturn([]);

        $repository = new CommissionRepository($pdo);
        $repository->getPayrollSummary('2024-01', 42, 7);
    }

    public function testFinancialTransactionRepositoryGetMonthlySummaryUsesTenantFirst(): void
    {
        $pdo = $this->createMock(PDO::class);
        $statement = $this->createMock(PDOStatement::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($statement);

        $statement->expects($this->once())
            ->method('execute')
            ->with([
                '2024-01-01',
                '2024-01-31',
                42,
                7,
            ])
            ->willReturn(true);

        $statement->method('fetch')->willReturn(['total_receita' => 0]);

        $repository = new FinancialTransactionRepository($pdo);
        $repository->getMonthlySummary('2024-01', 42, 7);
    }
}
