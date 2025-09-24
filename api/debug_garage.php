<?php
header('Content-Type: application/json');
require_once '../config/database.php';

try {
    $stmt = $pdo->query("SELECT * FROM properties LIMIT 1");
    $row = $stmt->fetch();
    echo json_encode(array_keys($row));
} catch (PDOException $e) {
    echo json_encode(['error' => 'Erro de conexão: ' . $e->getMessage()]);
}
?>
