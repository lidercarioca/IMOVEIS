<?php
require_once '../config/database.php';
header('Content-Type: application/json');

try {
    $targetDir = '../assets/imagens/banners/';
    
    // Verifica e cria o diretório se necessário
    if (!file_exists($targetDir)) {
        if (!mkdir($targetDir, 0777, true)) {
            throw new Exception('Não foi possível criar o diretório de upload');
        }
        chmod($targetDir, 0777);
    }

    if (!is_writable($targetDir)) {
        chmod($targetDir, 0777);
        if (!is_writable($targetDir)) {
            throw new Exception('Diretório sem permissão de escrita');
        }
    }

    // Verifica se recebeu o arquivo
    if (!isset($_FILES['image'])) {
        throw new Exception('Nenhuma imagem enviada');
    }

    if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'O arquivo excede o tamanho máximo permitido',
            UPLOAD_ERR_FORM_SIZE => 'O arquivo excede o tamanho máximo do formulário',
            UPLOAD_ERR_PARTIAL => 'O upload foi feito parcialmente',
            UPLOAD_ERR_NO_FILE => 'Nenhum arquivo foi enviado',
            UPLOAD_ERR_NO_TMP_DIR => 'Diretório temporário não encontrado',
            UPLOAD_ERR_CANT_WRITE => 'Falha ao escrever o arquivo',
            UPLOAD_ERR_EXTENSION => 'Upload bloqueado por extensão'
        ];
        throw new Exception($uploadErrors[$_FILES['image']['error']] ?? 'Erro desconhecido no upload');
    }

    // Verifica o tipo do arquivo
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $fileType = $_FILES['image']['type'];
    if (!in_array($fileType, $allowedTypes)) {
        throw new Exception('Tipo de arquivo não permitido. Use apenas JPG, PNG ou GIF');
    }

    // Gera nome único para o arquivo
    $fileName = uniqid() . '_' . basename($_FILES['image']['name']);
    $targetFile = $targetDir . $fileName;
    $imagePath = 'assets/imagens/banners/' . $fileName;

    // Move o arquivo
    if (!move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
        error_log("Falha ao mover arquivo para: " . $targetFile);
        error_log("Arquivo temporário: " . $_FILES['image']['tmp_name']);
        error_log("Permissões do diretório: " . substr(sprintf('%o', fileperms($targetDir)), -4));
        throw new Exception('Falha ao mover o arquivo para o servidor');
    }

    // Prepara os dados
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $order = $_POST['order'] ?? 0;

    // Insere no banco
    $sql = "INSERT INTO banners (image_path, title, description, order_position) VALUES (?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    
    if (!$stmt->execute([$imagePath, $title, $description, $order])) {
        // Remove o arquivo se falhar no banco
        if (file_exists($targetFile)) {
            unlink($targetFile);
        }
        throw new Exception('Erro ao inserir no banco de dados');
    }

    echo json_encode([
        'success' => true,
        'image_url' => '/' . $imagePath
    ]);

} catch (Exception $e) {
    error_log("Erro no addBanner.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}