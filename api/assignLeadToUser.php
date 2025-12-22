<?php
header("Content-Type: application/json");
require_once '../config/database.php';
require_once '../auth.php';

checkAuth();

try {
    // Verifica se é um POST
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        throw new Exception("Método não permitido.");
    }

    // Verifica se é admin (apenas admin pode atribuir leads)
    if (!isAdmin()) {
        throw new Exception("Acesso negado. Apenas administradores podem atribuir leads.");
    }

    $data = json_decode(file_get_contents("php://input"), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Dados JSON inválidos");
    }

    // Validação básica
    if (!isset($data['lead_id']) || !isset($data['assigned_user_id'])) {
        throw new Exception("Parâmetros obrigatórios faltando");
    }

    $leadId = intval($data['lead_id']);
    $assignedUserId = intval($data['assigned_user_id']) ?: null; // null se for 0 ou vazio

    // Verifica se o lead existe
    $checkStmt = $pdo->prepare("SELECT id FROM leads WHERE id = ?");
    $checkStmt->execute([$leadId]);
    if (!$checkStmt->fetch()) {
        throw new Exception("Lead não encontrado.");
    }

    // Se assignedUserId não for null, verifica se o usuário existe
    if ($assignedUserId) {
        $userCheckStmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $userCheckStmt->execute([$assignedUserId]);
        if (!$userCheckStmt->fetch()) {
            throw new Exception("Usuário não encontrado.");
        }
    }

    // Atualiza o assigned_user_id do lead
    $stmt = $pdo->prepare("UPDATE leads SET assigned_user_id = ? WHERE id = ?");
    $stmt->execute([$assignedUserId, $leadId]);

    echo json_encode([
        "success" => true,
        "message" => $assignedUserId ? "Lead atribuído com sucesso" : "Atribuição removida com sucesso"
    ]);

} catch (Exception $e) {
    $code = $e->getMessage() === "Método não permitido." ? 405 : 500;
    http_response_code($code);
    error_log("Erro ao atribuir lead: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
?>
