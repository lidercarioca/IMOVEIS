<?php
// Ativa exibição de erros para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

try {
    require_once '../config/database.php';
    require_once '../app/security/Security.php';

    // Verifica se é uma requisição POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }

    // Obtém e valida os dados da requisição
    $input = file_get_contents('php://input');
    if (empty($input)) {
        throw new Exception('Nenhum dado recebido');
    }

    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON inválido: ' . json_last_error_msg());
    }

    $userId = $data['id'] ?? null;
    if (!$userId) {
        throw new Exception('ID do usuário não fornecido');
    }

    // Verifica se não está tentando excluir o admin
    if ($userId == 1) {
        throw new Exception('Não é permitido excluir o usuário administrador principal');
    }

    // Inicia a transação
    $pdo->beginTransaction();

    // Verifica se o usuário existe
    $checkStmt = $pdo->prepare('SELECT id FROM users WHERE id = ?');
    $checkStmt->execute([$userId]);
    if (!$checkStmt->fetch()) {
        throw new Exception('Usuário não encontrado');
    }

    // Exclui o usuário
    $stmt = $pdo->prepare('DELETE FROM users WHERE id = ? AND id != 1');
    $stmt->execute([$userId]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('Usuário não pode ser excluído');
    }

    // Commit da transação
    $pdo->commit();

    echo json_encode([
        'success' => true, 
        'message' => 'Usuário excluído com sucesso',
        'userId' => $userId
    ]);

} catch (Exception $e) {
    // Rollback em caso de erro
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'debug' => [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]
    ]);
}
