<?php
// Habilita CORS para desenvolvimento
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

// Se for uma requisição OPTIONS, retorna OK
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../config/database.php';
require_once '../auth.php';

// Verifica autenticação
checkAuth();

// Obtém o ID do usuário logado
$userId = $_SESSION['user_id'] ?? null;

// Log da requisição para debug
error_log("=== Início da Requisição deleteNotification.php ===");
error_log("Método: " . $_SERVER['REQUEST_METHOD']);
error_log("Headers: " . json_encode(getallheaders()));
error_log("Query String: " . $_SERVER['QUERY_STRING']);
error_log("Request URI: " . $_SERVER['REQUEST_URI']);

// Permite POST ou DELETE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    error_log("Corpo da requisição: " . $rawInput);
    
    $input = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Erro ao decodificar JSON: " . json_last_error_msg());
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'JSON inválido']);
        exit;
    }
    
    if (!isset($input['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID não fornecido']);
        exit;
    }
    $id = intval($input['id']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    if (!isset($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID não fornecido']);
        exit;
    }
    $id = intval($_GET['id']);
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Verifica se o ID é válido
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

// Prepara e executa a query
try {
    // Primeiro verifica se a notificação existe e está marcada como lida
    $checkStmt = $pdo->prepare("SELECT is_read, link, user_id FROM notifications WHERE id = :id");
    $checkStmt->execute(['id' => $id]);
    $notification = $checkStmt->fetch();

    if (!$notification) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Notificação não encontrada']);
        exit;
    }

    if (!$notification['is_read']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'A notificação precisa estar marcada como lida para ser excluída']);
        exit;
    }
    // Verifica permissões: admin pode excluir qualquer notificação;
    // usuário só pode excluir notificações dirigidas a ele (user_id igual) — não pode excluir notificações globais (user_id IS NULL)
    $currentUserId = $userId;
    $isAdminUser = isAdmin();

    if (!$isAdminUser) {
        // se for notificação global (user_id NULL), negar
        if ($notification['user_id'] === null) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado. Não é possível excluir notificações globais.']);
            exit;
        }
        // se não pertence ao usuário, negar
        if (intval($notification['user_id']) !== intval($currentUserId)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado. Você só pode excluir suas próprias notificações.']);
            exit;
        }
    }

    // Se passou pelas validações, executa a exclusão
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = :id");
    
    if ($stmt->execute(['id' => $id])) {
        // Se a notificação tinha um link para uma mensagem específica, remove também a mensagem
        try {
            if (!empty($notification['link']) && strpos($notification['link'], 'tab=messages') !== false) {
                // Tenta extrair o id da query string, exemplo: painel.php?tab=messages&id=123
                $parts = parse_url($notification['link']);
                if (isset($parts['query'])) {
                    parse_str($parts['query'], $qs);
                    if (isset($qs['id']) && intval($qs['id']) > 0) {
                        $mid = intval($qs['id']);
                        $delMsg = $pdo->prepare("DELETE FROM messages WHERE id = :mid");
                        $delMsg->execute(['mid' => $mid]);
                        error_log("Mensagem relacionada (id={$mid}) excluída junto com a notificação id={$id}");
                    }
                }
            }
        } catch (Exception $ex) {
            // Não interrompe o fluxo principal se falhar ao remover a mensagem
            error_log("Falha ao remover mensagem relacionada: " . $ex->getMessage());
        }

        echo json_encode(['success' => true, 'message' => 'Notificação excluída com sucesso']);
    } else {
        throw new Exception('Erro ao excluir notificação');
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log("Erro ao excluir notificação: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao excluir notificação']);
}
?>