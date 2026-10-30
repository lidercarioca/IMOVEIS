<?php

/**
 * API: GET Revenue Report
 * Endpoint: GET /api/v1/billing/getRevenueReport.php
 * 
 * Retorna relatório de receita com análises por período
 * Parâmetros:
 * - periodo: 'mes' | 'trimestre' | 'ano' | 'todos' (padrão: mes)
 * - data_inicio: data inicial em formato Y-m-d (opcional, sobrescreve periodo)
 * - data_fim: data final em formato Y-m-d (opcional, sobrescreve periodo)
 */

use App\Models\BillingHistoryModel;

header('Content-Type: application/json');

require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/app/bootstrap.php';
// Mantém BaseModel legacy por compatibilidade
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/app/models/BaseModel.php';
// BillingHistoryModel agora resolvido via Composer PSR-4 (src/Models)
if (file_exists(dirname(dirname(dirname(dirname(__FILE__)))) . '/vendor/autoload.php')) {
    require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/vendor/autoload.php';
}
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/app/security/TenantContext.php';

// Validar autenticação
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

try {
    // Inicializar middleware de tenant
    if (function_exists('initializeTenantMiddleware')) {
        initializeTenantMiddleware($pdo);
    }

    // Obter tenant ID do contexto
    $tenantId = TenantContext::getTenantId();
    if (!$tenantId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Tenant não identificado']);
        exit;
    }

    // Parâmetros
    $periodo = $_GET['periodo'] ?? 'mes';
    $dataInicio = $_GET['data_inicio'] ?? null;
    $dataFim = $_GET['data_fim'] ?? null;

    // Validar período
    if (!in_array($periodo, ['mes', 'trimestre', 'ano', 'todos'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Período inválido']);
        exit;
    }

    // Validar datas (se fornecidas)
    if ($dataInicio && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicio)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Formato de data_inicio inválido (use Y-m-d)']);
        exit;
    }

    if ($dataFim && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFim)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Formato de data_fim inválido (use Y-m-d)']);
        exit;
    }

    // Obter relatório
    $model = new BillingHistoryModel($pdo);
    $result = $model->getRevenueReport($tenantId, $periodo, $dataInicio, $dataFim);

    if (!$result['success']) {
        http_response_code(500);
        echo json_encode($result);
        exit;
    }

    // Formatar resposta
    http_response_code(200);
    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao gerar relatório: ' . $e->getMessage()
    ]);
}
