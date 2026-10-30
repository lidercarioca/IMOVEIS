<?php

/**
 * API: Cria uma sessão do Stripe Customer Portal para o tenant atual
 * Endpoint: POST /api/v1/billing/createStripeBillingPortalSession.php
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
$csrfToken = isset($data['csrf_token']) ? $data['csrf_token'] : '';

if (!$csrfToken) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Token CSRF ausente.']);
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

if (empty($stripeConfig['secret_key']) || empty($stripeConfig['site_url'])) {
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

$tenant = $tenantRepo->getTenantData($tenantId);
$customerId = !empty($tenant['stripe_customer_id']) ? (string)$tenant['stripe_customer_id'] : null;
if (!$customerId) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Cliente Stripe não encontrado para este tenant. Verifique se há uma assinatura ativa.']);
    exit;
}

$siteUrl = rtrim($stripeConfig['site_url'] ?? '', '/');
if (empty($siteUrl)) {
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $siteUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

// Verificação segura de formato de URL
if (!filter_var($siteUrl, FILTER_VALIDATE_URL)) {
    $siteUrl = 'https://' . ltrim($siteUrl, '/');
}

function isUrlReachable(string $url): bool
{
    if (!function_exists('curl_version')) {
        return true;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode >= 200 && $httpCode < 400;
}

if (!isUrlReachable($siteUrl)) {
    error_log(sprintf('[STRIPE PORTAL WARNING] site_url offline or unreachable: %s', $siteUrl));
}

$returnUrl = $siteUrl . '/painel.php';

// Logs temporários de diagnóstico sem expor secrets
error_log(sprintf(
    '[STRIPE PORTAL] APP_URL=%s site_url=%s return_url=%s request_host=%s',
    getenv('APP_URL') ?: 'none',
    $siteUrl,
    $returnUrl,
    $_SERVER['HTTP_HOST'] ?? 'n/a'
));

$payload = [
    'customer' => $customerId,
    'return_url' => $returnUrl
];

try {
    $config = new \App\Infrastructure\Billing\StripeConfiguration(require dirname(dirname(dirname(dirname(__FILE__)))) . '/config/payments.php');
    $apiClient = new \App\Infrastructure\Billing\StripeApiClient($config);
    $portalService = new \App\Services\Billing\StripeBillingPortalService($apiClient);

    $result = $portalService->createPortalSession($customerId, $returnUrl);
    if (empty($result) || empty($result['url'])) {
        http_response_code(500);
        $message = $result['error']['message'] ?? 'Erro ao criar sessão do portal Stripe.';
        echo json_encode(['success' => false, 'message' => $message, 'details' => $result]);
        exit;
    }

    echo json_encode(['success' => true, 'portal_url' => $result['url']]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro de comunicação com Stripe: ' . $e->getMessage()]);
    exit;
}
