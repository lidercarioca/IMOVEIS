<?php

/**
 * API: Sincroniza Stripe Price IDs para planos locais
 * Endpoint: POST /api/v1/billing/syncStripePrices.php
 */

header('Content-Type: application/json; charset=utf-8');

$rootPath = dirname(dirname(dirname(dirname(__FILE__))));
require_once $rootPath . '/auth.php';
require_once $rootPath . '/config/database.php';
require_once $rootPath . '/config/payments.php';
require_once $rootPath . '/vendor/autoload.php';

checkAuth();

if (empty($_SESSION['is_superadmin'])) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Acesso negado. Apenas superadmin pode sincronizar Stripe Price IDs.'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método inválido. Use POST.']);
    exit;
}

$paymentsConfig = require dirname(dirname(dirname(dirname(__FILE__)))) . '/config/payments.php';
$stripeConfig = $paymentsConfig['stripe'] ?? [];
$secretKey = trim($stripeConfig['secret_key'] ?? '');

if (!$secretKey) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Configuração Stripe não encontrada.']);
    exit;
}

try {
    $service = new \App\Services\Billing\StripePriceSyncService($pdo);
    $result = $service->syncStripePriceIds($secretKey);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
