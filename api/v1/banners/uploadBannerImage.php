<?php
require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/session_manager.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/utils/InputValidator.php';

checkAuth();

header('Content-Type: application/json; charset=utf-8');

try {
    $targetDir = dirname(dirname(dirname(__DIR__))) . '/assets/imagens/banners/';

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

    if (!isset($_FILES['banner'])) {
        throw new Exception('Nenhuma imagem enviada');
    }

    if ($_FILES['banner']['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'O arquivo excede o tamanho máximo permitido',
            UPLOAD_ERR_FORM_SIZE => 'O arquivo excede o tamanho máximo do formulário',
            UPLOAD_ERR_PARTIAL => 'O upload foi feito parcialmente',
            UPLOAD_ERR_NO_FILE => 'Nenhum arquivo foi enviado',
            UPLOAD_ERR_NO_TMP_DIR => 'Diretório temporário não encontrado',
            UPLOAD_ERR_CANT_WRITE => 'Falha ao escrever o arquivo',
            UPLOAD_ERR_EXTENSION => 'Upload bloqueado por extensão'
        ];
        throw new Exception($uploadErrors[$_FILES['banner']['error']] ?? 'Erro desconhecido no upload');
    }

    \App\Service\BannerService::validateUploadSize($_FILES['banner'], \App\Service\BannerService::MAX_UPLOAD_SIZE_BYTES);

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $_FILES['banner']['tmp_name']);
    finfo_close($finfo);

    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mimeType, $allowedTypes)) {
        throw new Exception('Tipo de arquivo não permitido. Use apenas JPG, PNG, GIF ou WebP');
    }

    if (!getimagesize($_FILES['banner']['tmp_name'])) {
        throw new Exception('Arquivo não é uma imagem válida');
    }

    $fileName = uniqid('banner_') . '_' . basename($_FILES['banner']['name']);
    $targetFile = $targetDir . $fileName;
    $imagePath = 'assets/imagens/banners/' . $fileName;

    if (!move_uploaded_file($_FILES['banner']['tmp_name'], $targetFile)) {
        error_log("Falha ao mover arquivo para: " . $targetFile);
        error_log("Arquivo temporário: " . $_FILES['banner']['tmp_name']);
        error_log("Permissões do diretório: " . substr(sprintf('%o', fileperms($targetDir)), -4));
        throw new Exception('Falha ao mover o arquivo para o servidor');
    }

    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $tenantId = getRequestTenantId();

    $bannerService = new \App\Service\BannerService($pdo);
    try {
        $record = $bannerService->createBanner($tenantId, [
            'image_path' => $imagePath,
            'title' => $title,
            'description' => $description,
        ]);
    } catch (Exception $e) {
        if (file_exists($targetFile)) {
            unlink($targetFile);
        }
        throw $e;
    }

    echo json_encode([
        'success' => true,
        'filePath' => '/' . $record['image_path'],
        'image_url' => '/' . $record['image_path'],
        'id' => $record['id'] ?? null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Exception $e) {
    error_log("Erro no uploadBannerImage.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
