<?php
header("Content-Type: application/json");
require_once '../config/database.php';
require_once '../auth.php';

checkAuth();

// Helper de logging de ações do usuário
require_once __DIR__ . '/../app/utils/logger_functions.php';

try {
    $db = new Database();
    $pdo = $db->connect();

    // Verifica se é admin
    $isAdmin = isAdmin();
    $userId = $_SESSION['user_id'] ?? null;

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

        // Se não for admin, verifica se os leads pertencem às propriedades do usuário
        if (!$isAdmin && $userId) {
            // Verifica se todos os leads pertencem a propriedades atribuídas ao usuário
            $placeholders = str_repeat('?,', count($data['leadIds']) - 1) . '?';
            $checkSql = "SELECT COUNT(*) as count FROM leads 
                        WHERE id IN ($placeholders) 
                        AND (property_id IS NULL OR property_id IN (SELECT id FROM properties WHERE assigned_user_id = ?))";
            $checkStmt = $pdo->prepare($checkSql);
            $params = array_merge($data['leadIds'], [$userId]);
            $checkStmt->execute($params);
            $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] != count($data['leadIds'])) {
                throw new Exception("Acesso negado. Você não tem permissão para deletar esses leads.");
            }
        }

        // Prepara a query com múltiplos placeholders
        $placeholders = str_repeat('?,', count($data['leadIds']) - 1) . '?';
        $sql = "DELETE FROM leads WHERE id IN ($placeholders)";
        
        // Prepara e executa a query
        $stmt = $pdo->prepare($sql);
        $stmt->execute($data['leadIds']);
        // Log exclusão em lote de leads
        log_user_action('lead_delete', ['lead_ids' => $data['leadIds']]);
        
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
        
        // Se não for admin, verifica se o lead pertence a uma propriedade atribuída ao usuário
        if (!$isAdmin && $userId) {
            $checkStmt = $pdo->prepare("SELECT COUNT(*) as count FROM leads 
                                       WHERE id = ? 
                                       AND (property_id IS NULL OR property_id IN (SELECT id FROM properties WHERE assigned_user_id = ?))");
            $checkStmt->execute([$id, $userId]);
            $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] == 0) {
                throw new Exception("Acesso negado. Você não tem permissão para deletar esse lead.");
            }
        }
        
        $stmt = $pdo->prepare("DELETE FROM leads WHERE id = ?");
        $stmt->execute([$id]);

        // Log exclusão de lead único
        log_user_action('lead_delete', ['lead_id' => $id]);

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
