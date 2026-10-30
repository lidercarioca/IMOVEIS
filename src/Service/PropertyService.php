<?php

namespace App\Service;

use PDO;
use App\Auth\AuthorizationService;
use App\Auth\Permissions;
use App\Repository\PropertyRepository;
use App\Domain\DTO\PropertyDTO;
use App\Cache\CacheInterface;
use App\Cache\CacheFactory;
use App\Database\QueryBuilder;
use App\Logging\LoggerInterface;
use App\Logging\LoggerFactory;
use App\Audit\AuditLogger;
use App\Observability\RequestTracker;
use App\Models\CommissionModel;
use App\Infrastructure\Finance\FinancialTransactionRepository;

/**
 * PropertyService - Camada de Serviço para Propriedades
 *
 * Responsável por:
 * - Orquestrar lógica de negócio de propriedades
 * - Construir filtros e isolamento de tenant
 * - Delegar acesso a BD para Repository
 * - Processar e formatar dados
 * - Cache transparente (PHASE 10)
 *
 * ARQUITETURA:
 * - Método público: getProperties() → interface do serviço
 * - Método privado: getPropertiesInternal() → orquestração
 * - Cache layer: verifica antes de Repository
 * - Repository: busca dados brutos do banco
 * - Helpers: formatação de dados
 */
class PropertyService
{
    private PDO $pdo;
    private PropertyRepository $repository;
    private CacheInterface $cache;
    private LoggerInterface $logger;
    private AuditLogger $auditLogger;
    private AuthorizationService $authorizationService;

    /**
     * Construtor
     *
     * @param PDO $pdo Conexão com banco de dados
     * @param CacheInterface|null $cache Driver de cache (use CacheFactory se null)
     * @param LoggerInterface|null $logger Logger (use LoggerFactory se null)
     * @param AuditLogger|null $auditLogger Audit logger (criado se null)
     * @param AuthorizationService|null $authorizationService Serviço de autorização
     */
    public function __construct(
        PDO $pdo,
        ?CacheInterface $cache = null,
        ?LoggerInterface $logger = null,
        ?AuditLogger $auditLogger = null,
        ?AuthorizationService $authorizationService = null
    ) {
        $this->pdo = $pdo;
        $this->repository = new PropertyRepository($pdo);
        $this->cache = $cache ?? CacheFactory::make();
        $this->logger = $logger ?? LoggerFactory::make();
        $this->auditLogger = $auditLogger ?? new AuditLogger($this->logger);
        $this->authorizationService = $authorizationService ?? new AuthorizationService();
    }

    /**
     * Obtém lista de propriedades com filtros aplicados
     *
     * Interface pública do service. Delega para método privado
     * que encapsula toda a lógica de dados.
     *
     * @param array $options Opções de filtro:
     *        - 'isPanel' (bool): se é requisição do painel
     *        - 'tenantId' (int): ID do tenant
     *        - 'userId' (int|null): ID do usuário
     *        - 'isAdmin' (bool): se usuário é admin
     *
     * @return array Lista formatada de propriedades
     * @throws \InvalidArgumentException Se opções inválidas
     * @throws \Exception Se erro na lógica de dados
     */
    public function getProperties(array $options): array
    {
        // ============================================================
        // VALIDAÇÃO DE ENTRADA (Type Safety)
        // ============================================================

        // Validar que opções obrigatórias existem
        if (!isset($options['isPanel'])) {
            $this->logger->warning("getProperties: opção 'isPanel' ausente", [
                'request_id' => RequestTracker::getId(),
                'options' => $options,
            ]);
            throw new \InvalidArgumentException("Opção 'isPanel' é obrigatória");
        }

        if (!isset($options['tenantId'])) {
            $this->logger->warning("getProperties: opção 'tenantId' ausente", [
                'request_id' => RequestTracker::getId(),
                'options' => $options,
            ]);
            throw new \InvalidArgumentException("Opção 'tenantId' é obrigatória");
        }

        // Validar tipos e valores
        if (!is_bool($options['isPanel'])) {
            $this->logger->warning("getProperties: 'isPanel' não é boolean", [
                'request_id' => RequestTracker::getId(),
                'value' => $options['isPanel'],
            ]);
            throw new \InvalidArgumentException("'isPanel' deve ser boolean");
        }

        $tenantId = (int)($options['tenantId'] ?? 0);
        if ($tenantId <= 0) {
            $this->logger->warning("getProperties: 'tenantId' inválido", [
                'request_id' => RequestTracker::getId(),
                'value' => $tenantId,
            ]);
            throw new \InvalidArgumentException("'tenantId' deve ser um inteiro positivo");
        }

        if (isset($options['userId']) && $options['userId'] !== null) {
            $userId = (int)$options['userId'];
            if ($userId < 0) {
                $this->logger->warning("getProperties: 'userId' negativo", [
                    'request_id' => RequestTracker::getId(),
                    'value' => $userId,
                ]);
                throw new \InvalidArgumentException("'userId' não pode ser negativo");
            }
        }

        if (!is_bool($options['isAdmin'])) {
            $this->logger->warning("getProperties: 'isAdmin' não é boolean", [
                'request_id' => RequestTracker::getId(),
                'value' => $options['isAdmin'],
            ]);
            throw new \InvalidArgumentException("'isAdmin' deve ser boolean");
        }

        // Delegar para método privado (lógica concentrada)
        return $this->getPropertiesInternal($options);
    }

    /**
     * Lógica interna de busca de propriedades
     *
     * Encapsula:
     * - Construção de filtros baseado no tipo de requisição
     * - Delegação ao Repository para acesso a BD
     * - Processamento e formatação
     * - Agrupamento de imagens
     *
     * IMPORTANTE: Este método é privado pois contém lógica interna.
     * Use getProperties() para acessar de fora da classe.
     *
     * @param array $options Opções validadas
     * @return array Lista de propriedades formatadas
     * @throws Exception Se houver erro nos dados
     */
    private function getPropertiesInternal(array $options): array
    {
        // ============================================================
        // 0. EXTRAIR OPÇÕES (preparar para cache)
        // ============================================================

        $isPanel = $options['isPanel'] ?? false;
        $tenantId = (int)($options['tenantId'] ?? 0);
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException("'tenantId' deve ser um inteiro positivo");
        }

        $userId = isset($options['userId']) ? (int)$options['userId'] : null;
        $isAdmin = $options['isAdmin'] ?? false;

        // ============================================================
        // 2. VERIFICAR CACHE (PHASE 10)
        // ============================================================

        $cacheKey = $this->generateCacheKey($isPanel, $tenantId, $userId, $isAdmin);
        $cachedResult = $this->cache->get($cacheKey);

        if ($cachedResult !== null) {
            $this->logger->debug("Cache HIT", [
                'request_id' => RequestTracker::getId(),
                'cache_key' => $cacheKey,
                'count' => count($cachedResult),
            ]);
            return $cachedResult;
        }

        $this->logger->debug("Cache MISS", [
            'request_id' => RequestTracker::getId(),
            'cache_key' => $cacheKey,
        ]);

        // ============================================================
        // 3. CONSTRUIR FILTROS (lógica de negócio — fica no Service)
        // ============================================================

        $whereConditions = [];
        $params = [];

        if ($isPanel) {
            // PAINEL (autenticada)
            error_log("🔍 PropertyService - Panel Request | User: " . ($userId ?? 'unknown') .
                " | Tenant: " . $tenantId . " | Admin: " . ($isAdmin ? 'yes' : 'no'));

            // Filtro obrigatório: isolamento por tenant
            $whereConditions[] = "p.tenant_id = ?";
            $params[] = $tenantId;

            // Filtro: usuários não-admin veem apenas seus imóveis
            if (!$isAdmin && $userId) {
                $whereConditions[] = "p.assigned_user_id = ?";
                $params[] = $userId;
                // Usuários comuns só podem ver imóveis ativos (não ver pending)
                $whereConditions[] = "p.status = 'active'";
            } elseif (!$isAdmin && !$userId) {
                // Segurança: não-admin sem user_id é inválido
                throw new \Exception("Usuário não identificado");
            }
        } else {
            // SITE (pública, não autenticada)
            // Mostrar apenas imóveis com status específico
            $whereConditions[] = "p.status IN ('active', 'vendido', 'alugado', 'sold', 'rented')";
            $whereConditions[] = "p.tenant_id = ?";
            $params[] = $tenantId;

            error_log("[PropertyService] Public Request | Tenant: " . $tenantId);
        }

        // Montar cláusula WHERE
        $whereClause = !empty($whereConditions)
            ? "WHERE " . implode(" AND ", $whereConditions) . " "
            : "";

        // ============================================================
        // 4. BUSCAR DADOS (delegado ao Repository)
        // ============================================================

        $rawRows = $this->repository->getProperties($whereClause, $params);

        // ============================================================
        // 3. PROCESSAR RESULTADOS (lógica de negócio — fica no Service)
        // ============================================================

        $propertyMap = [];
        foreach ($rawRows as $row) {
            // Agrupa múltiplas imagens da mesma propriedade em um único objeto
            // Função pura definida em property_helpers.php
            \groupPropertiesWithImages($propertyMap, $row);
        }

        // ============================================================
        // 4. CONVERTER PARA DTO (Type Safety — camada interna)
        // ============================================================
        // 
        // A conversão para DTO é interna e não exposta pela API.
        // Garante que todos os dados retornados são tipados e validados.
        // Depois convertemos de volta para array para manter compatibilidade.
        //
        $dtos = [];
        foreach ($propertyMap as $propertyData) {
            try {
                $dto = PropertyDTO::fromArray($propertyData);
                $dtos[] = $dto->toArray(); // De volta para array para compatibilidade
            } catch (\InvalidArgumentException $e) {
                // Log de erro se houver problema na conversão
                error_log("Erro ao converter propriedade para DTO: " . $e->getMessage());
                // Continuar com dados originais se falhar
                $dtos[] = $propertyData;
            }
        }

        // ============================================================
        // 5. SALVAR EM CACHE (PHASE 10)
        // ============================================================

        $ttl = $isPanel ? 300 : 3600; // Panel: 5 min, Public: 1 hora
        $this->cache->set($cacheKey, $dtos, $ttl);

        $this->logger->info("Properties retrieved and cached", [
            'request_id' => RequestTracker::getId(),
            'cache_key' => $cacheKey,
            'ttl' => $ttl,
            'count' => count($dtos),
            'is_panel' => $isPanel,
            'tenant_id' => $tenantId,
        ]);

        return $dtos;
    }

    /**
     * Gera chave de cache baseada no contexto da requisição
     *
     * PHASE 10: Gera chaves consistentes para cache hit/miss
     *
     * @param bool $isPanel Se é requisição do painel
     * @param int $tenantId ID do tenant
     * @param int|null $userId ID do usuário (se aplicável)
     * @param bool $isAdmin Se é admin
     * @return string Chave de cache única
     */
    private function generateCacheKey(bool $isPanel, int $tenantId, ?int $userId, bool $isAdmin): string
    {
        if ($isPanel) {
            if ($isAdmin) {
                // Admin do painel: pode ver todas as propriedades do tenant
                return "properties_panel_tenant_" . $tenantId . "_admin";
            } else {
                // Usuário comum: pode ver apenas suas propriedades
                return "properties_panel_tenant_" . $tenantId . "_user_" . ($userId ?? 0);
            }
        } else {
            // Site público: mesmas propriedades para todo mundo do tenant
            return "properties_public_tenant_" . $tenantId;
        }
    }

    /**
     * Invalida cache de propriedades para um tenant
     *
     * PHASE 10: Chamado após create/update/delete de propriedade
     *
     * @param int $tenantId ID do tenant
     * @return void
     */
    public function invalidateCache(int $tenantId): void
    {
        // Invalidar cache do painel (admin e users)
        $adminKey = "properties_panel_tenant_" . $tenantId . "_admin";
        $this->cache->forget($adminKey);

        // Invalidar cache público
        $publicKey = "properties_public_tenant_" . $tenantId;
        $this->cache->forget($publicKey);

        error_log("[PropertyService] Cache invalidated for tenant $tenantId");
    }

    /**
     * Invalida cache específico de um usuário
     *
     * PHASE 10: Chamado quando propriedade do usuário é alterada
     *
     * @param int $tenantId ID do tenant
     * @param int $userId ID do usuário
     * @return void
     */
    public function invalidateCacheForUser(int $tenantId, int $userId): void
    {
        $userKey = "properties_panel_tenant_" . $tenantId . "_user_" . $userId;
        $this->cache->forget($userKey);

        error_log("[PropertyService] Cache invalidated for user $userId in tenant $tenantId");
    }

    /**
     * Retorna contagem de propriedades para um tenant (delegando ao Repository)
     *
     * @param int $tenantId
     * @return int
     */
    public function getPropertyCountForTenant(int $tenantId): int
    {
        return $this->repository->countPropertiesByTenant($tenantId);
    }

    /**
     * Obtém dados de uma propriedade específica
     *
     * @param int $propertyId ID da propriedade
     * @param array $options Opções de contexto (tenant, user, etc)
     * @return array|null Dados da propriedade ou null se não encontrada
     */
    public function getProperty(int $propertyId, array $options): ?array
    {
        $tenantId = isset($options['tenantId']) ? (int)$options['tenantId'] : 0;
        $isAdmin = $options['isAdmin'] ?? false;
        $userId = isset($options['userId']) ? (int)$options['userId'] : null;

        if ($tenantId <= 0) {
            return null;
        }

        $rows = $this->repository->findPropertyById($propertyId, $tenantId, $isAdmin);
        if ($rows === null) {
            return null;
        }

        $propertyMap = [];
        foreach ($rows as $row) {
            \groupPropertiesWithImages($propertyMap, $row);
        }

        $propertyData = reset($propertyMap);
        if (!$propertyData) {
            return null;
        }

        try {
            $dto = PropertyDTO::fromArray($propertyData);
            return $dto->toArray();
        } catch (\InvalidArgumentException $e) {
            $this->logger->error('Erro ao converter propriedade para DTO', [
                'request_id' => RequestTracker::getId(),
                'property_id' => $propertyId,
                'error' => $e->getMessage(),
            ]);
            return $propertyData;
        }
    }

    /**
     * Retorna o tenant_id associado a uma propriedade (ou null se não existir)
     * Delegado ao repository para manter todo SQL centralizado.
     *
     * @param int $propertyId
     * @return int|null
     */
    public function getTenantIdForProperty(int $propertyId): ?int
    {
        return $this->repository->getTenantIdByPropertyId($propertyId);
    }

    /**
     * Wrapper mínimo: busca propriedades no formato usado pelo controller legado.
     * Retorna array de linhas tal como vieram do DB.
     *
     * @param int $tenantId
     * @param int|null $assignedUserId
     * @param bool $isAdmin
     * @return array
     */
    public function fetchPropertiesForController(int $tenantId, ?int $assignedUserId = null, bool $isAdmin = false): array
    {
        return $this->repository->fetchPropertiesForController($tenantId, $assignedUserId, $isAdmin);
    }

    /**
     * Wrapper mínimo: encontra propriedade por id para controller legado
     *
     * @param int $id
     * @param int $tenantId
     * @param int|null $userId
     * @param bool $isAdmin
     * @return array|null
     */
    public function findPropertyForController(int $id, int $tenantId, ?int $userId = null, bool $isAdmin = false): ?array
    {
        return $this->repository->findPropertyForController($id, $tenantId, $userId, $isAdmin);
    }

    /**
     * Wrapper mínimo para busca pública de propriedades com paginação.
     * Mantém SQL idêntico ao legado do endpoint público.
     *
     * @param int $tenantId
     * @param string $status
     * @param string|null $type
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getPublicProperties(int $tenantId, string $status, ?string $type, int $limit, int $offset): array
    {
        return $this->repository->fetchPublicProperties($tenantId, $status, $type, $limit, $offset);
    }

    /**
     * Wrapper mínimo para contagem pública de propriedades.
     * Mantém SQL idêntico ao legado do endpoint público.
     *
     * @param int $tenantId
     * @param string $status
     * @param string|null $type
     * @return int
     */
    public function countPublicProperties(int $tenantId, string $status, ?string $type): int
    {
        return $this->repository->countPublicProperties($tenantId, $status, $type);
    }

    /**
     * Wrapper mínimo para inserir propriedade no endpoint público.
     * Mantém SQL idêntico ao legado.
     *
     * @param array $data
     * @return int
     */
    public function insertPublicProperty(array $data): int
    {
        return $this->repository->insertPublicProperty($data);
    }

    /**
     * Wrapper mínimo para inserir propriedade com created_at = NOW() (compat com controller legado)
     *
     * @param array $data
     * @return int
     */
    public function insertPropertyFromController(array $data): int
    {
        return $this->repository->insertPropertyController($data);
    }

    /**
     * Wrapper para recuperar assigned_user_id
     */
    public function getAssignedUserId(int $id, int $tenantId): ?int
    {
        return $this->repository->getAssignedUserId($id, $tenantId);
    }

    /**
     * Atualiza apenas campos permitidos em um patch do controller legado.
     */
    public function updatePropertyPatch(int $id, int $tenantId, array $changes): void
    {
        $allowed = ['title', 'type', 'price', 'description', 'status', 'area', 'bedrooms', 'bathrooms', 'garage', 'location', 'neighborhood', 'city', 'state'];
        $normalized = [];

        foreach ($changes as $field => $value) {
            if (!in_array($field, $allowed, true)) {
                continue;
            }

            if ($field === 'title') {
                $normalized['title'] = (string)$value;
                continue;
            }

            if ($field === 'price') {
                $normalized['price'] = (float)$value;
                continue;
            }

            if (in_array($field, ['area', 'bedrooms', 'bathrooms', 'garage'], true)) {
                $normalized[$field] = (float)$value;
                continue;
            }

            if ($field === 'status') {
                $validStatuses = ['active', 'pending', 'vendido', 'alugado', 'inactive'];
                if (!in_array((string)$value, $validStatuses, true)) {
                    throw new \InvalidArgumentException('Status inválido. Valores permitidos: ' . implode(', ', $validStatuses));
                }
            }

            $normalized[$field] = $value;
        }

        if (empty($normalized)) {
            throw new \InvalidArgumentException('Nenhum campo válido para atualizar');
        }

        $this->repository->updatePropertyPatch((int)$id, (int)$tenantId, $normalized);
    }

    /**
     * Wrapper para update via controller
     */
    public function updatePropertyController(string $assignments, array $params): void
    {
        $this->repository->updatePropertyController($assignments, $params);
    }

    /**
     * Wrapper para delete via controller
     */
    public function deletePropertyController(int $id, int $tenantId): void
    {
        $this->repository->deletePropertyController($id, $tenantId);
    }

    /**
     * Obtém propriedades específicas para exportação a portais.
     *
     * Este método replica o comportamento legado esperado pelos exporters:
     * - isolamento por tenant quando fornecido
     * - apenas status = 'active'
     * - ordenação por updated_at DESC, id DESC
     * - limite pelo parâmetro
     * - agrupa imagens (mantendo o valor cru de image_url do banco)
     *
     * NOTE: Esta função delega todo acesso a BD para o PropertyRepository
     * via QueryBuilder + execute(), e retorna arrays brutos compatíveis com
     * o que `app/integrations/PortalManager.php` espera.
     *
     * @param int|null $tenantId
     * @param int $limit
     * @param string|null $portal
     * @param array $config
     * @return array
     */
    public function getPropertiesForPortalExport(?int $tenantId, int $limit = 500, ?string $portal = null, array $config = []): array
    {
        // Construir WHERE exatamente como no comportamento legado
        $whereParts = [];
        $params = [];
        if ($tenantId !== null) {
            $whereParts[] = 'p.tenant_id = ?';
            $params[] = $tenantId;
        }
        // Apenas propriedades ativas para exportação (mesmo filtro legado)
        $whereParts[] = "p.status = 'active'";

        $whereClause = !empty($whereParts) ? 'WHERE ' . implode(' AND ', $whereParts) : '';

        // Construir QueryBuilder manualmente (delegando ao Repository)
        $qb = $this->repository->getQueryBuilder();
        $qb->select(['p.*', 'pi.image_url'])
            ->from('properties p')
            ->leftJoin('property_images pi', 'p.id = pi.property_id');

        // Remover prefixo WHERE se presente para usar qb->where()
        $whereForQb = $whereClause;
        if (str_starts_with($whereForQb, 'WHERE')) {
            $whereForQb = trim(substr($whereForQb, 5));
        }
        if (!empty($whereForQb)) {
            $qb->where($whereForQb, $params);
        }

        // Ordenação idêntica ao legado
        $qb->orderBy('p.updated_at', 'DESC');
        $qb->orderBy('p.id', 'DESC');

        $qb->limit($limit);

        $rows = $this->repository->execute($qb);

        // Agrupar linhas retornadas (LEFT JOIN cria múltiplas linhas por imagem)
        $unique = [];
        $ordered = [];
        $duplicates = [];
        foreach ($rows as $r) {
            $pid = $r['id'] ?? null;
            if ($pid === null) continue;
            if (!isset($unique[$pid])) {
                // Primeira ocorrência: copiar todos os campos, inicializar images
                $entry = $r;
                $entry['images'] = [];
                if (isset($r['image_url']) && $r['image_url'] !== null) {
                    $entry['images'][] = $r['image_url'];
                }
                $unique[$pid] = $entry;
                $ordered[] = $pid;
            } else {
                // Registro duplicado encontrado
                $duplicates[] = $pid;
                // Adicionar imagem se existir
                if (isset($r['image_url']) && $r['image_url'] !== null) {
                    $unique[$pid]['images'][] = $r['image_url'];
                }
            }
        }

        // Reconstruir lista preservando ordem
        $result = [];
        foreach ($ordered as $pid) {
            $result[] = $unique[$pid];
        }

        return [
            'properties' => $result,
            'duplicates' => array_values(array_unique($duplicates)),
            'original_count' => count($rows),
        ];
    }

    /**
     * Obtém uma propriedade no formato de contrato legado para getPropertyById.php.
     *
     * @param int $propertyId
     * @param array $options
     * @return array|null
     */
    public function getPropertyByIdForApi(int $propertyId, array $options): ?array
    {
        $tenantId = isset($options['tenantId']) ? (int)$options['tenantId'] : 0;
        $isAdmin = (bool)($options['isAdmin'] ?? false);
        $allowGlobal = (bool)($options['allowGlobal'] ?? false);

        if ($tenantId <= 0) {
            if ($allowGlobal && $isAdmin) {
                $property = $this->repository->findPropertyByIdForLegacy($propertyId, null, true);
            } else {
                return null;
            }
        } else {
            $property = $this->repository->findPropertyByIdForLegacy($propertyId, $tenantId, $isAdmin);
        }

        if ($property === null) {
            return null;
        }

        if (isset($property['price'])) {
            $property['price'] = floatval($property['price']);
        }
        if (isset($property['area'])) {
            $property['area'] = floatval($property['area']);
        }
        if (isset($property['condominium'])) {
            $property['condominium'] = $property['condominium'] === null ? null : floatval($property['condominium']);
        } else {
            $property['condominium'] = null;
        }
        if (isset($property['iptu'])) {
            $property['iptu'] = $property['iptu'] === null ? null : floatval($property['iptu']);
        } else {
            $property['iptu'] = null;
        }
        if (isset($property['suites'])) {
            $property['suites'] = $property['suites'] === null ? null : intval($property['suites']);
        } else {
            $property['suites'] = null;
        }

        if (!empty($property['features'])) {
            $decoded = json_decode($property['features'], true);
            $property['features'] = is_array($decoded) ? $decoded : [$property['features']];
        } else {
            $property['features'] = [];
        }

        foreach (['neighborhood', 'city', 'state', 'zip'] as $field) {
            if (!isset($property[$field])) {
                $property[$field] = '';
            }
        }

        return $property;
    }

    /**
     * Obtém lista simples de propriedades para o painel, preservando o contrato legado.
     *
     * @param array $options
     * @return array
     */
    public function getPropertiesSimple(array $options): array
    {
        if (!isset($options['tenantId'])) {
            throw new \InvalidArgumentException("Opção 'tenantId' é obrigatória");
        }

        $tenantId = (int)$options['tenantId'];
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException("Opção 'tenantId' deve ser um inteiro positivo");
        }

        $isAdmin = $options['isAdmin'] ?? false;
        if (!is_bool($isAdmin)) {
            throw new \InvalidArgumentException("Opção 'isAdmin' deve ser boolean");
        }

        $userId = isset($options['userId']) ? (int)$options['userId'] : null;
        if (!$isAdmin && ($userId === null || $userId <= 0)) {
            throw new \InvalidArgumentException("Opção 'userId' é obrigatória para usuários não-admin");
        }

        return $this->repository->getSimplePropertyList($tenantId, $userId, $isAdmin, 200);
    }

    /**
     * Retorna todas as propriedades válidas para exportação de CSV.
     *
     * Mantém o mesmo comportamento da query antiga:
     * SELECT * FROM properties WHERE deleted_at IS NULL LIMIT 1000
     *
     * @return array
     */
    public function getExportProperties(): array
    {
        $qb = $this->repository->getQueryBuilder();
        $qb->select(['*'])
            ->from('properties')
            ->where('deleted_at IS NULL', [])
            ->limit(1000);

        return $this->repository->execute($qb);
    }

    /**
     * Retorna propriedades para o formulário de imóvel.
     *
     * Mantém o mesmo comportamento da query antiga:
     * SELECT * FROM properties WHERE deleted_at IS NULL LIMIT 5
     *
     * @return array
     */
    public function getFormProperties(): array
    {
        $qb = $this->repository->getQueryBuilder();
        $qb->select(['*'])
            ->from('properties')
            ->where('deleted_at IS NULL', [])
            ->limit(5);

        return $this->repository->execute($qb);
    }

    /**
     * Cria uma nova propriedade e registra auditoria/cache.
     *
     * @param array $data Dados normalizados da propriedade
     * @param int $tenantId
     * @param int|null $userId
     * @return int
     */
    public function createProperty(array $data, int $tenantId, ?int $userId = null): int
    {
        $this->authorizationService->requirePermission(Permissions::CREATE_PROPERTY);

        $data['tenant_id'] = $tenantId;
        $data = $this->normalizePropertyData($data, true);

        $propertyId = $this->repository->insertProperty($data);
        $this->invalidateCache($tenantId);

        $this->logger->info('Property created', [
            'request_id' => RequestTracker::getId(),
            'property_id' => $propertyId,
            'tenant_id' => $tenantId,
            'user_id' => $userId,
        ]);

        $this->auditLogger->logCreate('Property', $propertyId, $data, $userId);

        return $propertyId;
    }

    private static function normalizeTransitionStatus(?string $status): ?string
    {
        if (!is_string($status)) {
            return null;
        }

        $normalized = strtolower(trim($status));
        $statusMap = [
            'active' => 'active',
            'ativo' => 'active',
            'pending' => 'pending',
            'pendente' => 'pending',
            'sold' => 'vendido',
            'vendido' => 'vendido',
            'rented' => 'alugado',
            'alugado' => 'alugado',
        ];

        return $statusMap[$normalized] ?? $normalized;
    }

    public static function resolveEffectiveCommissionPercentage($explicitCommissionPercent, array $propertyData): float
    {
        if ($explicitCommissionPercent !== null && $explicitCommissionPercent !== '' && !is_array($explicitCommissionPercent)) {
            return (float)$explicitCommissionPercent;
        }

        $propertyCommission = $propertyData['sale_commission_percentage'] ?? null;
        if ($propertyCommission !== null && $propertyCommission !== '') {
            return (float)$propertyCommission;
        }

        return 6.0;
    }

    public static function resolveEffectiveBrokerCommissionPercentage(array $propertyData, ?int $brokerUserId = null, $explicitBrokerCommissionPercent = null): ?float
    {
        $candidates = [];

        if ($explicitBrokerCommissionPercent !== null && $explicitBrokerCommissionPercent !== '' && !is_array($explicitBrokerCommissionPercent)) {
            $numericExplicit = (float)$explicitBrokerCommissionPercent;
            if (is_finite($numericExplicit) && $numericExplicit >= 0 && $numericExplicit <= 100) {
                $candidates[] = $numericExplicit;
            }
        }

        foreach (['broker_commission_percentage', 'broker_percentage', 'broker_commission_percent', 'broker_default_commission_percentage'] as $key) {
            $value = $propertyData[$key] ?? null;
            if ($value !== null && $value !== '' && !is_array($value) && is_numeric($value)) {
                $numericValue = (float)$value;
                if (is_finite($numericValue) && $numericValue >= 0 && $numericValue <= 100) {
                    $candidates[] = $numericValue;
                }
            }
        }

        foreach ($candidates as $candidate) {
            $numericCandidate = (float)$candidate;
            if (is_finite($numericCandidate) && $numericCandidate >= 0 && $numericCandidate <= 100) {
                return $numericCandidate;
            }
        }

        return null;
    }

    public static function determineStatusTransitionAction(?string $oldStatus, ?string $newStatus): ?string
    {
        $previousStatus = self::normalizeTransitionStatus($oldStatus);
        $nextStatus = self::normalizeTransitionStatus($newStatus);

        if ($previousStatus === null || $nextStatus === null || $previousStatus === $nextStatus) {
            return null;
        }

        $closingOrigins = ['active', 'pending'];
        $closingTargets = ['vendido', 'alugado'];
        if (in_array($previousStatus, $closingOrigins, true) && in_array($nextStatus, $closingTargets, true)) {
            return 'close';
        }

        $reversalOrigins = ['vendido', 'alugado'];
        $reversalTargets = ['active', 'pending'];
        if (in_array($previousStatus, $reversalOrigins, true) && in_array($nextStatus, $reversalTargets, true)) {
            return 'revert';
        }

        return null;
    }

    private function resolveEffectiveBrokerUserId(int $tenantId, array $propertyData, ?int $userId = null): ?int
    {
        $candidateIds = [];

        foreach (['broker_user_id', 'broker_id', 'broker_user'] as $brokerKey) {
            $explicitBrokerUserId = $propertyData[$brokerKey] ?? null;
            if ($explicitBrokerUserId !== null && $explicitBrokerUserId !== '' && (int)$explicitBrokerUserId > 0) {
                $candidateIds[] = (int)$explicitBrokerUserId;
                break;
            }
        }

        $assignedUserId = $propertyData['assigned_user_id'] ?? null;
        if ($assignedUserId !== null && $assignedUserId !== '' && (int)$assignedUserId > 0) {
            $candidateIds[] = (int)$assignedUserId;
        }

        $candidateIds = array_values(array_unique(array_filter($candidateIds, static fn($candidate) => $candidate > 0)));

        $userRepository = new \App\Infrastructure\User\UserRepository($this->pdo);
        foreach ($candidateIds as $candidateId) {
            $userRow = $userRepository->findById($candidateId, $tenantId);
            if ($userRow !== null) {
                return $candidateId;
            }
        }

        return null;
    }

    private function closePropertySaleFlow(int $propertyId, int $tenantId, array $propertyData, ?int $userId = null): void
    {
        $existingSale = $this->pdo->prepare(
            'SELECT * FROM property_sales WHERE property_id = :property_id AND tenant_id = :tenant_id AND deleted_at IS NULL ORDER BY id DESC LIMIT 1'
        );
        $existingSale->execute([
            ':property_id' => $propertyId,
            ':tenant_id' => $tenantId,
        ]);

        if ($existingSale->fetch(PDO::FETCH_ASSOC)) {
            return;
        }

        $title = (string)($propertyData['title'] ?? $propertyData['name'] ?? 'Propriedade');
        $price = isset($propertyData['price']) ? (float)$propertyData['price'] : 0.0;
        $effectiveBrokerUserId = $this->resolveEffectiveBrokerUserId($tenantId, $propertyData, $userId);
        $userRepository = new \App\Infrastructure\User\UserRepository($this->pdo);
        $userRow = $effectiveBrokerUserId !== null ? $userRepository->findById($effectiveBrokerUserId, $tenantId) : null;

        if ($userRow !== null && array_key_exists('broker_default_commission_percentage', $userRow)) {
            $propertyData['broker_default_commission_percentage'] = $userRow['broker_default_commission_percentage'];
        }

        $commissionPercent = self::resolveEffectiveCommissionPercentage(
            $propertyData['sale_commission_percentage'] ?? null,
            $propertyData
        );
        $brokerCommissionPercent = self::resolveEffectiveBrokerCommissionPercentage(
            $propertyData,
            $effectiveBrokerUserId,
            $propertyData['broker_commission_percentage'] ?? $propertyData['broker_percentage'] ?? $propertyData['broker_commission_percent'] ?? null
        );

        $commissionValue = ($price * $commissionPercent) / 100;
        $brokerCommissionValue = $brokerCommissionPercent !== null ? (($commissionValue * $brokerCommissionPercent) / 100) : null;

        $username = (string)($userRow['username'] ?? '');

        $insertSale = $this->pdo->prepare(
            'INSERT INTO property_sales (tenant_id, property_id, user_id, username, property_title, property_price, commission_6percent, status, created_at, updated_at, broker_user_id, commission_percentage_applied, commission_value_applied, broker_commission_percentage, broker_commission_value) VALUES (:tenant_id, :property_id, :user_id, :username, :property_title, :property_price, :commission_6percent, :status, NOW(), NOW(), :broker_user_id, :commission_percentage_applied, :commission_value_applied, :broker_commission_percentage, :broker_commission_value)'
        );
        $insertSale->execute([
            ':tenant_id' => $tenantId,
            ':property_id' => $propertyId,
            ':user_id' => $effectiveBrokerUserId,
            ':username' => $username,
            ':property_title' => $title,
            ':property_price' => $price,
            ':commission_6percent' => $commissionValue,
            ':status' => 'completed',
            ':broker_user_id' => $effectiveBrokerUserId,
            ':commission_percentage_applied' => $commissionPercent,
            ':commission_value_applied' => $commissionValue,
            ':broker_commission_percentage' => $brokerCommissionPercent,
            ':broker_commission_value' => $brokerCommissionValue,
        ]);

        $saleId = (int)$this->pdo->lastInsertId();
        if ($saleId <= 0) {
            throw new \RuntimeException('Não foi possível registrar a operação de venda/aluguel');
        }

        $financialRepository = new FinancialTransactionRepository($this->pdo);

        $existingRevenueStmt = $this->pdo->prepare(
            'SELECT id FROM financeiro WHERE tenant_id = :tenant_id AND property_id = :property_id AND tipo = :tipo AND reference_sale_id = :reference_sale_id AND deleted_at IS NULL LIMIT 1'
        );
        $existingRevenueStmt->execute([
            ':tenant_id' => $tenantId,
            ':property_id' => $propertyId,
            ':tipo' => 'receita',
            ':reference_sale_id' => $saleId,
        ]);

        if (!$existingRevenueStmt->fetch(PDO::FETCH_ASSOC)) {
            $transactionId = $financialRepository->createTransaction([
                'tipo' => 'receita',
                'property_id' => $propertyId,
                'user_id' => $effectiveBrokerUserId ?? 0,
                'descricao' => 'Receita da comissão de venda/aluguel do imóvel ' . $title,
                'valor' => (float)$commissionValue,
                'data_transacao' => date('Y-m-d'),
                'categoria' => 'Comissão',
                'status' => 'pendente',
                'reference_sale_id' => $saleId,
                'reference_commission_id' => null,
                'reversal_of_id' => null,
            ], $tenantId);

            if ($transactionId <= 0) {
                throw new \RuntimeException('Não foi possível registrar a transação financeira');
            }
        }

        if ($effectiveBrokerUserId !== null && $brokerCommissionPercent !== null && $brokerCommissionPercent > 0) {
            $commissionModel = new CommissionModel($this->pdo);
            $commissionResult = $commissionModel->createFromSale([
                'sale_id' => $saleId,
                'user_id' => $effectiveBrokerUserId,
                'property_id' => $propertyId,
                'sale_value' => $commissionValue,
                'commission_percentage' => $brokerCommissionPercent,
            ]);

            if ($commissionResult === false || empty($commissionResult['id'])) {
                throw new \RuntimeException('Não foi possível registrar a comissão da operação');
            }

            $commissionId = (int)$commissionResult['id'];
            $existingExpenseStmt = $this->pdo->prepare(
                'SELECT id FROM financeiro WHERE tenant_id = :tenant_id AND property_id = :property_id AND tipo = :tipo AND reference_commission_id = :reference_commission_id AND deleted_at IS NULL LIMIT 1'
            );
            $existingExpenseStmt->execute([
                ':tenant_id' => $tenantId,
                ':property_id' => $propertyId,
                ':tipo' => 'despesa',
                ':reference_commission_id' => $commissionId,
            ]);

            if (!$existingExpenseStmt->fetch(PDO::FETCH_ASSOC)) {
                $expenseId = $financialRepository->createTransaction([
                    'tipo' => 'despesa',
                    'property_id' => $propertyId,
                    'user_id' => $effectiveBrokerUserId,
                    'descricao' => 'Comissão de Corretor - venda do imóvel ' . $title,
                    'valor' => (float)$brokerCommissionValue,
                    'data_transacao' => date('Y-m-d'),
                    'categoria' => 'Comissão de Corretor',
                    'status' => 'pendente',
                    'reference_sale_id' => $saleId,
                    'reference_commission_id' => $commissionId,
                    'reversal_of_id' => null,
                ], $tenantId);

                if ($expenseId <= 0) {
                    throw new \RuntimeException('Não foi possível registrar a despesa financeira do repasse');
                }
            }
        }
    }

    private function revertPropertySaleFlow(int $propertyId, int $tenantId): void
    {
        $saleStmt = $this->pdo->prepare(
            'SELECT * FROM property_sales WHERE property_id = :property_id AND tenant_id = :tenant_id AND deleted_at IS NULL ORDER BY id DESC LIMIT 1'
        );
        $saleStmt->execute([
            ':property_id' => $propertyId,
            ':tenant_id' => $tenantId,
        ]);
        $sale = $saleStmt->fetch(PDO::FETCH_ASSOC);

        if (!$sale) {
            return;
        }

        $commissionRepository = new \App\Infrastructure\Finance\CommissionRepository($this->pdo);
        $commission = $commissionRepository->getCommissionBySaleId((int)$sale['id'], $tenantId);
        if ($commission) {
            $commissionRepository->cancelCommission((int)$commission['id'], $tenantId, 'Reversão de status do imóvel');
        }

        $financialRepository = new FinancialTransactionRepository($this->pdo);
        $financialRepository->softDeleteTransactionsBySaleId((int)$sale['id'], $tenantId);
        $commissionRepository->softDeleteSaleById((int)$sale['id'], $tenantId);
    }

    private function handleStatusTransition(int $propertyId, int $tenantId, ?string $oldStatus, ?string $newStatus, array $propertyData, ?int $userId = null): void
    {
        $action = self::determineStatusTransitionAction($oldStatus, $newStatus);
        if ($action === null) {
            return;
        }

        if ($action === 'close') {
            $this->closePropertySaleFlow($propertyId, $tenantId, $propertyData, $userId);
            return;
        }

        $this->revertPropertySaleFlow($propertyId, $tenantId);
    }

    public function updateProperty(int $propertyId, array $data, int $tenantId, ?int $userId = null, bool $isAdmin = false): bool
    {
        $this->authorizationService->requirePermission(Permissions::UPDATE_PROPERTY);

        $rows = $this->repository->findPropertyById($propertyId, $tenantId, $isAdmin);
        if ($rows === null) {
            throw new \RuntimeException('Imóvel não encontrado ou acesso negado');
        }

        $propertyMap = [];
        foreach ($rows as $row) {
            \groupPropertiesWithImages($propertyMap, $row);
        }

        $existingProperty = reset($propertyMap);
        $existingNormalized = $this->normalizePropertyData($existingProperty, false);
        $data = $this->normalizePropertyData($data, false);

        if (empty($data)) {
            throw new \InvalidArgumentException('Nenhum campo para atualizar');
        }

        $previousStatus = self::normalizeTransitionStatus($existingProperty['status'] ?? null);
        $nextStatus = self::normalizeTransitionStatus($data['status'] ?? $existingProperty['status'] ?? null);

        $this->pdo->beginTransaction();
        try {
            $result = $this->repository->updateProperty($propertyId, $data, $isAdmin, $tenantId);
            if (!$result) {
                throw new \RuntimeException('Nenhuma alteração foi aplicada');
            }

            if (isset($data['assigned_user_id']) && $data['assigned_user_id'] !== ($existingProperty['assigned_user_id'] ?? null)) {
                $this->invalidateCacheForUser($tenantId, (int)$data['assigned_user_id']);
                if (!empty($existingProperty['assigned_user_id'])) {
                    $this->invalidateCacheForUser($tenantId, (int)$existingProperty['assigned_user_id']);
                }
            }

            $this->handleStatusTransition($propertyId, $tenantId, $previousStatus, $nextStatus, $existingProperty, $userId);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $this->invalidateCache($tenantId);

        $this->logger->info('Property updated', [
            'request_id' => RequestTracker::getId(),
            'property_id' => $propertyId,
            'tenant_id' => $tenantId,
            'user_id' => $userId,
        ]);

        $this->auditLogger->logUpdate('Property', $propertyId, $existingNormalized, $data, $userId);

        return true;
    }

    /**
     * Exclui uma propriedade.
     *
     * @param int $propertyId
     * @param int $tenantId
     * @param int|null $userId
     * @param bool $isAdmin
     * @return bool
     */
    public function deleteProperty(int $propertyId, int $tenantId, ?int $userId = null, bool $isAdmin = false): bool
    {
        $this->authorizationService->requirePermission(Permissions::DELETE_PROPERTY);

        $rows = $this->repository->findPropertyById($propertyId, $tenantId, $isAdmin);
        if ($rows === null) {
            throw new \RuntimeException('Imóvel não encontrado ou acesso negado');
        }

        $propertyMap = [];
        foreach ($rows as $row) {
            \groupPropertiesWithImages($propertyMap, $row);
        }

        $property = reset($propertyMap);
        if ($property === false) {
            throw new \RuntimeException('Imóvel não encontrado');
        }

        $this->pdo->beginTransaction();
        try {
            $this->repository->deletePropertyImagesByPropertyId($propertyId);
            $deleted = $this->repository->deleteProperty($propertyId, $isAdmin, $tenantId);
            if (!$deleted) {
                $this->pdo->rollBack();
                return false;
            }
            $this->pdo->commit();
        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $tenantToInvalidate = isset($property['tenant_id']) && ((int)$property['tenant_id'] > 0)
            ? (int)$property['tenant_id']
            : $tenantId;

        $this->invalidateCache($tenantToInvalidate);

        $this->logger->info('Property deleted', [
            'request_id' => RequestTracker::getId(),
            'property_id' => $propertyId,
            'tenant_id' => $tenantId,
            'user_id' => $userId,
        ]);

        $this->auditLogger->logDelete('Property', $propertyId, $property, $userId);

        return true;
    }

    /**
     * Persiste metadados de imagens de propriedade.
     *
     * @param int $propertyId
     * @param array $filenames
     * @return void
     */
    public function savePropertyImages(int $propertyId, array $filenames): void
    {
        $this->repository->insertPropertyImages($propertyId, $filenames);
    }

    /**
     * Retorna imagens de uma propriedade no formato público (URLs completas)
     *
     * @param int $propertyId
     * @param int|null $tenantId
     * @return array
     */
    public function getPropertyImages(int $propertyId, ?int $tenantId = null): array
    {
        // Verifica se a propriedade existe e pertence ao tenant quando tenantId fornecido
        $rows = $this->repository->findPropertyById($propertyId, $tenantId, false);
        if ($rows === null) {
            return [];
        }

        $images = $this->repository->getPropertyImagesByPropertyId($propertyId);
        $urls = [];
        foreach ($images as $file) {
            $urls[] =
                '/assets/imagens/' . $propertyId . '/' . ltrim($file, '/');
        }
        return $urls;
    }

    /**
     * Remove uma imagem de propriedade, orquestrando validação, remoção física e DB.
     *
     * @param int $propertyId
     * @param string $filename
     * @param int $tenantId
     * @param int|null $userId
     * @param bool $isAdmin
     * @return bool
     * @throws \Exception
     */
    public function deletePropertyImage(int $propertyId, string $filename, int $tenantId, ?int $userId = null, bool $isAdmin = false): bool
    {
        // Verifica propriedade e isolamento
        $rows = $this->repository->findPropertyById($propertyId, $tenantId, $isAdmin);
        if ($rows === null) {
            throw new \RuntimeException('Propriedade não encontrada ou acesso negado');
        }

        // Buscar registro da imagem
        $img = $this->repository->getPropertyImageRecord($propertyId, $filename);
        if ($img === null) {
            throw new \RuntimeException('Registro de imagem não encontrado');
        }

        $imagePath = $_SERVER['DOCUMENT_ROOT'] . '/assets/imagens/' . $propertyId . '/' . $img['image_url'];

        $this->pdo->beginTransaction();
        try {
            $deleted = $this->repository->deletePropertyImageById((int)$img['id']);
            if (!$deleted) {
                $this->pdo->rollBack();
                throw new \RuntimeException('Falha ao remover referência no banco');
            }

            if (!@unlink($imagePath)) {
                $this->pdo->rollBack();
                throw new \RuntimeException('Erro ao excluir arquivo físico.');
            }

            $this->pdo->commit();

            // Auditoria e cache
            $this->auditLogger->logDelete('PropertyImage', (int)$img['id'], $img, $userId);
            if (isset($rows[0]['tenant_id'])) {
                $this->invalidateCache((int)$rows[0]['tenant_id']);
            }

            return true;
        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Retorna as categorias (com estatísticas) para um tenant no formato da API
     *
     * @param int $tenantId
     * @return array
     */
    public function getPropertyCategories(int $tenantId): array
    {
        $rows = $this->repository->getPropertyCategories($tenantId);

        $typeMap = ['apartment' => 'Apartamento', 'house' => 'Casa', 'land' => 'Terreno', 'commercial' => 'Comercial'];

        $categories = [];
        foreach ($rows as $row) {
            if ($row['type']) {
                $categories[] = [
                    'value' => $row['type'],
                    'name' => $typeMap[$row['type']] ?? ucfirst($row['type']),
                    'total' => intval($row['total']),
                    'ativos' => intval($row['ativos']),
                    'inativos' => intval($row['inativos']),
                    'vendidos' => intval($row['vendidos']),
                    'alugados' => intval($row['alugados']),
                    'preco' => [
                        'minimo' => $row['preco_minimo'] ? floatval($row['preco_minimo']) : 0,
                        'maximo' => $row['preco_maximo'] ? floatval($row['preco_maximo']) : 0,
                        'medio' => $row['preco_medio'] ? floatval($row['preco_medio']) : 0
                    ]
                ];
            }
        }

        return $categories;
    }

    /**
     * Retorna as regiões (neighborhoods) normalizadas e ordenadas para um tenant
     *
     * @param int $tenantId
     * @return array
     */
    public function getPropertyRegions(int $tenantId): array
    {
        $rows = $this->repository->getPropertyRegions($tenantId);
        // Normalização: extrair campo 'neighborhood' e remover vazios
        $regions = [];
        foreach ($rows as $r) {
            $n = trim((string)($r['neighborhood'] ?? ''));
            if ($n !== '') {
                $regions[] = $n;
            }
        }
        sort($regions, SORT_STRING | SORT_FLAG_CASE);
        // voltar no mesmo formato que os controllers antigos retornavam (array de assoc)
        $out = [];
        foreach ($regions as $reg) {
            $out[] = ['neighborhood' => $reg];
        }
        return $out;
    }

    /**
     * Normaliza entradas de propriedade para o banco.
     *
     * @param array $data
     * @param bool $isCreate
     * @return array
     */
    private function normalizePropertyData(array $data, bool $isCreate = false): array
    {
        $fieldMap = [
            'titulo' => 'title',
            'title' => 'title',
            'tipo' => 'type',
            'type' => 'type',
            'transactionType' => 'transactionType',
            'transaction_type' => 'transactionType',
            'preco' => 'price',
            'price' => 'price',
            'location' => 'location',
            'endereco' => 'location',
            'description' => 'description',
            'descricao' => 'description',
            'bedrooms' => 'bedrooms',
            'dormitorios' => 'bedrooms',
            'bathrooms' => 'bathrooms',
            'banheiros' => 'bathrooms',
            'garage' => 'garage',
            'suites' => 'suites',
            'area' => 'area',
            'yearBuilt' => 'yearBuilt',
            'neighborhood' => 'neighborhood',
            'city' => 'city',
            'state' => 'state',
            'zip' => 'zip',
            'assigned_user_id' => 'assigned_user_id',
            'status' => 'status',
            'features' => 'features',
            'sale_commission_percentage' => 'sale_commission_percentage',
            'condominium' => 'condominium',
            'iptu' => 'iptu',
            'tenant_id' => 'tenant_id',
        ];

        $normalized = [];
        foreach ($data as $key => $value) {
            if (!array_key_exists($key, $fieldMap)) {
                continue;
            }

            $column = $fieldMap[$key];
            if ($column === 'features') {
                if (is_array($value)) {
                    $normalized[$column] = json_encode(array_values($value), JSON_UNESCAPED_UNICODE);
                } elseif (is_string($value) && $value !== '') {
                    $decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $normalized[$column] = json_encode(array_values($decoded), JSON_UNESCAPED_UNICODE);
                    } else {
                        $normalized[$column] = $value;
                    }
                } else {
                    $normalized[$column] = $value === null ? null : json_encode([]);
                }
                continue;
            }

            if (in_array($column, ['price', 'area', 'sale_commission_percentage', 'condominium', 'iptu'], true)) {
                $normalized[$column] = $value === null || $value === '' ? null : (float)$value;
                continue;
            }

            if (in_array($column, ['bedrooms', 'bathrooms', 'garage', 'suites', 'yearBuilt', 'assigned_user_id', 'tenant_id'], true)) {
                $normalized[$column] = $value === null || $value === '' ? null : (int)$value;
                continue;
            }

            if ($column === 'status') {
                $statusMap = [
                    'active' => 'active',
                    'ativo' => 'active',
                    'pending' => 'pending',
                    'pendente' => 'pending',
                    'inactive' => 'inactive',
                    'inativo' => 'inactive',
                    'sold' => 'vendido',
                    'vendido' => 'vendido',
                    'rented' => 'alugado',
                    'alugado' => 'alugado',
                ];
                $statusKey = strtolower(trim((string)$value));
                $normalized[$column] = $statusMap[$statusKey] ?? $value;
                continue;
            }

            $normalized[$column] = $value;
        }

        if ($isCreate) {
            if (!isset($normalized['title']) || trim($normalized['title']) === '') {
                throw new \InvalidArgumentException('Campo obrigatório faltando: title');
            }
            if (!isset($normalized['type']) || trim($normalized['type']) === '') {
                throw new \InvalidArgumentException('Campo obrigatório faltando: type');
            }
            if (!array_key_exists('price', $normalized) || $normalized['price'] === null) {
                throw new \InvalidArgumentException('Campo obrigatório faltando: price');
            }
            if (!isset($normalized['status']) || trim((string)$normalized['status']) === '') {
                $normalized['status'] = 'active';
            }
        }

        return $normalized;
    }
}
