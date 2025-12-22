<?php
header('Content-Type: application/json');
session_start();
require_once '../auth.php';
require_once '../config/database.php';

checkAuth();

try {
    $userId = $_SESSION['user_id'];
    
    // Obter agendamentos de hoje e amanhã
    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            a.data_agendamento,
            a.status,
            a.descricao,
            p.title as property_title,
            p.id as property_id,
            u.name as user_name
        FROM agendamentos a
        LEFT JOIN properties p ON a.property_id = p.id
        LEFT JOIN users u ON a.user_id = u.id
        WHERE a.user_id = ?
        AND DATE(a.data_agendamento) >= CURDATE()
        AND DATE(a.data_agendamento) <= DATE_ADD(CURDATE(), INTERVAL 1 DAY)
        ORDER BY a.data_agendamento ASC
    ");
    
    $stmt->execute([$userId]);
    $agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organizar por data
    $hoje = [];
    $amanha = [];
    
    foreach ($agendamentos as $agendamento) {
        $data = date('Y-m-d', strtotime($agendamento['data_agendamento']));
        $dataHoje = date('Y-m-d');
        $dataAmanha = date('Y-m-d', strtotime('+1 day'));
        
        if ($data === $dataHoje) {
            $hoje[] = $agendamento;
        } elseif ($data === $dataAmanha) {
            $amanha[] = $agendamento;
        }
    }
    
    echo json_encode([
        'success' => true,
        'hoje' => $hoje,
        'amanha' => $amanha,
        'total' => count($agendamentos)
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
