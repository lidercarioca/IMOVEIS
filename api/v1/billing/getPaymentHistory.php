<?php

/**
 * API: GET Payment History
 * Endpoint: GET /api/v1/billing/getPaymentHistory.php
 *
 * Retorna histórico de pagamentos com filtros e paginação
 */

header('Content-Type: application/json; charset=utf-8');

// Carregar autenticação legada e sessão
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/auth.php';
// Conexão com BD (define $pdo)
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config/database.php';
// Carregar BaseModel legacy (mantido por compatibilidade)
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/app/models/BaseModel.php';
// BillingHistoryModel: agora resolvido via Composer PSR-4 (src/Models)
if (file_exists(dirname(dirname(dirname(dirname(__FILE__)))) . '/vendor/autoload.php')) {
    require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/vendor/autoload.php';
}
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/app/security/TenantContext.php';

try {
    // Forçar autenticação (lança SessionAuthException para APIs)
    try {
        checkAuth();
    } catch (SessionAuthException $sae) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'unauthorized',
            'message' => $sae->getMessage()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Inicializar contexto de tenant
    TenantContext::initialize();
    $tenantId = TenantContext::getTenantId();
    if (!$tenantId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Tenant não identificado']);
        exit;
    }

    // Parâmetros
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $filters = [
        'mes' => $_GET['mes'] ?? null,
        'status' => $_GET['status'] ?? null,
        'plano' => $_GET['plano'] ?? null
    ];

    // Validações simples
    if (!empty($filters['status']) && !in_array($filters['status'], ['pago', 'pendente', 'cancelado'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Status inválido']);
        exit;
    }
    if (!empty($filters['mes']) && !preg_match('/^\d{4}-\d{2}$/', $filters['mes'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Formato de mês inválido (use YYYY-MM)']);
        exit;
    }

    // Executar busca
    $model = new \App\Models\BillingHistoryModel($pdo);
    $result = $model->listPayments($tenantId, $filters, $page, $limit);

    if (!$result['success']) {
        http_response_code(500);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'success' => true,
        'data' => $result['data'],
        'pagination' => $result['pagination'],
        'filters' => $filters
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao obter histórico: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
