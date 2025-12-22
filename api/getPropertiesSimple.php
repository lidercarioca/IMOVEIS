<?php
header('Content-Type: application/json');
session_start();
require_once '../config/database.php';

// Autenticação opcional - permite tanto usuários autenticados quanto não autenticados
$needsAuth = false;
if ($needsAuth) {
    require_once '../auth.php';
    checkAuth();
}

try {
    $sql = "SELECT id, title FROM properties ORDER BY title ASC LIMIT 999";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $properties = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $properties,
        'count' => count($properties)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
