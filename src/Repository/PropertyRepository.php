<?php

namespace App\Repository;

use PDO;
use App\Database\QueryBuilder;

/**
 * PropertyRepository - Camada de Acesso a Dados para Propriedades
 *
 * Responsável APENAS por:
 * - Construir e executar SQL queries
 * - Gerenciar conexão PDO
 * - Retornar dados brutos do banco
 *
 * NÃO faz:
 * - Formatação de dados
 * - Sanitização
 * - Processamento de resultados
 * - Lógica de negócio
 *
 * A lógica de negócio fica no Service.
 * Os helpers tratam formatação.
 *
 * REFATOR PHASE 9:
 * - Substitui SQL string manual por QueryBuilder
 * - Mantém SQL final idêntico
 * - Facilita debug e reutilização de queries
 */
class PropertyRepository
{
    private PDO $pdo;

    /**
     * Construtor
     *
     * @param PDO $pdo Conexão com banco de dados
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Constrói QueryBuilder com todas as cláusulas para propriedades
     *
     * DESIGN PARA TESTABILIDADE E MANUTENIBILIDADE:
     * Este método usa QueryBuilder (sem SQL string manual) para construir
     * a query de propriedades, removendo completamente concatenação de strings.
     *
     * REFATOR PHASE 9:
     * - Usa QueryBuilder ao invés de concatenação de strings
     * - Mantém SQL final idêntico ao anterior
     * - Suporte a múltiplos parâmetros por condição
     * - Sem efeitos colaterais entre chamadas
     *
     * @param string $whereClause Cláusula WHERE já montada (ex: "WHERE tenant_id = ?")
     * @param array $params Parâmetros já extraídos
     * @return QueryBuilder QueryBuilder pronto para execução
     */
    private function buildQuery(string $whereClause, array $params, string $orderByColumn = 'p.id', string $orderByDirection = 'DESC', ?int $limit = null): QueryBuilder
    {
        // Criar novo QueryBuilder para construir a query
        $qb = new QueryBuilder();
        $qb->select(['p.*', 'pi.image_url'])
            ->from('properties p')
            ->leftJoin('property_images pi', 'p.id = pi.property_id');

        // Processar WHERE clause se existir
        if (!empty($whereClause)) {
            // Remover "WHERE " se estiver no início (vem do Service)
            $whereClause = trim($whereClause);
            if (str_starts_with($whereClause, 'WHERE')) {
                $whereClause = trim(substr($whereClause, 5));
            }

            // Se há WHERE clause, usar o método where() com todos os parâmetros
            // A cláusula WHERE completa é passada como uma única string
            // e todos os parâmetros são passados de uma vez
            $qb->where($whereClause, $params);
        }

        // Adicionar ORDER BY
        $qb->orderBy($orderByColumn, $orderByDirection);

        if ($limit !== null) {
            $qb->limit($limit);
        }

        return $qb;
    }

    /**
     * Busca propriedades com filtros aplicados
     *
     * Retorna dados BRUTOS do banco (sem processamento).
     * O Service é responsável por formatar e processar.
     *
     * IMPORTANTE: A query é construída NO SERVICE, aqui apenas executamos.
     * Isso garante que toda lógica de negócio (filtros, isolamento de tenant)
     * fica no Service, não no Repository.
     *
     * REFATOR PHASE 9:
     * - Usa QueryBuilder internamente (sem SQL manual)
     * - PDO prepare/execute ocorrem aqui
     * - SQL final idêntico ao anterior
     *
     * @param string $whereClause Cláusula WHERE já montada (ex: "WHERE p.tenant_id = ? AND p.status = ?")
     * @param array $params Parâmetros para prepared statement
     * @return array Lista de linhas brutas (resultado de fetchAll)
     */
    public function getProperties(string $whereClause, array $params, string $orderByColumn = 'p.id', string $orderByDirection = 'DESC', ?int $limit = null): array
    {
        // Construir QueryBuilder com todas as cláusulas
        $qb = $this->buildQuery($whereClause, $params, $orderByColumn, $orderByDirection, $limit);

        // Obter SQL e parâmetros do QueryBuilder
        $sql = $qb->getSql();
        $bindParams = $qb->getParams();

        // Executar query via PDO prepare/execute
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindParams);

        // Retornar todas as linhas brutas
        // Não fazer fetch() em loop - let the service handle that
        // para poder usar groupPropertiesWithImages() sem mudança
        $rows = [];
        while ($row = $stmt->fetch()) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Retorna um novo QueryBuilder pré-configurado (para futuras queries)
     *
     * FASE 9: Método para facilitar construção de queries mais complexas
     * sem precisar de frameworks externos.
     *
     * Exemplo de uso:
     *   $qb = $repository->getQueryBuilder();
     *   $query = $qb->select(['id', 'name'])
     *              ->from('properties')
     *              ->where('tenant_id = ?', [$tenantId])
     *              ->getSql();
     *
     * @return QueryBuilder Novo builder vazio
     */
    public function getQueryBuilder(): QueryBuilder
    {
        return new QueryBuilder();
    }

    /**
     * Resolve explicitamente o tenant_id de uma propriedade sem depender
     * de bypass de tenant/global em findPropertyById().
     *
     * @param int $propertyId
     * @return int|null
     */
    public function getTenantIdByPropertyId(int $propertyId): ?int
    {
        $stmt = $this->pdo->prepare('SELECT tenant_id FROM properties WHERE id = ? LIMIT 1');
        $stmt->execute([$propertyId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row && isset($row['tenant_id']) ? (int)$row['tenant_id'] : null;
    }

    /**
     * Executa uma query construída com QueryBuilder
     *
     * FASE 9: Método auxiliar para executar queries complexas
     * sem duplicar lógica de prepare/execute.
     *
     * @param QueryBuilder $queryBuilder Query builder com SQL pronto
     * @return array Resultado das linhas
     */
    public function execute(QueryBuilder $queryBuilder): array
    {
        $sql = $queryBuilder->getSql();
        $params = $queryBuilder->getParams();

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $rows = [];
        while ($row = $stmt->fetch()) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Insere uma nova propriedade no banco.
     *
     * @param array $data Campos da propriedade já normalizados
     * @return int ID da propriedade criada
     */
    public function insertProperty(array $data): int
    {
        $allowedFields = [
            'tenant_id',
            'title',
            'price',
            'location',
            'type',
            'transactionType',
            'area',
            'yearBuilt',
            'description',
            'bedrooms',
            'bathrooms',
            'garage',
            'suites',
            'neighborhood',
            'city',
            'state',
            'zip',
            'assigned_user_id',
            'status',
            'features',
            'sale_commission_percentage',
            'condominium',
            'iptu'
        ];

        $columns = array_values(array_filter($allowedFields, fn($field) => array_key_exists($field, $data)));
        if (empty($columns)) {
            throw new \InvalidArgumentException('Nenhum campo válido para inserir');
        }

        $placeholders = array_fill(0, count($columns), '?');
        $sql = 'INSERT INTO properties (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';

        $stmt = $this->pdo->prepare($sql);
        $params = [];
        foreach ($columns as $column) {
            $params[] = $data[$column];
        }

        $stmt->execute($params);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Atualiza uma propriedade existente.
     *
     * @param int $id
     * @param array $data Campos atualizados
     * @param bool $isAdmin
     * @param int $tenantId
     * @return bool
     */
    public function updateProperty(int $id, array $data, bool $isAdmin, int $tenantId): bool
    {
        $allowedFields = [
            'title',
            'price',
            'location',
            'type',
            'transactionType',
            'area',
            'yearBuilt',
            'description',
            'bedrooms',
            'bathrooms',
            'garage',
            'suites',
            'neighborhood',
            'city',
            'state',
            'zip',
            'assigned_user_id',
            'status',
            'features',
            'sale_commission_percentage',
            'condominium',
            'iptu'
        ];

        $columns = array_values(array_filter($allowedFields, fn($field) => array_key_exists($field, $data)));
        if (empty($columns)) {
            return false;
        }

        $assignments = array_map(fn($column) => "{$column} = ?", $columns);
        $sql = 'UPDATE properties SET ' . implode(', ', $assignments) . ', updated_at = NOW() ';
        if ($isAdmin) {
            $sql .= 'WHERE id = ?';
        } else {
            $sql .= 'WHERE id = ? AND tenant_id = ?';
        }

        $params = [];
        foreach ($columns as $column) {
            $params[] = $data[$column];
        }
        $params[] = $id;
        if (!$isAdmin) {
            $params[] = $tenantId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    /**
     * Remove uma propriedade do banco.
     *
     * @param int $id
     * @param bool $isAdmin
     * @param int $tenantId
     * @return bool
     */
    public function deleteProperty(int $id, bool $isAdmin, int $tenantId): bool
    {
        if ($isAdmin) {
            $sql = 'DELETE FROM properties WHERE id = ?';
            $params = [$id];
        } else {
            $sql = 'DELETE FROM properties WHERE id = ? AND tenant_id = ?';
            $params = [$id, $tenantId];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    /**
     * Busca uma propriedade por ID, com isolamento de tenant opcional.
     *
     * @param int $id
     * @param int|null $tenantId
     * @param bool $isAdmin
     * @return array|null
     */
    public function findPropertyById(int $id, ?int $tenantId, bool $isAdmin): ?array
    {
        $sql = 'SELECT p.*, pi.image_url FROM properties p LEFT JOIN property_images pi ON p.id = pi.property_id ';
        if ($isAdmin || $tenantId === null || $tenantId <= 0) {
            $sql .= 'WHERE p.id = ?';
            $params = [$id];
        } else {
            $sql .= 'WHERE p.id = ? AND p.tenant_id = ?';
            $params = [$id, $tenantId];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false || empty($rows) ? null : $rows;
    }

    /**
     * Busca propriedade e imagens no formato legacy para endpoints que preservam contrato antigo.
     *
     * @param int $id
     * @param int|null $tenantId
     * @param bool $isAdmin
     * @return array|null
     */
    public function findPropertyByIdForLegacy(int $id, ?int $tenantId, bool $isAdmin): ?array
    {
        if ($isAdmin || $tenantId === null || $tenantId <= 0) {
            $stmt = $this->pdo->prepare('SELECT * FROM properties WHERE id = ?');
            $stmt->execute([$id]);
        } else {
            $stmt = $this->pdo->prepare('SELECT * FROM properties WHERE id = ? AND tenant_id = ?');
            $stmt->execute([$id, $tenantId]);
        }

        $property = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$property) {
            return null;
        }

        $stmtImages = $this->pdo->prepare('SELECT image_url FROM property_images WHERE property_id = ?');
        $stmtImages->execute([$id]);
        $images = $stmtImages->fetchAll(PDO::FETCH_COLUMN);
        $property['images'] = $images ?: [];

        return $property;
    }

    /**
     * Busca propriedades simples para listagem de painel.
     *
     * @param int $tenantId
     * @param int|null $userId
     * @param bool $isAdmin
     * @param int $limit
     * @return array
     */
    public function getSimplePropertyList(int $tenantId, ?int $userId, bool $isAdmin, int $limit = 200): array
    {
        $qb = $this->getQueryBuilder();
        $qb->select(['p.id', 'p.title', 'p.price', 'p.status', 'p.type'])
            ->from('properties p')
            ->where('p.tenant_id = ?', [$tenantId]);

        if ($isAdmin) {
            $qb->andWhere("p.status IN ('active', 'pending')");
        } else {
            $qb->andWhere('p.status = ?', ['active'])
                ->andWhere('p.assigned_user_id = ?', [$userId]);
        }

        $qb->orderBy('p.title', 'ASC')
            ->limit($limit);

        return $this->execute($qb);
    }

    /**
     * BUSCA usada por controllers legados: SELECT * FROM properties WHERE tenant_id = ? [AND assigned_user_id = ? AND status = 'active'] ORDER BY id DESC
     * Mantém SQL idêntico ao legado.
     *
     * @param int $tenantId
     * @param int|null $assignedUserId
     * @param bool $isAdmin
     * @return array
     */
    public function fetchPropertiesForController(int $tenantId, ?int $assignedUserId = null, bool $isAdmin = false): array
    {
        $sql = "SELECT * FROM properties WHERE tenant_id = ?";
        $params = [$tenantId];

        if (!$isAdmin && $assignedUserId) {
            $sql .= " AND assigned_user_id = ?";
            $params[] = $assignedUserId;
            $sql .= " AND status = 'active'";
        }

        $sql .= " ORDER BY id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: [];
    }

    /**
     * Busca propriedades públicas com paginação e isolamento de tenant.
     * Preserva o SQL idêntico ao endpoint legado.
     *
     * @param int $tenantId
     * @param string $status
     * @param string|null $type
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function fetchPublicProperties(int $tenantId, string $status, ?string $type, int $limit, int $offset): array
    {
        $sql = 'SELECT id, title, type, price, description, bedrooms, bathrooms, area, status, neighborhood, city, state, created_at, updated_at 
            FROM properties WHERE tenant_id = ?';
        $params = [$tenantId];

        if ($status !== '') {
            $sql .= ' AND status = ?';
            $params[] = $status;
        }

        if ($type) {
            $sql .= ' AND type = ?';
            $params[] = $type;
        }

        $sql .= ' ORDER BY created_at DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: [];
    }

    /**
     * Conta propriedades públicas com isolamento de tenant.
     * Preserva o SQL idêntico ao endpoint legado.
     *
     * @param int $tenantId
     * @param string $status
     * @param string|null $type
     * @return int
     */
    public function countPublicProperties(int $tenantId, string $status, ?string $type): int
    {
        $sql = 'SELECT COUNT(*) as total FROM properties WHERE tenant_id = ?';
        $params = [$tenantId];

        if ($status !== '') {
            $sql .= ' AND status = ?';
            $params[] = $status;
        }

        if ($type) {
            $sql .= ' AND type = ?';
            $params[] = $type;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($row['total'] ?? 0);
    }

    /**
     * Insere propriedade pública usando SQL idêntico ao endpoint legado.
     *
     * @param array $data
     * @return int
     */
    public function insertPublicProperty(array $data): int
    {
        $sql = 'INSERT INTO properties (tenant_id, title, type, price, description, bedrooms, bathrooms, area, status) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $data['tenant_id'],
            $data['title'],
            $data['type'],
            $data['price'],
            $data['description'],
            $data['bedrooms'],
            $data['bathrooms'],
            $data['area'],
            $data['status']
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Recupera uma propriedade por id e tenant (SELECT * FROM properties WHERE id = :id AND tenant_id = :tenant_id [AND assigned_user_id = :user_id])
     *
     * @param int $id
     * @param int $tenantId
     * @param int|null $userId
     * @param bool $isAdmin
     * @return array|null
     */
    public function findPropertyForController(int $id, int $tenantId, ?int $userId = null, bool $isAdmin = false): ?array
    {
        $sql = 'SELECT * FROM properties WHERE id = :id AND tenant_id = :tenant_id';
        $params = ['id' => $id, 'tenant_id' => $tenantId];
        if (!$isAdmin && $userId !== null) {
            $sql .= ' AND assigned_user_id = :user_id';
            $params['user_id'] = $userId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * Insere propriedade com o mesmo SQL legado (inclui created_at = NOW())
     * Mantém a ordem e nomes de colunas idênticos.
     *
     * @param array $data (tenant_id, name, price, description, user_id)
     * @return int novo id
     */
    public function insertPropertyController(array $data): int
    {
        $sql = "INSERT INTO properties (tenant_id, name, price, description, assigned_user_id, created_at)\n                     VALUES (:tenant_id, :name, :price, :description, :user_id, NOW())";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':tenant_id' => $data['tenant_id'],
            ':name' => $data['name'],
            ':price' => $data['price'],
            ':description' => $data['description'],
            ':user_id' => $data['user_id']
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Recupera assigned_user_id para uma propriedade (usado em verificações de permissão)
     *
     * @param int $id
     * @param int $tenantId
     * @return int|null
     */
    public function getAssignedUserId(int $id, int $tenantId): ?int
    {
        $stmt = $this->pdo->prepare('SELECT assigned_user_id FROM properties WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($row && isset($row['assigned_user_id'])) ? (int)$row['assigned_user_id'] : null;
    }

    /**
     * Atualiza propriedade usando SQL idêntico ao legado (adiciona updated_at = NOW()).
     * Recebe a string de assignments e parâmetros já preparados pelo caller.
     *
     * @param string $assignments
     * @param array $params
     * @return void
     */
    public function updatePropertyController(string $assignments, array $params): void
    {
        $sql = 'UPDATE properties SET ' . $assignments . ', updated_at = NOW() WHERE id = :id AND tenant_id = :tenant_id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Atualiza uma propriedade a partir de um patch normalizado do controller legado.
     *
     * @param int $id
     * @param int $tenantId
     * @param array $changes
     * @return void
     */
    public function updatePropertyPatch(int $id, int $tenantId, array $changes): void
    {
        if ($changes === []) {
            return;
        }

        $setParts = [];
        $params = [':id' => $id, ':tenant_id' => $tenantId];

        foreach ($changes as $field => $value) {
            $placeholder = ':' . $field;
            $setParts[] = $field . ' = ' . $placeholder;
            $params[$placeholder] = $value;
        }

        $sql = 'UPDATE properties SET ' . implode(', ', $setParts) . ', updated_at = NOW() WHERE id = :id AND tenant_id = :tenant_id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Deleta propriedade mantendo SQL idêntico ao legado
     *
     * @param int $id
     * @param int $tenantId
     * @return void
     */
    public function deletePropertyController(int $id, int $tenantId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM properties WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
    }

    /**
     * Remove todos os registros de imagens associados a uma propriedade.
     *
     * @param int $propertyId
     * @return void
     */
    public function deletePropertyImagesByPropertyId(int $propertyId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM property_images WHERE property_id = ?');
        $stmt->execute([$propertyId]);
    }

    /**
     * Insere registros de imagens para uma propriedade.
     *
     * @param int $propertyId
     * @param array $filenames
     * @return void
     */
    public function insertPropertyImages(int $propertyId, array $filenames): void
    {
        if (empty($filenames)) {
            return;
        }

        $stmt = $this->pdo->prepare('INSERT INTO property_images (property_id, image_url) VALUES (?, ?)');
        foreach ($filenames as $filename) {
            $stmt->execute([$propertyId, $filename]);
        }
    }

    /**
     * Retorna lista de image_url para uma propriedade
     *
     * @param int $propertyId
     * @return string[]
     */
    public function getPropertyImagesByPropertyId(int $propertyId): array
    {
        $stmt = $this->pdo->prepare('SELECT image_url FROM property_images WHERE property_id = ? ORDER BY id ASC');
        $stmt->execute([$propertyId]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return $rows ?: [];
    }

    /**
     * Conta propriedades para um tenant específico.
     *
     * @param int $tenantId
     * @return int
     */
    public function countPropertiesByTenant(int $tenantId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) as total FROM properties WHERE tenant_id = ?');
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($row['total'] ?? 0);
    }

    /**
     * Retorna um registro de imagem por property_id e image_url
     *
     * @param int $propertyId
     * @param string $imageUrl
     * @return array|null
     */
    public function getPropertyImageRecord(int $propertyId, string $imageUrl): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, property_id, image_url FROM property_images WHERE property_id = ? AND image_url = ? LIMIT 1');
        $stmt->execute([$propertyId, $imageUrl]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * Remove registro de imagem por id
     *
     * @param int $imageId
     * @return bool
     */
    public function deletePropertyImageById(int $imageId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM property_images WHERE id = ?');
        $stmt->execute([$imageId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Busca estatísticas de categorias (aggregations) para um tenant
     *
     * @param int $tenantId
     * @return array
     */
    public function getPropertyCategories(int $tenantId): array
    {
        $sql = "
            SELECT
                type,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as ativos,
                SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inativos,
                SUM(CASE WHEN status = 'vendido' THEN 1 ELSE 0 END) as vendidos,
                SUM(CASE WHEN status = 'alugado' THEN 1 ELSE 0 END) as alugados,
                MIN(price) as preco_minimo,
                MAX(price) as preco_maximo,
                AVG(price) as preco_medio
            FROM properties
            WHERE tenant_id = ? AND type IS NOT NULL
            GROUP BY type
            ORDER BY total DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$tenantId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: [];
    }

    /**
     * Retorna as regiões (neighborhoods) distintas para um tenant
     *
     * @param int $tenantId
     * @return array
     */
    public function getPropertyRegions(int $tenantId): array
    {
        $stmt = $this->pdo->prepare("SELECT DISTINCT neighborhood FROM properties WHERE tenant_id = ? AND neighborhood IS NOT NULL AND neighborhood != '' ORDER BY neighborhood");
        $stmt->execute([$tenantId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: [];
    }

    /**
     * EXEMPLOS DE USO - QueryBuilder (PHASE 9)
     *
     * Exemplo 1: Query simples
     * --------------------------
     *   $qb = $repository->getQueryBuilder();
     *   $qb->select(['id', 'name'])
     *      ->from('properties')
     *      ->where('tenant_id = ?', [1])
     *      ->orderBy('name', 'ASC');
     *   $results = $repository->execute($qb);
     *
     * Exemplo 2: Query com JOINs e múltiplas condições
     * -------------------------------------------------
     *   $qb = $repository->getQueryBuilder();
     *   $qb->select(['p.*', 'u.name as owner'])
     *      ->from('properties p')
     *      ->innerJoin('users u', 'p.owner_id = u.id')
     *      ->where('p.tenant_id = ?', [1])
     *      ->andWhere('p.status = ?', ['active'])
     *      ->orderBy('p.price', 'DESC');
     *   $results = $repository->execute($qb);
     *
     * Exemplo 3: Query para debug (via logging)
     * ------------------------------------------
     *   $qb = $repository->getQueryBuilder();
     *   $qb->select(['*'])
     *      ->from('properties')
     *      ->where('tenant_id = ?', [1]);
     *   
     *   error_log('SQL: ' . $qb->getSql());
     *   error_log('Params: ' . json_encode($qb->getParams()));
     *   $results = $repository->execute($qb);
     */
}
