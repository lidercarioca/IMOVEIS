<?php
require_once '../config/database.php';
header('Content-Type: application/json');

try {
    // Log da requisição
    error_log("Requisição recebida para deleteBanner.php");
    error_log("POST: " . print_r($_POST, true));

    // Validação do ID
    $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
    if (!$id || $id <= 0) {
        throw new Exception('ID inválido ou não fornecido');
    }

    // Busca o banner no banco
    $sql = "SELECT image_path FROM banners WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    if (!$stmt->execute([$id])) {
        throw new Exception('Erro ao buscar informações do banner');
    }
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        error_log("Banner encontrado, iniciando processo de exclusão");
        
        // Remove o prefixo '/assets/imagens/' se existir
        $image_path = str_replace(['assets/imagens/', '/assets/imagens/'], '', $result['image_path']);
        $file = '../assets/imagens/' . $image_path;
        
        error_log("Tentando excluir arquivo: " . $file);
        
        if (file_exists($file)) {
            if (!is_writable($file)) {
                error_log("Arquivo sem permissão de escrita: " . $file);
                throw new Exception('Sem permissão para excluir o arquivo de imagem');
            }
            
            if (!unlink($file)) {
                error_log("Falha ao excluir arquivo: " . $file);
                throw new Exception('Falha ao excluir arquivo de imagem');
            }
            error_log("Arquivo excluído com sucesso");
        } else {
            error_log("Arquivo não encontrado: " . $file);
        }
        
        // Exclui o registro do banco
        $sql = "DELETE FROM banners WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        if (!$stmt->execute([$id])) {
            throw new Exception('Erro ao excluir banner do banco de dados');
        }
        
        error_log("Banner excluído com sucesso do banco de dados");
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Banner não encontrado.']);
    }
} catch (Exception $e) {
    error_log("Erro ao excluir banner: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
