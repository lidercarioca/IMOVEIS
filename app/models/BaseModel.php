<?php

namespace App\Models;

use PDO;
use PDOException;

/**
 * BaseModel - Classe abstrata base para todos os modelos
 * 
 * Fornece CRUD genérico com prepared statements para segurança
 * 
 * Uso:
 * ```
 * class PropertyModel extends BaseModel {
 *     protected string $table = 'properties';
 * }
 * 
 * $model = new PropertyModel($pdo);
 * $property = $model->find(123);          // SELECT * FROM properties WHERE id = 123
 * $all = $model->all();                    // SELECT * FROM properties
 * $id = $model->create(['name' => 'Casa']); // INSERT
 * $model->update(123, ['price' => 100000]); // UPDATE
 * $model->delete(123);                     // DELETE (soft delete se tiver campo deleted_at)
 * ```
 */
abstract class BaseModel
{

    protected PDO $pdo;
    protected string $table = '';
    protected string $primaryKey = 'id';
    protected bool $useSoftDelete = false;
    protected string $deletedAtColumn = 'deleted_at';
    protected array $fillable = [];

    /**
     * Filtra somente campos autorizados para gravação pelo modelo
     *
     * @param array $data Dados recebidos
     * @return array Dados filtrados
     */
    protected function filterFillable(array $data): array
    {
        if (empty($this->fillable)) {
            return $data;
        }

        return array_intersect_key($data, array_flip($this->fillable));
    }

    /**
     * Valida cláusula ORDER BY simples para evitar injeção via string
     *
     * @param string $orderBy Cláusula ORDER BY
     * @return string Cláusula sanitizada
     */
    protected function validateOrderBy(string $orderBy): string
    {
        $orderBy = trim($orderBy);

        if ($orderBy === '') {
            return "{$this->primaryKey} DESC";
        }

        if (!preg_match('/^[a-zA-Z0-9_\.]+(?:\s+(?:ASC|DESC))?(?:\s*,\s*[a-zA-Z0-9_\.]+(?:\s+(?:ASC|DESC))?)*$/i', $orderBy)) {
            throw new PDOException("ORDER BY inválido: $orderBy");
        }

        return $orderBy;
    }

    /**
     * Construtor - Inicializa conexão PDO
     * 
     * @param PDO|null $pdo Conexão PDO (usa global $pdo se null)
     */
    public function __construct(?PDO $pdo = null)
    {
        if ($pdo) {
            $this->pdo = $pdo;
        } else {
            global $pdo;
            if (!$pdo) {
                throw new PDOException("PDO connection not available");
            }
            $this->pdo = $pdo;
        }

        if (empty($this->table)) {
            throw new PDOException("Table name must be defined in model");
        }
    }

    /**
     * Buscar um registro por ID
     * 
     * @param int $id ID do registro
     * @return array|null Registro encontrado ou null
     */
    public function find(int $id): ?array
    {
        $query = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id";

        if ($this->useSoftDelete) {
            $query .= " AND {$this->deletedAtColumn} IS NULL";
        }

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([':id' => $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            throw new PDOException("Erro ao buscar registro: " . $e->getMessage());
        }
    }

    /**
     * Buscar todos os registros
     * 
     * @param string $orderBy Campo para ordenar (padrão: id DESC)
     * @return array Lista de registros
     */
    public function all(string $orderBy = 'id DESC'): array
    {
        $query = "SELECT * FROM {$this->table}";

        if ($this->useSoftDelete) {
            $query .= " WHERE {$this->deletedAtColumn} IS NULL";
        }

        $query .= " ORDER BY {$this->validateOrderBy($orderBy)}";

        try {
            $stmt = $this->pdo->query($query);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new PDOException("Erro ao listar registros: " . $e->getMessage());
        }
    }

    /**
     * Buscar com paginação
     * 
     * @param int $page Número da página (começa em 1)
     * @param int $limit Registros por página (padrão: 20)
     * @return array ['data' => registros, 'total' => total, 'page' => página, 'pages' => total de páginas]
     */
    public function paginate(int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;

        // Contar total
        $countQuery = "SELECT COUNT(*) as total FROM {$this->table}";
        if ($this->useSoftDelete) {
            $countQuery .= " WHERE {$this->deletedAtColumn} IS NULL";
        }

        $countStmt = $this->pdo->query($countQuery);
        $total = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Buscar dados
        $query = "SELECT * FROM {$this->table}";
        if ($this->useSoftDelete) {
            $query .= " WHERE {$this->deletedAtColumn} IS NULL";
        }
        $query .= " ORDER BY {$this->primaryKey} DESC LIMIT :limit OFFSET :offset";

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $totalPages = ceil($total / $limit);

            return [
                'data' => $data,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => $totalPages
            ];
        } catch (PDOException $e) {
            throw new PDOException("Erro ao paginar: " . $e->getMessage());
        }
    }

    /**
     * Criar novo registro
     * 
     * @param array $data Dados do registro ['column' => 'value']
     * @return int ID do registro criado
     */
    public function create(array $data): int
    {
        if (empty($data)) {
            throw new PDOException("Dados vazios para inserção");
        }

        $data = $this->filterFillable($data);
        if (empty($data)) {
            throw new PDOException("Nenhum campo válido para inserção");
        }

        // Validar campos
        foreach ($data as $key => $value) {
            if (is_string($key) && !preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
                throw new PDOException("Nome de coluna inválido: $key");
            }
        }

        $columns = implode(',', array_keys($data));
        $placeholders = implode(',', array_map(fn($k) => ":$k", array_keys($data)));

        $query = "INSERT INTO {$this->table} ($columns) VALUES ($placeholders)";

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($data);
            return (int)$this->pdo->lastInsertId();
        } catch (PDOException $e) {
            throw new PDOException("Erro ao criar registro: " . $e->getMessage());
        }
    }

    /**
     * Atualizar registro
     * 
     * @param int $id ID do registro
     * @param array $data Dados a atualizar
     * @return bool True se atualizado com sucesso
     */
    public function update(int $id, array $data): bool
    {
        if (empty($data)) {
            throw new PDOException("Dados vazios para atualização");
        }

        $data = $this->filterFillable($data);
        if (empty($data)) {
            throw new PDOException("Nenhum campo válido para atualização");
        }

        // Validar campos
        foreach ($data as $key => $value) {
            if (is_string($key) && !preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
                throw new PDOException("Nome de coluna inválido: $key");
            }
        }
        $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($data)));
        $query = "UPDATE {$this->table} SET $set WHERE {$this->primaryKey} = :id";
        // Montar array de parâmetros sem os dois pontos
        $params = $data;
        $params['id'] = $id;
        try {
            $stmt = $this->pdo->prepare($query);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            throw new PDOException("Erro ao atualizar registro: " . $e->getMessage());
        }
    }

    /**
     * Deletar registro (soft delete ou permanente)
     * 
     * @param int $id ID do registro
     * @return bool True se deletado com sucesso
     */
    public function delete(int $id): bool
    {
        try {
            if ($this->useSoftDelete) {
                // Soft delete - apenas marca como deletado
                return $this->update($id, [
                    $this->deletedAtColumn => date('Y-m-d H:i:s')
                ]);
            } else {
                // Hard delete - remove permanentemente
                $query = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id";
                $stmt = $this->pdo->prepare($query);
                return $stmt->execute([':id' => $id]);
            }
        } catch (PDOException $e) {
            throw new PDOException("Erro ao deletar registro: " . $e->getMessage());
        }
    }

    /**
     * Restaurar registro deletado (soft delete)
     * 
     * @param int $id ID do registro
     * @return bool True se restaurado com sucesso
     */
    public function restore(int $id): bool
    {
        if (!$this->useSoftDelete) {
            throw new PDOException("Soft delete não está ativado para este modelo");
        }

        return $this->update($id, [
            $this->deletedAtColumn => null
        ]);
    }

    /**
     * Buscar por coluna específica
     * 
     * @param string $column Nome da coluna
     * @param mixed $value Valor a buscar
     * @return array|null Primeiro registro encontrado ou null
     */
    public function findBy(string $column, $value): ?array
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            throw new PDOException("Nome de coluna inválido: $column");
        }

        $query = "SELECT * FROM {$this->table} WHERE $column = :value";

        if ($this->useSoftDelete) {
            $query .= " AND {$this->deletedAtColumn} IS NULL";
        }

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([':value' => $value]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            throw new PDOException("Erro ao buscar por coluna: " . $e->getMessage());
        }
    }

    /**
     * Buscar todos por coluna
     * 
     * @param string $column Nome da coluna
     * @param mixed $value Valor a buscar
     * @return array Lista de registros
     */
    public function findAllBy(string $column, $value): array
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            throw new PDOException("Nome de coluna inválido: $column");
        }

        $query = "SELECT * FROM {$this->table} WHERE $column = :value";

        if ($this->useSoftDelete) {
            $query .= " AND {$this->deletedAtColumn} IS NULL";
        }

        $query .= " ORDER BY {$this->primaryKey} DESC";

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([':value' => $value]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new PDOException("Erro ao buscar múltiplos registros: " . $e->getMessage());
        }
    }

    /**
     * Contar registros totais
     * 
     * @return int Total de registros
     */
    public function count(): int
    {
        $query = "SELECT COUNT(*) as total FROM {$this->table}";

        if ($this->useSoftDelete) {
            $query .= " WHERE {$this->deletedAtColumn} IS NULL";
        }

        try {
            $stmt = $this->pdo->query($query);
            return (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
        } catch (PDOException $e) {
            throw new PDOException("Erro ao contar registros: " . $e->getMessage());
        }
    }

    /**
     * Executar query customizada (apenas leitura)
     * 
     * ⚠️ Usar com cuidado - sempre validar entrada
     * 
     * @param string $query SQL query
     * @param array $params Parâmetros bindados
     * @return array Resultados
     */
    public function query(string $query, array $params = []): array
    {
        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new PDOException("Erro ao executar query: " . $e->getMessage());
        }
    }

    /**
     * Obter última inserção
     * 
     * @return string|false ID da última inserção
     */
    public function lastInsertId(): string|false
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * Iniciar transação
     */
    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    /**
     * Confirmar transação
     */
    public function commit(): void
    {
        $this->pdo->commit();
    }

    /**
     * Reverter transação
     */
    public function rollback(): void
    {
        $this->pdo->rollBack();
    }

    /**
     * Verificar se está em transação
     */
    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }
}
