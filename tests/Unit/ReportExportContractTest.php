<?php

namespace Tests\Unit;

use App\Service\ReportService;
use PHPUnit\Framework\TestCase;

class ReportExportContractTest extends TestCase
{
    public function testExportDashboardEndpointUsesTenantAndAuthContractForJsonAndCsv(): void
    {
        $endpoint = file_get_contents(__DIR__ . '/../../api/relatorios/exportDashboard.php');

        $this->assertNotFalse($endpoint);
        $this->assertStringContainsString('initializeTenantMiddleware($pdo);', $endpoint);
        $this->assertStringContainsString('$tenantId = getCurrentTenantId();', $endpoint);
        $this->assertStringContainsString('AuthContext::getInstance()', $endpoint);
        $this->assertStringContainsString('getExportDashboardData($tenantId', $endpoint);
        $this->assertStringContainsString("'csv'", $endpoint);
        $this->assertStringContainsString("'excel'", $endpoint);
        $this->assertStringContainsString("report_type", $endpoint);
        $this->assertStringContainsString("period", $endpoint);
        $this->assertStringContainsString('Content-Type: text/csv; charset=utf-8', $endpoint);
        $this->assertStringContainsString('json_encode([', $endpoint);
    }

    public function testServiceExposesExportContractMethods(): void
    {
        $this->assertTrue(method_exists(ReportService::class, 'getExportDashboardData'));
        $this->assertTrue(method_exists(ReportService::class, 'getExportSummarySnapshot'));
    }

    public function testExcelEndpointRequirementsAndGenerationFlowAreExplicit(): void
    {
        $endpoint = file_get_contents(__DIR__ . '/../../api/relatorios/exportExcel.php');

        $this->assertNotFalse($endpoint);
        $this->assertStringContainsString('initializeTenantMiddleware($pdo);', $endpoint);
        $this->assertStringContainsString('$tenantId = getCurrentTenantId();', $endpoint);
        $this->assertStringContainsString('AuthContext::getInstance()', $endpoint);
        $this->assertStringContainsString('getExportSummarySnapshot', $endpoint);
        $this->assertStringContainsString('Spreadsheet', $endpoint);
        $this->assertStringContainsString('new Xlsx($spreadsheet)', $endpoint);
        $this->assertStringContainsString("\$_GET['period'] ?? 7", $endpoint);
        $this->assertStringContainsString('Content-Type: application/json', $endpoint);
    }

    public function testPdfEndpointRequirementsAndGenerationFlowAreExplicit(): void
    {
        $endpoint = file_get_contents(__DIR__ . '/../../api/relatorios/exportPDF.php');

        $this->assertNotFalse($endpoint);
        $this->assertStringContainsString('initializeTenantMiddleware($pdo);', $endpoint);
        $this->assertStringContainsString('$tenantId = getCurrentTenantId();', $endpoint);
        $this->assertStringContainsString('AuthContext::getInstance()', $endpoint);
        $this->assertStringContainsString('getExportSummarySnapshot', $endpoint);
        $this->assertStringContainsString('Dompdf', $endpoint);
        $this->assertStringContainsString('generatePDFHTML', $endpoint);
        $this->assertStringContainsString('InputValidator::getInt', $endpoint);
        $this->assertStringContainsString("\$_GET['download'] ?? false", $endpoint);
        $this->assertStringContainsString('Content-Type: application/pdf', $endpoint);
    }
}
