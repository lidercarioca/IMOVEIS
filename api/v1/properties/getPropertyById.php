<?php
header('Content-Type: application/json');
require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/session_manager.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';

use App\Service\PropertyService;

try {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Autenticação obrigatória']);
        exit;
    }

    // Validação do ID
    $id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : false;
    if ($id === false) {
        throw new Exception('ID inválido');
    }

    // O tenant é exclusivamente resolvido a partir do contexto autenticado.
    // Nunca aceitar tenant_id informado na requisição.
    initializeTenantMiddleware($pdo);
    $tenantId = (int) getCurrentTenantId();
    if ($tenantId <= 0) {
        throw new Exception('Contexto de tenant inválido');
    }

    $service = new PropertyService($pdo);
    $property = $service->getPropertyByIdForApi($id, [
        'tenantId' => $tenantId,
        'isAdmin' => false,
    ]);

    if ($property === null) {
        throw new Exception('Imóvel não encontrado');
    }

    echo json_encode([
        'success' => true,
        'data' => $property
    ]);

} catch (PDOException $e) {
    error_log('Erro de banco ao buscar imóvel: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar detalhes do imóvel'
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

