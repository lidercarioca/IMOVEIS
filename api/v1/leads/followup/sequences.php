<?php

/**
 * API para gerenciar sequências de follow-up
 * CRUD completo para sequências de follow-up automático
 */

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../config/session_manager.php';
require_once __DIR__ . '/../../../../app/services/LeadFollowupService.php';

header('Content-Type: application/json');

// Verificar autenticação
require_once __DIR__ . '/../../../../api/check_auth.php';

$tenantId = getRequestTenantId();

$method = $_SERVER['REQUEST_METHOD'];

try {
    $followupService = new App\Services\LeadFollowupService($pdo, $tenantId);

    switch ($method) {
        case 'GET':
            $sequenceId = $_GET['id'] ?? null;

            if ($sequenceId) {
                // Buscar sequência específica com passos
                $sequence = $followupService->getSequenceByIdWithSteps($sequenceId);
                if ($sequence) {
                    echo json_encode([
                        'success' => true,
                        'data' => $sequence
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Sequência não encontrada']);
                }
            } else {
                // Listar todas as sequências
                $sequences = $followupService->listSequences();
                echo json_encode([
                    'success' => true,
                    'data' => $sequences
                ]);
            }
            break;

        case 'POST':
            // Criar nova sequência
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data || !isset($data['name']) || !isset($data['trigger_condition'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Dados inválidos']);
                exit;
            }

            $result = $followupService->createSequence($data);

            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Sequência criada com sucesso',
                    'sequence_id' => $result['sequence_id']
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => $result['error']
                ]);
            }
            break;

        case 'PUT':
            // Atualizar sequência
            $data = json_decode(file_get_contents('php://input'), true);
            $sequenceId = $data['id'] ?? null;

            if (!$sequenceId || !$data) {
                http_response_code(400);
                echo json_encode(['error' => 'ID da sequência e dados são obrigatórios']);
                exit;
            }

            $result = $followupService->updateSequence($sequenceId, $data);

            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Sequência atualizada com sucesso'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => $result['error']
                ]);
            }
            break;

        case 'PATCH':
            // Ativar/pausar sequência (atualiza apenas is_active)
            $data = json_decode(file_get_contents('php://input'), true);
            $sequenceId = $data['id'] ?? null;
            $isActive = isset($data['is_active']) ? (bool)$data['is_active'] : null;

            if (!$sequenceId || !isset($isActive)) {
                http_response_code(400);
                echo json_encode(['error' => 'ID da sequência e novo status são obrigatórios']);
                exit;
            }

            $ok = $followupService->toggleSequenceActive($sequenceId, $isActive);

            if ($ok) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Status da sequência atualizado com sucesso'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Erro ao atualizar status da sequência'
                ]);
            }
            break;


        case 'DELETE':
            // Excluir sequência
            $data = json_decode(file_get_contents('php://input'), true);
            $sequenceId = $data['id'] ?? null;

            if (!$sequenceId) {
                error_log('[Followup] Exclusão falhou: ID da sequência não informado. Payload: ' . json_encode($data));
                http_response_code(400);
                echo json_encode(['error' => 'ID da sequência é obrigatório']);
                exit;
            }

            $result = $followupService->deleteSequence($sequenceId);

            if ($result['success']) {
                error_log('[Followup] Sequência excluída com sucesso. ID: ' . $sequenceId);
                echo json_encode([
                    'success' => true,
                    'message' => 'Sequência excluída com sucesso'
                ]);
            } else {
                error_log('[Followup] Erro ao excluir sequência ID ' . $sequenceId . ': ' . $result['error']);
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => $result['error']
                ]);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
