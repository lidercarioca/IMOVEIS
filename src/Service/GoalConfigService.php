<?php

namespace App\Service;

use App\Repository\GoalConfigRepository;
use PDO;

class GoalConfigService
{
    private const DEFAULT_GOALS = [
        'leads' => 'Leads Gerados',
        'sales' => 'Vendas Completadas',
        'revenue' => 'Receita Total',
        'conversion' => 'Taxa de Conversão (%)',
        'properties' => 'Imóveis Cadastrados',
    ];

    private PDO $pdo;
    private GoalConfigRepository $repository;

    public function __construct(PDO $pdo, ?GoalConfigRepository $repository = null)
    {
        $this->pdo = $pdo;
        $this->repository = $repository ?? new GoalConfigRepository($pdo);
    }

    public function listGoals(int $tenantId): array
    {
        $tenantId = (int) $tenantId;
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('Tenant inválido');
        }

        $goalsById = [];
        foreach ($this->repository->listGoals($tenantId) as $goal) {
            $goalId = (string) ($goal['goal_id'] ?? '');
            if ($goalId !== '') {
                $goalsById[$goalId] = $goal;
            }
        }

        $goals = [];
        foreach (self::DEFAULT_GOALS as $goalId => $goalName) {
            if (isset($goalsById[$goalId])) {
                $goals[] = $goalsById[$goalId];
                continue;
            }

            $goals[] = [
                'id' => null,
                'goal_id' => $goalId,
                'goal_name' => $goalName,
                'target_value' => $this->defaultTargetValue($goalId),
                'period_days' => 30,
                'is_active' => 1,
            ];
        }

        return $goals;
    }

    public function saveGoal(int $tenantId, string $goalId, float $targetValue, int $periodDays = 30, int $isActive = 1): array
    {
        $tenantId = (int) $tenantId;
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('Tenant inválido');
        }

        $goalId = trim((string) $goalId);
        if ($goalId === '') {
            throw new \InvalidArgumentException('goal_id inválido');
        }

        $goalName = self::DEFAULT_GOALS[$goalId] ?? ucfirst($goalId);
        $this->repository->upsertGoal($tenantId, $goalId, $goalName, (float) $targetValue, (int) $periodDays, (int) $isActive);

        return [
            'success' => true,
            'message' => 'Meta atualizada com sucesso',
        ];
    }

    public static function getDefaultGoals(): array
    {
        return self::DEFAULT_GOALS;
    }

    private function defaultTargetValue(string $goalId): float
    {
        if ($goalId === 'revenue') {
            return 100000.0;
        }

        if ($goalId === 'conversion') {
            return 5.0;
        }

        return 10.0;
    }
}
