<?php
// api/deletePropertyImage.php
header('Content-Type: application/json');
require_once '../config/database.php';

try {
    // Validação dos parâmetros
    $id = isset($_POST['id']) ? preg_replace('/[^0-9]/', '', $_POST['id']) : '';
    $file = isset($_POST['file']) ? $_POST['file'] : '';
    
    if (!$id || !$file) {
        throw new Exception('ID ou arquivo não informado.');
    }
    
    // Validação do caminho do arquivo
    $dir = __DIR__ . '/../assets/imagens/' . $id . '/';
    $path = realpath($dir . basename($file));
    
    if (!$path || strpos($path, realpath($dir)) !== 0 || !file_exists($path)) {
        throw new Exception('Arquivo não encontrado.');
    }
    
    // Inicia transação para garantir consistência entre arquivo e banco
    $pdo->beginTransaction();
    
    try {
        // Remove a referência do banco primeiro
        $stmt = $pdo->prepare('DELETE FROM property_images WHERE property_id = :property_id AND image_url = :image_url');
        $stmt->execute([
            ':property_id' => $id,
            ':image_url' => $file
        ]);
        
        // Se removeu do banco com sucesso, remove o arquivo
        if (!unlink($path)) {
            throw new Exception('Erro ao excluir arquivo físico.');
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Imagem excluída com sucesso.']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Erro de banco ao excluir imagem: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao excluir referência da imagem no banco de dados.']);
} catch (Exception $e) {
    error_log("Erro ao excluir imagem: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
