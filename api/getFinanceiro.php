<?php
header('Content-Type: application/json');
session_start();
require_once '../auth.php';
require_once '../config/database.php';

checkAuth();

try {
    $isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    $userId = $_SESSION['user_id'];
    $modo = $_GET['modo'] ?? ($_GET['tipo'] ?? 'resumo'); // resumo ou detalhado
    $mes = $_GET['mes'] ?? date('Y-m');
    $tipoFiltro = $_GET['tipoFiltro'] ?? 'todos'; // filtro por tipo de transação: receita, despesa, comissao
    
    // Criar tabela de financeiro se não existir
    $createTable = "CREATE TABLE IF NOT EXISTS financeiro (
        id INT AUTO_INCREMENT PRIMARY KEY,
        property_id INT,
        user_id INT NOT NULL,
        tipo VARCHAR(50) COMMENT 'receita, despesa, comissao',
        descricao VARCHAR(255),
        valor DECIMAL(10, 2) NOT NULL,
        data_transacao DATE NOT NULL,
        categoria VARCHAR(100),
        status VARCHAR(50) DEFAULT 'pendente' COMMENT 'pendente, concluído, cancelado',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE SET NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    
    $pdo->exec($createTable);
    
    $where = "WHERE 1=1";
    $params = [];
    
    // Filtrar por mês se não for "todos"
    if ($mes !== 'todos') {
        $where .= " AND DATE_FORMAT(f.data_transacao, '%Y-%m') = ?";
        $params[] = $mes;
    }
    
    if (!$isAdmin) {
        $where .= " AND f.user_id = ?";
        $params[] = $userId;
    }
    
    if ($modo === 'resumo') {
        // Resumo geral
        $sql = "SELECT 
                    SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END) as total_receitas,
                    SUM(CASE WHEN tipo = 'despesa' THEN valor ELSE 0 END) as total_despesas,
                    SUM(CASE WHEN tipo = 'comissao' THEN valor ELSE 0 END) as total_comissoes,
                    SUM(CASE WHEN tipo = 'receita' THEN valor WHEN tipo = 'despesa' THEN -valor WHEN tipo = 'comissao' THEN -valor ELSE 0 END) as lucro_liquido
                FROM financeiro f
                $where";
    }
    else {
        // Detalhado
        $sql = "SELECT f.*, p.title as property_title, u.name as user_name
                FROM financeiro f
                LEFT JOIN properties p ON f.property_id = p.id
                LEFT JOIN users u ON f.user_id = u.id
                $where";
        
        if ($tipoFiltro !== 'todos') {
            $sql .= " AND f.tipo = ?";
            $params[] = $tipoFiltro;
        }
        
        if (isset($_GET['categoria']) && $_GET['categoria'] !== 'todos') {
            $sql .= " AND f.categoria = ?";
            $params[] = $_GET['categoria'];
        }
        
        if (isset($_GET['status']) && $_GET['status'] !== 'todos') {
            $sql .= " AND f.status = ?";
            $params[] = $_GET['status'];
        }
        
        $sql .= " ORDER BY f.data_transacao DESC";
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    if ($modo === 'resumo') {
        $resumo = $stmt->fetch();
        $resumo['total_receitas'] = (float)($resumo['total_receitas'] ?? 0);
        $resumo['total_despesas'] = (float)($resumo['total_despesas'] ?? 0);
        $resumo['total_comissoes'] = (float)($resumo['total_comissoes'] ?? 0);
        $resumo['lucro_liquido'] = (float)($resumo['lucro_liquido'] ?? 0);
        
        echo json_encode([
            'success' => true,
            'data' => $resumo
        ]);
    } else {
        $dados = [];
        while ($row = $stmt->fetch()) {
            $row['valor'] = (float)$row['valor'];
            $dados[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $dados,
            'total' => count($dados)
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
