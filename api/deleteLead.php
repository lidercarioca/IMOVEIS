<?php
header("Content-Type: application/json");
require_once '../config/database.php';

try {
    $db = new Database();
    $pdo = $db->connect();

    // Verifica se é uma requisição POST (múltiplos leads) ou GET (lead único)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Recebe os dados JSON do corpo da requisição
        $data = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Dados JSON inválidos");
        }
        
        if (!isset($data['leadIds']) || !is_array($data['leadIds'])) {
            throw new Exception("IDs não fornecidos corretamente");
        }

        // Prepara a query com múltiplos placeholders
        $placeholders = str_repeat('?,', count($data['leadIds']) - 1) . '?';
        $sql = "DELETE FROM leads WHERE id IN ($placeholders)";
        
        // Prepara e executa a query
        $stmt = $pdo->prepare($sql);
        $stmt->execute($data['leadIds']);
        
        echo json_encode([
            "success" => true, 
            "message" => count($data['leadIds']) > 1 ? 
                        "Leads excluídos com sucesso" : 
                        "Lead excluído com sucesso"
        ]);

    } else {
        // Método GET para exclusão individual (mantém compatibilidade)
        if (!isset($_GET['id'])) {
            throw new Exception("ID não fornecido");
        }

        $id = intval($_GET['id']);
        $stmt = $pdo->prepare("DELETE FROM leads WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode([
            "success" => true, 
            "message" => "Lead excluído com sucesso"
        ]);
    }

} catch (Exception $e) {
    error_log("Erro ao excluir lead(s): " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
?>
