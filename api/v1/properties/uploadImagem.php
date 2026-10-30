<?php
require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';
require_once dirname(dirname(dirname(__DIR__))) . '/api/check_auth.php';
checkApiAuth();

require_once dirname(dirname(dirname(__DIR__))) . '/auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/middleware/api_auth.php';

use App\Service\PropertyService;

checkAuth();
checkApiAuth();

// Initialize tenant isolation
global $pdo;
initializeTenantMiddleware($pdo);
$tenantId = (int) getCurrentTenantId();
if ($tenantId <= 0) {
    throw new Exception('Contexto de tenant inválido para upload de imagem');
}
header('Content-Type: application/json');

try {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método inválido');
    }

    if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
        throw new Exception('ID do imóvel inválido');
    }

    if (!isset($_FILES['imagens'])) {
        throw new Exception('Nenhuma imagem enviada');
    }

    $id = (int) $_POST['id'];

    $service = new PropertyService($pdo);
    $property = $service->getProperty($id, [
        'tenantId' => $tenantId,
        'isAdmin' => false,
    ]);

    if ($property === null) {
        throw new Exception('Propriedade não encontrada ou sem permissão');
    }

    // ===============================
    // PASTA DO IMÓVEL
    // ===============================
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . "/assets/imagens/$id/";
    $uploadUrl = "/assets/imagens/$id/";

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // ===============================
    // TIPOS PERMITIDOS
    // ===============================
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);

    // ===============================
    // HASHES EXISTENTES (ANTI DUPLICATA)
    // ===============================
    $hashesExistentes = [];
    foreach ($property['images'] as $imgUrl) {
        $path = $_SERVER['DOCUMENT_ROOT'] . $imgUrl;
        if (file_exists($path)) {
            $hashesExistentes[] = md5_file($path);
        }
    }

    // ===============================
    // PROCESSA UPLOAD
    // ===============================
    $imagensSalvas = [];

    foreach ($_FILES['imagens']['tmp_name'] as $i => $tmpName) {

        if ($_FILES['imagens']['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }

        $mime = finfo_file($finfo, $tmpName);
        if (!isset($allowedTypes[$mime])) {
            continue;
        }

        $hashNova = md5_file($tmpName);
        if (in_array($hashNova, $hashesExistentes, true)) {
            continue;
        }

        $filename = uniqid('img_', true) . '.' . $allowedTypes[$mime];
        $destPath = $uploadDir . $filename;
        $imageUrl = $uploadUrl . $filename;

        if (move_uploaded_file($tmpName, $destPath)) {
            $imagensSalvas[] = $imageUrl;
        }
    }

    if (!empty($imagensSalvas)) {
        $service->savePropertyImages($id, $imagensSalvas);
    }

    // finfo é fechado automaticamente pelo garbage collector

    echo json_encode([
        'success' => true,
        'images' => $imagensSalvas
    ]);
    exit;
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit;
}
