<?php

/**
 * OLX Webhook Receiver - SECURE VERSION
 * 
 * Endpoint seguro para receber webhooks da OLX.
 * 
 * Requerimentos:
 * - Header X-Webhook-Signature (HMAC-SHA256)
 * - Header X-Webhook-Timestamp (Unix seconds)
 * 
 * Segurança:
 * - Validação de assinatura HMAC-SHA256
 * - Validação de timestamp (replay protection)
 * - Autenticação via portal_integrations
 * - Validação de ownership do property_id
 * - Isolamento multi-tenant obrigatório
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/Application/Integration/OLXWebhookService.php';

use App\Application\Integration\OLXWebhookService;
use App\Observability\RequestTracker;
use App\Security\RateLimitPolicy;
use App\Security\RateLimiter;

$clientIp = RequestTracker::resolveClientIp();
if ($clientIp === null) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'INVALID_IP',
        'message' => 'Não foi possível validar sua conexão. Tente novamente.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$endpoint = 'olx_pre_auth';
$config = RateLimitPolicy::getConfig($endpoint);
$ipIdentifier = $clientIp;
$resolvedIdentifier = $endpoint . ':' . $ipIdentifier;
$limit = (int) $config['limit'];
$window = (int) $config['window'];
$rateLimiter = new RateLimiter();
$headerInfo = $rateLimiter->getHeaderInfo($resolvedIdentifier, $limit, $window);
if (!$rateLimiter->isAllowed($resolvedIdentifier, $limit, $window)) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => 'RATE_LIMIT_EXCEEDED',
        'message' => sprintf('Muitas requisições. Aguarde %d segundos.', $headerInfo['reset_in']),
        'retry_after' => $headerInfo['reset_in'],
        'reset_at' => $headerInfo['reset']
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // 1. Capturar payload raw (ANTES de processar)
    $payloadRaw = file_get_contents('php://input');
    if ($payloadRaw === false || trim($payloadRaw) === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Payload vazio']);
        exit;
    }

    // 2. Capturar headers de autenticação
    $signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? null;
    $timestamp = $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] ?? null;

    if (!$signature || !$timestamp) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Headers X-Webhook-Signature e X-Webhook-Timestamp obrigatórios']);
        exit;
    }

    // 3. Processar webhook de forma segura
    $service = new OLXWebhookService($pdo);
    $result = $service->processWebhook($payloadRaw, $signature, $timestamp);

    // 4. Responder com resultado
    if ($result['success']) {
        http_response_code(201);
    } else {
        // Diferenciar erros de autenticação (401/403) de erros de negócio (400)
        if (
            strpos($result['error'], 'Assinatura') !== false ||
            strpos($result['error'], 'autenticado') !== false ||
            strpos($result['error'], 'configurado') !== false ||
            strpos($result['error'], 'RATE_LIMIT_EXCEEDED') !== false
        ) {
            http_response_code($result['error'] === 'RATE_LIMIT_EXCEEDED' ? 429 : 401);
        } elseif (strpos($result['error'], 'não pertence') !== false) {
            http_response_code(403);
        } elseif (strpos($result['error'], 'não encontrada') !== false) {
            http_response_code(404);
        } elseif (strpos($result['error'], 'replay') !== false) {
            http_response_code(200); // Idempotente: replay retorna sucesso
            echo json_encode([
                'success' => true,
                'message' => 'Webhook já processado (idempotente)',
                'data' => ['replay_detected' => true]
            ]);
            exit;
        } else {
            http_response_code(400);
        }
    }

    echo json_encode([
        'success' => $result['success'],
        'message' => $result['message'],
        'data' => $result['success'] ? [
            'lead_id' => $result['lead_id'],
            'source' => 'olx',
        ] : null,
        'error' => $result['error'],
    ]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao processar webhook: ' . $e->getMessage()]);
    exit;
}
