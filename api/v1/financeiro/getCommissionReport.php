<?php

/**
 * GET /api/v1/financeiro/getCommissionReport.php
 * 
 * Gera relatório de comissões com filtros avançados
 * Query params:
 * - start_date: Data inicial (YYYY-MM-DD)
 * - end_date: Data final (YYYY-MM-DD)
 * - user_id: ID do vendedor (opcional)
 * - status: pendente|aprovada|paga|cancelada (opcional)
 * - export: csv|json (padrão: json)
 */

header('Content-Type: application/json');

require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/app/bootstrap.php';
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/app/helpers/tenant_isolation_helpers.php';

try {
    $authContext = \App\Auth\AuthContext::getInstance();
    $currentUserId = $authContext->isAuthenticated() ? $authContext->getUserId() : ($_SESSION['user_id'] ?? null);

    if ($currentUserId === null || (int)$currentUserId <= 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Não autenticado']);
        exit;
    }

    initializeTenantMiddleware($GLOBALS['pdo']);
    $startDate = $_GET['start_date'] ?? date('Y-m-01');
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    $userId = $_GET['user_id'] ?? null;
    $status = $_GET['status'] ?? null;
    $export = $_GET['export'] ?? 'json';

    $filters = [
        'start_date' => $startDate,
        'end_date' => $endDate,
        'user_id' => $userId,
        'status' => $status,
        'export' => $export
    ];

    $service = new \App\Services\FinanceService($GLOBALS['pdo']);
    $response = $service->getCommissionReport($filters);

    if (!$response['success']) {
        throw new Exception($response['error']);
    }

    if ($export === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="relatorio-comissoes-' . date('Y-m-d') . '.csv"');

        echo "ID,Venda,Vendedor,Propriedade,Valor Venda,Percentual,Comissão,Status,Data Criação,Data Aprovação,Aprovado Por,Data Pagamento,Método Pagamento\n";
        foreach ($response['data'] as $c) {
            $line = [
                $c['id'],
                $c['sale_id'],
                $c['vendedor'],
                $c['propriedade'],
                number_format($c['sale_value'], 2, ',', '.'),
                number_format($c['commission_percentage'], 2, ',', '.') . '%',
                number_format($c['commission_value'], 2, ',', '.'),
                strtoupper($c['status']),
                $c['created_at'],
                $c['approved_at'] ?? '-',
                $c['approved_by_name'] ?? '-',
                $c['paid_at'] ?? '-',
                $c['paid_method'] ?? '-'
            ];
            echo implode(',', $line) . "\n";
        }
        exit;
    }

    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao gerar relatório',
        'details' => $e->getMessage()
    ]);
}
