<?php
header('Content-Type: application/json');
session_start();
require_once '../auth.php';
require_once '../config/database.php';

checkAuth();

try {
    $filtro = $_GET['filtro'] ?? 'proximos';
    $mes = $_GET['mes'] ?? date('Y-m');
    $userId = $_SESSION['user_id'];
    $isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    
    // Se um ID específico foi solicitado, retornar apenas esse agendamento
    if (isset($_GET['id'])) {
        $id = $_GET['id'];
        $stmt = $pdo->prepare("SELECT a.*, p.title as property_title, u.name as user_name
                             FROM agendamentos a
                             LEFT JOIN properties p ON a.property_id = p.id
                             LEFT JOIN users u ON a.user_id = u.id
                             WHERE a.id = ?");
        $stmt->execute([$id]);
        $agendamento = $stmt->fetch();
        
        if (!$agendamento) {
            throw new Exception('Agendamento não encontrado');
        }
        
        echo json_encode([
            'success' => true,
            'data' => [$agendamento],
            'total' => 1
        ]);
        exit;
    }
    
    $sql = "SELECT a.*, p.title as property_title, u.name as user_name, u.id as agente_id
            FROM agendamentos a
            LEFT JOIN properties p ON a.property_id = p.id
            LEFT JOIN users u ON a.user_id = u.id
            WHERE 1=1";
    
    $params = [];
    
    // Se não for admin, mostrar apenas seus agendamentos
    if (!$isAdmin) {
        $sql .= " AND a.user_id = ?";
        $params[] = $userId;
    }
    
    // Filtro por período
    switch ($filtro) {
        case 'proximos':
            $sql .= " AND DATE(a.data_agendamento) >= CURDATE()";
            break;
        case 'passados':
            $sql .= " AND DATE(a.data_agendamento) < CURDATE()";
            break;
        case 'mes':
            $sql .= " AND DATE_FORMAT(a.data_agendamento, '%Y-%m') = ?";
            $params[] = $mes;
            break;
    }
    
    // Status
    if (isset($_GET['status']) && $_GET['status'] !== 'todos') {
        $sql .= " AND a.status = ?";
        $params[] = $_GET['status'];
    }
    
    $sql .= " ORDER BY a.data_agendamento ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    $agendamentos = [];
    while ($row = $stmt->fetch()) {
        // Calcular dias restantes
        $dataAgendamento = new DateTime($row['data_agendamento']);
        $hoje = new DateTime();
        $intervalo = $hoje->diff($dataAgendamento);
        
        $row['dias_faltam'] = $intervalo->invert ? -$intervalo->days : $intervalo->days;
        $row['data_formatada'] = $dataAgendamento->format('d/m/Y H:i');
        
        $agendamentos[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $agendamentos,
        'total' => count($agendamentos)
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
