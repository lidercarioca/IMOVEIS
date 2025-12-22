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
    
    // Verifica se é admin
    require_once '../auth.php';
    $isAdmin = isAdmin();
    $userId = $_SESSION['user_id'] ?? null;
    
    // Monta a cláusula WHERE para leads se não for admin
    $leadsWhere = "WHERE created_at >= :start_date";
    if (!$isAdmin && $userId) {
        $leadsWhere .= " AND (leads.property_id IS NULL OR leads.property_id IN (SELECT id FROM properties WHERE assigned_user_id = :user_id))";
    }
    
    // Consulta visualizações/contatos por dia
    $query = "SELECT 
        DATE(created_at) as date,
        COUNT(*) as total_leads
    FROM leads 
    {$leadsWhere}
    GROUP BY DATE(created_at)
    ORDER BY date";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':start_date', $startDate);
    if (!$isAdmin && $userId) {
        $stmt->bindParam(':user_id', $userId);
    }
    $stmt->execute();
    $leadStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Monta a cláusula WHERE para propriedades se não for admin
    $propertiesWhere = "WHERE created_at >= :start_date";
    if (!$isAdmin && $userId) {
        $propertiesWhere .= " AND assigned_user_id = :user_id";
    }
    
    // Consulta imóveis novos por dia
    $query = "SELECT 
        DATE(created_at) as date,
        COUNT(*) as new_properties
    FROM properties 
    {$propertiesWhere}
    GROUP BY DATE(created_at)
    ORDER BY date";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':start_date', $startDate);
    if (!$isAdmin && $userId) {
        $stmt->bindParam(':user_id', $userId);
    }
    $stmt->execute();
    $propertyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Monta a cláusula WHERE para negócios fechados se não for admin
    $dealsWhere = "WHERE (status = 'vendido' OR status = 'alugado') AND updated_at >= :start_date";
    if (!$isAdmin && $userId) {
        $dealsWhere .= " AND assigned_user_id = :user_id";
    }
    
    // Consulta status alterados para vendido/alugado por dia
    $query = "SELECT 
        DATE(updated_at) as date,
        COUNT(*) as closed_deals
    FROM properties 
    {$dealsWhere}
    GROUP BY DATE(updated_at)
    ORDER BY date";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':start_date', $startDate);
    if (!$isAdmin && $userId) {
        $stmt->bindParam(':user_id', $userId);
    }
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

    // Monta a cláusula WHERE para categorias se não for admin
    $categoryWhere = "WHERE status != 'inactive'";
    if (!$isAdmin && $userId) {
        $categoryWhere .= " AND assigned_user_id = :user_id";
    }
    
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
    {$categoryWhere}
    GROUP BY type 
    ORDER BY type";
    
    $stmt = $pdo->prepare($query);
    if (!$isAdmin && $userId) {
        $stmt->bindParam(':user_id', $userId);
    }
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
