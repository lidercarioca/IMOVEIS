<?php
// Arquivo temporário para debug da tabela users
header('Content-Type: application/json');
require_once '../config/database.php';

try {
    // Verifica se a tabela existe
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'users'");
    $stmt->execute();
    $tableExists = $stmt->rowCount() > 0;

    if (!$tableExists) {
        echo json_encode([
            'success' => false,
            'message' => 'Tabela users não existe',
            'debug' => [
                'tables' => $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN)
            ]
        ]);
        exit;
    }

    // Obtém a estrutura da tabela
    $stmt = $pdo->prepare("DESCRIBE users");
    $stmt->execute();
    $structure = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtém alguns registros para teste
    $stmt = $pdo->prepare("SELECT id, username, name, email, role, active FROM users LIMIT 5");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'structure' => $structure,
        'sample_data' => $users
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao verificar tabela: ' . $e->getMessage()
    ]);
}
