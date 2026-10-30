<?php

/**
 * POST /api/v1/alertas/handleInactivityAlert.php
 * 
 * Processar ações em alertas de inatividade
 * Body:
 * - action: 'acknowledge' | 'resolve' | 'delete'
 * - alert_id: ID do alerta
 * - lead_id: ID do lead (para marcar como contatado)
 */

header('Content-Type: application/json');

require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/vendor/autoload.php';
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config/database.php';
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/auth.php';
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/app/helpers/tenant_isolation_helpers.php';

checkAuth();

try {
    global $pdo;

    // Initialize tenant isolation
    initializeTenantMiddleware($pdo);
    $tenantId = getCurrentTenantId();
    $userId = $_SESSION['user_id'] ?? null;

    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? null;
    $alertId = (int)($input['alert_id'] ?? 0);
    $leadId = (int)($input['lead_id'] ?? 0);

    if (!$action || !$alertId) {
        throw new Exception('Parâmetros obrigatórios faltando: action, alert_id');
    }

    $alertService = new \App\Services\LeadInactivityAlertService($pdo, $tenantId, $userId);

    switch ($action) {
        case 'acknowledge':
            // Reconhecer alerta (usuário viu e vai agir)
            $result = $alertService->acknowledgeAlert($alertId);
            break;

        case 'resolve':
            // Resolver alerta (usuário entrou em contato com lead)
            $result = $alertService->resolveAlert($alertId);

            // Se lead_id fornecido, registrar interação via repository
            if ($leadId > 0) {
                $alertService->createLeadInteraction(
                    $leadId,
                    $userId,
                    'called',
                    'Contato por alerta de inatividade',
                    'Usuário marcou alerta como resolvido'
                );
            }
            break;

        case 'delete':
            // Deletar alerta (usuário quer remover sem resolver)
            $result = $alertService->softDeleteAlert($alertId);
            break;

        default:
            throw new Exception('Ação desconhecida: ' . $action);
    }

    echo json_encode([
        'success' => true,
        'action' => $action,
        'alert_id' => $alertId,
        'message' => "Alerta {$action}d com sucesso"
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
