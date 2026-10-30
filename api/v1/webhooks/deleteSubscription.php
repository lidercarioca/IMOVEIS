<?php

/**
 * API: Deletar Webhook Subscription
 * POST /api/v1/webhooks/deleteSubscription.php
 * Body: {"id": <id>}
 */

// Garantir que nenhuma saída anterior interfira com JSON
while (ob_get_level()) ob_end_clean();
ob_start();

// Header JSON antes de qualquer lógica
header("Content-Type: application/json; charset=utf-8");
http_response_code(200);

// Autoload e imports
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

use App\Infrastructure\Webhook\WebhookRepository;
use App\Service\WebhookService;

try {
    // Apenas POST permitido
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método não permitido. Use POST']);
        exit;
    }

    // Incluir dependências
    require_once __DIR__ . '/../../../config/database.php';
    require_once __DIR__ . '/../../../config/session_manager.php';
    require_once __DIR__ . '/../../../app/security/Security.php';
    require_once __DIR__ . '/../../../app/helpers/tenant_isolation_helpers.php';

    // Verificar autenticação e inicializar tenant
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Não autenticado']);
        exit;
    }

    initializeTenantMiddleware($pdo);

    $userId = \App\Auth\AuthContext::getInstance()->getUserId();
    $tenantId = getCurrentTenantId();

    // Obter ID do webhook (pode vir de GET ou POST)
    $subscriptionId = 0;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        $subscriptionId = isset($data['id']) ? (int)$data['id'] : 0;
    }

    if ($subscriptionId <= 0) {
        // Tentar pegar de GET como fallback
        $subscriptionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    }

    if ($subscriptionId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID do webhook inválido']);
        exit;
    }

    $repo = new WebhookRepository($pdo);
    $service = new WebhookService($repo, $pdo);

    $rowCount = $service->deleteSubscription($subscriptionId, $userId, $tenantId);

    if ($rowCount > 0) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Webhook deletado com sucesso'
        ]);
    } else if ($rowCount === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Webhook não encontrado ou sem permissão'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Falha ao deletar webhook'
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro no banco: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro: ' . $e->getMessage()
    ]);
} finally {
    // Garantir que apenas JSON seja enviado
    while (ob_get_level() > 1) {
        ob_end_clean();
    }
    ob_end_flush();
}
