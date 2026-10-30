<?php

namespace App\Repository;

use PDO;

class GoalConfigRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function listGoals(int $tenantId): array
    {
        $tenantId = (int) $tenantId;
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('Tenant inválido');
        }

        $stmt = $this->pdo->prepare(
            "SELECT id, goal_id, goal_name, target_value, period_days, is_active
            FROM goals_config
            WHERE tenant_id = :tenant_id
            ORDER BY FIELD(goal_id, 'leads', 'sales', 'revenue', 'conversion', 'properties')"
        );
        $stmt->execute([':tenant_id' => $tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function upsertGoal(int $tenantId, string $goalId, string $goalName, float $targetValue, int $periodDays = 30, int $isActive = 1): bool
    {
        $tenantId = (int) $tenantId;
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('Tenant inválido');
        }

        $goalId = trim((string) $goalId);
        if ($goalId === '') {
            throw new \InvalidArgumentException('goal_id inválido');
        }

        $goalName = trim((string) $goalName);
        if ($goalName === '') {
            $goalName = ucfirst($goalId);
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO goals_config
            (tenant_id, goal_id, goal_name, target_value, period_days, is_active)
            VALUES (:tenant_id, :goal_id, :goal_name, :target_value, :period_days, :is_active)
            ON DUPLICATE KEY UPDATE
                goal_name = VALUES(goal_name),
                target_value = VALUES(target_value),
                period_days = VALUES(period_days),
                is_active = VALUES(is_active),
                updated_at = NOW()"
        );

        return $stmt->execute([
            ':tenant_id' => $tenantId,
            ':goal_id' => $goalId,
            ':goal_name' => $goalName,
            ':target_value' => (float) $targetValue,
            ':period_days' => (int) $periodDays,
            ':is_active' => (int) $isActive,
        ]);
    }
}
