<?php
require_once dirname(dirname(__DIR__)) . '/api/check_auth.php';
checkApiAuth();

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/tenant_isolation_helpers.php';
require_once dirname(dirname(__DIR__)) . '/app/middleware/api_auth.php';

checkApiAuth();

// Initialize tenant isolation
global $pdo;
initializeTenantMiddleware($pdo);
$tenantId = getCurrentTenantId();

// Verificar dependência
if (!file_exists(dirname(__DIR__) . '/../../vendor/autoload.php')) {
    die(json_encode([
        'success' => false,
        'error' => 'PhpSpreadsheet not installed. Run: composer require phpoffice/phpspreadsheet'
    ]));
}

require_once dirname(__DIR__) . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Fill;

header('Content-Type: application/json');

try {
    $authContext = \App\Auth\AuthContext::getInstance();
    $actorId = $authContext->getUserId();
    $isAdmin = $authContext->isAdmin() || $authContext->isSuperAdmin();
    $period = (int)($_GET['period'] ?? 7);
    $user_id = isset($_GET['user_id']) && $_GET['user_id'] !== '' ? (int)$_GET['user_id'] : null;
    $category = $_GET['category'] ?? null;
    $region = $_GET['region'] ?? null;

    $reportService = new \App\Service\ReportService($pdo);
    $snapshot = $reportService->getExportSummarySnapshot($tenantId, $period, $user_id, $category, $region, $isAdmin);
    $kpi = $snapshot['kpiData'] ?? [];
    $brokers = $snapshot['brokers'] ?? [];

    $dataFim = date('Y-m-d');
    $dataInicio = date('Y-m-d', strtotime("-{$period} days"));

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Dashboard');

    // Estilos
    $headerStyle = [
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '007bff']],
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    ];

    // Cabeçalho
    $sheet->setCellValue('A1', 'DASHBOARD EXECUTIVO');
    $sheet->mergeCells('A1:F1');
    $sheet->getStyle('A1')->getFont()->setSize(16)->setBold(true);

    $sheet->setCellValue('A2', 'Período: ' . $dataInicio . ' a ' . $dataFim);
    $sheet->mergeCells('A2:F2');
    $sheet->getStyle('A2')->getFont()->setSize(11);

    // KPIs
    $row = 4;
    $sheet->setCellValue('A' . $row, 'KPI');
    $sheet->setCellValue('B' . $row, 'Valor');
    $sheet->getStyle('A' . $row . ':B' . $row)->applyFromArray($headerStyle);

    $row++;
    $kpis = [
        'Total de Imóveis' => $kpi['total_properties'] ?? 0,
        'Total de Leads' => $kpi['total_leads'] ?? 0,
        'Total de Vendas' => $kpi['total_sales'] ?? 0,
        'Ticket Médio' => 'R$ ' . number_format((float)($kpi['avg_ticket'] ?? 0), 2, ',', '.'),
        'Ciclo Médio' => ($kpi['avg_cycle'] ?? 0) . ' dias',
        'Imóveis Parados' => $kpi['stopped_count'] ?? 0,
        'Taxa Conversão' => (($kpi['total_leads'] > 0 ? round(($kpi['total_sales'] / $kpi['total_leads']) * 100, 2) : 0)) . '%'
    ];

    foreach ($kpis as $label => $value) {
        $sheet->setCellValue('A' . $row, $label);
        $sheet->setCellValue('B' . $row, $value);
        $row++;
    }

    // Top Brokers
    $row += 2;
    $sheet->setCellValue('A' . $row, 'BROKERS');
    $sheet->getStyle('A' . $row)->getFont()->setSize(14)->setBold(true);

    $row++;
    $sheet->setCellValue('A' . $row, 'Broker');
    $sheet->setCellValue('B' . $row, 'Vendas');
    $sheet->setCellValue('C' . $row, 'Ticket Médio');
    $sheet->getStyle('A' . $row . ':C' . $row)->applyFromArray($headerStyle);

    $row++;
    foreach ($brokers as $broker) {
        $sheet->setCellValue('A' . $row, $broker['name']);
        $sheet->setCellValue('B' . $row, $broker['sales_count']);
        $sheet->setCellValue('C' . $row, 'R$ ' . number_format($broker['avg_price'] ?? 0, 2, ',', '.'));
        $row++;
    }

    // Leads
    $row += 2;
    $sheet->setCellValue('A' . $row, 'LEADS');
    $sheet->getStyle('A' . $row)->getFont()->setSize(14)->setBold(true);

    $row++;
    $sheet->setCellValue('A' . $row, 'ID');
    $sheet->setCellValue('B' . $row, 'Propriedade');
    $sheet->setCellValue('C' . $row, 'Cliente');
    $sheet->setCellValue('D' . $row, 'Status');
    $sheet->setCellValue('E' . $row, 'Data');
    $sheet->getStyle('A' . $row . ':E' . $row)->applyFromArray($headerStyle);

    $leads = $snapshot['leadRows'] ?? [];

    $row++;
    foreach ($leads as $lead) {
        $sheet->setCellValue('A' . $row, $lead['id']);
        $sheet->setCellValue('B' . $row, substr($lead['title'], 0, 30));
        $sheet->setCellValue('C' . $row, $lead['contact_name']);
        $sheet->setCellValue('D' . $row, $lead['status']);
        $sheet->setCellValue('E' . $row, substr($lead['created_at'], 0, 10));
        $row++;
    }

    // Auto-fit columns
    $sheet->getColumnDimension('A')->setAutoSize(true);
    $sheet->getColumnDimension('B')->setAutoSize(true);
    $sheet->getColumnDimension('C')->setAutoSize(true);

    // Salvar
    $filename = 'dashboard_' . date('Y-m-d_H-i-s') . '.xlsx';
    $filepath = sys_get_temp_dir() . '/' . $filename;

    $writer = new Xlsx($spreadsheet);
    $writer->save($filepath);

    // Retornar
    if ($_GET['download'] ?? false) {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        readfile($filepath);
        unlink($filepath);
    } else {
        echo json_encode([
            'success' => true,
            'excel' => base64_encode(file_get_contents($filepath)),
            'filename' => $filename,
            'generated_at' => date('Y-m-d H:i:s')
        ]);
        unlink($filepath);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
