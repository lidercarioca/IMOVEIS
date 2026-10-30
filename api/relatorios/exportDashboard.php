<?php

/**
 * Export Dashboard API
 * Exporta dados do dashboard em PDF ou Excel
 * COM ISOLAMENTO DE TENANT_ID
 */

require_once '../../config/session_manager.php';
require_once '../../auth.php';
require_once '../../config/database.php';
require_once '../../app/helpers/tenant_isolation_helpers.php';
require_once '../../app/middleware/api_auth.php';

checkAuth();
checkApiAuth();

// Initialize tenant isolation
global $pdo;
initializeTenantMiddleware($pdo);
$tenantId = getCurrentTenantId();

header('Content-Type: application/json');

try {
    $pdo = getConnection();
    $authContext = \App\Auth\AuthContext::getInstance();
    $userId = $authContext->getUserId();
    $isAdmin = $authContext->isAdmin() || $authContext->isSuperAdmin();
    $format = $_POST['format'] ?? 'json'; // json, csv, excel
    $reportType = $_POST['report_type'] ?? 'summary'; // summary, kpis, portfolio, leads, goals
    $period = (int)($_POST['period'] ?? 30);

    $reportService = new \App\Service\ReportService($pdo);
    $reportPayload = $reportService->getExportDashboardData($tenantId, $period, $userId, $isAdmin, $reportType);
    $reportData = $reportPayload['data'] ?? [];
    $reportTitle = $reportPayload['title'] ?? 'Relatório Resumido';

    // =====================
    // Exportar conforme formato
    // =====================
    if ($format === 'csv') {
        // CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $reportType . '_' . date('Y-m-d_His') . '.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, array_keys($reportData[0] ?? []), ';', '"');

        foreach ($reportData as $row) {
            fputcsv($output, $row, ';', '"');
        }

        fclose($output);
        exit;
    } elseif ($format === 'excel') {
        // Excel (formato CSV com encoding para Excel)
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $reportType . '_' . date('Y-m-d_His') . '.xls"');

        // BOM para UTF-8
        echo "\xEF\xBB\xBF";

        $output = fopen('php://output', 'w');
        if (!empty($reportData)) {
            fputcsv($output, array_keys($reportData[0]));
        }

        foreach ($reportData as $row) {
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    } else {
        // JSON
        echo json_encode([
            'success' => true,
            'report_type' => $reportType,
            'title' => $reportTitle,
            'generated_at' => date('Y-m-d H:i:s'),
            'period_days' => $period,
            'data' => $reportData,
            'total_rows' => count($reportData)
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao exportar dados',
        'details' => $e->getMessage()
    ]);
}
