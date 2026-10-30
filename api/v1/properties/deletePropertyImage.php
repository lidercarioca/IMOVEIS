<?php
// api/deletePropertyImage.php
header('Content-Type: application/json');
require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/api/check_auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/middleware/api_auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/utils/InputValidator.php';

use App\Service\PropertyService;

checkAuth();
checkApiAuth();

// Initialize tenant isolation
global $pdo;
initializeTenantMiddleware($pdo);
$tenantId = getCurrentTenantId();

try {
    // Validação dos parâmetros com InputValidator
    $id = InputValidator::getInt($_POST['id'] ?? null, 'ID da propriedade', 1);
    $file = InputValidator::getFilename($_POST['file'] ?? '', 'Nome do arquivo', true);

    // Instanciar novo service (src/Service)
    $service = new PropertyService($pdo);

    $userId = getCurrentUserId();
    $isExplicitGlobalAdmin = \TenantContext::isSuperAdmin() || (!empty($_SESSION['is_superadmin']));

    $result = $service->deletePropertyImage($id, $file, $tenantId, $userId, $isExplicitGlobalAdmin);
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Imagem excluída com sucesso.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Falha ao excluir imagem.']);
    }
} catch (PDOException $e) {
    error_log("Erro de banco ao excluir imagem: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao excluir referência da imagem no banco de dados.']);
} catch (Exception $e) {
    error_log("Erro ao excluir imagem: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
