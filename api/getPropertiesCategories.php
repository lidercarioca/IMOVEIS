<?php
require_once '../config/database.php';
require_once '../app/middleware/api_auth.php';

header('Content-Type: application/json');

try {
    $conn = getConnection();
    
    // Busca estatísticas detalhadas por categoria
    $stmt = $conn->prepare("
        SELECT 
            type,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as ativos,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inativos,
            SUM(CASE WHEN status = 'vendido' THEN 1 ELSE 0 END) as vendidos,
            SUM(CASE WHEN status = 'alugado' THEN 1 ELSE 0 END) as alugados,
            MIN(price) as preco_minimo,
            MAX(price) as preco_maximo,
            AVG(price) as preco_medio
        FROM properties 
        WHERE type IS NOT NULL 
        GROUP BY type
        ORDER BY total DESC"
    );
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formata os resultados com estatísticas detalhadas
    $categories = [];
    foreach ($results as $row) {
        if ($row['type']) {
            $categories[$row['type']] = [
                'total' => intval($row['total']),
                'ativos' => intval($row['ativos']),
                'inativos' => intval($row['inativos']),
                'vendidos' => intval($row['vendidos']),
                'alugados' => intval($row['alugados']),
                'preco' => [
                    'minimo' => $row['preco_minimo'] ? floatval($row['preco_minimo']) : 0,
                    'maximo' => $row['preco_maximo'] ? floatval($row['preco_maximo']) : 0,
                    'medio' => $row['preco_medio'] ? floatval($row['preco_medio']) : 0
                ]
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $categories
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar categorias de imóveis: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
