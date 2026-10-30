<?php

namespace App\Repository;

use PDO;

class UtmRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function listCampaigns(int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM utm_campaigns WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY created_at DESC'
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createCampaign(int $tenantId, string $campaignName, string $source, string $medium, ?string $content, ?string $description, int $createdBy): array
    {
        $utmLink = getenv('APP_URL') . '/api/v1/utm/trackClick.php?utm_source=' . urlencode($source)
            . '&utm_medium=' . urlencode($medium)
            . '&utm_campaign=' . urlencode($campaignName)
            . ($content ? '&utm_content=' . urlencode($content) : '')
            . '&url=' . urlencode((string) getenv('APP_URL'));

        $stmt = $this->pdo->prepare(
            'INSERT INTO utm_campaigns (tenant_id, campaign_name, source, medium, content, utm_link, description, created_by, created_at) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $tenantId,
            $campaignName,
            $source,
            $medium,
            $content,
            $utmLink,
            $description,
            $createdBy,
        ]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'campaign_name' => $campaignName,
            'utm_link' => $utmLink,
        ];
    }

    public function updateCampaign(int $tenantId, int $campaignId, array $fields): bool
    {
        if ($campaignId <= 0 || empty($fields)) {
            return false;
        }

        $setClauses = [];
        $params = [];

        foreach ($fields as $field => $value) {
            $setClauses[] = $field . ' = ?';
            $params[] = $value;
        }

        $setClauses[] = 'updated_at = NOW()';
        $params[] = $campaignId;
        $params[] = $tenantId;

        $sql = 'UPDATE utm_campaigns SET ' . implode(', ', $setClauses) . ' WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL';
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute($params);
    }

    public function deleteCampaign(int $tenantId, int $campaignId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE utm_campaigns SET deleted_at = NOW() WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$campaignId, $tenantId]);

        return $stmt->rowCount() > 0;
    }

    public function findCampaignIdByTenantAndName(int $tenantId, string $campaignName): ?int
    {
        if ($campaignName === '') {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id FROM utm_campaigns WHERE campaign_name = ? AND tenant_id = ? AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([$campaignName, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row && isset($row['id']) ? (int) $row['id'] : null;
    }

    public function insertTracking(int $tenantId, ?int $campaignId, array $data, string $userIp, string $userAgent, string $referrer): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO utm_tracking (tenant_id, campaign_id, utm_source, utm_medium, utm_campaign, utm_content, utm_term, clicked_at, user_ip, user_agent, referrer, destination_url) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?)'
        );

        $stmt->execute([
            $tenantId,
            $campaignId,
            $data['utm_source'] ?? null,
            $data['utm_medium'] ?? null,
            $data['utm_campaign'] ?? null,
            $data['utm_content'] ?? null,
            $data['utm_term'] ?? null,
            $userIp,
            $userAgent,
            $referrer,
            $data['destination_url'] ?? '',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function getAnalytics(int $tenantId, int $days, ?int $campaignId = null): array
    {
        $startDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $where = 'WHERE ut.tenant_id = ? AND ut.clicked_at >= ?';
        $params = [$tenantId, $startDate];

        if ($campaignId !== null && $campaignId > 0) {
            $where .= ' AND ut.campaign_id = ?';
            $params[] = $campaignId;
        }

        $totalClicksStmt = $this->pdo->prepare("SELECT COUNT(*) as total_clicks FROM utm_tracking ut {$where}");
        $totalClicksStmt->execute($params);
        $totalClicks = (int) $totalClicksStmt->fetchColumn();

        $uniqueVisitorsStmt = $this->pdo->prepare("SELECT COUNT(DISTINCT user_ip) as unique_visitors FROM utm_tracking ut {$where}");
        $uniqueVisitorsStmt->execute($params);
        $uniqueVisitors = (int) $uniqueVisitorsStmt->fetchColumn();

        $conversionsSql = 'SELECT COUNT(*) as conversions FROM leads l WHERE l.utm_tracking_id IS NOT NULL AND l.tenant_id = ? AND l.created_at >= ?';
        $conversionParams = [$tenantId, $startDate];
        if ($campaignId !== null && $campaignId > 0) {
            $conversionsSql = 'SELECT COUNT(*) as conversions FROM leads l INNER JOIN utm_tracking ut ON ut.id = l.utm_tracking_id WHERE l.tenant_id = ? AND l.created_at >= ? AND ut.tenant_id = ? AND ut.campaign_id = ?';
            $conversionParams = [$tenantId, $startDate, $tenantId, $campaignId];
        }

        $conversionsStmt = $this->pdo->prepare($conversionsSql);
        $conversionsStmt->execute($conversionParams);
        $conversions = (int) $conversionsStmt->fetchColumn();

        $conversionRate = $totalClicks > 0 ? ($conversions / $totalClicks) : 0;

        $bySourceStmt = $this->pdo->prepare("SELECT utm_source, COUNT(*) as clicks FROM utm_tracking ut {$where} GROUP BY utm_source ORDER BY utm_source ASC, clicks DESC");
        $bySourceStmt->execute($params);
        $bySource = [];
        while ($row = $bySourceStmt->fetch(PDO::FETCH_ASSOC)) {
            $bySource[$row['utm_source'] ?? 'Unknown'] = (int) $row['clicks'];
        }

        $byMediumStmt = $this->pdo->prepare("SELECT utm_medium, COUNT(*) as clicks FROM utm_tracking ut {$where} GROUP BY utm_medium ORDER BY utm_medium ASC, clicks DESC");
        $byMediumStmt->execute($params);
        $byMedium = [];
        while ($row = $byMediumStmt->fetch(PDO::FETCH_ASSOC)) {
            $byMedium[$row['utm_medium'] ?? 'Unknown'] = (int) $row['clicks'];
        }

        $timelineStmt = $this->pdo->prepare("SELECT DATE(clicked_at) as date, COUNT(*) as clicks FROM utm_tracking ut {$where} GROUP BY DATE(clicked_at) ORDER BY date ASC");
        $timelineStmt->execute($params);
        $timeline = [];
        while ($row = $timelineStmt->fetch(PDO::FETCH_ASSOC)) {
            $timeline[$row['date']] = (int) $row['clicks'];
        }

        $byCampaignStmt = $this->pdo->prepare(
            'SELECT COALESCE(ut.utm_campaign, "Direct") as campaign, COUNT(*) as total_clicks, COUNT(DISTINCT ut.user_ip) as unique_visitors, COUNT(l.id) as conversions, COUNT(l.id) / COUNT(*) as conversion_rate '
                . 'FROM utm_tracking ut LEFT JOIN leads l ON ut.id = l.utm_tracking_id '
                . 'WHERE ut.tenant_id = ? AND ut.clicked_at >= ?' . ($campaignId !== null && $campaignId > 0 ? ' AND ut.campaign_id = ?' : '')
                . ' GROUP BY ut.utm_campaign ORDER BY total_clicks DESC'
        );
        $campaignParams = [$tenantId, $startDate];
        if ($campaignId !== null && $campaignId > 0) {
            $campaignParams[] = $campaignId;
        }
        $byCampaignStmt->execute($campaignParams);
        $byCampaign = [];
        while ($row = $byCampaignStmt->fetch(PDO::FETCH_ASSOC)) {
            $campaign = $row['campaign'] ?? 'Unknown';
            $byCampaign[$campaign] = [
                'total_clicks' => (int) $row['total_clicks'],
                'unique_visitors' => (int) $row['unique_visitors'],
                'conversions' => (int) $row['conversions'],
                'conversion_rate' => (float) ($row['conversion_rate'] ?? 0),
            ];
        }

        return [
            'summary' => [
                'total_clicks' => $totalClicks,
                'unique_visitors' => $uniqueVisitors,
                'total_conversions' => $conversions,
                'conversion_rate' => (float) $conversionRate,
                'period_days' => $days,
            ],
            'by_source' => $bySource,
            'by_medium' => $byMedium,
            'timeline' => $timeline,
            'by_campaign' => $byCampaign,
        ];
    }
}
