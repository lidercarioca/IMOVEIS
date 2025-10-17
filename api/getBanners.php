<?php
/**
 * API endpoint para buscar banners ativos do sistema
 * Retorna a lista de banners ordenados por posição e ID
 * 
 * @return JSON Lista de banners com suas informações
 */

require_once '../config/database.php';
header('Content-Type: application/json');

try {
    $sql = "SELECT * FROM banners WHERE active = 1 ORDER BY order_position, id DESC";
    $stmt = $pdo->query($sql);
    $banners = array_map(function($row) {
        $row['image_url'] = $row['image_path'];
        return $row;
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    
    echo json_encode($banners);
} catch (Exception $e) {
    error_log("Erro ao buscar banners: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar banners'
    ]);
}
