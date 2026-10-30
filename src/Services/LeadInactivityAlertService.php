<?php

namespace App\Services;

use PDO;
use Exception;
use App\Infrastructure\Lead\LeadInactivityAlertRepository;

class LeadInactivityAlertService
{
    private PDO $pdo;
    private LeadInactivityAlertRepository $repository;
    private int $tenantId;
    private $userId;

    public function __construct(PDO $pdo, int $tenantId = 1, $userId = null)
    {
        $this->pdo = $pdo;
        $this->tenantId = $tenantId;
        $this->userId = $userId;
        $this->repository = new LeadInactivityAlertRepository($pdo);
    }

    public function generateInactivityAlerts(int $daysThreshold = 7, int $maxAlerts = 100): array
    {
        return $this->repository->generateInactivityAlertsForTenant($this->tenantId, $daysThreshold, $maxAlerts);
    }

    public function generateInactivityAlertsForAllTenants(int $daysThreshold = 7, int $maxAlertsPerTenant = 500): array
    {
        $summary = [
            'success' => true,
            'tenants' => [],
            'total_alerts_generated' => 0,
            'total_leads_checked' => 0,
        ];

        $tenantIds = $this->repository->getActiveTenantIds();

        foreach ($tenantIds as $tenantId) {
            $fallbackUserId = $this->repository->getDefaultAlertUserId($tenantId);
            $result = $this->repository->generateInactivityAlertsForTenant($tenantId, $daysThreshold, $maxAlertsPerTenant, $fallbackUserId);

            $summary['tenants'][] = [
                'tenant_id' => $tenantId,
                'alerts_generated' => $result['alerts_generated'] ?? 0,
                'leads_checked' => $result['leads_checked'] ?? 0,
            ];
            $summary['total_alerts_generated'] += $result['alerts_generated'] ?? 0;
            $summary['total_leads_checked'] += $result['leads_checked'] ?? 0;
        }

        return $summary;
    }

    private function calculateSeverity(int $daysInactive): string
    {
        if ($daysInactive < 7) return 'low';
        if ($daysInactive < 14) return 'medium';
        if ($daysInactive < 30) return 'high';
        return 'critical';
    }

    public function getActiveAlerts(?int $userId = null, int $limit = 20): array
    {
        return $this->repository->getActiveAlerts($this->tenantId, $userId, $limit);
    }

    public function acknowledgeAlert(int $alertId): array
    {
        try {
            $success = $this->repository->acknowledgeAlert($this->tenantId, $alertId);
            return ['success' => $success];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function resolveAlert(int $alertId): array
    {
        try {
            $success = $this->repository->resolveAlert($this->tenantId, $alertId);
            return ['success' => $success];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createLeadInteraction(int $leadId, ?int $userId, string $interactionType = 'called', string $title = '', string $description = ''): bool
    {
        return $this->repository->createLeadInteraction($this->tenantId, $leadId, $userId, $interactionType, $title, $description);
    }

    public function softDeleteAlert(int $alertId): array
    {
        try {
            $success = $this->repository->softDeleteAlert($this->tenantId, $alertId);
            return ['success' => $success];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
