<?php
header("Content-Type: application/json");
require_once '../config/database.php';

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
