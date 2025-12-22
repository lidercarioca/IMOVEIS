<?php
require_once '../config/database.php';
header('Content-Type: application/json');

try {
    if (!isset($_FILES['banner']) || $_FILES['banner']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Arquivo não enviado ou inválido.');
    }

    $file = $_FILES['banner'];
    
    // Validação robusta do arquivo de imagem
    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        throw new Exception('Arquivo enviado não é uma imagem válida.');
    }
    $mime = $info['mime'] ?? '';
    $mimeMap = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    ];
    if (!isset($mimeMap[$mime])) {
        throw new Exception('Formato de imagem não suportado. Use JPG, PNG, GIF ou WebP.');
    }
    $ext = $mimeMap[$mime];

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
    
    // Re-encoda a imagem para remover metadados e scripts embutidos
    require_once __DIR__ . '/../app/utils/image.php';
    $reencoded = reencode_image($file['tmp_name'], $targetPath, $mime);
    if (!$reencoded) {
        error_log("Falha ao re-encodar banner: " . $targetPath);
        throw new Exception('Falha ao processar imagem enviada');
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
