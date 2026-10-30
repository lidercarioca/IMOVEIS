<?php
// Arquivo temporário para debug da tabela users
header('Content-Type: application/json');
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Infrastructure/User/UserRepository.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/session_manager.php';

use App\Infrastructure\User\UserRepository;

try {
    // Verifica se a tabela existe
    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt->execute(['users']);
    $tableExists = $stmt->rowCount() > 0;

    if (!$tableExists) {
        $stmtTables = $pdo->prepare("SHOW TABLES");
        $stmtTables->execute();
        echo json_encode([
            'success' => false,
            'message' => 'Tabela users não existe',
            'debug' => [
                'tables' => $stmtTables->fetchAll(PDO::FETCH_COLUMN)
            ]
        ]);
        exit;
    }

    // Obtém a estrutura da tabela
    $stmt = $pdo->prepare("DESCRIBE users");
    $stmt->execute();
    $structure = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtém alguns registros para teste via repositório
    $userRepo = new UserRepository($pdo);
    $tenantId = getRequestTenantId();
    $users = $userRepo->listUsers($tenantId, [], 5, 0);

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
