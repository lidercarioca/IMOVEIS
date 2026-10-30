<?php

/**
 * PropertiesController - Controlador de Imóveis
 * 
 * Responsável por:
 * - Listar imóveis
 * - Criar imóvel
 * - Atualizar imóvel
 * - Deletar imóvel
 * - Gerenciar imagens
 */

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../helpers/NotificationHelper.php';

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

class PropertiesController extends BaseController
{

    public function listProperties()
    {
        if (!$this->requireAuth()) {
            return $this->error('Não autenticado', 401);
        }

        try {
            global $pdo;

            // ISOLAMENTO: Obter tenant_id do usuário atual
            $tenantId = $this->getTenantId();

            // Delegar busca ao PropertyService (Repository contém SQL idêntico ao legado)
            $service = new \App\Service\PropertyService($pdo);
            $properties = $service->fetchPropertiesForController($tenantId, $this->userId, $this->isAdmin);

            return $this->respond($properties, true, 'Imóveis carregados');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    public function getProperty($id)
    {
        if (!$this->requireAuth()) {
            return $this->error('Não autenticado', 401);
        }

        try {
            global $pdo;

            // ISOLAMENTO: Obter tenant_id do usuário atual
            $tenantId = $this->getTenantId();

            $service = new \App\Service\PropertyService($pdo);
            $property = $service->findPropertyForController(intval($id), $tenantId, $this->userId, $this->isAdmin);

            if (!$property) {
                return $this->error('Imóvel não encontrado ou acesso negado', 404);
            }

            return $this->respond($property, true, 'Imóvel carregado');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    public function createProperty($data)
    {
        if (!$this->requireAuth()) {
            return $this->error('Não autenticado', 401);
        }

        try {
            // Validação básica
            if (empty($data['name']) || empty($data['price'])) {
                return $this->error('Nome e preço são obrigatórios', 400);
            }

            global $pdo;

            // ISOLAMENTO: Obter tenant_id do usuário atual
            $tenantId = $this->getTenantId();

            $limiteCheck = podeCriarImovel($tenantId);
            if (!$limiteCheck['pode']) {
                return $this->error($limiteCheck['motivo'], 403);
            }

            // Inserir via PropertyService (Repository preserva o SQL exato)
            $service = new \App\Service\PropertyService($pdo);
            $propertyId = $service->insertPropertyFromController([
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'price' => floatval($data['price']),
                'description' => $data['description'] ?? '',
                'user_id' => $this->userId
            ]);

            // Enviar notificação da nova propriedade
            $notificationData = [
                'id' => $propertyId,
                'name' => $data['name'],
                'price' => $data['price']
            ];
            NotificationHelper::notifyNewProperty($notificationData);

            return $this->respond(['id' => $propertyId], true, 'Imóvel criado');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    public function updateProperty($id, $data)
    {
        if (!$this->requireAuth()) {
            return $this->error('Não autenticado', 401);
        }

        try {
            // ISOLAMENTO: Obter tenant_id do usuário atual
            $tenantId = $this->getTenantId();

            // Verificar propriedade do imóvel E TENANT (via PropertyService)
            global $pdo;
            $service = new \App\Service\PropertyService($pdo);
            $assignedUser = $service->getAssignedUserId((int)$id, $tenantId);

            if ($assignedUser === null) {
                return $this->error('Imóvel não encontrado ou acesso negado', 404);
            }

            if (!$this->isAdmin && $assignedUser != $this->userId) {
                return $this->error('Sem permissão', 403);
            }

            $changes = [];

            // Status válidos para imóvel
            $validStatuses = ['active', 'pending', 'vendido', 'alugado', 'inactive'];

            if (array_key_exists('title', $data)) {
                $changes['title'] = $data['title'];
            }
            if (array_key_exists('name', $data)) {
                $changes['title'] = $data['name'];
            }
            if (array_key_exists('price', $data)) {
                $changes['price'] = floatval($data['price']);
            }
            if (array_key_exists('description', $data)) {
                $changes['description'] = $data['description'];
            }
            if (array_key_exists('status', $data)) {
                if (!in_array($data['status'], $validStatuses, true)) {
                    return $this->error('Status inválido. Valores permitidos: ' . implode(', ', $validStatuses), 400);
                }
                $changes['status'] = $data['status'];
            }
            if (array_key_exists('area', $data)) {
                $changes['area'] = floatval($data['area']);
            }
            if (array_key_exists('bedrooms', $data)) {
                $changes['bedrooms'] = intval($data['bedrooms']);
            }
            if (array_key_exists('bathrooms', $data)) {
                $changes['bathrooms'] = intval($data['bathrooms']);
            }
            if (array_key_exists('garage', $data)) {
                $changes['garage'] = intval($data['garage']);
            }
            if (array_key_exists('location', $data)) {
                $changes['location'] = $data['location'];
            }
            if (array_key_exists('neighborhood', $data)) {
                $changes['neighborhood'] = $data['neighborhood'];
            }
            if (array_key_exists('city', $data)) {
                $changes['city'] = $data['city'];
            }

            if (empty($changes)) {
                return $this->error('Nenhum campo para atualizar', 400);
            }

            $service->updatePropertyPatch((int)$id, $tenantId, $changes);

            return $this->respond(null, true, 'Imóvel atualizado');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    public function deleteProperty($id)
    {
        if (!$this->requireAuth()) {
            return $this->error('Não autenticado', 401);
        }

        try {
            // ISOLAMENTO: Obter tenant_id do usuário atual
            $tenantId = $this->getTenantId();

            // Verificar propriedade do imóvel E TENANT (via PropertyService)
            global $pdo;
            $service = new \App\Service\PropertyService($pdo);
            $assignedUser = $service->getAssignedUserId((int)$id, $tenantId);

            if ($assignedUser === null) {
                return $this->error('Imóvel não encontrado ou acesso negado', 404);
            }

            if (!$this->isAdmin && $assignedUser != $this->userId) {
                return $this->error('Sem permissão', 403);
            }

            // Deletar apenas propriedade do tenant (isolamento)
            $service->deletePropertyController((int)$id, $tenantId);

            return $this->respond(null, true, 'Imóvel deletado');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
}
