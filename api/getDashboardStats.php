<?php
require_once '../config/database.php';
require_once '../app/middleware/api_auth.php';

// Verifica autenticação da API
checkApiAuth();

// Headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
header('Access-Control-Allow-Credentials: true');

// Debug
error_log('getDashboardStats.php iniciado');
error_log('Session ID: ' . session_id());
error_log('Session data: ' . print_r($_SESSION, true));

// Verifica se o usuário está autenticado
if (!isset($_SESSION['user_id'])) {
    error_log('Falha na autenticação: user_id não encontrado na sessão');
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Não autorizado'
    ]);
    exit;
}

// Regenera ID da sessão periodicamente
if (!isset($_SESSION['last_regeneration']) || time() - $_SESSION['last_regeneration'] > 300) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// Pega o período solicitado
$period = $_GET['period'] ?? '7'; // 7, 30 ou 90 dias
$period = filter_var($period, FILTER_VALIDATE_INT);
$period = in_array($period, [7, 30, 90]) ? $period : 7;

try {
    global $pdo;
    
    if (!isset($pdo)) {
        throw new Exception("Erro de conexão com o banco de dados");
    }
    
    // Data inicial para o período selecionado
    $startDate = date('Y-m-d', strtotime("-$period days"));
    
    // Consulta visualizações/contatos por dia
    $query = "SELECT 
        DATE(created_at) as date,
        COUNT(*) as total_leads
    FROM leads 
    WHERE created_at >= :start_date
    GROUP BY DATE(created_at)
    ORDER BY date";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':start_date', $startDate);
    $stmt->execute();
    $leadStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Consulta imóveis novos por dia
    $query = "SELECT 
        DATE(created_at) as date,
        COUNT(*) as new_properties
    FROM properties 
    WHERE created_at >= :start_date
    GROUP BY DATE(created_at)
    ORDER BY date";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':start_date', $startDate);
    $stmt->execute();
    $propertyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Consulta status alterados para vendido/alugado por dia
    $query = "SELECT 
        DATE(updated_at) as date,
        COUNT(*) as closed_deals
    FROM properties 
    WHERE (status = 'vendido' OR status = 'alugado')
    AND updated_at >= :start_date
    GROUP BY DATE(updated_at)
    ORDER BY date";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':start_date', $startDate);
    $stmt->execute();
    $dealsStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organiza os dados por dia
    $stats = [];
    $currentDate = new DateTime($startDate);
    $endDate = new DateTime();
    
    while ($currentDate <= $endDate) {
        $dateStr = $currentDate->format('Y-m-d');
        $stats[$dateStr] = [
            'date' => $dateStr,
            'leads' => 0,
            'new_properties' => 0,
            'closed_deals' => 0
        ];
        $currentDate->modify('+1 day');
    }
    
    // Preenche com os dados reais
    foreach ($leadStats as $stat) {
        if (isset($stats[$stat['date']])) {
            $stats[$stat['date']]['leads'] = (int)$stat['total_leads'];
        }
    }
    
    foreach ($propertyStats as $stat) {
        if (isset($stats[$stat['date']])) {
            $stats[$stat['date']]['new_properties'] = (int)$stat['new_properties'];
        }
    }
    
    foreach ($dealsStats as $stat) {
        if (isset($stats[$stat['date']])) {
            $stats[$stat['date']]['closed_deals'] = (int)$stat['closed_deals'];
        }
    }
    
    // Converte para array e remove as chaves
    $finalStats = array_values($stats);

    // Consulta imóveis por categoria e status
    $query = "SELECT 
        CASE 
            WHEN type = 'apartment' THEN 'Apartamentos'
            WHEN type = 'house' THEN 'Casas'
            WHEN type = 'commercial' THEN 'Comercial'
            WHEN type = 'land' THEN 'Terrenos'
        END as categoria,
        COUNT(*) as total
    FROM properties 
    WHERE status != 'inactive'
    GROUP BY type 
    ORDER BY type";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $categoryStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Organiza os dados por categoria
    $categories = [
        'Apartamentos' => 0,
        'Casas' => 0,
        'Comercial' => 0,
        'Terrenos' => 0
    ];

    foreach ($categoryStats as $stat) {
        if (!empty($stat['categoria'])) {
            $categories[$stat['categoria']] = (int)$stat['total'];
        }
    }
    
    // Garante que todas as categorias tenham valores válidos
    foreach ($categories as $key => $value) {
        $categories[$key] = (int)$value;
    }

    // Retorna os dados em formato JSON
    echo json_encode([
        'success' => true,
        'data' => $finalStats,
        'categories' => $categories
    ], JSON_NUMERIC_CHECK);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Erro no getDashboardStats.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao carregar dados: ' . $e->getMessage()
    ]);
}
