<?php
/**
 * API para obter vendas pendentes que precisam de transação financeira
 */

// limpa output buffer ANTES de qualquer include
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

header('Content-Type: application/json; charset=utf-8');

try {
    require_once '../auth.php';
    require_once '../config/database.php';

    // Verifica autenticação e permissão
    ob_end_clean();
    checkAuth();
    ob_start();
    if (!isAdmin()) {
        http_response_code(403);
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Acesso negado'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Busca todas as vendas pendentes
    $stmt = $pdo->prepare("
        SELECT 
            ps.id,
            ps.property_id,
            ps.username,
            ps.property_title,
            ps.property_price,
            ps.commission_6percent,
            ps.status,
            ps.created_at,
            p.title,
            p.price
        FROM property_sales ps
        LEFT JOIN properties p ON ps.property_id = p.id
        WHERE ps.status = 'pending'
        ORDER BY ps.created_at DESC
    ");
    
    $stmt->execute();
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'data' => $sales,
        'count' => count($sales)
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
