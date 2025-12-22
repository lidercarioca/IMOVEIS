<?php
// api/getVisitStats.php - Obtém estatísticas de visitas
header('Content-Type: application/json');
require_once '../config/database.php';

// Nota: A autenticação é feita via SESSION no navegador do usuário
// quando painel.php é acessado. Esta API é chamada pelo JS do dashboard.

try {
    // Período: últimos 30 dias por padrão
    $period = $_GET['period'] ?? '30'; // dias
    
    // Visitas totais no período
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT session_id) as total_visits
        FROM site_visits 
        WHERE visited_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    ");
    $stmt->execute([$period]);
    $totalVisits = $stmt->fetch(PDO::FETCH_ASSOC)['total_visits'] ?? 0;
    
    // Visitas de hoje
    $stmtToday = $pdo->prepare("
        SELECT COUNT(DISTINCT session_id) as visits_today
        FROM site_visits 
        WHERE DATE(visited_at) = CURDATE()
    ");
    $stmtToday->execute();
    $visitsToday = $stmtToday->fetch(PDO::FETCH_ASSOC)['visits_today'] ?? 0;
    
    // Visitas do mês passado (para comparação)
    $stmtLastMonth = $pdo->prepare("
        SELECT COUNT(DISTINCT session_id) as visits_last_month
        FROM site_visits 
        WHERE visited_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
        AND visited_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmtLastMonth->execute();
    $visitsLastMonth = $stmtLastMonth->fetch(PDO::FETCH_ASSOC)['visits_last_month'] ?? 0;
    
    // Calcula variação percentual
    $percentChange = 0;
    if ($visitsLastMonth > 0) {
        $percentChange = round((($totalVisits - $visitsLastMonth) / $visitsLastMonth) * 100, 1);
    }
    
    // Páginas mais visitadas
    $stmtPages = $pdo->prepare("
        SELECT page_url, COUNT(*) as visits
        FROM site_visits 
        WHERE visited_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY page_url
        ORDER BY visits DESC
        LIMIT 5
    ");
    $stmtPages->execute([$period]);
    $topPages = $stmtPages->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'total_visits' => (int)$totalVisits,
            'visits_today' => (int)$visitsToday,
            'visits_last_period' => (int)$visitsLastMonth,
            'percent_change' => $percentChange,
            'top_pages' => $topPages,
            'period_days' => (int)$period
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
