<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../config/database.php';

// Autenticação
checkAuth();

// Somente admin principal tem acesso
if (!isAdmin() || !isset($_SESSION['username']) || $_SESSION['username'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$data = [
    'anomalies_count' => 0,
    'suspicious_ips_count' => 0,
    'blocked_ips_count' => 0,
    'critical_alerts_count' => 0,
    'anomalies' => [],
    'suspicious_ips' => [],
    'blocked_ips' => [],
    'alerts' => []
];

try {
    // Tenta ler contagens se as tabelas existirem
    $tables = ['login_anomalies', 'login_history', 'blocked_ips', 'security_alerts'];
    foreach ($tables as $t) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE :t");
        $stmt->execute([':t' => $t]);
        $exists = $stmt->fetch();
        if (!$exists) continue;
    }

    // Anomalias (últimas 24h)
    $stmt = $pdo->prepare("SELECT id, user_id, username, anomaly_type, ip_address, severity, detected_at FROM login_anomalies WHERE resolved = 0 AND detected_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) ORDER BY detected_at DESC LIMIT 50");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $data['anomalies'] = $rows;
    $data['anomalies_count'] = count($rows);

    // IPs suspeitas (última hora)
    $stmt = $pdo->prepare("SELECT ip_address, COUNT(*) as attempt_count, COUNT(DISTINCT username) as unique_users, GROUP_CONCAT(DISTINCT username SEPARATOR ', ') as attempted_users FROM login_history WHERE login_time > DATE_SUB(NOW(), INTERVAL 1 HOUR) GROUP BY ip_address HAVING attempt_count > 1 ORDER BY attempt_count DESC LIMIT 50");
    $stmt->execute();
    $data['suspicious_ips'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $data['suspicious_ips_count'] = count($data['suspicious_ips']);

    // IPs bloqueadas ativas
    $stmt = $pdo->prepare("SELECT ip_address, reason, blocked_at FROM blocked_ips WHERE is_active = 1 AND expires_at > NOW() ORDER BY blocked_at DESC LIMIT 50");
    $stmt->execute();
    $data['blocked_ips'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $data['blocked_ips_count'] = count($data['blocked_ips']);

    // Alertas não resolvidos
    $stmt = $pdo->prepare("SELECT id, alert_type, severity, message, related_ip_address, alert_time FROM security_alerts WHERE resolved = 0 ORDER BY alert_time DESC LIMIT 50");
    $stmt->execute();
    $data['alerts'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $data['critical_alerts_count'] = count(array_filter($data['alerts'], function($a){ return $a['severity'] === 'CRITICAL'; }));

    echo json_encode(['success' => true, 'data' => $data]);
    exit;
} catch (Exception $e) {
    error_log('Erro em getSecurityMonitoring: ' . $e->getMessage());
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}
