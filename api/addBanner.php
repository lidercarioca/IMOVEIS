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

    // Validação robusta do tipo do arquivo usando getimagesize
    $info = @getimagesize($_FILES['image']['tmp_name']);
    if ($info === false) {
        throw new Exception('Arquivo não é uma imagem válida');
    }
    $mime = $info['mime'] ?? '';
    $mimeMap = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    ];
    if (!isset($mimeMap[$mime])) {
        throw new Exception('Tipo de arquivo não permitido. Use apenas JPG, PNG, GIF ou WEBP');
    }

    // Gera nome único com extensão segura
    $ext = $mimeMap[$mime];
    $fileName = uniqid() . '.' . $ext;
    $targetFile = $targetDir . $fileName;
    $imagePath = 'assets/imagens/banners/' . $fileName;

    // Re-encoda a imagem usando utilitário (remove metadados/payloads)
    require_once __DIR__ . '/../app/utils/image.php';
    $reencoded = reencode_image($_FILES['image']['tmp_name'], $targetFile, $mime);
    if (!$reencoded) {
        error_log("Falha ao re-encodar imagem para: " . $targetFile);
        throw new Exception('Falha ao processar a imagem enviada');
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