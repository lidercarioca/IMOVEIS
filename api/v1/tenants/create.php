<?php

/**
 * API Endpoint: Criar novo Tenant com Subdomínio Automático
 * 
 * POST /api/v1/tenants/create
 * 
 * Request:
 * {
 *   "name": "Nova Empresa",
 *   "description": "Descrição opcional",
 *   "max_users": 10
 * }
 * 
 * Response (sucesso):
 * {
 *   "success": true,
 *   "tenant_id": 5,
 *   "name": "Nova Empresa",
 *   "subdomain": "nova-empresa",
 *   "access_url": "https://nova-empresa.meusite.com",
 *   "created_at": "2026-03-30T10:30:00"
 * }
 * 
 * Response (erro):
 * {
 *   "success": false,
 *   "error": "Nome da empresa é obrigatório"
 * }
 */

// PSR-4 Autoload
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/app/bootstrap.php';

use App\Application\Tenant\TenantRegistrationService;

// Middleware de autenticação (apenas admin pode criar tenants)
// Aqui você deve verificar se o usuário é superadmin
// Por exemplo:
// $middleware = new TenantIsolationMiddleware();
// $middleware->verifySuperAdmin();

header('Content-Type: application/json');

// Permitir apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

// Parse do corpo da requisição
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// Validação
$errors = [];

if (empty($input['name'])) {
    $errors[] = 'Nome da empresa é obrigatório';
}

if (strlen($input['name'] ?? '') > 100) {
    $errors[] = 'Nome não pode exceder 100 caracteres';
}

if (!empty($input['max_users']) && !is_numeric($input['max_users'])) {
    $errors[] = 'Max users deve ser um número';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'errors' => $errors
    ]);
    exit;
}

try {
    $service = new TenantRegistrationService($GLOBALS['pdo']);
    $result = $service->createTenant($input);

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'tenant_id' => $result['tenant_id'],
        'name' => $result['name'],
        'subdomain' => $result['subdomain'],
        'description' => $result['description'],
        'max_users' => $result['max_users'],
        'access_url' => $result['access_url'],
        'created_at' => $result['created_at']
    ]);
} catch (\Exception $e) {
    error_log("[API TENANT CREATE] Erro: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

exit;
