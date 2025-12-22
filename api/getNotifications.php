<?php
header('Content-Type: application/json; charset=utf-8');

// Evita que erros PHP quebrem o JSON
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../auth.php';
require_once '../config/database.php';

// Garante que nenhum output seja enviado antes do JSON
ob_start();

// Limpa qualquer saída anterior
while (ob_get_level()) {
    ob_end_clean();
}

try {
    // Verifica autenticação
    checkAuth();
    
    // Obtém o ID do usuário logado
    $userId = $_SESSION['user_id'];
    
    // Pega o timestamp da última verificação (se fornecido)
    $lastCheck = isset($_GET['timestamp']) ? (int)$_GET['timestamp'] : 0;
    
        // Verifica se é admin
        $isAdmin = isAdmin();

        // Query base para notificações
        if ($isAdmin) {
            // Admin vê todas as notificações
            $baseQuery = "FROM notifications";
        } else {
            // Usuário comum vê apenas notificações dirigidas a ele ou notificações globais (user_id IS NULL)
            $baseQuery = "FROM notifications WHERE (user_id = ? OR user_id IS NULL)";
        }
    
    // Adiciona filtro de timestamp se fornecido
    if ($lastCheck > 0) {
        $baseQuery .= " AND UNIX_TIMESTAMP(created_at) * 1000 > ?";
    }
    
    // Busca notificações com os filtros apropriados
    $stmt = $pdo->prepare("
        SELECT id, title, message, type, created_at, is_read 
        $baseQuery 
        ORDER BY is_read ASC, created_at DESC 
        LIMIT 50
    ");
    
    // Executa a query com os parâmetros apropriados
    if ($isAdmin) {
        if ($lastCheck > 0) {
            $stmt->execute([$lastCheck]);
        } else {
            $stmt->execute();
        }
    } else {
        if ($lastCheck > 0) {
            $stmt->execute([$userId, $lastCheck]);
        } else {
            $stmt->execute([$userId]);
        }
    }
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Conta não lidas (sempre usa a query completa para contar)
        // Conta não lidas (usa filtro apropriado para admin/usuário)
        if ($isAdmin) {
            $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE is_read = FALSE");
            $stmtCount->execute();
        } else {
            $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND is_read = FALSE");
            $stmtCount->execute([$userId]);
        }
        $unreadCount = (int)$stmtCount->fetchColumn();
    
    // Verifica se há novas notificações desde a última verificação
    $hasNewNotifications = false;
    if ($lastCheck > 0) {
            if ($isAdmin) {
                $stmtNew = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE UNIX_TIMESTAMP(created_at) * 1000 > ?");
                $stmtNew->execute([$lastCheck]);
            } else {
                $stmtNew = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND UNIX_TIMESTAMP(created_at) * 1000 > ?");
                $stmtNew->execute([$userId, $lastCheck]);
            }
            $hasNewNotifications = (int)$stmtNew->fetchColumn() > 0;
    }
    
    // Prepara resposta
    $response = [
        'success' => true,
        'unreadCount' => $unreadCount,
        'hasNewNotifications' => $hasNewNotifications ?? false,
        'notifications' => $notifications,
        'timestamp' => time() * 1000 // Timestamp atual em milissegundos
    ];

    // Limpa buffer e envia resposta
    if (ob_get_length()) ob_clean();
    if (ob_get_length()) ob_clean();
    
    // Converte datas para formato amigável
    foreach ($notifications as &$notification) {
        if (isset($notification['created_at'])) {
            $date = new DateTime($notification['created_at']);
            $notification['created_at'] = $date->format('Y-m-d H:i:s');
        }
        // Remove possíveis caracteres que quebrariam o JSON
        $notification['message'] = strip_tags($notification['message']);
        $notification['title'] = strip_tags($notification['title']);
    }
    
    // Força o cabeçalho JSON e UTF-8
    header('Content-Type: application/json; charset=utf-8');
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unreadCount' => $unreadCount
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    
} catch (Exception $e) {
    // Limpa buffer e envia erro
    if (ob_get_length()) ob_clean();
    
    // Força o cabeçalho JSON e UTF-8
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    
    echo json_encode([
        'success' => false,
        'error' => strip_tags($e->getMessage())
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
}

// Garante que nada mais será enviado
exit();
exit;
