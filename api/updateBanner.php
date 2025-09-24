<?php
require_once '../config/database.php';
require_once '../auth.php';
require_once '../app/security/Security.php';

Security::init();
checkAuth();

header('Content-Type: application/json');

if (!isset($_POST['id'])) {
    echo json_encode(['success' => false, 'error' => 'ID não fornecido']);
    exit;
}

$id = intval($_POST['id']);

try {
    $updates = [];
    $params = [];

    if (isset($_POST['title'])) {
        $updates[] = "title = ?";
        $params[] = $_POST['title'];
    }

    if (isset($_POST['description'])) {
        $updates[] = "description = ?";
        $params[] = $_POST['description'];
    }

    if (empty($updates)) {
        echo json_encode(['success' => false, 'error' => 'Nenhum dado para atualizar']);
        exit;
    }

    $sql = "UPDATE banners SET " . implode(", ", $updates) . " WHERE id = ?";
    $params[] = $id;

    $stmt = $pdo->prepare($sql);
    if ($stmt->execute($params)) {
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Banner não encontrado ou nenhuma alteração realizada']);
        }
    } else {
        throw new Exception('Erro ao executar a atualização');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erro ao atualizar banner: ' . $e->getMessage()]);
}
