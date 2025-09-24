<?php
require_once '../config/database.php';
header('Content-Type: application/json');

try {
    if (!isset($_FILES['banner']) || $_FILES['banner']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Arquivo não enviado ou inválido.');
    }

    $file = $_FILES['banner'];
    
    // Validação do tipo MIME
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Formato de imagem não suportado. Use JPG, PNG, GIF ou WebP.');
    }

    // Validação da extensão
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
        throw new Exception('Extensão de arquivo não suportada.');
    }

    $targetDir = '../assets/imagens/banners/';
    if (!is_dir($targetDir)) {
        if (!mkdir($targetDir, 0777, true)) {
            throw new Exception('Falha ao criar diretório de upload');
        }
        chmod($targetDir, 0777);
    }

    if (!is_writable($targetDir)) {
        chmod($targetDir, 0777);
        if (!is_writable($targetDir)) {
            throw new Exception('Diretório sem permissão de escrita');
        }
    }

    $newName = uniqid('banner_') . '.' . $ext;
    $targetPath = $targetDir . $newName;
    
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        error_log("Falha ao mover arquivo. Detalhes:");
        error_log("Arquivo temporário existe: " . (file_exists($file['tmp_name']) ? 'Sim' : 'Não'));
        error_log("Permissões do diretório: " . substr(sprintf('%o', fileperms($targetDir)), -4));
        throw new Exception('Falha ao salvar imagem no servidor');
    }

    $imagePath = 'assets/imagens/banners/' . $newName;
    
    $sql = "INSERT INTO banners (image_path, active, order_position) VALUES (?, 1, 0)";
    $stmt = $pdo->prepare($sql);
    
    if (!$stmt->execute([$imagePath])) {
        // Se falhar no banco, remove o arquivo
        if (file_exists($targetPath)) {
            unlink($targetPath);
        }
        throw new Exception('Erro ao registrar banner no banco de dados');
    }

    echo json_encode([
        'success' => true, 
        'image_url' => '/' . $imagePath
    ]);

} catch (Exception $e) {
    error_log("Erro no uploadBannerImage.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}
