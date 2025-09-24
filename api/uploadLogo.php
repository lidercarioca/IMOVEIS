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

    // Valida o tipo de arquivo (MIME type)
    $allowedTypes = ['image/png'];
    if (!in_array($file['type'], $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'Tipo de arquivo não permitido. Apenas arquivos PNG são aceitos.']);
        exit;
    }
    // Garante extensão .png
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($fileExtension !== 'png') {
        echo json_encode(['success' => false, 'message' => 'Apenas arquivos com extensão .png são aceitos.']);
        exit;
    }

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

    // Gera um nome de arquivo único para evitar sobreposições
    $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newFileName = 'logo.' . $fileExtension; // Salva sempre como 'logo' para ser fácil de encontrar
    $uploadPath = $uploadDir . $newFileName;

    // Move o arquivo para o diretório de destino
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        // Atualiza o caminho no banco de dados
        $relativeFilePath = 'assets/imagens/logo/' . $newFileName;
        try {
            $stmt = $pdo->prepare('UPDATE company_settings SET company_logo = :logo_path WHERE id = 1');
            $stmt->execute([':logo_path' => $relativeFilePath]);
        } catch (Exception $e) {
            // Se falhar, ainda retorna sucesso do upload, mas avisa
            echo json_encode(['success' => true, 'filePath' => $relativeFilePath, 'warning' => 'Logo salva, mas não foi possível atualizar o banco.']);
            exit;
        }
        echo json_encode(['success' => true, 'filePath' => $relativeFilePath]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Falha ao mover o arquivo para o destino. Verifique as permissões da pasta.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Nenhum arquivo foi enviado.']);
}
?>
