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

    $data = json_decode(file_get_contents("php://input"), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Dados JSON inválidos");
    }

    // Verificação básica
    if (!isset($data['id'])) {
        throw new Exception("ID do lead não informado.");
    }

    // Verifica se é admin
    $isAdmin = isAdmin();
    $userId = $_SESSION['user_id'] ?? null;

    // Se não for admin, verifica se o lead pertence a uma propriedade atribuída ao usuário
    if (!$isAdmin && $userId) {
        $checkStmt = $pdo->prepare("SELECT COUNT(*) as count FROM leads 
                                   WHERE id = ? 
                                   AND (property_id IS NULL OR property_id IN (SELECT id FROM properties WHERE assigned_user_id = ?))");
        $checkStmt->execute([$data['id'], $userId]);
        $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] == 0) {
            throw new Exception("Acesso negado. Você não tem permissão para atualizar esse lead.");
        }
    }

    // Permite atualizar status e/ou notes
    $fields = [];
    $params = [];
    
    if (isset($data['status'])) {
        $fields[] = 'status = :status';
        $params[':status'] = $data['status'];
    }
    if (array_key_exists('notes', $data)) {
        $fields[] = 'notes = :notes';
        $params[':notes'] = $data['notes'];
    }
    
    $fields[] = 'updated_at = NOW()';
    $sql = "UPDATE leads SET ".implode(', ', $fields)." WHERE id = :id";
    $params[':id'] = $data['id'];

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode([
        "success" => true,
        "message" => "Lead atualizado com sucesso"
    ]);

} catch (Exception $e) {
    $code = $e->getMessage() === "Método não permitido." ? 405 : 500;
    http_response_code($code);
    error_log("Erro ao atualizar lead: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
?>
