<?php

header('Content-Type: application/json');
require_once '../config/database.php';

// Define o diretório de destino para a logo
$uploadDir = '../assets/imagens/logo/';

// Cria o diretório se ele não existir
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true)) {
        echo json_encode(['success' => false, 'message' => 'Falha ao criar o diretório de uploads.']);
        exit;
    }
}

// Verifica se um arquivo foi enviado
if (isset($_FILES['logo'])) {
    $file = $_FILES['logo'];

    // Valida erros no upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Erro no upload do arquivo. Código: ' . $file['error']]);
        exit;
    }

    // Validação robusta do arquivo: usa getimagesize para validar imagem e tipo
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        echo json_encode(['success' => false, 'message' => 'Arquivo enviado não é uma imagem válida.']);
        exit;
    }
    $mime = $imageInfo['mime'] ?? '';
    $allowedMimes = ['image/png'];
    if (!in_array($mime, $allowedMimes)) {
        echo json_encode(['success' => false, 'message' => 'Tipo de arquivo não permitido. Apenas PNG são aceitos.']);
        exit;
    }
    // Força extensão correta a partir do tipo detectado
    $fileExtension = 'png';

    // Valida o tamanho do arquivo (ex: máximo de 5MB)
    if ($file['size'] > 5 * 1024 * 1024) { 
        echo json_encode(['success' => false, 'message' => 'O arquivo é muito grande (máximo 5MB).']);
        exit;
    }

    // Buscar o caminho da logo anterior no banco
    $oldLogoPath = null;
    try {
        $stmt = $pdo->query('SELECT company_logo FROM company_settings WHERE id = 1 LIMIT 1');
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $oldLogoPath = $row['company_logo'];
        }
    } catch (Exception $e) {}

    // Deleta a logo anterior se existir e for diferente da nova
    if ($oldLogoPath && file_exists('../' . $oldLogoPath)) {
        @unlink('../' . $oldLogoPath);
    }

    // Gera um nome de arquivo único para evitar sobreposições (mantém 'logo' para compatibilidade)
    $newFileName = 'logo.' . $fileExtension; // Salva sempre como 'logo' para ser fácil de encontrar
    $uploadPath = $uploadDir . $newFileName;

    // Re-encoda e salva o arquivo para remover metadados e possíveis payloads
    require_once __DIR__ . '/../app/utils/image.php';
    $reencoded = reencode_image($file['tmp_name'], $uploadPath, $mime);
    if ($reencoded) {
        $relativeFilePath = 'assets/imagens/logo/' . $newFileName;
        try {
            $stmt = $pdo->prepare('UPDATE company_settings SET company_logo = :logo_path WHERE id = 1');
            $stmt->execute([':logo_path' => $relativeFilePath]);
        } catch (Exception $e) {
            echo json_encode(['success' => true, 'filePath' => $relativeFilePath, 'warning' => 'Logo salva, mas não foi possível atualizar o banco.']);
            exit;
        }
        echo json_encode(['success' => true, 'filePath' => $relativeFilePath]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Falha ao processar a imagem.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Nenhum arquivo foi enviado.']);
}
?>
