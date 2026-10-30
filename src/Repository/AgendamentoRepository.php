<?php

namespace App\Repository;

use PDO;

class AgendamentoRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function countForUser(int $userId, int $tenantId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) as total FROM agendamentos WHERE user_id = ? AND tenant_id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$userId, $tenantId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return isset($result['total']) ? (int) $result['total'] : 0;
    }

    public function countForTenant(int $tenantId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) as total FROM agendamentos WHERE tenant_id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$tenantId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return isset($result['total']) ? (int) $result['total'] : 0;
    }
}
