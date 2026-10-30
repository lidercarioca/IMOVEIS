<?php
require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/session_manager.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/utils/InputValidator.php';

checkAuth();

header('Content-Type: application/json');

try {
    $targetDir = '../assets/imagens/banners/';

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

    \App\Service\BannerService::validateUploadSize($_FILES['image'], \App\Service\BannerService::MAX_UPLOAD_SIZE_BYTES);

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $_FILES['image']['tmp_name']);
    finfo_close($finfo);

    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($mimeType, $allowedTypes)) {
        throw new Exception('Tipo de arquivo não permitido. Use apenas JPG, PNG ou GIF');
    }

    if (!getimagesize($_FILES['image']['tmp_name'])) {
        throw new Exception('Arquivo não é uma imagem válida');
    }

    $fileName = uniqid() . '_' . basename($_FILES['image']['name']);
    $targetFile = $targetDir . $fileName;
    $imagePath = 'assets/imagens/banners/' . $fileName;

    if (!move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
        error_log("Falha ao mover arquivo para: " . $targetFile);
        error_log("Arquivo temporário: " . $_FILES['image']['tmp_name']);
        error_log("Permissões do diretório: " . substr(sprintf('%o', fileperms($targetDir)), -4));
        throw new Exception('Falha ao mover o arquivo para o servidor');
    }

    $title = InputValidator::getString($_POST['title'] ?? '', 'Título', true, 500);
    $description = InputValidator::getText($_POST['description'] ?? '', 'Descrição', false, 2000);
    $order = isset($_POST['order']) && $_POST['order'] !== '' ? InputValidator::getInt($_POST['order'], 'Posição', 0, 1000) : 0;
    $tenantId = getRequestTenantId();

    $bannerService = new \App\Service\BannerService($pdo);
    try {
        $record = $bannerService->createBanner($tenantId, [
            'image_path' => $imagePath,
            'title' => $title,
            'description' => $description,
            'order_position' => $order,
        ]);
    } catch (Exception $e) {
        if (file_exists($targetFile)) {
            unlink($targetFile);
        }
        throw $e;
    }

    echo json_encode([
        'success' => true,
        'image_url' => '/' . $record['image_path'],
        'id' => $record['id'] ?? null,
    ]);
} catch (Exception $e) {
    error_log("Erro no addBanner.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
