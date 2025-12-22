<?php
// api/trackVisit.php - Rastreia visitas do site
header('Content-Type: application/json');
require_once '../config/database.php';

// Inicia sessão se não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Obtém informações do visitante
    $visitor_ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
    $visitor_ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $page_url = parse_url($_SERVER['HTTP_REFERER'] ?? $_REQUEST['page'] ?? '/', PHP_URL_PATH);
    $referrer = $_SERVER['HTTP_REFERER'] ?? null;
    $session_id = session_id();

    // Verifica se já existe visita na sessão nos últimos 5 minutos
    $stmt = $pdo->prepare("
        SELECT id FROM site_visits 
        WHERE session_id = ? AND visited_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ORDER BY visited_at DESC LIMIT 1
    ");
    $stmt->execute([$session_id]);
    
    // Apenas registra se não houver visita recente da mesma sessão
    if ($stmt->rowCount() === 0) {
        $insertStmt = $pdo->prepare("
            INSERT INTO site_visits (visitor_ip, visitor_ua, page_url, referrer, session_id)
            VALUES (?, ?, ?, ?, ?)
        ");
        $insertStmt->execute([$visitor_ip, $visitor_ua, $page_url, $referrer, $session_id]);
    }

    echo json_encode(['success' => true, 'tracked' => $insertStmt->rowCount() > 0 ?? false]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
