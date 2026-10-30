<?php
header('Content-Type: application/json');
require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/session_manager.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/utils/error_logger.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';

use App\Infrastructure\User\UserRepository;
use App\Service\PropertyService;

try {
    // Verificar autenticação
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Não autenticado']);
        exit;
    }

    // Inicializar isolamento de tenant
    initializeTenantMiddleware($pdo);
    $tenantId = getCurrentTenantId();
    $userId = $_SESSION['user_id'];

    // Verificar role do usuário
    $userRepository = new UserRepository($pdo);
    $userRole = $userRepository->getRole($userId, $tenantId);

    if ($userRole === null) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Usuário não encontrado']);
        exit;
    }

    $userRole = strtolower(trim($userRole));
    $isAdmin = ($userRole === 'admin' || $userRole === 'administrator' || $userRole === 'superadmin');

    $service = new PropertyService($pdo);
    $properties = $service->getPropertiesSimple([
        'tenantId' => $tenantId,
        'userId' => $userId,
        'isAdmin' => $isAdmin,
    ]);

    echo json_encode([
        'success' => true,
        'data' => $properties
    ]);
} catch (Throwable $e) {
    log_exception($e, ['endpoint' => 'getPropertiesSimple.php']);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => getenv('ENVIRONMENT') === 'development' ? $e->getMessage() : 'Erro ao carregar propriedades'
    ]);
}
