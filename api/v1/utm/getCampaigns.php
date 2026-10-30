<?php
header("Content-Type: application/json");

require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/session_manager.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';

use App\Service\UtmService;

if (!isLoggedIn() || !isAdmin()) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Acesso não autorizado"]);
    exit;
}

try {
    initializeTenantMiddleware($pdo);
    $tenantId = getCurrentTenantId();
    $service = new \App\Service\UtmService($pdo);
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        http_response_code(200);
        echo json_encode([
            "success" => true,
            "data" => $service->listCampaigns($tenantId)
        ]);
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $result = $service->createCampaign($tenantId, (int) ($_SESSION['user_id'] ?? 0), $data);

        http_response_code(201);
        echo json_encode([
            "success" => true,
            "message" => "Campanha criada com sucesso",
            "data" => $result
        ]);
    } elseif ($method === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $service->updateCampaign($tenantId, $data);

        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Campanha atualizada com sucesso"
        ]);
    } elseif ($method === 'DELETE') {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $id = $data['id'] ?? null;
        $service->deleteCampaign($tenantId, (int) $id);

        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Campanha deletada com sucesso"
        ]);
    } else {
        throw new Exception("Método HTTP não suportado");
    }
} catch (Exception $e) {
    error_log("Erro em getCampaigns: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
