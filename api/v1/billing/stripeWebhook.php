<?php

/**
 * Stripe Webhook Receiver
 * Endpoint: POST /api/v1/billing/stripeWebhook.php
 */

require_once __DIR__ . '/../../../config/load_env.php';
require_once __DIR__ . '/../../../config/payments.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../app/security/Security.php';

Security::setSecurityHeaders();
header('Content-Type: application/json; charset=utf-8');

$paymentsConfig = require __DIR__ . '/../../../config/payments.php';
$stripeConfig = $paymentsConfig['stripe'] ?? [];
$webhookSecret = trim($stripeConfig['webhook_secret'] ?? '');

$payload = @file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

if (!$payload || !$sigHeader || !$webhookSecret) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Payload, assinatura Stripe ou segredo de webhook ausente']);
    exit;
}

function writeStripeWebhookLog(array $result): void
{
    $logDir = __DIR__ . '/../../../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . '/stripe-webhook.log';
    @file_put_contents($logFile, date('c') . ' ' . json_encode($result, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
}

function respondStripeWebhook(array $result): void
{
    $httpCode = 200;

    if (!empty($result['retryable'])) {
        $httpCode = 500;
    } elseif (!empty($result['client_error'])) {
        $httpCode = 400;
    } elseif (!empty($result['error']) && empty($result['processed'])) {
        $httpCode = 500;
    }

    http_response_code($httpCode);
    writeStripeWebhookLog($result);
    echo json_encode($result);
    exit;
}
// Delegate processing to PSR-4 service while keeping response/logging semantics
require_once __DIR__ . '/../../../vendor/autoload.php';
try {
    $configPayments = require __DIR__ . '/../../../config/payments.php';
    $config = new \App\Infrastructure\Billing\StripeConfiguration($configPayments);
    $apiClient = new \App\Infrastructure\Billing\StripeApiClient($config);
    $pdo = getConnection();
    $paymentRepo = new \App\Infrastructure\Billing\PaymentRepository(null, $pdo);
    $tenantRepo = new \App\Infrastructure\Billing\TenantRepository($pdo);
    $eventRepo = new \App\Infrastructure\Billing\StripeEventRepository($pdo);

    $tenantBillingService = new \App\Services\Billing\TenantBillingService($paymentRepo, $tenantRepo, $eventRepo, $pdo);
    $validator = new \App\Services\Billing\StripeWebhookValidator();
    $userRepository = new \App\Infrastructure\User\UserRepository($pdo);
    $notificationLogRepository = new \App\Infrastructure\Billing\NotificationLogRepository($pdo);
    $mailerService = new \App\Infrastructure\Mail\MailerService();
    $billingNotificationService = new \App\Services\Billing\BillingNotificationService(
        $tenantRepo,
        $userRepository,
        $mailerService,
        $notificationLogRepository
    );
    $webhookService = new \App\Services\Billing\StripeWebhookService($validator, $apiClient, $eventRepo, $tenantBillingService, $billingNotificationService);

    $serviceResult = $webhookService->handle($payload, $sigHeader, $webhookSecret);
    // serviceResult expected as associative array similar to previous $result
    respondStripeWebhook($serviceResult);
} catch (\Throwable $e) {
    error_log('Stripe webhook endpoint error: ' . $e->getMessage());
    respondStripeWebhook([
        'success' => false,
        'message' => 'Erro interno ao processar webhook.',
        'retryable' => true,
    ]);
}
