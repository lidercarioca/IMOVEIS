<?php
require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';

checkAuth();

header('Content-Type: application/json');

if (!isset($_POST['id'])) {
    echo json_encode(['success' => false, 'error' => 'ID não fornecido']);
    exit;
}

$id = intval($_POST['id']);
$tenantId = getRequestTenantId();

try {
    $fields = [];

    if (array_key_exists('title', $_POST)) {
        $fields['title'] = $_POST['title'];
    }

    if (array_key_exists('description', $_POST)) {
        $fields['description'] = $_POST['description'];
    }

    if (empty($fields)) {
        echo json_encode(['success' => false, 'error' => 'Nenhum dado para atualizar']);
        exit;
    }

    $bannerService = new \App\Service\BannerService($pdo);
    $bannerService->updateBanner($tenantId, $id, $fields);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erro ao atualizar banner: ' . $e->getMessage()]);
}
