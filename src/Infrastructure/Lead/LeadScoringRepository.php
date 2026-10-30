<?php

namespace App\Infrastructure\Lead;

use PDO;

class LeadScoringRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getScoringStats(int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                COUNT(*) as total_leads,
                AVG(score) as avg_score,
                SUM(CASE WHEN score_category = 'hot' THEN 1 ELSE 0 END) as hot_count,
                SUM(CASE WHEN score_category = 'warm' THEN 1 ELSE 0 END) as warm_count,
                SUM(CASE WHEN score_category = 'cold' THEN 1 ELSE 0 END) as cold_count
            FROM leads
            WHERE tenant_id = ?"
        );
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: [
            'total_leads' => 0,
            'avg_score' => 0,
            'hot_count' => 0,
            'warm_count' => 0,
            'cold_count' => 0,
        ];
    }

    public function getScoreDistribution(int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT score_category, COUNT(*) as count
            FROM leads
            WHERE tenant_id = ?
            GROUP BY score_category"
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function getTopLeadsByScore(int $tenantId, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, name, score, score_category
            FROM leads
            WHERE tenant_id = ?
            ORDER BY score DESC
            LIMIT ?"
        );
        $stmt->bindValue(1, $tenantId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllLeadIdsByTenant(int $tenantId): array
    {
        $stmt = $this->pdo->prepare('SELECT id FROM leads WHERE tenant_id = ?');
        $stmt->execute([$tenantId]);

        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
    }

    public function getLeadData(int $leadId, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT l.id,
                    l.tenant_id,
                    l.status,
                    l.estimated_value,
                    l.score AS current_score,
                    l.score_category AS current_category,
                    (SELECT MAX(created_at) FROM lead_interactions WHERE lead_id = l.id) as last_interaction
            FROM leads l
            WHERE l.id = ? AND l.tenant_id = ? LIMIT 1'
        );
        $stmt->execute([$leadId, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function getScoringConfig(int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT interaction_type, weight, max_points FROM lead_scoring_config WHERE tenant_id = ?'
        );
        $stmt->execute([$tenantId]);

        $configRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $configs = [];
        foreach ($configRows as $row) {
            $configs[$row['interaction_type']] = [
                'weight' => $row['weight'],
                'max_points' => $row['max_points']
            ];
        }

        return $configs;
    }

    public function getInteractionCountsForLead(int $leadId, int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT interaction_type, COUNT(*) as count FROM lead_interactions WHERE lead_id = ? AND tenant_id = ? GROUP BY interaction_type'
        );
        $stmt->execute([$leadId, $tenantId]);

        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function updateLeadScore(int $leadId, int $tenantId, int $score, string $scoreCategory): bool
    {
        $stmt = $this->pdo->prepare('UPDATE leads SET score = ?, score_category = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?');
        return $stmt->execute([$score, $scoreCategory, $leadId, $tenantId]);
    }

    public function logScoreChange(int $leadId, int $tenantId, ?int $oldScore, int $newScore, ?string $oldCategory, string $newCategory): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO lead_score_history (lead_id, tenant_id, old_score, new_score, old_category, new_category, reason, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );

        return $stmt->execute([
            $leadId,
            $tenantId,
            $oldScore,
            $newScore,
            $oldCategory,
            $newCategory,
            'Recálculo automático'
        ]);
    }
}
