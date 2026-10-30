<?php
/**
 * API Endpoint: Preview de Subdomínio
 * 
 * POST /api/v1/tenants/preview-subdomain
 * 
 * Gera um preview (sem salvar) de qual subdomínio será atribuído
 * ao criar um tenant com um determinado nome.
 * 
 * Útil para real-time preview em formulários.
 * 
 * Request:
 * {
 *   "name": "Imóveis de Luxo",
 *   "exclude_tenant_id": null
 * }
 * 
 * Response:
 * {
 *   "success": true,
 *   "name": "Imóveis de Luxo",
 *   "subdomain": "imoveisdeluxo",
 *   "full_url": "imoveisdeluxo.meusite.com",
 *   "is_available": true,
 *   "collision_warning": null
 * }
 */

// PSR-4 Autoload
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/app/bootstrap.php';

use App\Services\TenantSubdomainManager;
use App\Utils\SubdomainNormalizer;

header('Content-Type: application/json');

// Permitir POST e GET
if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'GET'])) {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

// Parse da requisição
$input = $_SERVER['REQUEST_METHOD'] === 'POST' 
    ? json_decode(file_get_contents('php://input'), true) ?? $_POST
    : $_GET;

$name = trim($input['name'] ?? '');
$excludeTenantId = intval($input['exclude_tenant_id'] ?? 0) ?: null;

if (empty($name)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Nome é obrigatório'
    ]);
    exit;
}

try {
    $manager = new TenantSubdomainManager($GLOBALS['pdo']);
    
    // Normalizar nome
    $baseSubdomain = SubdomainNormalizer::normalize($name);
    
    // Verificar disponibilidade
    $exists = $manager->subdomainExists($baseSubdomain, $excludeTenantId);
    
    $warning = null;
    $finalSubdomain = $baseSubdomain;
    
    if ($exists) {
        // Se existe, gerar um com sufixo
        try {
            $finalSubdomain = $manager->generateUniqueSubdomain($name, $excludeTenantId);
            $warning = "Subdomínio '{$baseSubdomain}' já existe. Será usado: '{$finalSubdomain}'";
        } catch (\Exception $e) {
            $warning = $e->getMessage();
        }
    }
    
    // Construir hostname (assumindo que está em um subdomínio)
    $host = $_SERVER['HTTP_HOST'] ?? 'meusite.com';
    if (strpos($host, '.') === 0) {
        // Se está localhost:8000 ou similar, usar como-é
        $hostParts = explode(':', $host)[0];
    } else {
        $hostParts = $host;
    }
    
    // Tentar extrair domínio base
    $parts = explode('.', $hostParts);
    if (count($parts) > 2) {
        // hostname.meusite.com → meusite.com
        $domain = implode('.', array_slice($parts, -2));
    } else {
        $domain = $hostParts;
    }
    
    $fullUrl = $finalSubdomain . '.' . $domain;
    
    // Resposta
    echo json_encode([
        'success' => true,
        'name' => $name,
        'subdomain' => $finalSubdomain,
        'full_url' => $fullUrl,
        'is_available' => !$exists,
        'collision_warning' => $warning,
        'note' => 'Este é apenas um preview. O subdomínio final será confirmado no momento do cadastro.'
    ]);
    
} catch (\Exception $e) {
    error_log("[API SUBDOMAIN PREVIEW] Erro: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

exit;
