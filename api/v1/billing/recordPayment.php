<?php

/**
 * API: Record Payment
 * Endpoint: POST /api/v1/billing/recordPayment.php
 * 
 * Registra um novo pagamento para o tenant
 * Body JSON:
 * {
 *   "valor": 29.90,
 *   "plano": "basico|profissional|premium",
 *   "status": "pago" (opcional, padrão: pago),
 *   "descricao": "Renovação de plano" (opcional),
 *   "metodo_pagamento": "cartao|transferencia|pix" (opcional),
 *   "referencia": "REF123456" (opcional)
 * }
 */

use App\Models\BillingHistoryModel;

header('Content-Type: application/json');

require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/app/bootstrap.php';
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/app/models/BaseModel.php';
// BillingHistoryModel agora carregado via Composer PSR-4 em src/Models
// antigo: require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/app/models/BillingHistoryModel.php';
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/app/security/TenantContext.php';
// Logger helper (para audit logs)
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/app/utils/logger_functions.php';

// Usa o PDO inicializado pelo bootstrap (variável global)
global $pdo;
$tenantRepo = new \App\Infrastructure\Billing\TenantRepository($pdo);

// Validar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Validar autenticação
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

// Autorização mínima: exigir admin (isAdmin())
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permissão negada']);
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

    // Obter dados JSON
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'JSON inválido']);
        exit;
    }

    // Validar campos obrigatórios
    if (empty($data['valor']) || empty($data['plano'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Campos obrigatórios: valor, plano'
        ]);
        exit;
    }

    // Validar valor
    $valor = (float)$data['valor'];
    if ($valor <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Valor deve ser maior que zero']);
        exit;
    }

    // Validar plano
    $planos = $tenantRepo->getActivePlanCatalog();
    if (!isset($planos[$data['plano']])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Plano inválido']);
        exit;
    }

    // Preparar dados para inserção
    $paymentData = [
        'valor' => $valor,
        'plano' => $data['plano'],
        'status' => in_array($data['status'] ?? null, ['pago', 'pendente', 'cancelado']) ? $data['status'] : 'pago',
        'descricao' => $data['descricao'] ?? null,
        'metodo_pagamento' => $data['metodo_pagamento'] ?? null,
        'referencia' => $data['referencia'] ?? null
    ];

    // Registrar pagamento via service moderno: transação + idempotência + audit sem SQL direto no endpoint
    $paymentRepo = new \App\Infrastructure\Billing\PaymentRepository(new BillingHistoryModel($pdo), $pdo);
    $tenantBillingService = new \App\Services\Billing\TenantBillingService($paymentRepo, $tenantRepo, null, $pdo);
    $result = $tenantBillingService->registerPayment(
        $tenantId,
        [
            'valor' => $valor,
            'plano' => $paymentData['plano'],
            'status' => $paymentData['status'],
            'descricao' => $paymentData['descricao'],
            'metodo_pagamento' => $paymentData['metodo_pagamento'],
            'referencia' => $paymentData['referencia'],
            'stripe_reference' => $paymentData['referencia'] ?? null,
            'stripe_reference_type' => 'manual',
            'stripe_event_id' => null,
        ],
        ['plano' => $paymentData['plano'], 'status' => 'active']
    );

    if (empty($result) || !$result['success']) {
        http_response_code(400);
        echo json_encode($result);
        exit;
    }

    // Auditoria: logar apenas em caso de sucesso real
    if (function_exists('log_user_action')) {
        $details = [
            'tenant_id' => $tenantId,
            'payment_id' => $result['id'] ?? null,
            'valor' => $paymentData['valor'] ?? null,
            'plano' => $paymentData['plano'] ?? null,
            'metodo_pagamento' => $paymentData['metodo_pagamento'] ?? null,
            'referencia' => $paymentData['referencia'] ?? null,
            'origin' => 'api/v1/billing/recordPayment.php',
            'result' => 'success'
        ];
        // Função existente que delega para UserActionLogger::log
        log_user_action('manual_payment_create', $details);
    }

    // Sucesso
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Pagamento registrado com sucesso',
        'payment' => [
            'id' => $result['id'],
            'tenant_id' => $tenantId,
            ...$paymentData
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao registrar pagamento: ' . $e->getMessage()
    ]);
}
