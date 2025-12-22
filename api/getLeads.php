<?php
header("Content-Type: application/json");
require_once '../config/database.php';
require_once '../auth.php';

checkAuth();

try {
    // Parâmetros de paginação
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 10;
    $offset = ($page - 1) * $limit;

    // Filtros opcionais
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $period = isset($_GET['period']) ? trim($_GET['period']) : 'all';

    // Verifica se é admin
    $isAdmin = isAdmin();
    $userId = $_SESSION['user_id'] ?? null;

    // Monta cláusulas WHERE dinamicamente
    $where = [];
    $bindings = [];

    // Se não for admin, filtra apenas leads atribuídos ao usuário
    if (!$isAdmin && $userId) {
        // Busca leads atribuídos ao usuário OR leads de propriedades atribuídas ao usuário
        $where[] = '(l.assigned_user_id = :user_id OR l.property_id IN (SELECT id FROM properties WHERE assigned_user_id = :user_id2))';
        $bindings[':user_id'] = $userId;
        $bindings[':user_id2'] = $userId;
    }

    if ($status !== '' && $status !== 'all') {
        $where[] = 'l.status = :status';
        $bindings[':status'] = $status;
    }

    if ($q !== '') {
        // Busca em nome, email, phone e mensagem (placeholders separados para compatibilidade PDO)
        $where[] = '(l.name LIKE :q1 OR l.email LIKE :q2 OR l.phone LIKE :q3 OR l.message LIKE :q4)';
        $likeQ = '%' . $q . '%';
        $bindings[':q1'] = $likeQ;
        $bindings[':q2'] = $likeQ;
        $bindings[':q3'] = $likeQ;
        $bindings[':q4'] = $likeQ;
    }

    if ($period !== '' && $period !== 'all') {
        if ($period === 'today') {
            $where[] = "l.created_at >= CURDATE()";
        } elseif ($period === 'week') {
            $where[] = "l.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        } elseif ($period === 'month') {
            $where[] = "l.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        }
    }

    $whereSql = '';
    if (count($where) > 0) {
        $whereSql = 'WHERE ' . implode(' AND ', $where);
    }

    // Busca o total de leads com filtros
    $totalStmt = $pdo->prepare("SELECT COUNT(*) as total FROM leads l {$whereSql}");
    foreach ($bindings as $k => $v) {
        $totalStmt->bindValue($k, $v);
    }
    $totalStmt->execute();
    $totalResult = $totalStmt->fetch(PDO::FETCH_ASSOC);
    $total = intval($totalResult['total']);

    // Busca os leads da página atual com filtros
    $sql = "SELECT l.*, u.name as assigned_user_name FROM leads l 
            LEFT JOIN users u ON l.assigned_user_id = u.id 
            {$whereSql} ORDER BY l.created_at DESC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    foreach ($bindings as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalPages = ($limit > 0) ? ceil($total / $limit) : 1;

    echo json_encode([
        "success" => true,
        "data" => $leads,
        "pagination" => [
            "current_page" => $page,
            "total_pages" => $totalPages,
            "total_records" => $total,
            "per_page" => $limit,
            "offset" => $offset
        ]
    ]);

} catch (Exception $e) {
    error_log("Erro ao buscar leads: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erro ao buscar leads",
        "error" => $e->getMessage()
    ]);
}
?>
