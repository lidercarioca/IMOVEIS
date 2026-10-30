<?php

/**
 * API Pública - Propriedades
 * Endpoint público para listar e gerenciar imóveis
 */

require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Repository/ApiKeyRepository.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Service/ApiKeyService.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Service/PropertyService.php';

function podeCriarImovel($tenantId)
{
    global $pdo;

    try {
        $tenantRepo = new \App\Infrastructure\Billing\TenantRepository($pdo);
        $tenant = $tenantRepo->getTenantData((int)$tenantId);
        $status = $tenantRepo->checkTenantActiveStatus((int)$tenantId);

        if (!$tenant || !$status['ativo']) {
            return ['pode' => false, 'motivo' => 'Tenant não está ativo: ' . ($status['motivo'] ?? 'Status inválido'), 'limite' => null, 'total' => 0];
        }

        $plan = $tenantRepo->getPlanByCode((string)($tenant['plano'] ?? ''));
        $limiteImoveis = $plan && isset($plan['limite_imoveis']) ? (int)$plan['limite_imoveis'] : null;
        $totalImoveis = (new \App\Service\PropertyService($pdo))->getPropertyCountForTenant((int)$tenantId);

        if ($limiteImoveis !== null && $totalImoveis >= $limiteImoveis) {
            return ['pode' => false, 'motivo' => "Limite de imóveis do plano atingido ({$totalImoveis}/{$limiteImoveis}). Atualize o plano para cadastrar mais imóveis.", 'limite' => $limiteImoveis, 'total' => $totalImoveis];
        }

        return ['pode' => true, 'motivo' => 'OK', 'limite' => $limiteImoveis, 'total' => $totalImoveis];
    } catch (Exception $e) {
        error_log('Erro ao validar limite de imóveis: ' . $e->getMessage());
        return ['pode' => false, 'motivo' => 'Erro ao validar limite de imóveis', 'limite' => null, 'total' => 0];
    }
}

header('Content-Type: application/json');

// Validar API Key
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? null;
if (!$apiKey) {
    http_response_code(401);
    echo json_encode(['error' => 'API Key não fornecida', 'message' => 'Header X-API-Key é obrigatório']);
    exit;
}

// Validar API Key via service/repositório
$apiKeyService = new \App\Service\ApiKeyService($GLOBALS['pdo']);
$apiKeyRecord = $apiKeyService->validateKey($apiKey);

if (!$apiKeyRecord) {
    http_response_code(401);
    echo json_encode(['error' => 'API Key inválida', 'message' => 'A API Key fornecida não é válida ou expirou']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$pathInfo = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
$resourceId = end($pathInfo);

$tenantId = isset($apiKeyRecord['tenant_id']) ? intval($apiKeyRecord['tenant_id']) : 0;
if ($tenantId <= 0) {
    http_response_code(403);
    echo json_encode(['error' => 'Tenant não definido para esta chave de API']);
    exit;
}

try {
    switch ($method) {
        case 'GET':
            if (is_numeric($resourceId)) {
                // GET /api/v1/public/properties/{id}
                handleGetProperty($resourceId, $tenantId);
            } else {
                // GET /api/v1/public/properties?page=1&limit=20
                handleListProperties($tenantId);
            }
            break;

        case 'POST':
            // POST /api/v1/public/properties
            handleCreateProperty($tenantId);
            break;

        case 'PUT':
            // PUT /api/v1/public/properties/{id}
            if (is_numeric($resourceId)) {
                handleUpdateProperty($resourceId, $tenantId);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'ID do imóvel inválido']);
            }
            break;

        case 'DELETE':
            // DELETE /api/v1/public/properties/{id}
            if (is_numeric($resourceId)) {
                handleDeleteProperty($resourceId, $tenantId);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'ID do imóvel inválido']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Listar imóveis com paginação - COM ISOLAMENTO DE TENANT
 */
function handleListProperties(int $tenantId)
{
    global $pdo;

    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    // Por padrão, não expor imóveis pendentes em listagens públicas
    $status = trim((string)($_GET['status'] ?? '')) !== '' ? $_GET['status'] : 'active';
    $type = $_GET['type'] ?? null;  // type, não tipo

    // Limitar para máximo 100 por página
    $limit = min($limit, 100);

    $offset = ($page - 1) * $limit;

    $service = new \App\Service\PropertyService($GLOBALS['pdo']);

    $total = $service->countPublicProperties($tenantId, $status, $type);
    $properties = $service->getPublicProperties($tenantId, $status, $type, $limit, $offset);

    echo json_encode([
        'success' => true,
        'data' => $properties,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

/**
 * Obter detalhes de um imóvel - COM ISOLAMENTO DE TENANT
 */
function handleGetProperty($id, int $tenantId)
{
    global $pdo;

    $service = new \App\Service\PropertyService($GLOBALS['pdo']);
    $property = $service->findPropertyForController($id, $tenantId, null, false);

    if (!$property) {
        http_response_code(404);
        echo json_encode(['error' => 'Imóvel não encontrado ou acesso negado']);
        return;
    }

    echo json_encode([
        'success' => true,
        'data' => $property
    ]);
}

/**
 * Criar novo imóvel - COM ISOLAMENTO DE TENANT
 */
function handleCreateProperty(int $tenantId)
{
    global $pdo;

    $data = json_decode(file_get_contents('php://input'), true);

    // Validar dados obrigatórios
    $required = ['title', 'type', 'price'];  // Usar nomes corretos
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Campo obrigatório faltando: $field"]);
            return;
        }
    }

    try {
        // VERIFICAÇÃO DO LIMITE DE IMÓVEIS
        $limiteCheck = podeCriarImovel($tenantId);
        if (!$limiteCheck['pode']) {
            http_response_code(403);
            echo json_encode(['error' => $limiteCheck['motivo']]);
            return;
        }

        $service = new \App\Service\PropertyService($GLOBALS['pdo']);

        $propertyId = $service->insertPublicProperty([
            'tenant_id' => $tenantId,
            'title' => $data['title'],
            'type' => $data['type'],
            'price' => floatval($data['price']),
            'description' => $data['description'] ?? null,
            'bedrooms' => intval($data['bedrooms'] ?? 0),
            'bathrooms' => intval($data['bathrooms'] ?? 0),
            'area' => floatval($data['area'] ?? 0),
            'status' => $data['status'] ?? 'active'
        ]);

        if ($propertyId) {

            // Disparar webhook
            triggerWebhook('property.created', [
                'property_id' => $propertyId,
                'titulo' => $data['titulo'],
                'tipo' => $data['tipo']
            ]);

            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Imóvel criado com sucesso',
                'property_id' => $propertyId
            ]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Erro ao criar imóvel']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Atualizar imóvel - COM ISOLAMENTO DE TENANT
 */
function handleUpdateProperty($id, int $tenantId)
{
    global $pdo;

    $data = json_decode(file_get_contents('php://input'), true);

    $service = new \App\Service\PropertyService($GLOBALS['pdo']);
    if (!$service->findPropertyForController($id, $tenantId, null, false)) {
        http_response_code(404);
        echo json_encode(['error' => 'Imóvel não encontrado ou acesso negado']);
        return;
    }

    try {
        $changes = [];

        // Usar nomes de campos corretos
        $allowedFields = ['title', 'type', 'price', 'description', 'bedrooms', 'bathrooms', 'area', 'status', 'neighborhood', 'city', 'state'];

        $fieldMapping = [
            'titulo' => 'title',
            'tipo' => 'type',
            'endereco' => 'price',
            'preco' => 'price',
            'descricao' => 'description',
            'dormitorios' => 'bedrooms',
            'banheiros' => 'bathrooms'
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $changes[$field] = $data[$field];
            }
        }

        // Também suportar nomes antigos (português) e traduzir
        foreach ($fieldMapping as $old => $new) {
            if (isset($data[$old]) && !isset($data[$new])) {
                $changes[$new] = $data[$old];
            }
        }

        if (empty($changes)) {
            http_response_code(400);
            echo json_encode(['error' => 'Nenhum campo para atualizar']);
            return;
        }

        $service = new \App\Service\PropertyService($GLOBALS['pdo']);
        $service->updatePropertyPatch((int)$id, (int)$tenantId, $changes);

        // Disparar webhook
        triggerWebhook('property.updated', [
            'property_id' => $id,
            'changes' => $data
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Imóvel atualizado com sucesso'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Deletar imóvel - COM ISOLAMENTO DE TENANT
 */
function handleDeleteProperty($id, int $tenantId)
{
    global $pdo;

    try {
        $service = new \App\Service\PropertyService($GLOBALS['pdo']);
        if (!$service->findPropertyForController($id, $tenantId, null, false)) {
            http_response_code(404);
            echo json_encode(['error' => 'Imóvel não encontrado ou acesso negado']);
            return;
        }

        $service->deletePropertyController($id, $tenantId);

        http_response_code(204);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Disparar webhook para eventos críticos
 */
function triggerWebhook($event, $data)
{
    try {
        $webhookService = new \App\Service\WebhookService(
            new \App\Infrastructure\Webhook\WebhookRepository($GLOBALS['pdo']),
            $GLOBALS['pdo']
        );
        $webhookUrls = $webhookService->getActiveUrlsForEvent((string) $event);

        foreach ($webhookUrls as $webhookUrl) {
            // Disparar webhook de forma assincronizada (fila)
            $payload = json_encode([
                'event' => $event,
                'timestamp' => date('c'),
                'data' => $data
            ]);

            // Aqui você poderia usar uma fila de jobs (RabbitMQ, Redis, etc)
            // Por enquanto, fazer requisição direta
            $ch = curl_init($webhookUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            curl_exec($ch);
            curl_close($ch);
        }
    } catch (Exception $e) {
        // Log silenciosamente para não impedir a operação principal
        error_log('Erro ao disparar webhook: ' . $e->getMessage());
    }
}
