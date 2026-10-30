<?php

namespace App\Infrastructure\Finance;

use PDO;
use PDOException;

/**
 * CommissionRepository - Data access layer for commission-related queries.
 *
 * This repository is the single owner of SQL access for the commissions table
 * and related commission queries.
 */
class CommissionRepository
{
    private PDO $pdo;
    private string $table = 'commissions';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function existsBySaleId(int $saleId, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT id FROM {$this->table} WHERE sale_id = :sale_id AND tenant_id = :tenant_id AND deleted_at IS NULL"
        );
        $stmt->execute([
            ':sale_id' => $saleId,
            ':tenant_id' => $tenantId
        ]);

        return (bool)$stmt->fetch();
    }

    public function getPropertyCommissionPercentage(int $propertyId, int $tenantId): ?float
    {
        $stmt = $this->pdo->prepare(
            'SELECT sale_commission_percentage FROM properties WHERE id = :id AND tenant_id = :tenant_id'
        );
        $stmt->execute([
            ':id' => $propertyId,
            ':tenant_id' => $tenantId
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return isset($row['sale_commission_percentage']) ? (float)$row['sale_commission_percentage'] : null;
    }

    public function insertCommission(array $data, int $tenantId)
    {
        $sql = "INSERT INTO {$this->table} 
                    (tenant_id, sale_id, user_id, property_id, sale_value, commission_percentage, commission_value, status)
                    VALUES (:tenant_id, :sale_id, :user_id, :property_id, :sale_value, :commission_percentage, :commission_value, 'pendente')";

        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute([
            ':tenant_id' => $tenantId,
            ':sale_id' => $data['sale_id'],
            ':user_id' => $data['user_id'],
            ':property_id' => $data['property_id'],
            ':sale_value' => $data['sale_value'],
            ':commission_percentage' => $data['commission_percentage'],
            ':commission_value' => $data['commission_value']
        ]);

        return $result ? (int)$this->pdo->lastInsertId() : false;
    }

    public function getCommissionsByFilters(array $filters = [], int $tenantId): array
    {
        $sql = "SELECT 
                        c.id,
                        c.sale_id,
                        c.user_id,
                        c.property_id,
                        c.sale_value,
                        c.commission_percentage,
                        c.commission_value,
                        c.status,
                        c.approval_notes,
                        c.approved_at,
                        c.paid_at,
                        c.paid_method,
                        c.created_at,
                        u.name as seller_name,
                        u.email as seller_email,
                        p.title as property_title,
                        ps.created_at as sale_date
                    FROM {$this->table} c
                    LEFT JOIN users u ON c.user_id = u.id
                    LEFT JOIN properties p ON c.property_id = p.id
                    LEFT JOIN property_sales ps ON c.sale_id = ps.id
                    WHERE c.deleted_at IS NULL
                    AND c.tenant_id = :tenant_id";

        $params = [':tenant_id' => $tenantId];

        if (!empty($filters['user_id'])) {
            $sql .= " AND c.user_id = :user_id";
            $params[':user_id'] = $filters['user_id'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND c.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['start_date'])) {
            $sql .= " AND DATE(c.created_at) >= :start_date";
            $params[':start_date'] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $sql .= " AND DATE(c.created_at) <= :end_date";
            $params[':end_date'] = $filters['end_date'];
        }

        // If caller provided limit/offset, repository will apply them as-is.
        if (isset($filters['limit']) && isset($filters['offset'])) {
            $sql .= " ORDER BY c.created_at DESC LIMIT :limit OFFSET :offset";
            $params[':limit'] = (int)$filters['limit'];
            $params[':offset'] = (int)$filters['offset'];
        } else {
            $sql .= " ORDER BY c.created_at DESC";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Conta comissões com os mesmos filtros aplicados.
     * Retorna apenas o número total (int).
     */
    public function countCommissionsByFilters(array $filters = [], int $tenantId): int
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} c
                    WHERE c.deleted_at IS NULL
                    AND c.tenant_id = :tenant_id";

        $params = [':tenant_id' => $tenantId];

        if (!empty($filters['user_id'])) {
            $sql .= " AND c.user_id = :user_id";
            $params[':user_id'] = $filters['user_id'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND c.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['start_date'])) {
            $sql .= " AND DATE(c.created_at) >= :start_date";
            $params[':start_date'] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $sql .= " AND DATE(c.created_at) <= :end_date";
            $params[':end_date'] = $filters['end_date'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $countResult = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int)($countResult['count'] ?? 0);
    }

    public function getPendingCommissions(int $tenantId): array
    {
        $sql = "SELECT 
                        c.*, 
                        u.name as seller_name, 
                        p.title as property_title
                    FROM {$this->table} c
                    LEFT JOIN users u ON c.user_id = u.id
                    LEFT JOIN properties p ON c.property_id = p.id
                    WHERE c.status = 'pendente'
                    AND c.deleted_at IS NULL
                    AND c.tenant_id = :tenant_id
                    ORDER BY c.created_at ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':tenant_id' => $tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCommissionStats(int $tenantId, ?int $userId = null, ?string $startDate = null, ?string $endDate = null): array
    {
        // Repository must not apply defaults or post-process aggregates.
        // Only include date filters if provided by caller.
        $sql = "SELECT 
                        c.status,
                        COUNT(*) as count,
                        SUM(c.commission_value) as total_value,
                        AVG(c.commission_value) as average_value,
                        MIN(c.commission_value) as min_value,
                        MAX(c.commission_value) as max_value
                    FROM {$this->table} c
                    WHERE c.deleted_at IS NULL
                    AND c.tenant_id = :tenant_id";

        $params = [':tenant_id' => $tenantId];

        if (!empty($userId)) {
            $sql .= " AND c.user_id = :user_id";
            $params[':user_id'] = $userId;
        }

        if (!empty($startDate)) {
            $sql .= " AND DATE(c.created_at) >= :start_date";
            $params[':start_date'] = $startDate;
        }
        if (!empty($endDate)) {
            $sql .= " AND DATE(c.created_at) <= :end_date";
            $params[':end_date'] = $endDate;
        }

        $sql .= " AND c.deleted_at IS NULL GROUP BY c.status";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        // Return raw aggregate rows; Model will apply defaults and assemble totals
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function approveCommission(int $commissionId, int $approvedBy, int $tenantId, string $notes = ''): bool
    {
        try {
            $sql = "UPDATE {$this->table} 
                    SET status = 'aprovada',
                        approved_by = :approved_by,
                        approval_notes = :notes,
                        approved_at = NOW(),
                        updated_at = NOW()
                    WHERE id = :id
                    AND tenant_id = :tenant_id
                    AND deleted_at IS NULL";

            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                ':id' => $commissionId,
                ':tenant_id' => $tenantId,
                ':approved_by' => $approvedBy,
                ':notes' => $notes
            ]);
        } catch (PDOException $e) {
            error_log("Erro ao aprovar comissão: " . $e->getMessage());
            return false;
        }
    }

    public function payCommission(int $commissionId, int $tenantId, string $method = 'transferência'): bool
    {
        try {
            $sql = "UPDATE {$this->table} 
                    SET status = 'paga',
                        paid_method = :method,
                        paid_at = NOW(),
                        updated_at = NOW()
                    WHERE id = :id 
                    AND tenant_id = :tenant_id
                    AND status = 'aprovada'
                    AND deleted_at IS NULL";

            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                ':id' => $commissionId,
                ':tenant_id' => $tenantId,
                ':method' => $method
            ]);
        } catch (PDOException $e) {
            error_log("Erro ao pagar comissão: " . $e->getMessage());
            return false;
        }
    }

    public function cancelCommission(int $commissionId, int $tenantId, string $reason = ''): bool
    {
        try {
            $sql = "UPDATE {$this->table} 
                    SET status = 'cancelada',
                        approval_notes = CONCAT(IFNULL(approval_notes, ''), '\nMotivo cancelamento: ', :reason),
                        deleted_at = NOW(),
                        updated_at = NOW()
                    WHERE id = :id
                    AND tenant_id = :tenant_id";

            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                ':id' => $commissionId,
                ':tenant_id' => $tenantId,
                ':reason' => $reason
            ]);
        } catch (PDOException $e) {
            error_log("Erro ao cancelar comissão: " . $e->getMessage());
            return false;
        }
    }

    public function getPayrollSummary(string $month, int $tenantId, ?int $userId = null): array
    {
        $sql = "SELECT 
                        c.user_id,
                        u.name as seller_name,
                        u.email,
                        COUNT(*) as total_sales,
                        SUM(c.commission_value) as total_commission,
                        SUM(CASE WHEN c.status = 'paga' THEN c.commission_value ELSE 0 END) as paid,
                        SUM(CASE WHEN c.status IN ('pendente', 'aprovada') THEN c.commission_value ELSE 0 END) as pending
                    FROM {$this->table} c
                    LEFT JOIN users u ON c.user_id = u.id
                    WHERE DATE_FORMAT(c.created_at, '%Y-%m') = :month
                    AND c.deleted_at IS NULL
                    AND c.tenant_id = :tenant_id";

        if ($userId) {
            $sql .= " AND c.user_id = :user_id";
        }

        $sql .= " GROUP BY c.user_id, u.name, u.email
                     ORDER BY u.name ASC";

        $stmt = $this->pdo->prepare($sql);
        $params = [':month' => $month, ':tenant_id' => $tenantId];
        if ($userId) {
            $params[':user_id'] = $userId;
        }
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCommissionBySaleId(int $saleId, int $tenantId): ?array
    {
        try {
            $sql = "SELECT * FROM {$this->table} 
                    WHERE sale_id = :sale_id 
                    AND tenant_id = :tenant_id
                    AND deleted_at IS NULL
                    LIMIT 1";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':sale_id' => $saleId,
                ':tenant_id' => $tenantId
            ]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log("Erro ao buscar comissão por sale_id: " . $e->getMessage());
            return null;
        }
    }

    public function getCommissionById(int $commissionId, int $tenantId): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id' => $commissionId,
            ':tenant_id' => $tenantId
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function softDeleteCommission(int $commissionId, int $tenantId): bool
    {
        $sql = "UPDATE {$this->table} SET deleted_at = NOW() WHERE id = :id AND tenant_id = :tenant_id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id' => $commissionId,
            ':tenant_id' => $tenantId
        ]);
    }

    public function getSaleById(int $saleId, int $tenantId): ?array
    {
        $sql = "SELECT * FROM property_sales WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id' => $saleId,
            ':tenant_id' => $tenantId
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getSaleByIdIncludingDeleted(int $saleId, int $tenantId): ?array
    {
        $sql = "SELECT * FROM property_sales WHERE id = :id AND tenant_id = :tenant_id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id' => $saleId,
            ':tenant_id' => $tenantId
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getSaleByPropertyId(int $propertyId, int $tenantId): ?array
    {
        $sql = "SELECT * FROM property_sales WHERE property_id = :property_id AND tenant_id = :tenant_id AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':property_id' => $propertyId,
            ':tenant_id' => $tenantId
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function softDeleteSaleById(int $saleId, int $tenantId): bool
    {
        $sql = "UPDATE property_sales SET deleted_at = NOW() WHERE id = :id AND tenant_id = :tenant_id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id' => $saleId,
            ':tenant_id' => $tenantId
        ]);
    }

    public function getCommissionSettingsByPropertyId(int $propertyId, int $tenantId): ?array
    {
        $sql = "SELECT * FROM commission_settings WHERE property_id = :property_id AND tenant_id = :tenant_id ORDER BY created_at DESC LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':property_id' => $propertyId,
            ':tenant_id' => $tenantId
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getCommissionLogs(int $commissionId, int $tenantId): array
    {
        $sql = "SELECT * FROM alert_logs WHERE entity_type = 'commission' AND entity_id = :id AND tenant_id = :tenant_id ORDER BY created_at DESC LIMIT 20";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id' => $commissionId,
            ':tenant_id' => $tenantId
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCommissionReportData(array $filters = [], int $tenantId): array
    {
        $sql = "SELECT \
            c.id,\
            c.sale_id,\
            c.user_id,\
            u.name as vendedor,\
            c.property_id,\
            p.title as propriedade,\
            c.sale_value,\
            c.commission_percentage,\
            c.commission_value,\
            c.status,\
            c.approved_at,\
            c.approved_by,\
            c.paid_at,\
            c.paid_method,\
            c.created_at,\
            ab.name as approved_by_name\
        FROM {$this->table} c\
        LEFT JOIN users u ON c.user_id = u.id\
        LEFT JOIN properties p ON c.property_id = p.id\
        LEFT JOIN users ab ON c.approved_by = ab.id\
        WHERE c.deleted_at IS NULL\
        AND c.tenant_id = :tenant_id";
        $sql = "SELECT 
                c.id,
                c.sale_id,
                c.user_id,
                u.name as vendedor,
                c.property_id,
                p.title as propriedade,
                c.sale_value,
                c.commission_percentage,
                c.commission_value,
                c.status,
                c.approved_at,
                c.approved_by,
                c.paid_at,
                c.paid_method,
                c.created_at,
                ab.name as approved_by_name
            FROM {$this->table} c
            LEFT JOIN users u ON c.user_id = u.id
            LEFT JOIN properties p ON c.property_id = p.id
            LEFT JOIN users ab ON c.approved_by = ab.id
            WHERE c.deleted_at IS NULL
            AND c.tenant_id = :tenant_id";

        $params = [':tenant_id' => $tenantId];

        if (!empty($filters['user_id'])) {
            $sql .= " AND c.user_id = :user_id";
            $params[':user_id'] = $filters['user_id'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND c.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['start_date'])) {
            $sql .= " AND c.created_at >= :start_date";
            $params[':start_date'] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $sql .= " AND c.created_at <= DATE_ADD(:end_date, INTERVAL 1 DAY)";
            $params[':end_date'] = $filters['end_date'];
        }

        $sql .= " ORDER BY c.created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
