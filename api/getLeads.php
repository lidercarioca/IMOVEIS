<?php
header("Content-Type: application/json");
require_once '../config/database.php';

try {
    $stmt = $pdo->query("SELECT * FROM leads ORDER BY created_at DESC");
    $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        "success" => true,
        "data" => $leads
    ]);

} catch (Exception $e) {
    error_log("Erro ao buscar leads: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erro ao buscar leads",
        "error" => $e->getMessage()
    ]);
}
?>
