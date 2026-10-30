<?php

/**
 * API: Cria sessão de checkout Stripe para pagamento de plano
 * Endpoint: POST /api/v1/billing/createStripeCheckoutSession.php
 */

header('Content-Type: application/json; charset=utf-8');

require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/auth.php';
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config/database.php';
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config/load_env.php';
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config/payments.php';
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/app/security/Security.php';
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/app/security/CSRF.php';
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/app/security/TenantContext.php';
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/vendor/autoload.php';

$tenantRepo = new \App\Infrastructure\Billing\TenantRepository($pdo);

checkAuth();
TenantContext::initialize();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método inválido.']);
    exit;
}

$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true);

$plan = isset($data['plan']) ? trim($data['plan']) : '';
$csrfToken = isset($data['csrf_token']) ? $data['csrf_token'] : '';

if (!$plan || !$csrfToken) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parâmetros obrigatórios ausentes.']);
    exit;
}

try {
    if (!CSRF::verifyToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Token CSRF inválido.']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido.']);
    exit;
}

$paymentsConfig = require dirname(dirname(dirname(dirname(__FILE__)))) . '/config/payments.php';
$stripeConfig = $paymentsConfig['stripe'] ?? [];

if (empty($stripeConfig['secret_key']) || empty($stripeConfig['publishable_key'])) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Configuração Stripe não encontrada.']);
    exit;
}

$tenantId = TenantContext::getTenantId();
if (!$tenantId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Tenant não identificado.']);
    exit;
}

$planos = $tenantRepo->getActivePlanCatalog();
if (!isset($planos[$plan])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Plano inválido.']);
    exit;
}

if ($plan === 'trial') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Trial não requer Stripe.']);
    exit;
}

$tenant = $tenantRepo->getTenantData($tenantId);
if (!$tenant) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Tenant não encontrado.']);
    exit;
}

$selectedPlan = $tenantRepo->getPlanByCode($plan) ?? $planos[$plan];
if (!empty($tenant['status']) && $tenant['status'] === 'active' && !empty($tenant['plano']) && $tenant['plano'] !== 'trial' && $plan !== 'trial') {
    if ($tenantRepo->isPlanDowngrade((string)$tenant['plano'], $plan, $planos)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Downgrade não permitido enquanto o plano atual estiver ativo.']);
        exit;
    }
}

if ($tenantRepo->hasTrialHistory($tenantId) && $plan === 'trial') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Trial já utilizado.']);
    exit;
}

$selectedPlan = $planos[$plan];
$amountCents = (int)round(($selectedPlan['valor_mensal'] ?? 0) * 100);
if ($amountCents <= 0 && empty($selectedPlan['stripe_price_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valor do plano inválido para pagamento.']);
    exit;
}

$siteUrl = rtrim($stripeConfig['site_url'] ?? '', '/');
$successUrl = $siteUrl . '/stripe_success.php?session_id={CHECKOUT_SESSION_ID}';
$cancelUrl = $siteUrl . '/planos.php?cancel=1';

$payload = [
    'success_url' => $successUrl,
    'cancel_url' => $cancelUrl,
    'mode' => 'subscription',
    'payment_method_types[]' => 'card',
    'line_items[0][quantity]' => 1,
    'metadata[tenant_id]' => $tenantId,
    'metadata[tenant_plan]' => $plan,
    'metadata[user_id]' => $_SESSION['user_id'] ?? '',
    'subscription_data[metadata][tenant_id]' => $tenantId,
    'subscription_data[metadata][tenant_plan]' => $plan,
    'subscription_data[metadata][user_id]' => $_SESSION['user_id'] ?? '',
    'locale' => 'pt'
];

if (!empty($selectedPlan['stripe_price_id'])) {
    $payload['line_items[0][price]'] = $selectedPlan['stripe_price_id'];
} else {
    $payload['line_items[0][price_data][currency]'] = $stripeConfig['currency'];
    $payload['line_items[0][price_data][unit_amount]'] = $amountCents;
    $payload['line_items[0][price_data][recurring][interval]'] = 'month';
    $payload['line_items[0][price_data][product_data][name]'] = 'Plano ' . ($selectedPlan['nome'] ?? $plan);
    $payload['line_items[0][price_data][product_data][description]'] = $selectedPlan['descricao'] ?? 'Assinatura mensal';
}

try {
    $config = new \App\Infrastructure\Billing\StripeConfiguration(require dirname(dirname(dirname(dirname(__FILE__)))) . '/config/payments.php');
    $apiClient = new \App\Infrastructure\Billing\StripeApiClient($config);
    $checkoutService = new \App\Services\Billing\StripeCheckoutService($apiClient);

    $result = $checkoutService->createCheckoutSession($payload);
    if (empty($result) || empty($result['url'])) {
        http_response_code(500);
        $message = $result['error']['message'] ?? 'Erro ao criar sessão Stripe.';
        echo json_encode(['success' => false, 'message' => $message, 'details' => $result]);
        exit;
    }

    echo json_encode(['success' => true, 'checkout_url' => $result['url']]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro de comunicação com Stripe: ' . $e->getMessage()]);
    exit;
}
