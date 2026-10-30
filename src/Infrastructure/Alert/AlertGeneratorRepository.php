<?php

namespace App\Infrastructure\Alert;

use PDO;

class AlertGeneratorRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findStoppedPropertyAlerts(int $tenantId, ?int $userId, bool $isAdmin, int $threshold, int $limit = 10): array
    {
        $sql = "SELECT plm.property_id, p.title, p.price, p.updated_at, plm.days_without_movement
                FROM property_last_movement plm
                JOIN properties p ON plm.property_id = p.id
                WHERE plm.days_without_movement >= :threshold
                  AND p.status != 'inactive'
                  AND p.tenant_id = :tenant_id";

        if (!$isAdmin && $userId !== null) {
            $sql .= ' AND p.assigned_user_id = :user_id';
        }

        $sql .= ' ORDER BY plm.days_without_movement DESC LIMIT :limit';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':threshold', $threshold, PDO::PARAM_INT);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        if (!$isAdmin && $userId !== null) {
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findInactiveLeadAlerts(int $tenantId, ?int $userId, bool $isAdmin, int $threshold, int $limit = 10): array
    {
        $sql = "SELECT l.id, l.name, l.email, l.updated_at, DATEDIFF(NOW(), l.updated_at) as dias_inativo
                FROM leads l
                WHERE DATEDIFF(NOW(), l.updated_at) >= :threshold
                  AND l.status NOT IN ('converted', 'rejected')
                  AND l.tenant_id = :tenant_id";

        if (!$isAdmin && $userId !== null) {
            $sql .= ' AND l.assigned_user_id = :user_id';
        }

        $sql .= ' ORDER BY l.updated_at ASC LIMIT :limit';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':threshold', $threshold, PDO::PARAM_INT);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        if (!$isAdmin && $userId !== null) {
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findUncompletedScheduleAlerts(int $tenantId, ?int $userId, bool $isAdmin, int $limit = 10): array
    {
        $sql = "SELECT a.id, a.property_id, a.data_agendamento, l.name as contact_name, p.title
                FROM agendamentos a
                LEFT JOIN leads l ON a.leads_id = l.id
                LEFT JOIN properties p ON a.property_id = p.id
                WHERE DATE(a.data_agendamento) = CURDATE()
                  AND a.status != 'realizado'
                  AND a.tenant_id = :tenant_id";

        if (!$isAdmin && $userId !== null) {
            $sql .= ' AND a.user_id = :user_id';
        }

        $sql .= ' ORDER BY a.data_agendamento ASC LIMIT :limit';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        if (!$isAdmin && $userId !== null) {
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findLeadsWithoutVisitAlerts(int $tenantId, ?int $userId, bool $isAdmin, int $threshold, int $limit = 10): array
    {
        $sql = "SELECT l.id, l.name, l.email, l.created_at, DATEDIFF(NOW(), l.created_at) as dias_sem_visita
                FROM leads l
                LEFT JOIN agendamentos a ON l.id = a.leads_id AND a.deleted_at IS NULL
                WHERE a.id IS NULL
                  AND DATEDIFF(NOW(), l.created_at) >= :threshold
                  AND l.status NOT IN ('converted', 'rejected')
                  AND l.tenant_id = :tenant_id
                GROUP BY l.id
                ORDER BY l.created_at ASC
                LIMIT :limit";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':threshold', $threshold, PDO::PARAM_INT);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findPropertiesWithoutViewsAlerts(int $tenantId, ?int $userId, bool $isAdmin, int $threshold, int $limit = 10): array
    {
        $sql = "SELECT p.id, p.title, p.price, p.created_at, DATEDIFF(NOW(), p.created_at) as dias_sem_view
                FROM properties p
                LEFT JOIN agendamentos a ON p.id = a.property_id AND a.deleted_at IS NULL
                WHERE a.id IS NULL
                  AND DATEDIFF(NOW(), p.created_at) >= :threshold
                  AND p.status != 'inactive'
                  AND p.tenant_id = :tenant_id
                GROUP BY p.id
                ORDER BY p.created_at ASC
                LIMIT :limit";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':threshold', $threshold, PDO::PARAM_INT);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findHighConversionLeadsAlerts(int $tenantId, ?int $userId, bool $isAdmin, int $threshold): array
    {
        $sql = "SELECT assigned_user_id, COUNT(*) as lead_count
                FROM leads
                WHERE status = 'qualified'
                  AND assigned_user_id IS NOT NULL
                  AND tenant_id = :tenant_id";

        if (!$isAdmin && $userId !== null) {
            $sql .= ' AND assigned_user_id = :user_id';
        }

        $sql .= ' GROUP BY assigned_user_id HAVING lead_count >= :threshold ORDER BY lead_count DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        if (!$isAdmin && $userId !== null) {
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':threshold', $threshold, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findLowConversionRateAlerts(int $tenantId, ?int $userId, bool $isAdmin, int $threshold): array
    {
        $sql = "SELECT l.assigned_user_id,
                       COUNT(DISTINCT l.id) as total_leads,
                       COUNT(DISTINCT ps.id) as total_sales,
                       ROUND((COUNT(DISTINCT ps.id) / COUNT(DISTINCT l.id)) * 100, 2) as conversion_rate
                FROM leads l
                LEFT JOIN property_sales ps ON l.linked_sale_id = ps.id
                WHERE l.assigned_user_id IS NOT NULL
                  AND DATE_FORMAT(l.created_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')
                  AND l.tenant_id = :tenant_id";

        if (!$isAdmin && $userId !== null) {
            $sql .= ' AND l.assigned_user_id = :user_id';
        }

        $sql .= ' GROUP BY l.assigned_user_id HAVING conversion_rate < :threshold ORDER BY conversion_rate ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        if (!$isAdmin && $userId !== null) {
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':threshold', $threshold, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findSalesMilestoneAlerts(int $tenantId, ?int $userId, bool $isAdmin, int $threshold): array
    {
        $sql = "SELECT ps.user_id, COUNT(*) as sales_count, SUM(ps.property_price) as total_value
                FROM property_sales ps
                WHERE ps.status = 'completed'
                  AND DATE_FORMAT(ps.created_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')
                  AND ps.tenant_id = :tenant_id";

        if (!$isAdmin && $userId !== null) {
            $sql .= ' AND ps.user_id = :user_id';
        }

        $sql .= ' GROUP BY ps.user_id HAVING sales_count >= :threshold ORDER BY sales_count DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        if (!$isAdmin && $userId !== null) {
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':threshold', $threshold, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
