<?php

namespace App\Repository;

use PDO;

class SiteVisitRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function hasRecentVisitForSession(string $sessionId, int $minutes = 5, ?int $tenantId = null): bool
    {
        if ($sessionId === '') {
            return false;
        }

        $sql = 'SELECT id '
            . 'FROM site_visits '
            . 'WHERE session_id = ? AND visited_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)';
        $params = [$sessionId, $minutes];

        if ($tenantId !== null) {
            $sql .= ' AND tenant_id = ?';
            $params[] = $tenantId;
        }

        $sql .= ' ORDER BY visited_at DESC LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn() !== false;
    }

    public function insertVisit(array $data): bool
    {
        $tenantId = (int)($data['tenant_id'] ?? 0);
        if ($tenantId <= 0) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO site_visits (tenant_id, visitor_ip, visitor_ua, page_url, referrer, session_id) '
                . 'VALUES (?, ?, ?, ?, ?, ?)'
        );

        return $stmt->execute([
            $tenantId,
            $data['visitor_ip'] ?? null,
            $data['visitor_ua'] ?? null,
            $data['page_url'] ?? '/',
            $data['referrer'] ?? null,
            $data['session_id'] ?? null,
        ]) && $stmt->rowCount() > 0;
    }
}
