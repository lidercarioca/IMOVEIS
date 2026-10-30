<?php

namespace Tests\Integration;

use App\Repository\ReportRepository;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/config/test_database.php';

class TenantReportReadIntegrationTest extends TestCase
{
    /**
     * Este teste prepara validação real de leitura por tenant em schema exclusivo.
     * Ele NÃO deve executar contra o banco da aplicação sem uma configuração explícita.
     */
    public function testPortfolioReportCanReadByTenantFromDedicatedSchema(): void
    {
        assertTestDatabaseIsIsolated();

        $pdo = getTestDatabasePdo();
        $repository = new ReportRepository($pdo);

        $result = $repository->getPortfolioStats(1, null, false);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }
}
