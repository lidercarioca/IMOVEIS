<?php

require_once 'check_auth.php';
checkApiAuth();
require_once '../auth.php';
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Apenas administradores podem excluir imóveis."]);
    exit;
}

// Log: início do script
file_put_contents(__DIR__.'/../log_delete.txt', 'INICIOU SCRIPT'.PHP_EOL, FILE_APPEND);
header("Content-Type: application/json");

// Verifica se foi enviado via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    file_put_contents(__DIR__.'/../log_delete.txt', 'ERRO: método'.PHP_EOL, FILE_APPEND);
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Método não permitido."]);
    exit;
}

// Verifica se o ID foi enviado
if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    file_put_contents(__DIR__.'/../log_delete.txt', 'ERRO: id inválido'.PHP_EOL, FILE_APPEND);
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "ID inválido."]);
    exit;
}


$propertyId = intval($_POST['id']);

// Conexão com o banco (ajuste para o seu ambiente)
file_put_contents(__DIR__.'/../log_delete.txt', 'ANTES DO REQUIRE DB'.PHP_EOL, FILE_APPEND);
require_once '../config/database.php';
file_put_contents(__DIR__.'/../log_delete.txt', 'APOS REQUIRE DB'.PHP_EOL, FILE_APPEND);

file_put_contents(__DIR__.'/../log_delete.txt', 'ID recebido: '.$propertyId.PHP_EOL, FILE_APPEND);

// Verifica se o imóvel existe antes de tentar deletar
$stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM properties WHERE id = ?");
$stmtCheck->execute([$propertyId]);
$exists = $stmtCheck->fetchColumn();
file_put_contents(__DIR__.'/../log_delete.txt', 'Existe no banco: '.$exists.PHP_EOL, FILE_APPEND);

file_put_contents(__DIR__.'/../log_delete.txt', 'ANTES DO TRY'.PHP_EOL, FILE_APPEND);
try {
    file_put_contents(__DIR__.'/../log_delete.txt', 'DENTRO DO TRY'.PHP_EOL, FILE_APPEND);
    // Primeiro, busca o caminho das imagens
    $stmtImgs = $pdo->prepare("SELECT image_url FROM property_images WHERE property_id = ?");
    $stmtImgs->execute([$propertyId]);
    $images = $stmtImgs->fetchAll(PDO::FETCH_COLUMN);

    // Remove imagens físicas
    foreach ($images as $img) {
        $path = __DIR__ . "/../assets/imagens/$propertyId/$img";
        if (file_exists($path)) {
            unlink($path);
        }
    }

    // Remove a pasta se estiver vazia
    $folder = __DIR__ . "/../assets/imagens/$propertyId";
    if (is_dir($folder)) {
        @rmdir($folder);
    }

    // Remove as imagens do banco
    $stmtImgsDel = $pdo->prepare("DELETE FROM property_images WHERE property_id = ?");
    $stmtImgsDel->execute([$propertyId]);

    // Remove o imóvel
    $stmt = $pdo->prepare("DELETE FROM properties WHERE id = ?");
    $stmt->execute([$propertyId]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(["success" => false, "message" => "Imóvel não encontrado ou já removido."]);
        exit;
    }

    echo json_encode(["success" => true, "message" => "Imóvel excluído com sucesso."]);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Erro ao excluir imóvel.", "error" => $e->getMessage()]);
    exit;
}
