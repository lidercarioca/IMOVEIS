<?php

namespace App\Infrastructure\Pipeline;

use PDO;

/**
 * PipelineStageRepository - Camada de Acesso a Dados para estágios de pipeline
 *
 * Responsável exclusivamente por:
 * - construir e executar consultas à tabela pipeline_stages
 * - gerenciar a conexão PDO
 * - retornar dados brutos para a camada de serviço
 */
class PipelineStageRepository
{
    private PDO $pdo;

    // Estágios padrão para novos tenants
    private const DEFAULT_STAGES = [
        ['name' => 'Prospecção', 'description' => 'Lead inicial, não qualificado', 'color' => '#3498db', 'position' => 1],
        ['name' => 'Qualificação', 'description' => 'Lead validado e com interesse', 'color' => '#9b59b6', 'position' => 2],
        ['name' => 'Proposta', 'description' => 'Proposta de venda enviada', 'color' => '#f39c12', 'position' => 3],
        ['name' => 'Negociação', 'description' => 'Em discussão de termos', 'color' => '#e74c3c', 'position' => 4],
        ['name' => 'Fechamento', 'description' => 'Venda concluída com sucesso', 'color' => '#27ae60', 'position' => 5],
        ['name' => 'Ganho', 'description' => 'Negócio ganho', 'color' => '#16a085', 'position' => 6],
        ['name' => 'Perdido', 'description' => 'Lead perdido / sem interesse', 'color' => '#95a5a6', 'position' => 7],
    ];

    /**
     * Construtor
     *
     * @param PDO $pdo Conexão com o banco de dados
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Criar novo estágio de pipeline
     *
     * @param int $tenantId ID do tenant
     * @param array $data Dados do estágio (name, color, position, description, is_active)
     * @return int ID do estágio criado
     */
    public function create(int $tenantId, array $data): int
    {
        $sql = 'INSERT INTO pipeline_stages (
            tenant_id,
            name,
            description,
            position,
            color,
            is_active
        ) VALUES (?, ?, ?, ?, ?, ?)';

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            $tenantId,
            $data['name'] ?? '',
            $data['description'] ?? '',
            $data['position'] ?? 0,
            $data['color'] ?? '#3498db',
            $data['is_active'] ?? 1,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Buscar estágios de um tenant
     *
     * @param int $tenantId ID do tenant
     * @return array Lista de estágios ordenados por position
     */
    public function findByTenant(int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, tenant_id, name, description, position, color, is_active, created_at, updated_at
            FROM pipeline_stages WHERE tenant_id = ? ORDER BY position ASC'
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Buscar estágio específico por ID
     *
     * @param int $id ID do estágio
     * @return array|null Dados do estágio ou null se não encontrado
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, tenant_id, name, description, position, color, is_active, created_at, updated_at
            FROM pipeline_stages WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ?: null;
    }

    /**
     * Criar estágios padrão para novo tenant
     *
     * Insere os 7 estágios padrão (Prospecção, Qualificação, Proposta, Negociação, Fechamento, Ganho, Perdido)
     *
     * @param int $tenantId ID do tenant
     * @return array IDs dos estágios criados
     */
    public function createDefaults(int $tenantId): array
    {
        $createdIds = [];

        foreach (self::DEFAULT_STAGES as $stage) {
            $id = $this->create($tenantId, $stage);
            $createdIds[] = $id;
        }

        return $createdIds;
    }

    /**
     * Atualizar estágio de pipeline
     *
     * @param int $id ID do estágio
     * @param array $data Dados a atualizar (name, color, position, description, is_active)
     * @return bool true se sucesso
     */
    public function update(int $id, array $data): bool
    {
        $updates = [];
        $values = [];

        foreach (['name', 'description', 'color', 'position', 'is_active'] as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $values[] = $data[$field];
            }
        }

        if (empty($updates)) {
            return false;
        }

        $values[] = $id;
        $updates[] = 'updated_at = NOW()';

        $sql = 'UPDATE pipeline_stages SET ' . implode(', ', $updates) . ' WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute($values);
    }

    /**
     * Deletar estágio de pipeline
     *
     * @param int $id ID do estágio
     * @return bool true se sucesso
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM pipeline_stages WHERE id = ?');
        return $stmt->execute([$id]);
    }
}
