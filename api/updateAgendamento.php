<?php
header('Content-Type: application/json');
session_start();
require_once '../auth.php';
require_once '../config/database.php';
require_once '../app/utils/NotificationManager.php';

checkAuth();

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || !isset($data['id'])) {
        throw new Exception('ID do agendamento obrigatório');
    }
    
    $id = $data['id'];
    $userId = $_SESSION['user_id'];
    $isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    
    // Verificar permissões
    $checkStmt = $pdo->prepare("SELECT user_id FROM agendamentos WHERE id = ?");
    $checkStmt->execute([$id]);
    $agendamento = $checkStmt->fetch();
    
    if (!$agendamento) {
        throw new Exception('Agendamento não encontrado');
    }
    
    if (!$isAdmin && $agendamento['user_id'] != $userId) {
        throw new Exception('Sem permissão para atualizar este agendamento');
    }
    
    // Atualizar status
    if (isset($data['status'])) {
        $stmt = $pdo->prepare("UPDATE agendamentos SET status = ? WHERE id = ?");
        $stmt->execute([$data['status'], $id]);
        $statusMudou = true;
        $novoStatus = $data['status'];
    }
    // Atualizar data
    elseif (isset($data['data_agendamento'])) {
        $stmt = $pdo->prepare("UPDATE agendamentos SET data_agendamento = ? WHERE id = ?");
        $stmt->execute([$data['data_agendamento'], $id]);
        $statusMudou = false;
    }
    // Atualizar descrição
    elseif (isset($data['descricao'])) {
        $stmt = $pdo->prepare("UPDATE agendamentos SET descricao = ? WHERE id = ?");
        $stmt->execute([$data['descricao'], $id]);
        $statusMudou = false;
    }
    else {
        throw new Exception('Nenhum campo para atualizar');
    }
    
    // Buscar agendamento atualizado
    $result = $pdo->prepare("SELECT a.*, p.title as property_title, u.name as user_name
                                     FROM agendamentos a
                                     LEFT JOIN properties p ON a.property_id = p.id
                                     LEFT JOIN users u ON a.user_id = u.id
                                     WHERE a.id = ?");
    
    $result->execute([$id]);
    $agendamento = $result->fetch();

    // Se o payload incluiu data_agendamento, force o retorno com o mesmo valor
    if (isset($data['data_agendamento']) && $data['data_agendamento']) {
        $agendamento['data_agendamento'] = $data['data_agendamento'];
    }
    
    // Disparar notificação se status foi alterado
    if (isset($statusMudou) && $statusMudou) {
        try {
            $notificationManager = new NotificationManager($pdo);
            $notificationManager->notifyAgendamentoStatusChange(
                $agendamento['property_title'],
                $novoStatus,
                $agendamento['user_name']
            );
        } catch (Exception $notifyError) {
            // Log erro mas não falha a atualização
            error_log("Erro ao notificar alteração de agendamento: " . $notifyError->getMessage());
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Agendamento atualizado com sucesso',
        'data' => $agendamento
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
