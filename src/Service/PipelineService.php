<?php

namespace App\Service;

use App\Repository\PipelineRepository;
use InvalidArgumentException;
use PDO;

class PipelineService
{
    private PDO $pdo;
    private PipelineRepository $repository;

    public function __construct(PDO $pdo, ?PipelineRepository $repository = null)
    {
        $this->pdo = $pdo;
        $this->repository = $repository ?? new PipelineRepository($pdo);
    }

    public function createItem(int $tenantId, int $userId, array $payload): array
    {
        if ($tenantId <= 0) {
            throw new InvalidArgumentException('Tenant inválido');
        }

        if ($userId <= 0) {
            throw new InvalidArgumentException('Usuário não autenticado');
        }

        $stageId = isset($payload['pipeline_stage_id']) ? (int) $payload['pipeline_stage_id'] : 0;
        if ($stageId <= 0) {
            throw new InvalidArgumentException('pipeline_stage_id é obrigatório');
        }

        $leadId = isset($payload['lead_id']) && $payload['lead_id'] !== '' && $payload['lead_id'] !== null
            ? (int) $payload['lead_id']
            : null;
        $propertyId = isset($payload['property_id']) && $payload['property_id'] !== '' && $payload['property_id'] !== null
            ? (int) $payload['property_id']
            : null;

        if (($leadId === null || $leadId <= 0) && ($propertyId === null || $propertyId <= 0)) {
            throw new InvalidArgumentException('lead_id ou property_id é obrigatório');
        }

        if ($leadId !== null && $leadId > 0 && ($propertyId === null || $propertyId <= 0)) {
            $propertyId = $this->repository->findLeadPropertyIdByTenant($tenantId, $leadId);
        }

        if (!$this->repository->findStageByTenant($tenantId, $stageId)) {
            throw new InvalidArgumentException('Estágio inválido');
        }

        $pipelineId = $this->repository->createItem($tenantId, $userId, [
            'lead_id' => $leadId,
            'property_id' => $propertyId,
            'pipeline_stage_id' => $stageId,
            'value' => self::normalizeDecimalValue($payload['value'] ?? 0),
            'expected_close_date' => $payload['expected_close_date'] ?? null,
            'notes' => $payload['notes'] ?? null,
        ]);

        if ($pipelineId <= 0) {
            throw new InvalidArgumentException('Erro ao criar item do pipeline');
        }

        return [
            'pipeline_id' => $pipelineId,
            'message' => 'Item adicionado ao pipeline',
        ];
    }

    public function getOptions(int $tenantId, string $type = 'lead', string $search = '', int $limit = 100): array
    {
        if ($tenantId <= 0) {
            throw new InvalidArgumentException('Tenant inválido');
        }

        $normalizedType = strtolower(trim($type));
        $safeLimit = max(1, min((int) $limit, 500));
        $safeSearch = is_string($search) ? trim($search) : '';

        if ($normalizedType === 'lead') {
            return $this->repository->findAvailableLeads($tenantId, $safeSearch, $safeLimit);
        }

        if ($normalizedType === 'property') {
            return $this->repository->findAvailableProperties($tenantId, $safeSearch, $safeLimit);
        }

        throw new InvalidArgumentException('Tipo inválido. Use "lead" ou "property"');
    }

    public function getItems(int $tenantId, int $userId, bool $isAdmin, ?int $stageId = null, int $archived = 0): array
    {
        if ($tenantId <= 0) {
            throw new InvalidArgumentException('Tenant inválido');
        }

        if ($userId <= 0 && !$isAdmin) {
            return [];
        }

        return $this->repository->listItemsByTenant($tenantId, $stageId, $archived, $isAdmin ? null : $userId, $isAdmin);
    }

    public function getStages(int $tenantId): array
    {
        if ($tenantId <= 0) {
            throw new InvalidArgumentException('Tenant inválido');
        }

        return $this->repository->listStagesByTenant($tenantId);
    }

    public function getMetrics(int $tenantId, int $userId, bool $isAdmin): array
    {
        if ($tenantId <= 0) {
            throw new InvalidArgumentException('Tenant inválido');
        }

        return $this->repository->getMetricsByTenant($tenantId, $isAdmin ? null : $userId, $isAdmin);
    }

    public function updateItem(int $tenantId, int $userId, bool $isAdmin, int $pipelineId, array $payload): array
    {
        if ($tenantId <= 0) {
            throw new InvalidArgumentException('Tenant inválido');
        }

        if ($pipelineId <= 0) {
            throw new InvalidArgumentException('ID do pipeline não fornecido');
        }

        $item = $this->repository->findItemByTenant($tenantId, $pipelineId);
        if (!$item) {
            throw new InvalidArgumentException('Item não encontrado');
        }

        if (!$isAdmin && (int) ($item['user_id'] ?? 0) !== (int) $userId) {
            throw new InvalidArgumentException('Você não tem permissão para editar este item');
        }

        $fields = [];
        if (array_key_exists('notes', $payload)) {
            $fields['notes'] = $payload['notes'];
        }

        if (array_key_exists('value', $payload) && $payload['value'] !== null && $payload['value'] !== '') {
            $fields['value'] = self::normalizeDecimalValue($payload['value']);
        }

        if ($fields === []) {
            throw new InvalidArgumentException('Nenhum campo para atualizar');
        }

        $updated = $this->repository->updateItemByIdAndTenant($tenantId, $pipelineId, $fields);
        if (!$updated) {
            throw new InvalidArgumentException('Erro ao atualizar item');
        }

        return [
            'pipeline_id' => $pipelineId,
            'notes' => $fields['notes'] ?? $item['notes'] ?? null,
            'value' => $fields['value'] ?? (float) ($item['value'] ?? 0.0),
            'message' => 'Item atualizado com sucesso',
        ];
    }

    public function moveItem(int $tenantId, int $userId, bool $isAdmin, int $pipelineId, int $toStageId, $value = null, ?string $expectedCloseDate = null, ?string $notes = null): array
    {
        if ($tenantId <= 0) {
            throw new InvalidArgumentException('Tenant inválido');
        }

        if ($pipelineId <= 0) {
            throw new InvalidArgumentException('pipeline_id é obrigatório');
        }

        if ($toStageId <= 0) {
            throw new InvalidArgumentException('to_stage_id é obrigatório');
        }

        $item = $this->repository->findItemByTenant($tenantId, $pipelineId);
        if (!$item) {
            throw new InvalidArgumentException('Item do pipeline não encontrado');
        }

        if (!$isAdmin && (int) ($item['user_id'] ?? 0) !== (int) $userId) {
            throw new InvalidArgumentException('Acesso negado ao item do pipeline');
        }

        if (!$this->repository->findStageByTenant($tenantId, $toStageId)) {
            throw new InvalidArgumentException('Estágio inválido');
        }

        $fields = ['pipeline_stage_id' => $toStageId];
        if ($value !== null && $value !== '') {
            $fields['value'] = self::normalizeDecimalValue($value);
        }
        if ($expectedCloseDate !== null && $expectedCloseDate !== '') {
            $fields['expected_close_date'] = $expectedCloseDate;
        }
        if ($notes !== null) {
            $fields['notes'] = $notes;
        }

        $updated = $this->repository->moveItemByIdAndTenant($tenantId, $pipelineId, $toStageId, $fields);
        if (!$updated) {
            throw new InvalidArgumentException('Erro ao mover item do pipeline');
        }

        return [
            'success' => true,
            'message' => 'Item movido com sucesso',
        ];
    }

    public function deleteItem(int $tenantId, int $userId, bool $isAdmin, int $pipelineId): array
    {
        if ($tenantId <= 0) {
            throw new InvalidArgumentException('Tenant inválido');
        }

        if ($pipelineId <= 0) {
            throw new InvalidArgumentException('pipeline_id é obrigatório');
        }

        if (!$isAdmin) {
            return [
                'success' => true,
                'message' => 'Operação concluída',
            ];
        }

        $item = $this->repository->findItemByTenant($tenantId, $pipelineId);
        if (!$item) {
            throw new InvalidArgumentException('Item não encontrado ou acesso negado');
        }

        $deleted = $this->repository->deleteItemByIdAndTenant($tenantId, $pipelineId);
        if (!$deleted) {
            throw new InvalidArgumentException('Item não encontrado ou acesso negado');
        }

        return [
            'success' => true,
            'message' => 'Item deletado com sucesso',
        ];
    }

    public static function normalizeDecimalValue($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_string($value)) {
            $value = trim($value);
            $value = preg_replace('/[^0-9,\.]/u', '', $value);
            $value = str_replace(['.', ','], ['', '.'], $value);

            return is_numeric($value) ? (float) $value : 0.0;
        }

        return (float) $value;
    }
}
