<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once dirname(dirname(dirname(__DIR__))) . '/config/session_manager.php';
require_once dirname(dirname(dirname(__DIR__))) . '/auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';
require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';

checkAuth();
initializeTenantMiddleware($pdo);

$modo = $_GET['modo'] ?? ($_GET['tipo'] ?? 'resumo'); // resumo ou detalhado
$mes = $_GET['mes'] ?? '90dias'; // Padrão alterado para 90dias
$tipoFiltro = $_GET['tipoFiltro'] ?? 'todos'; // filtro por tipo de transação: receita, despesa, comissao
$filters = [
    'modo' => $modo,
    'mes' => $mes,
    'tipo' => $tipoFiltro !== 'todos' ? $tipoFiltro : null,
    'categoria' => $_GET['categoria'] ?? null,
    'status' => $_GET['status'] ?? null,
    'page' => $_GET['page'] ?? null,
    'limit' => $_GET['limit'] ?? null
];

try {
    $authContext = \App\Auth\AuthContext::getInstance();
    $isAdmin = $authContext->isAuthenticated() ? $authContext->isAdmin() : (function_exists('isAdmin') ? isAdmin() : false);
    $userId = $authContext->isAuthenticated() ? $authContext->getUserId() : ($_SESSION['user_id'] ?? null);
    $service = new \App\Services\FinanceService($pdo);
    $response = $service->listTransactions($filters, (int)($userId ?? 0), $isAdmin);

    if (!$response['success']) {
        throw new Exception($response['error']);
    }

    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
