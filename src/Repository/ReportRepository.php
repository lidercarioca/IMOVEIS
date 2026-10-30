<?php

namespace App\Repository;

use PDO;
use RuntimeException;

/**
 * ReportRepository - Camada de Acesso a Dados para relatórios
 *
 * Responsável exclusivamente por:
 * - construir e executar consultas ao banco de dados
 * - gerenciar a conexão PDO
 * - retornar dados brutos para a camada de serviço
 *
 * NÃO é responsável por:
 * - lógica de negócio
 * - validação de regras
 * - formatação avançada de resultados
 */
class ReportRepository
{
    private PDO $pdo;

    /**
     * Construtor
     *
     * @param PDO $pdo Conexão com o banco de dados
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Busca dados de relatório geral
     *
     * @param array $criteria Critérios de relatório
     * @return array Dados brutos do relatório
     */
    public function getReports(array $criteria = []): array
    {
        $type = isset($criteria['type']) ? (string)$criteria['type'] : '';
        $period = isset($criteria['period']) ? (string)$criteria['period'] : 'month';
        $tenantId = isset($criteria['tenantId']) ? (int)$criteria['tenantId'] : 0;

        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('Tenant inválido');
        }

        switch ($type) {
            case 'inventory':
                return $this->getInventoryReport($tenantId);
            case 'leads':
                return $this->getLeadsReport($tenantId, $period);
            case 'sales':
                return $this->getSalesReport($tenantId, $period);
            case 'financial':
                return $this->getFinancialReport($tenantId, $period);
            case 'users':
                return $this->getUsersReport($tenantId);
            case 'properties-status':
                return $this->getPropertiesStatusReport($tenantId, $period);
            default:
                throw new \InvalidArgumentException('Tipo de relatório inválido');
        }
    }

    /**
     * Busca estatísticas do dashboard
     *
     * @param array $filters Filtros opcionais para a busca
     * @return array Dados brutos do dashboard
     */
    public function getDashboardStats(array $filters = []): array
    {
        $tenantId = isset($filters['tenantId']) ? (int)$filters['tenantId'] : 0;
        $startDate = isset($filters['startDate']) ? (string)$filters['startDate'] : '';
        $isAdmin = isset($filters['isAdmin']) ? (bool)$filters['isAdmin'] : false;
        $userId = isset($filters['userId']) ? (int)$filters['userId'] : null;

        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('Tenant inválido');
        }

        if ($startDate === '') {
            throw new \InvalidArgumentException('Período inválido');
        }

        $leadWhere = "WHERE created_at >= ? AND tenant_id = ?";
        $leadParams = [$startDate, $tenantId];

        if (!$isAdmin && $userId) {
            $leadWhere .= " AND (leads.property_id IS NULL OR leads.property_id IN (SELECT id FROM properties WHERE assigned_user_id = ? AND tenant_id = ?))";
            $leadParams[] = $userId;
            $leadParams[] = $tenantId;
        }

        $leadStats = $this->fetchAll(
            "SELECT
                DATE(created_at) as date,
                COUNT(*) as total_leads
            FROM leads
            {$leadWhere}
            GROUP BY DATE(created_at)
            ORDER BY date",
            $leadParams
        );

        $propertiesWhere = "WHERE created_at >= ? AND tenant_id = ?";
        $propertiesParams = [$startDate, $tenantId];

        if (!$isAdmin && $userId) {
            $propertiesWhere .= " AND assigned_user_id = ?";
            $propertiesParams[] = $userId;
        }

        $propertyStats = $this->fetchAll(
            "SELECT
                DATE(created_at) as date,
                COUNT(*) as new_properties
            FROM properties
            {$propertiesWhere}
            GROUP BY DATE(created_at)
            ORDER BY date",
            $propertiesParams
        );

        $dealsWhere = "WHERE (status = 'vendido' OR status = 'alugado') AND updated_at >= ? AND tenant_id = ?";
        $dealsParams = [$startDate, $tenantId];

        if (!$isAdmin && $userId) {
            $dealsWhere .= " AND assigned_user_id = ?";
            $dealsParams[] = $userId;
        }

        $dealsStats = $this->fetchAll(
            "SELECT
                DATE(updated_at) as date,
                COUNT(*) as closed_deals
            FROM properties
            {$dealsWhere}
            GROUP BY DATE(updated_at)
            ORDER BY date",
            $dealsParams
        );

        $categoryWhere = "WHERE status != 'inactive' AND tenant_id = ?";
        $categoryParams = [$tenantId];

        if (!$isAdmin && $userId) {
            $categoryWhere .= " AND assigned_user_id = ?";
            $categoryParams[] = $userId;
        }

        $categoryStats = $this->fetchAll(
            "SELECT
                CASE
                    WHEN type = 'apartment' THEN 'Apartamentos'
                    WHEN type = 'house' THEN 'Casas'
                    WHEN type = 'commercial' THEN 'Comercial'
                    WHEN type = 'land' THEN 'Terrenos'
                END as categoria,
                COUNT(*) as total
            FROM properties
            {$categoryWhere}
            GROUP BY type
            ORDER BY type",
            $categoryParams
        );

        return [
            'leadStats' => $leadStats,
            'propertyStats' => $propertyStats,
            'dealsStats' => $dealsStats,
            'categoryStats' => $categoryStats,
        ];
    }

    /**
     * Busca estatísticas de visitas
     *
     * @param array $filters Filtros de período e tenant
     * @return array Dados brutos de visita
     */
    public function getVisitStats(array $filters = []): array
    {
        $tenantId = isset($filters['tenantId']) ? (int)$filters['tenantId'] : 0;
        $period = isset($filters['period']) ? $filters['period'] : '30';

        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('Tenant inválido');
        }

        $totalVisitsRow = $this->fetchRow(
            "SELECT COUNT(DISTINCT sv.session_id) as total_visits
            FROM site_visits sv
            INNER JOIN properties p ON sv.property_id = p.id
            WHERE sv.visited_at >= DATE_SUB(NOW(), INTERVAL ? DAY) AND p.tenant_id = ?",
            [$period, $tenantId]
        );

        $visitsTodayRow = $this->fetchRow(
            "SELECT COUNT(DISTINCT sv.session_id) as visits_today
            FROM site_visits sv
            INNER JOIN properties p ON sv.property_id = p.id
            WHERE DATE(sv.visited_at) = CURDATE() AND p.tenant_id = ?",
            [$tenantId]
        );

        $visitsLastMonthRow = $this->fetchRow(
            "SELECT COUNT(DISTINCT sv.session_id) as visits_last_month
            FROM site_visits sv
            INNER JOIN properties p ON sv.property_id = p.id
            WHERE sv.visited_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
            AND sv.visited_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND p.tenant_id = ?",
            [$tenantId]
        );

        $topPages = $this->fetchAll(
            "SELECT sv.page_url, COUNT(*) as visits
            FROM site_visits sv
            INNER JOIN properties p ON sv.property_id = p.id
            WHERE sv.visited_at >= DATE_SUB(NOW(), INTERVAL ? DAY) AND p.tenant_id = ?
            GROUP BY sv.page_url
            ORDER BY visits DESC
            LIMIT 5",
            [$period, $tenantId]
        );

        return [
            'total_visits' => isset($totalVisitsRow['total_visits']) ? (int)$totalVisitsRow['total_visits'] : 0,
            'visits_today' => isset($visitsTodayRow['visits_today']) ? (int)$visitsTodayRow['visits_today'] : 0,
            'visits_last_month' => isset($visitsLastMonthRow['visits_last_month']) ? (int)$visitsLastMonthRow['visits_last_month'] : 0,
            'top_pages' => $topPages,
        ];
    }

    /**
     * Busca vendas pendentes
     *
     * @param array $filters Filtros de tenant e usuário
     * @return array Dados brutos de vendas pendentes
     */
    public function getPendingSales(array $filters = []): array
    {
        $tenantId = isset($filters['tenantId']) ? (int)$filters['tenantId'] : 0;

        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('Tenant inválido');
        }

        return $this->fetchAll(
            "SELECT 
                ps.id,
                ps.property_id,
                ps.username,
                ps.property_title,
                ps.property_price,
                ps.commission_6percent,
                p.sale_commission_percentage AS sale_commission_percentage,
                ps.status,
                ps.created_at,
                p.title,
                p.price
            FROM property_sales ps
            LEFT JOIN properties p ON ps.property_id = p.id AND p.tenant_id = ?
            WHERE ps.status = 'pending' AND ps.tenant_id = ?
            ORDER BY ps.created_at DESC",
            [$tenantId, $tenantId]
        );
    }

    /**
     * Busca as metas e objetivos do período com progresso real vs meta
     *
     * @param int $tenantId
     * @param int $period
     * @param bool $isAdmin
     * @param int|null $userId
     * @return array
     */
    public function getGoalsAndTargets(int $tenantId, int $period, bool $isAdmin = false, ?int $userId = null): array
    {
        $tenantId = (int)$tenantId;
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('Tenant inválido');
        }

        $period = (int)$period;
        $dataInicio = date('Y-m-d', strtotime("-{$period} days"));
        $dataFim = date('Y-m-d');

        $stmtConfig = $this->pdo->prepare("
            SELECT goal_id, goal_name, target_value, period_days
            FROM goals_config
            WHERE is_active = TRUE
                AND tenant_id = ?
            ORDER BY goal_id
        ");
        $stmtConfig->execute([$tenantId]);

        $goalsConfig = [];
        while ($row = $stmtConfig->fetch(PDO::FETCH_ASSOC)) {
            $goalsConfig[$row['goal_id']] = $row;
        }

        if (empty($goalsConfig)) {
            $goalsConfig = [
                'leads' => ['goal_id' => 'leads', 'goal_name' => 'Leads Gerados', 'target_value' => 50, 'period_days' => 30],
                'sales' => ['goal_id' => 'sales', 'goal_name' => 'Vendas/Aluguéis', 'target_value' => 5, 'period_days' => 30],
                'revenue' => ['goal_id' => 'revenue', 'goal_name' => 'Receita Total', 'target_value' => 500000, 'period_days' => 30],
            ];
        }

        $stmtLeads = $this->pdo->prepare(
            "
            SELECT COUNT(*) as total FROM leads
            WHERE created_at >= ?
            AND created_at <= DATE_ADD(?, INTERVAL 1 DAY)
            AND tenant_id = ?
            " . (!$isAdmin ? "AND assigned_user_id = ?" : "")
        );

        $params = [$dataInicio, $dataFim, $tenantId];
        if (!$isAdmin) {
            $params[] = $userId;
        }
        $stmtLeads->execute($params);
        $realLeads = (int)$stmtLeads->fetch(PDO::FETCH_ASSOC)['total'];

        $stmtSales = $this->pdo->prepare(
            "
            SELECT COUNT(*) as total FROM property_sales
            WHERE created_at >= ?
            AND created_at <= DATE_ADD(?, INTERVAL 1 DAY)
            AND tenant_id = ?
            AND deleted_at IS NULL
            " . (!$isAdmin ? "AND user_id = ?" : "")
        );

        $params = [$dataInicio, $dataFim, $tenantId];
        if (!$isAdmin) {
            $params[] = $userId;
        }
        $stmtSales->execute($params);
        $realSales = (int)$stmtSales->fetch(PDO::FETCH_ASSOC)['total'];

        $stmtRevenue = $this->pdo->prepare(
            "
            SELECT COALESCE(SUM(property_price), 0) as total FROM property_sales
            WHERE created_at >= ?
            AND created_at <= DATE_ADD(?, INTERVAL 1 DAY)
            AND deleted_at IS NULL
            AND tenant_id = ?
            " . (!$isAdmin ? "AND user_id = ?" : "")
        );

        $params = [$dataInicio, $dataFim, $tenantId];
        if (!$isAdmin) {
            $params[] = $userId;
        }
        $stmtRevenue->execute($params);
        $realRevenue = (float)$stmtRevenue->fetch(PDO::FETCH_ASSOC)['total'];

        $stmtConversion = $this->pdo->prepare(
            "
            SELECT
                COUNT(DISTINCT l.id) as leads_count,
                COUNT(DISTINCT ps.id) as sales_count
            FROM leads l
            LEFT JOIN property_sales ps ON ps.user_id = l.assigned_user_id
                AND ps.created_at >= l.created_at
                AND ps.created_at <= DATE_ADD(?, INTERVAL 1 DAY)
                AND ps.deleted_at IS NULL
                AND ps.tenant_id = ?
            WHERE l.created_at >= ?
            AND l.created_at <= DATE_ADD(?, INTERVAL 1 DAY)
            AND l.tenant_id = ?
            " . (!$isAdmin ? "AND l.assigned_user_id = ?" : "")
        );

        $params = [$dataFim, $tenantId, $dataInicio, $dataFim, $tenantId];
        if (!$isAdmin) {
            $params[] = $userId;
        }
        $stmtConversion->execute($params);
        $convData = $stmtConversion->fetch(PDO::FETCH_ASSOC);
        $leadsForConversion = (int)$convData['leads_count'];
        $realConversion = $leadsForConversion > 0 ? round(($convData['sales_count'] / $leadsForConversion) * 100, 2) : 0;

        $stmtProperties = $this->pdo->prepare(
            "
            SELECT COUNT(*) as total FROM properties
            WHERE created_at >= ?
            AND created_at <= DATE_ADD(?, INTERVAL 1 DAY)
            AND tenant_id = ?
            " . (!$isAdmin ? "AND assigned_user_id = ?" : "")
        );

        $params = [$dataInicio, $dataFim, $tenantId];
        if (!$isAdmin) {
            $params[] = $userId;
        }
        $stmtProperties->execute($params);
        $realProperties = (int)$stmtProperties->fetch(PDO::FETCH_ASSOC)['total'];

        return [
            'goalsConfig' => $goalsConfig,
            'data' => [
                'leads' => ['real' => $realLeads, 'target' => (int)($goalsConfig['leads']['target_value'] ?? 50), 'percent' => 0],
                'sales' => ['real' => $realSales, 'target' => (int)($goalsConfig['sales']['target_value'] ?? 5), 'percent' => 0],
                'revenue' => ['real' => $realRevenue, 'target' => (float)($goalsConfig['revenue']['target_value'] ?? 500000), 'percent' => 0],
                'conversion' => ['real' => $realConversion, 'target' => (float)($goalsConfig['conversion']['target_value'] ?? 20), 'percent' => 0],
                'properties' => ['real' => $realProperties, 'target' => (int)($goalsConfig['properties']['target_value'] ?? 20), 'percent' => 0],
            ],
            'date_range' => [
                'start' => $dataInicio,
                'end' => $dataFim,
            ],
        ];
    }

    /**
     * Busca feed de atividades do dashboard
     *
     * @param int $tenantId
     * @param int $limit
     * @param int $offset
     * @param bool $isAdmin
     * @param int|null $userId
     * @return array
     */
    public function getActivityFeed(int $tenantId, int $limit = 5, int $offset = 0, bool $isAdmin = false, ?int $userId = null): array
    {
        $tenantId = (int)$tenantId;
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('Tenant inválido');
        }

        $limit = (int)$limit;
        $offset = (int)$offset;

        if ($limit > 5) {
            $limit = 5;
        }

        if ($limit < 1) {
            $limit = 20;
        }

        if ($offset < 0) {
            $offset = 0;
        }

        $query = "
            SELECT
                'property_created' as activity_type,
                p.id as activity_id,
                p.title as description,
                u.name as user_name,
                u.id as user_id,
                'home' as icon,
                '#3b82f6' as color,
                p.created_at as timestamp
            FROM properties p
            LEFT JOIN users u ON p.assigned_user_id = u.id
            WHERE p.tenant_id = ? " . (!$isAdmin ? "AND p.assigned_user_id = ?" : "") . "

            UNION ALL

            SELECT
                'lead_created' as activity_type,
                l.id as activity_id,
                CONCAT(l.name, ' - ', l.email) as description,
                u.name as user_name,
                u.id as user_id,
                'user-friends' as icon,
                '#10b981' as color,
                l.created_at as timestamp
            FROM leads l
            LEFT JOIN users u ON l.assigned_user_id = u.id
            WHERE l.tenant_id = ? " . (!$isAdmin ? "AND l.assigned_user_id = ?" : "") . "

            UNION ALL

            SELECT
                'sale_created' as activity_type,
                ps.id as activity_id,
                CONCAT('Venda/Aluguel - R$ ', FORMAT(ps.property_price, 0)) as description,
                u.name as user_name,
                u.id as user_id,
                'handshake' as icon,
                '#f59e0b' as color,
                ps.created_at as timestamp
            FROM property_sales ps
            LEFT JOIN users u ON ps.user_id = u.id
            WHERE ps.tenant_id = ? AND ps.deleted_at IS NULL " . (!$isAdmin ? "AND ps.user_id = ?" : "") . "

            UNION ALL

            SELECT
                'agendamento_created' as activity_type,
                a.id as activity_id,
                CONCAT('Agendamento - ', a.property_id) as description,
                u.name as user_name,
                u.id as user_id,
                'calendar' as icon,
                '#8b5cf6' as color,
                a.created_at as timestamp
            FROM agendamentos a
            LEFT JOIN users u ON a.user_id = u.id
            WHERE a.tenant_id = ? AND a.deleted_at IS NULL " . (!$isAdmin ? "AND a.user_id = ?" : "") . "

            ORDER BY timestamp DESC
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->pdo->prepare($query);
        $params = [];
        $index = 1;

        $stmt->bindValue($index++, $tenantId, PDO::PARAM_INT);
        if (!$isAdmin && $userId !== null) {
            $stmt->bindValue($index++, (int)$userId, PDO::PARAM_INT);
        }

        $stmt->bindValue($index++, $tenantId, PDO::PARAM_INT);
        if (!$isAdmin && $userId !== null) {
            $stmt->bindValue($index++, (int)$userId, PDO::PARAM_INT);
        }

        $stmt->bindValue($index++, $tenantId, PDO::PARAM_INT);
        if (!$isAdmin && $userId !== null) {
            $stmt->bindValue($index++, (int)$userId, PDO::PARAM_INT);
        }

        $stmt->bindValue($index++, $tenantId, PDO::PARAM_INT);
        if (!$isAdmin && $userId !== null) {
            $stmt->bindValue($index++, (int)$userId, PDO::PARAM_INT);
        }

        $stmt->bindValue($index++, $limit, PDO::PARAM_INT);
        $stmt->bindValue($index++, $offset, PDO::PARAM_INT);

        $stmt->execute();
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $countQuery = "
            SELECT COUNT(*) as total FROM (
                SELECT p.id FROM properties p WHERE p.tenant_id = ? " . (!$isAdmin ? "AND p.assigned_user_id = ?" : "") . "
                UNION ALL
                SELECT l.id FROM leads l WHERE l.tenant_id = ? " . (!$isAdmin ? "AND l.assigned_user_id = ?" : "") . "
                UNION ALL
                SELECT ps.id FROM property_sales ps WHERE ps.tenant_id = ? AND ps.deleted_at IS NULL " . (!$isAdmin ? "AND ps.user_id = ?" : "") . "
                UNION ALL
                SELECT a.id FROM agendamentos a WHERE a.tenant_id = ? AND a.deleted_at IS NULL " . (!$isAdmin ? "AND a.user_id = ?" : "") . "
            ) as combined
        ";

        $countStmt = $this->pdo->prepare($countQuery);
        $countIndex = 1;
        $countStmt->bindValue($countIndex++, $tenantId, PDO::PARAM_INT);
        if (!$isAdmin && $userId !== null) {
            $countStmt->bindValue($countIndex++, (int)$userId, PDO::PARAM_INT);
        }

        $countStmt->bindValue($countIndex++, $tenantId, PDO::PARAM_INT);
        if (!$isAdmin && $userId !== null) {
            $countStmt->bindValue($countIndex++, (int)$userId, PDO::PARAM_INT);
        }

        $countStmt->bindValue($countIndex++, $tenantId, PDO::PARAM_INT);
        if (!$isAdmin && $userId !== null) {
            $countStmt->bindValue($countIndex++, (int)$userId, PDO::PARAM_INT);
        }

        $countStmt->bindValue($countIndex++, $tenantId, PDO::PARAM_INT);
        if (!$isAdmin && $userId !== null) {
            $countStmt->bindValue($countIndex++, (int)$userId, PDO::PARAM_INT);
        }

        $countStmt->execute();
        $total = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        return [
            'activities' => $activities,
            'total' => $total,
        ];
    }

    /**
     * Busca snapshot do dashboard em tempo real
     *
     * @param int $tenantId
     * @param int $userId
     * @param int $period
     * @return array
     */
    public function getRealtimeDashboardKPIs(int $tenantId, int $userId, int $period): array
    {
        $period = max(1, min($period, 365));
        $dataFim = date('Y-m-d');
        $dataInicio = date('Y-m-d', strtotime("-{$period} days"));

        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(DISTINCT p.id) as total_properties,
                COUNT(DISTINCT l.id) as total_leads,
                COUNT(DISTINCT ps.id) as total_sales,
                ROUND(AVG(ps.sale_price), 2) as avg_ticket,
                ROUND(AVG(DATEDIFF(l.converted_at, l.created_at)), 2) as cycle_days,
                COUNT(CASE WHEN pm.days_without_movement >= 30 THEN 1 END) as stopped_count,
                COUNT(DISTINCT l.id) as total_open_leads,
                (SELECT COUNT(*) FROM leads WHERE assigned_user_id = ? AND status = 'converted' AND converted_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) AND tenant_id = ?) as today_conversions
            FROM properties p
            LEFT JOIN leads l ON p.id = l.property_id AND l.assigned_user_id = ? AND l.tenant_id = ?
            LEFT JOIN property_sales ps ON p.id = ps.property_id AND ps.assigned_user_id = ? AND ps.tenant_id = ?
            LEFT JOIN property_last_movement pm ON p.id = pm.property_id AND pm.tenant_id = ?
            WHERE p.assigned_user_id = ?
            AND p.tenant_id = ?
            AND p.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
        ");

        $stmt->execute([$userId, $tenantId, $userId, $tenantId, $userId, $tenantId, $tenantId, $userId, $tenantId, $dataInicio, $dataFim]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: [];
    }

    /**
     * Busca top brokers do dashboard em tempo real
     *
     * @param int $tenantId
     * @param int $period
     * @return array
     */
    public function getRealtimeTopBrokers(int $tenantId, int $period): array
    {
        $period = max(1, min($period, 365));
        $dataFim = date('Y-m-d');
        $dataInicio = date('Y-m-d', strtotime("-{$period} days"));

        $stmt = $this->pdo->prepare("
            SELECT
                u.id, u.name,
                COUNT(ps.id) as sales_count,
                ROUND(AVG(ps.sale_price), 2) as avg_price,
                ROUND(SUM(ps.sale_price), 2) as total_revenue
            FROM users u
            LEFT JOIN property_sales ps ON u.id = ps.user_id AND ps.tenant_id = ?
            WHERE u.tenant_id = ?
                AND ps.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
            GROUP BY u.id, u.name
            ORDER BY sales_count DESC
            LIMIT 5
        ");

        $stmt->execute([$tenantId, $tenantId, $dataInicio, $dataFim]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca leads recentes do dashboard em tempo real
     *
     * @param int $tenantId
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public function getRealtimeRecentLeads(int $tenantId, int $userId, int $limit = 5): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                l.id, p.title, l.contact_name, l.status, l.created_at,
                (SELECT COUNT(*) FROM visits v WHERE v.lead_id = l.id) as visit_count
            FROM leads l
            JOIN properties p ON l.property_id = p.id AND p.tenant_id = ?
            WHERE l.assigned_user_id = ?
              AND l.tenant_id = ?
            ORDER BY l.created_at DESC
            LIMIT ?
        ");

        $stmt->execute([$tenantId, $userId, $tenantId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca KPIs filtrados do dashboard
     *
     * @param int $tenantId
     * @param int $period
     * @param int|null $userId
     * @param string|null $categoria
     * @param string|null $regiao
     * @return array
     */
    public function getExportDashboardData(int $tenantId, int $period, ?int $userId = null, bool $isAdmin = false, string $reportType = 'summary'): array
    {
        $tenantId = (int)$tenantId;
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('Tenant inválido');
        }

        $period = max(1, min((int)$period, 365));
        $reportType = trim($reportType);

        switch ($reportType) {
            case 'kpis':
                $stmt = $this->pdo->prepare(
                    "
                    SELECT
                        COUNT(DISTINCT properties.id) as total_properties,
                        COUNT(DISTINCT leads.id) as total_leads,
                        COUNT(DISTINCT property_sales.id) as total_sales
                    FROM properties
                    LEFT JOIN leads ON leads.assigned_user_id = properties.user_id AND leads.tenant_id = ?
                    LEFT JOIN property_sales ON property_sales.user_id = properties.user_id AND property_sales.tenant_id = ?
                    WHERE properties.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                        AND properties.tenant_id = ?
                    " . (!$isAdmin ? "AND properties.user_id = ?" : "")
                );

                $params = [$tenantId, $tenantId, $period, $tenantId];
                if (!$isAdmin && $userId !== null) {
                    $params[] = (int)$userId;
                }
                $stmt->execute($params);

                $kpiData = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
                $reportData = [
                    ['Métrica' => 'Total de Imóveis', 'Valor' => (int)($kpiData['total_properties'] ?? 0)],
                    ['Métrica' => 'Total de Leads', 'Valor' => (int)($kpiData['total_leads'] ?? 0)],
                    ['Métrica' => 'Total de Vendas', 'Valor' => (int)($kpiData['total_sales'] ?? 0)],
                ];
                return ['title' => 'Relatório de KPIs', 'data' => $reportData];

            case 'portfolio':
                $stmt = $this->pdo->prepare(
                    "
                    SELECT
                        property_type,
                        COUNT(*) as quantidade,
                        AVG(price) as preco_medio,
                        SUM(price) as preco_total
                    FROM properties
                    WHERE tenant_id = ?
                    " . (!$isAdmin ? "AND user_id = ?" : "") . "
                    GROUP BY property_type
                    "
                );

                if (!$isAdmin && $userId !== null) {
                    $stmt->execute([$tenantId, (int)$userId]);
                } else {
                    $stmt->execute([$tenantId]);
                }

                $portfolios = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $reportData = [];
                foreach ($portfolios as $p) {
                    $reportData[] = [
                        'Tipo' => $p['property_type'],
                        'Quantidade' => (int)($p['quantidade'] ?? 0),
                        'Preço Médio' => 'R$ ' . number_format((float)($p['preco_medio'] ?? 0), 2, ',', '.'),
                        'Preço Total' => 'R$ ' . number_format((float)($p['preco_total'] ?? 0), 2, ',', '.'),
                    ];
                }
                return ['title' => 'Relatório de Portfolio', 'data' => $reportData];

            case 'leads':
                $stmt = $this->pdo->prepare(
                    "
                    SELECT
                        id,
                        name,
                        email,
                        phone,
                        status,
                        source,
                        created_at
                    FROM leads
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                        AND tenant_id = ?
                    " . (!$isAdmin ? "AND assigned_user_id = ?" : "") . "
                    ORDER BY created_at DESC
                    LIMIT 500
                    "
                );

                $params = [$period, $tenantId];
                if (!$isAdmin && $userId !== null) {
                    $params[] = (int)$userId;
                }
                $stmt->execute($params);

                $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $reportData = [];
                foreach ($leads as $lead) {
                    $reportData[] = [
                        'ID' => $lead['id'],
                        'Nome' => $lead['name'],
                        'Email' => $lead['email'],
                        'Telefone' => $lead['phone'],
                        'Status' => $lead['status'],
                        'Origem' => $lead['source'],
                        'Data' => date('d/m/Y H:i', strtotime($lead['created_at'])),
                    ];
                }
                return ['title' => 'Relatório de Leads', 'data' => $reportData];

            case 'goals':
                return ['title' => 'Relatório de Metas', 'data' => [
                    ['Meta' => 'Leads Gerados', 'Real' => 0, 'Target' => 50, 'Status' => 'Em Progresso'],
                    ['Meta' => 'Vendas/Aluguéis', 'Real' => 0, 'Target' => 5, 'Status' => 'Em Progresso'],
                    ['Meta' => 'Receita Total', 'Real' => 'R$ 0', 'Target' => 'R$ 500.000', 'Status' => 'Em Progresso'],
                ]];

            default:
                return ['title' => 'Relatório Resumido', 'data' => [
                    ['Tipo de Relatório' => 'Resumido'],
                    ['Data' => date('d/m/Y H:i')],
                ]];
        }
    }

    public function getExportSummarySnapshot(int $tenantId, int $period, ?int $userId = null, ?string $categoria = null, ?string $regiao = null, bool $isAdmin = false): array
    {
        $tenantId = (int)$tenantId;
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('Tenant inválido');
        }

        $period = max(1, min((int)$period, 365));
        $dataFim = date('Y-m-d');
        $dataInicio = date('Y-m-d', strtotime("-{$period} days"));

        $where = "1=1";
        if (!$isAdmin && $userId !== null && (int)$userId > 0) {
            $where .= " AND p.assigned_user_id = ?";
        }
        if ($categoria) {
            $where .= " AND p.category = ?";
        }
        if ($regiao) {
            $where .= " AND p.neighborhood = ?";
        }

        $params = [];
        if (!$isAdmin && $userId !== null && (int)$userId > 0) {
            $params[] = (int)$userId;
        }
        if ($categoria) {
            $params[] = (string)$categoria;
        }
        if ($regiao) {
            $params[] = (string)$regiao;
        }
        $params[] = $tenantId;

        $stmt = $this->pdo->prepare(
            "
            SELECT
                COUNT(DISTINCT p.id) as total_properties,
                COUNT(DISTINCT l.id) as total_leads,
                COUNT(DISTINCT ps.id) as total_sales,
                ROUND(AVG(ps.sale_price), 2) as avg_ticket,
                ROUND(AVG(DATEDIFF(l.converted_at, l.created_at)), 2) as avg_cycle,
                COUNT(CASE WHEN pm.days_without_movement >= 30 THEN 1 END) as stopped_count
            FROM properties p
            LEFT JOIN leads l ON p.id = l.property_id AND l.tenant_id = ?
            LEFT JOIN property_sales ps ON ps.property_id = p.id AND ps.tenant_id = ?
            LEFT JOIN property_last_movement pm ON pm.property_id = p.id AND pm.tenant_id = ?
            WHERE {$where}
            AND p.tenant_id = ?
            AND p.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
            AND p.deleted_at IS NULL
            "
        );

        $executeParams = [];
        $executeParams[] = $tenantId;
        $executeParams[] = $tenantId;
        $executeParams[] = $tenantId;
        $executeParams = array_merge($executeParams, $params, [$dataInicio, $dataFim]);
        $stmt->execute($executeParams);
        $kpiData = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $stmtBrokers = $this->pdo->prepare(
            "
            SELECT
                u.id,
                u.name,
                COUNT(DISTINCT ps.id) as sales_count,
                ROUND(AVG(ps.sale_price), 2) as avg_price
            FROM users u
            LEFT JOIN property_sales ps ON u.id = ps.user_id
                AND ps.tenant_id = ?
                AND ps.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
            WHERE u.tenant_id = ?
            GROUP BY u.id, u.name
            ORDER BY sales_count DESC
            LIMIT 5
            "
        );
        $stmtBrokers->execute([$tenantId, $dataInicio, $dataFim, $tenantId]);

        $stmtLeads = $this->pdo->prepare(
            "
            SELECT l.id, p.title, l.contact_name, l.status, l.created_at
            FROM leads l
            JOIN properties p ON l.property_id = p.id AND p.tenant_id = ?
            WHERE l.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
              AND l.tenant_id = ?
            " . (!$isAdmin && $userId !== null && (int)$userId > 0 ? "AND l.assigned_user_id = ?" : "") . "
            LIMIT 100
            "
        );
        $leadParams = [$tenantId, $dataInicio, $dataFim, $tenantId];
        if (!$isAdmin && $userId !== null && (int)$userId > 0) {
            $leadParams[] = (int)$userId;
        }
        $stmtLeads->execute($leadParams);

        return [
            'kpiData' => $kpiData,
            'brokers' => $stmtBrokers->fetchAll(PDO::FETCH_ASSOC),
            'leadRows' => $stmtLeads->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    public function getFilteredDashboardKPIs(int $tenantId, int $period, ?int $userId = null, ?string $categoria = null, ?string $regiao = null): array
    {
        $tenantId = (int)$tenantId;
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('Tenant inválido');
        }

        $period = max(1, min((int)$period, 365));
        $dataFim = date('Y-m-d');
        $dataInicio = date('Y-m-d', strtotime("-{$period} days"));

        $whereClauseSales = ['ps.deleted_at IS NULL', 'ps.tenant_id = ?'];
        $whereClauseLeads = ["l.status != 'cancelled'", 'l.tenant_id = ?'];
        $paramsSales = [$tenantId];
        $paramsLeads = [$tenantId];

        $whereClauseSales[] = 'ps.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)';
        $paramsSales[] = $dataInicio;
        $paramsSales[] = $dataFim;

        $whereClauseLeads[] = 'l.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)';
        $paramsLeads[] = $dataInicio;
        $paramsLeads[] = $dataFim;

        if ($userId) {
            $whereClauseSales[] = 'ps.user_id = ?';
            $paramsSales[] = (int)$userId;
            $whereClauseLeads[] = 'l.assigned_user_id = ?';
            $paramsLeads[] = (int)$userId;
        }

        if ($categoria) {
            $whereClauseSales[] = 'p.category = ?';
            $paramsSales[] = (string)$categoria;
        }

        if ($regiao) {
            $whereClauseSales[] = 'p.neighborhood = ?';
            $paramsSales[] = (string)$regiao;
        }

        $whereSalesStr = implode(' AND ', $whereClauseSales);
        $whereLeadsStr = implode(' AND ', $whereClauseLeads);
        $paramsSales[] = $tenantId;

        $sqlLeads = 'SELECT COUNT(*) as total FROM leads l WHERE ' . $whereLeadsStr;
        $stmtLeads = $this->pdo->prepare($sqlLeads);
        $stmtLeads->execute($paramsLeads);
        $totalLeads = (int)($stmtLeads->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $sqlSales = 'SELECT COUNT(*) as total FROM property_sales ps '
            . 'JOIN properties p ON ps.property_id = p.id AND p.tenant_id = ? '
            . 'WHERE ' . $whereSalesStr;
        $stmtSales = $this->pdo->prepare($sqlSales);
        $stmtSales->execute($paramsSales);
        $totalSales = (int)($stmtSales->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $conversionRate = $totalLeads > 0 ? round(($totalSales / $totalLeads) * 100, 2) : 0;

        $sqlTicket = 'SELECT AVG(ps.property_price) as media FROM property_sales ps '
            . 'JOIN properties p ON ps.property_id = p.id AND p.tenant_id = ? '
            . 'WHERE ' . $whereSalesStr;
        $stmtTicket = $this->pdo->prepare($sqlTicket);
        $stmtTicket->execute($paramsSales);
        $averageTicket = (float)($stmtTicket->fetch(PDO::FETCH_ASSOC)['media'] ?? 0);

        $sqlRevenue = 'SELECT COALESCE(SUM(ps.property_price), 0) as total FROM property_sales ps '
            . 'JOIN properties p ON ps.property_id = p.id AND p.tenant_id = ? '
            . 'WHERE ' . $whereSalesStr;
        $stmtRevenue = $this->pdo->prepare($sqlRevenue);
        $stmtRevenue->execute($paramsSales);
        $totalRevenue = (float)($stmtRevenue->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $sqlTopBrokers = "
            SELECT
                u.id,
                u.name,
                COUNT(ps.id) as vendas,
                COALESCE(SUM(ps.property_price), 0) as receita,
                COUNT(DISTINCT l.id) as leads,
                ROUND((COUNT(ps.id) / COUNT(DISTINCT l.id)) * 100, 2) as taxa_conversao
            FROM users u
            LEFT JOIN property_sales ps ON u.id = ps.user_id
                AND ps.deleted_at IS NULL
                AND ps.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                AND ps.tenant_id = ?
            LEFT JOIN leads l ON u.id = l.assigned_user_id
                AND l.status != 'cancelled'
                AND l.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                AND l.tenant_id = ?
            WHERE u.is_superadmin != 1
                AND u.tenant_id = ?";

        $topBrokersParams = [$dataInicio, $dataFim, $tenantId, $dataInicio, $dataFim, $tenantId, $tenantId];

        if ($userId) {
            $sqlTopBrokers .= ' AND u.id = ?';
            $topBrokersParams[] = (int)$userId;
        }

        if ($categoria) {
            $sqlTopBrokers .= ' AND (SELECT COUNT(*) FROM property_sales ps2 '
                . 'JOIN properties p2 ON ps2.property_id = p2.id '
                . 'WHERE p2.category = ? AND ps2.user_id = u.id '
                . 'AND ps2.tenant_id = ? AND p2.tenant_id = ?) > 0';
            $topBrokersParams[] = (string)$categoria;
            $topBrokersParams[] = $tenantId;
            $topBrokersParams[] = $tenantId;
        }

        $sqlTopBrokers .= ' GROUP BY u.id, u.name ORDER BY vendas DESC LIMIT 5';
        $stmtTopBrokers = $this->pdo->prepare($sqlTopBrokers);
        $stmtTopBrokers->execute($topBrokersParams);
        $topBrokers = array_map(static function ($r) {
            return [
                'id' => (int)($r['id'] ?? 0),
                'name' => $r['name'] ?? '',
                'vendas' => (int)($r['vendas'] ?? 0),
                'receita' => (float)($r['receita'] ?? 0),
                'leads' => (int)($r['leads'] ?? 0),
                'taxa_conversao' => (float)($r['taxa_conversao'] ?? 0),
            ];
        }, $stmtTopBrokers->fetchAll(PDO::FETCH_ASSOC));

        return [
            'filters' => [
                'period' => $period,
                'user_id' => $userId,
                'category' => $categoria,
                'region' => $regiao,
            ],
            'date_range' => [
                'start' => $dataInicio,
                'end' => $dataFim,
            ],
            'kpis' => [
                'total_leads' => $totalLeads,
                'total_sales' => $totalSales,
                'conversion_rate' => $conversionRate,
                'average_ticket' => $averageTicket,
                'total_revenue' => $totalRevenue,
            ],
            'top_brokers' => $topBrokers,
        ];
    }

    /**
     * Ranking de corretores por vendas
     *
     * @param int $tenantId
     * @return array
     */
    public function getBrokerRanking(int $tenantId): array
    {
        $tenantId = (int)$tenantId;
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('Tenant inválido');
        }

        $stmt = $this->pdo->prepare("
            SELECT u.id, u.name, COUNT(ps.id) as vendas, SUM(ps.property_price) as valor
            FROM users u
            LEFT JOIN property_sales ps ON u.id = ps.user_id
                AND ps.deleted_at IS NULL
                AND ps.tenant_id = ?
            WHERE u.is_superadmin != 1
                AND u.tenant_id = ?
            GROUP BY u.id, u.name
            ORDER BY vendas DESC
            LIMIT 10
        ");
        $stmt->execute([$tenantId, $tenantId]);

        return array_map(static function ($r) {
            return [
                'label' => $r['name'],
                'vendas' => (int)($r['vendas'] ?? 0),
                'valor' => (float)($r['valor'] ?? 0),
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Taxa de conversão por corretor
     *
     * @param int $tenantId
     * @return array
     */
    public function getBrokerConversionRate(int $tenantId): array
    {
        $tenantId = (int)$tenantId;
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('Tenant inválido');
        }

        $stmt = $this->pdo->prepare("
            SELECT
                u.id,
                u.name,
                COUNT(DISTINCT l.id) as leads,
                COUNT(DISTINCT ps.id) as vendas
            FROM users u
            LEFT JOIN leads l ON u.id = l.assigned_user_id AND l.tenant_id = ?
            LEFT JOIN property_sales ps ON u.id = ps.user_id AND ps.deleted_at IS NULL AND ps.tenant_id = ?
            WHERE u.is_superadmin != 1
                AND u.tenant_id = ?
            GROUP BY u.id, u.name
        ");
        $stmt->execute([$tenantId, $tenantId, $tenantId]);

        $dados = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $totalVendas = (int)($r['vendas'] ?? 0);
            $totalLeads = (int)($r['leads'] ?? 0);
            $dados[] = [
                'label' => $r['name'],
                'taxa' => $totalLeads > 0 ? round(($totalVendas / $totalLeads) * 100, 2) : 0.0,
                'leads' => $totalLeads,
                'vendas' => $totalVendas,
            ];
        }

        return $dados;
    }

    /**
     * Ticket médio por período
     *
     * @param int $tenantId
     * @param string $periodo
     * @param string|null $dataInicio
     * @param string|null $dataFim
     * @return array
     */
    public function getAverageTicket(int $tenantId, string $periodo = 'mes', ?string $dataInicio = null, ?string $dataFim = null): array
    {
        $tenantId = (int)$tenantId;
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('Tenant inválido');
        }

        if ($periodo === 'custom' && $dataInicio && $dataFim) {
            $inicio = $dataInicio;
            $fim = $dataFim;
        } else {
            $fim = date('Y-m-d');
            switch ($periodo) {
                case 'trimestre':
                    $inicio = date('Y-m-d', strtotime('-3 months'));
                    break;
                case 'ano':
                    $inicio = date('Y-m-d', strtotime('-1 year'));
                    break;
                case 'mes':
                default:
                    $inicio = date('Y-m-d', strtotime('-1 month'));
                    break;
            }
        }

        $stmt = $this->pdo->prepare("
            SELECT AVG(property_price) as media, COUNT(*) as count
            FROM property_sales
            WHERE created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
              AND deleted_at IS NULL
              AND tenant_id = ?
        ");
        $stmt->execute([$inicio, $fim, $tenantId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [[
            'label' => 'Ticket Médio',
            'valor' => (float)($result['media'] ?? 0),
            'count' => (int)($result['count'] ?? 0),
        ]];
    }

    /**
     * Obtém leads por status
     *
     * @param int $tenantId
     * @param string $status
     * @param string $periodo
     * @return array
     */
    public function getLeadsByStatus(int $tenantId, string $status = '', string $periodo = 'mes'): array
    {
        $tenantId = (int)$tenantId;
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('Tenant inválido');
        }

        $dataFim = date('Y-m-d');
        if ($periodo === 'mes') {
            $dataInicio = date('Y-m-01');
        } elseif ($periodo === 'trimestre') {
            $trimestre = ceil(date('m') / 3);
            $dataInicio = date('Y-' . str_pad(($trimestre - 1) * 3 + 1, 2, '0', STR_PAD_LEFT) . '-01');
        } elseif ($periodo === 'ano') {
            $dataInicio = date('Y-01-01');
        } else {
            $dataInicio = date('Y-m-01', strtotime('-30 days'));
        }

        $query = "SELECT status, COUNT(*) as count FROM leads WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY) AND tenant_id = ?";
        $params = [$dataInicio, $dataFim, $tenantId];

        if ($status !== '') {
            $query .= " AND status = ?";
            $params[] = $status;
        }

        $query .= " GROUP BY status ORDER BY count DESC";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $statusMap = [
            'new' => 'Novo',
            'closed' => 'Fechado',
            'cancelled' => 'Cancelado',
            'pending' => 'Pendente',
            'qualified' => 'Qualificado',
            'lost' => 'Perdido',
            'in_progress' => 'Em Atendimento',
            'contacted' => 'Contatado',
        ];

        return array_map(static function ($row) use ($statusMap) {
            return [
                'status' => $statusMap[$row['status']] ?? $row['status'],
                'count' => (int)($row['count'] ?? 0),
                'quantidade' => (int)($row['count'] ?? 0),
            ];
        }, $resultados);
    }

    /**
     * Obtém leads por origem
     *
     * @param int $tenantId
     * @param string $status
     * @return array
     */
    public function getLeadsByOrigin(int $tenantId, string $status = ''): array
    {
        $tenantId = (int)$tenantId;
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('Tenant inválido');
        }

        $query = "SELECT source, COUNT(*) as count FROM leads WHERE tenant_id = ?";
        $params = [$tenantId];

        if ($status !== '') {
            $query .= " AND status = ?";
            $params[] = $status;
        }

        $query .= " GROUP BY source ORDER BY count DESC";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sourceMap = [
            'site' => 'Site',
            'portal' => 'Portal de Imóveis',
            'whatsapp' => 'WhatsApp',
            'instagram' => 'Instagram',
            'facebook' => 'Facebook',
            'google' => 'Google',
            'indicacao' => 'Indicação',
            'outro' => 'Outro',
        ];

        return array_map(static function ($row) use ($sourceMap) {
            return [
                'source' => $sourceMap[$row['source']] ?? $row['source'],
                'count' => (int)($row['count'] ?? 0),
                'quantidade' => (int)($row['count'] ?? 0),
            ];
        }, $resultados);
    }

    /**
     * Obtém leads por corretor
     *
     * @param int $tenantId
     * @return array
     */
    public function getLeadsByBroker(int $tenantId): array
    {
        $tenantId = (int)$tenantId;
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('Tenant inválido');
        }

        $stmt = $this->pdo->prepare("
            SELECT u.id, u.name, COUNT(l.id) as count
            FROM users u
            LEFT JOIN leads l ON u.id = l.assigned_user_id
                AND l.tenant_id = ?
            WHERE u.is_superadmin != 1
                AND u.tenant_id = ?
            GROUP BY u.id
            ORDER BY count DESC
        ");
        $stmt->execute([$tenantId, $tenantId]);

        return array_map(static function ($row) {
            return [
                'label' => $row['name'],
                'count' => (int)($row['count'] ?? 0),
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Obtém tempo médio de leads fechados
     *
     * @param int $tenantId
     * @return array
     */
    public function getLeadsAverageTime(int $tenantId): array
    {
        $tenantId = (int)$tenantId;
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('Tenant inválido');
        }

        $stmt = $this->pdo->prepare("
            SELECT AVG(DATEDIFF(updated_at, created_at)) as dias
            FROM leads
            WHERE status = 'closed' AND tenant_id = ?
        ");
        $stmt->execute([$tenantId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [[
            'label' => 'Tempo Médio',
            'dias' => (int)($result['dias'] ?? 0),
        ]];
    }

    /**
     * Obtém taxa de conversão por corretor
     *
     * @param int $tenantId
     * @return array
     */
    public function getLeadsConversionRate(int $tenantId): array
    {
        $tenantId = (int)$tenantId;
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('Tenant inválido');
        }

        $stmt = $this->pdo->prepare("
            SELECT l.assigned_user_id, u.name, COUNT(l.id) as total, COUNT(CASE WHEN l.status = 'closed' THEN 1 END) as vendas
            FROM leads l
            LEFT JOIN users u ON l.assigned_user_id = u.id
            WHERE u.is_superadmin != 1
                AND l.tenant_id = ?
                AND u.tenant_id = ?
            GROUP BY l.assigned_user_id
        ");
        $stmt->execute([$tenantId, $tenantId]);

        $dados = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $total = (int)($row['total'] ?? 0);
            $vendas = (int)($row['vendas'] ?? 0);
            $dados[] = [
                'label' => $row['name'] ?? 'Corretor ' . $row['assigned_user_id'],
                'taxa' => $total > 0 ? round(($vendas / $total) * 100, 2) : 0.0,
            ];
        }

        return $dados;
    }

    /**
     * Obtém funil de leads
     *
     * @param int $tenantId
     * @param int $periodo
     * @param int|null $userId
     * @param bool $isAdmin
     * @return array
     */
    public function getLeadsFunnel(int $tenantId, int $periodo = 7, ?int $userId = null, bool $isAdmin = false): array
    {
        $tenantId = (int)$tenantId;
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('Tenant inválido');
        }

        $stages = [
            'new' => ['label' => 'Novo', 'icon' => 'star', 'color' => '#3b82f6'],
            'contacted' => ['label' => 'Contatado', 'icon' => 'phone', 'color' => '#8b5cf6'],
            'negotiating' => ['label' => 'Negociando', 'icon' => 'handshake', 'color' => '#f59e0b'],
            'closed' => ['label' => 'Fechado', 'icon' => 'check-circle', 'color' => '#10b981'],
            'cancelled' => ['label' => 'Cancelado', 'icon' => 'times-circle', 'color' => '#ef4444'],
        ];

        $funnelData = [];
        foreach ($stages as $status => $info) {
            $query = "
                SELECT
                    COUNT(*) as total,
                    AVG(DATEDIFF(NOW(), created_at)) as dias_no_status,
                    MAX(created_at) as ultimo_lead
                FROM leads
                WHERE status = ?
                AND tenant_id = ?";
            $params = [$status, $tenantId];
            if (!$isAdmin && $userId !== null) {
                $query .= " AND assigned_user_id = ?";
                $params[] = $userId;
            }
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $funnelData[$status] = [
                'status' => $status,
                'label' => $info['label'],
                'icon' => $info['icon'],
                'color' => $info['color'],
                'total' => (int)($result['total'] ?? 0),
                'average_days_in_status' => (int)($result['dias_no_status'] ?? 0),
                'last_lead' => $result['ultimo_lead'] ?? null,
            ];
        }

        $totalLeads = array_sum(array_column($funnelData, 'total'));
        $closedLeads = $funnelData['closed']['total'];
        $cancelledLeads = $funnelData['cancelled']['total'];
        $activeLeads = $totalLeads - $closedLeads - $cancelledLeads;
        $conversionRate = $totalLeads > 0 ? round(($closedLeads / $totalLeads) * 100, 2) : 0;

        $stmtTemporal = $this->pdo->prepare("
            SELECT
                DATE(created_at) as data,
                status,
                COUNT(*) as total
            FROM leads
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            AND tenant_id = ?" . (!$isAdmin && $userId !== null ? " AND assigned_user_id = ?" : "") . "
            GROUP BY DATE(created_at), status
            ORDER BY data DESC
        ");
        $params = [$tenantId];
        if (!$isAdmin && $userId !== null) {
            $params[] = $userId;
        }
        $stmtTemporal->execute($params);
        $temporalData = $stmtTemporal->fetchAll(PDO::FETCH_ASSOC);

        $stmtCycleTime = $this->pdo->prepare("
            SELECT
                status,
                AVG(DATEDIFF(NOW(), created_at)) as dias_medio,
                MIN(DATEDIFF(NOW(), created_at)) as dias_min,
                MAX(DATEDIFF(NOW(), created_at)) as dias_max,
                COUNT(*) as total
            FROM leads
            WHERE tenant_id = ?" . (!$isAdmin && $userId !== null ? " AND assigned_user_id = ?" : "") . "
            GROUP BY status
        ");
        $params = [$tenantId];
        if (!$isAdmin && $userId !== null) {
            $params[] = $userId;
        }
        $stmtCycleTime->execute($params);
        $cycleTimeData = $stmtCycleTime->fetchAll(PDO::FETCH_ASSOC);

        $stmtSource = $this->pdo->prepare("
            SELECT
                source as origem,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as convertidos
            FROM leads
            WHERE source IS NOT NULL
            AND tenant_id = ?" . (!$isAdmin && $userId !== null ? " AND assigned_user_id = ?" : "") . "
            GROUP BY source
            ORDER BY total DESC
        ");
        $params = [$tenantId];
        if (!$isAdmin && $userId !== null) {
            $params[] = $userId;
        }
        $stmtSource->execute($params);
        $sourceData = $stmtSource->fetchAll(PDO::FETCH_ASSOC);

        return [
            'funnel' => array_values($funnelData),
            'summary' => [
                'total_leads' => $totalLeads,
                'active_leads' => $activeLeads,
                'converted_leads' => $closedLeads,
                'rejected_leads' => $cancelledLeads,
                'conversion_rate' => $conversionRate,
                'conversion_rate_label' => $conversionRate . '%',
            ],
            'temporal' => $temporalData,
            'cycle_time' => array_map(static function ($ct) {
                return [
                    'status' => $ct['status'],
                    'average_days' => (int)($ct['dias_medio'] ?? 0),
                    'min_days' => (int)($ct['dias_min'] ?? 0),
                    'max_days' => (int)($ct['dias_max'] ?? 0),
                    'total' => (int)($ct['total'] ?? 0),
                ];
            }, $cycleTimeData),
            'sources' => array_map(function ($s) use ($totalLeads) {
                return [
                    'name' => $s['origem'] ?? 'Desconhecido',
                    'total' => (int)($s['total'] ?? 0),
                    'converted' => (int)($s['convertidos'] ?? 0),
                    'percentage' => $totalLeads > 0 ? round(($s['total'] / $totalLeads) * 100, 1) : 0,
                    'conversion_rate' => ((int)($s['total'] ?? 0)) > 0 ? round((($s['convertidos'] ?? 0) / (int)($s['total'] ?? 0)) * 100, 1) : 0,
                ];
            }, $sourceData),
        ];
    }

    /**
     * Obtém leads perdidos
     *
     * @param int $tenantId
     * @return array
     */
    public function getLeadsLost(int $tenantId): array
    {
        $tenantId = (int)$tenantId;
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('Tenant inválido');
        }

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count FROM leads
            WHERE status = 'cancelled' AND tenant_id = ?
        ");
        $stmt->execute([$tenantId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [[
            'label' => 'Leads Perdidos',
            'count' => (int)($result['count'] ?? 0),
        ]];
    }

    /**
     * Obtém vendas por corretor
     *
     * @param int $tenantId
     * @return array
     */
    public function getSalesByBroker(int $tenantId): array
    {
        $tenantId = (int)$tenantId;
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('Tenant inválido');
        }

        $stmt = $this->pdo->prepare("
            SELECT u.id, u.name, COUNT(ps.id) as vendas, SUM(ps.property_price) as valor
            FROM users u
            LEFT JOIN property_sales ps ON u.id = ps.user_id
                AND ps.deleted_at IS NULL
                AND ps.tenant_id = ?
            WHERE u.is_superadmin != 1
                AND u.tenant_id = ?
            GROUP BY u.id
            ORDER BY vendas DESC
        ");
        $stmt->execute([$tenantId, $tenantId]);

        return array_map(static function ($row) {
            return [
                'label' => $row['name'],
                'count' => (int)($row['vendas'] ?? 0),
                'valor' => (float)($row['valor'] ?? 0),
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Obtém vendas por período
     *
     * @param int $tenantId
     * @param string $periodo
     * @param string|null $dataInicio
     * @param string|null $dataFim
     * @return array
     */
    public function getSalesByPeriod(int $tenantId, string $periodo = 'mes', ?string $dataInicio = null, ?string $dataFim = null): array
    {
        $tenantId = (int)$tenantId;
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('Tenant inválido');
        }

        if ($periodo === 'custom' && $dataInicio && $dataFim) {
            $inicio = $dataInicio;
            $fim = $dataFim;
        } else {
            $fim = date('Y-m-d');
            switch ($periodo) {
                case 'trimestre':
                    $inicio = date('Y-m-d', strtotime('-3 months'));
                    break;
                case 'ano':
                    $inicio = date('Y-m-d', strtotime('-1 year'));
                    break;
                case 'mes':
                default:
                    $inicio = date('Y-m-d', strtotime('-1 month'));
                    break;
            }
        }

        $stmt = $this->pdo->prepare("
            SELECT DATE_FORMAT(created_at, '%Y-%m') as mes, COUNT(*) as count, SUM(property_price) as valor
            FROM property_sales
            WHERE created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
              AND deleted_at IS NULL
              AND tenant_id = ?
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY mes DESC
            LIMIT 12
        ");
        $stmt->execute([$inicio, $fim, $tenantId]);

        return array_map(static function ($row) {
            return [
                'label' => $row['mes'],
                'count' => (int)($row['count'] ?? 0),
                'valor' => (float)($row['valor'] ?? 0),
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Obtém vendas por tipo de imóvel
     *
     * @param int $tenantId
     * @param string $periodo
     * @param string|null $dataInicio
     * @param string|null $dataFim
     * @return array
     */
    public function getSalesByType(int $tenantId, string $periodo = 'mes', ?string $dataInicio = null, ?string $dataFim = null): array
    {
        $tenantId = (int)$tenantId;
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('Tenant inválido');
        }

        if ($periodo === 'custom' && $dataInicio && $dataFim) {
            $inicio = $dataInicio;
            $fim = $dataFim;
        } else {
            $fim = date('Y-m-d');
            switch ($periodo) {
                case 'trimestre':
                    $inicio = date('Y-m-d', strtotime('-3 months'));
                    break;
                case 'ano':
                    $inicio = date('Y-m-d', strtotime('-1 year'));
                    break;
                case 'mes':
                default:
                    $inicio = date('Y-m-d', strtotime('-1 month'));
                    break;
            }
        }

        $typeMap = ['apartment' => 'Apartamento', 'house' => 'Casa', 'land' => 'Terreno', 'commercial' => 'Comercial'];

        $stmt = $this->pdo->prepare("
            SELECT p.type, COUNT(ps.id) as vendas, SUM(ps.property_price) as valor
            FROM properties p
            LEFT JOIN property_sales ps ON p.id = ps.property_id
              AND ps.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
              AND ps.deleted_at IS NULL
              AND ps.tenant_id = ?
            WHERE p.tenant_id = ?
            GROUP BY p.type
        ");
        $stmt->execute([$inicio, $fim, $tenantId, $tenantId]);

        return array_map(static function ($row) use ($typeMap) {
            return [
                'label' => $typeMap[$row['type']] ?? $row['type'],
                'count' => (int)($row['vendas'] ?? 0),
                'valor' => (float)($row['valor'] ?? 0),
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Obtém ciclo médio de vendas
     *
     * @param int $tenantId
     * @param string $periodo
     * @param string|null $dataInicio
     * @param string|null $dataFim
     * @return array
     */
    public function getSalesCycle(int $tenantId, string $periodo = 'mes', ?string $dataInicio = null, ?string $dataFim = null): array
    {
        $tenantId = (int)$tenantId;
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('Tenant inválido');
        }

        if ($periodo === 'custom' && $dataInicio && $dataFim) {
            $inicio = $dataInicio;
            $fim = $dataFim;
        } else {
            $fim = date('Y-m-d');
            switch ($periodo) {
                case 'trimestre':
                    $inicio = date('Y-m-d', strtotime('-3 months'));
                    break;
                case 'ano':
                    $inicio = date('Y-m-d', strtotime('-1 year'));
                    break;
                case 'mes':
                default:
                    $inicio = date('Y-m-d', strtotime('-1 month'));
                    break;
            }
        }

        $stmt = $this->pdo->prepare("
            SELECT AVG(DATEDIFF(ps.created_at, p.created_at)) as ciclo
            FROM property_sales ps
            JOIN properties p ON ps.property_id = p.id
            WHERE ps.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
              AND ps.deleted_at IS NULL
              AND ps.tenant_id = ?
              AND p.tenant_id = ?
        ");
        $stmt->execute([$inicio, $fim, $tenantId, $tenantId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [[
            'label' => 'Ciclo Médio',
            'dias' => (int)($result['ciclo'] ?? 0),
        ]];
    }

    /**
     * Obtém pipeline de vendas
     *
     * @param int $tenantId
     * @return array
     */
    public function getSalesPipeline(int $tenantId): array
    {
        $tenantId = (int)$tenantId;
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('Tenant inválido');
        }

        $query = "
            SELECT
                sp.id,
                sp.lead_id,
                sp.pipeline_stage_id,
                COALESCE(sp.property_id, l.property_id) as property_id,
                COALESCE(l.assigned_user_id, sp.user_id) as user_id,
                ps.name as stage_name,
                COALESCE(NULLIF(sp.value, 0), pr.price, 0) as value,
                sp.expected_close_date,
                COALESCE(NULLIF(sp.notes, ''), l.notes, '') as notes,
                CASE
                    WHEN sp.property_id IS NOT NULL THEN 'Imóvel'
                    ELSE 'Lead'
                END as type,
                COALESCE(l.name, pr.title) as item_title,
                pr.title as imovel,
                l.email as lead_email,
                l.phone as lead_phone,
                u.name as user_name
            FROM sales_pipeline sp
            LEFT JOIN pipeline_stages ps ON sp.pipeline_stage_id = ps.id AND ps.tenant_id = ?
            LEFT JOIN leads l ON sp.lead_id = l.id AND l.tenant_id = ?
            LEFT JOIN properties pr ON COALESCE(sp.property_id, l.property_id) = pr.id AND pr.tenant_id = ?
            LEFT JOIN users u ON COALESCE(l.assigned_user_id, sp.user_id) = u.id AND u.tenant_id = ?
            WHERE sp.deleted_at IS NULL
                AND sp.tenant_id = ?
            ORDER BY ps.position ASC, sp.created_at DESC
        ";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$tenantId, $tenantId, $tenantId, $tenantId, $tenantId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $byStage = [];
        $totals = ['total_value' => 0, 'total_items' => 0, 'stages' => []];

        foreach ($items as $item) {
            $stageId = $item['pipeline_stage_id'];
            $value = (float)($item['value'] ?? 0);

            if (!isset($byStage[$stageId])) {
                $byStage[$stageId] = [
                    'stage_name' => $item['stage_name'],
                    'items' => [],
                    'total' => 0,
                    'count' => 0,
                ];
            }

            $byStage[$stageId]['items'][] = $item;
            $byStage[$stageId]['total'] += $value;
            $byStage[$stageId]['count']++;
            $totals['total_value'] += $value;
            $totals['total_items']++;
        }

        return [
            'items' => $items,
            'by_stage' => $byStage,
            'totals' => $totals,
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Obtém receita e comissões
     *
     * @param int $tenantId
     * @param string $periodo
     * @param string|null $dataInicio
     * @param string|null $dataFim
     * @return array
     */
    public function getRevenueAndCommissions(int $tenantId, string $periodo = 'mes', ?string $dataInicio = null, ?string $dataFim = null): array
    {
        $tenantId = (int)$tenantId;
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('Tenant inválido');
        }

        if ($periodo === 'custom' && $dataInicio && $dataFim) {
            $inicio = $dataInicio;
            $fim = $dataFim;
        } else {
            $fim = date('Y-m-d');
            switch ($periodo) {
                case 'trimestre':
                    $inicio = date('Y-m-d', strtotime('-3 months'));
                    break;
                case 'ano':
                    $inicio = date('Y-m-d', strtotime('-1 year'));
                    break;
                case 'mes':
                default:
                    $inicio = date('Y-m-d', strtotime('-1 month'));
                    break;
            }
        }

        $stmt = $this->pdo->prepare("
            SELECT SUM(property_price) as receita, COUNT(*) as vendas
            FROM property_sales
            WHERE created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
              AND deleted_at IS NULL
              AND tenant_id = ?
        ");
        $stmt->execute([$inicio, $fim, $tenantId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [[
            'label' => 'Receita Total',
            'valor' => (float)($result['receita'] ?? 0),
            'vendas' => (int)($result['vendas'] ?? 0),
        ]];
    }

    /**
     * Busca KPIs do dashboard
     *
     * @param array $filters Filtros de tenant e período
     * @return array Dados brutos de KPIs
     */
    public function getDashboardKPIs(array $filters = []): array
    {
        $tenantId = isset($filters['tenantId']) ? (int)$filters['tenantId'] : 0;
        $period = isset($filters['period']) ? (int)$filters['period'] : 7;
        $userId = isset($filters['userId']) ? (int)$filters['userId'] : null;
        $isAdmin = isset($filters['isAdmin']) ? (bool)$filters['isAdmin'] : false;

        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('Tenant inválido');
        }

        $period = max(1, min($period, 365));
        $dataFim = date('Y-m-d');
        $dataInicio = date('Y-m-d', strtotime("-{$period} days"));
        [$dataInicioAnterior, $dataFimAnterior] = $this->calcularPeriodoAnterior($dataInicio, $period);

        if (!$isAdmin && !$userId) {
            throw new \RuntimeException('Sessão não inicializada - user_id não encontrado');
        }

        $propertyRepository = new \App\Repository\PropertyRepository($this->pdo);
        $propertyRows = $propertyRepository->fetchPropertiesForController($tenantId, $isAdmin ? null : $userId, $isAdmin);
        $activePropertyRows = array_values(array_filter($propertyRows, static function ($property) {
            return ($property['status'] ?? '') !== 'inactive';
        }));
        $totalProperties = count($activePropertyRows);

        if ($isAdmin) {
            $stmtLeads = $this->pdo->prepare("SELECT COUNT(*) as total FROM leads WHERE status != 'cancelled' AND tenant_id = ?");
            $stmtLeads->execute([$tenantId]);
        } else {
            $stmtLeads = $this->pdo->prepare("SELECT COUNT(*) as total FROM leads WHERE status != 'cancelled' AND assigned_user_id = ? AND tenant_id = ?");
            $stmtLeads->execute([$userId, $tenantId]);
        }
        $totalLeads = (int)$stmtLeads->fetch(PDO::FETCH_ASSOC)['total'];

        $stmtVisits = $this->pdo->prepare("SELECT COUNT(DISTINCT session_id) as total FROM site_visits WHERE visited_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY) AND tenant_id = ?");
        $stmtVisits->execute([$dataInicio, $dataFim, $tenantId]);
        $totalVisits = (int)$stmtVisits->fetch(PDO::FETCH_ASSOC)['total'];

        if ($isAdmin) {
            $stmtSales = $this->pdo->prepare("SELECT COUNT(*) as total FROM property_sales WHERE deleted_at IS NULL AND created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY) AND tenant_id = ?");
            $stmtSales->execute([$dataInicio, $dataFim, $tenantId]);
        } else {
            $stmtSales = $this->pdo->prepare("SELECT COUNT(*) as total FROM property_sales WHERE deleted_at IS NULL AND user_id = ? AND tenant_id = ? AND created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)");
            $stmtSales->execute([$userId, $tenantId, $dataInicio, $dataFim]);
        }
        $totalSales = (int)$stmtSales->fetch(PDO::FETCH_ASSOC)['total'];

        if ($isAdmin) {
            $stmtLeadsAnterior = $this->pdo->prepare("SELECT COUNT(*) as total FROM leads WHERE status != 'cancelled' AND created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY) AND tenant_id = ?");
            $stmtLeadsAnterior->execute([$dataInicioAnterior, $dataFimAnterior, $tenantId]);
        } else {
            $stmtLeadsAnterior = $this->pdo->prepare("SELECT COUNT(*) as total FROM leads WHERE status != 'cancelled' AND assigned_user_id = ? AND tenant_id = ? AND created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)");
            $stmtLeadsAnterior->execute([$userId, $tenantId, $dataInicioAnterior, $dataFimAnterior]);
        }
        $totalLeadsAnterior = (int)$stmtLeadsAnterior->fetch(PDO::FETCH_ASSOC)['total'];

        if ($isAdmin) {
            $stmtSalesAnterior = $this->pdo->prepare("SELECT COUNT(*) as total FROM property_sales WHERE deleted_at IS NULL AND tenant_id = ? AND created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)");
            $stmtSalesAnterior->execute([$tenantId, $dataInicioAnterior, $dataFimAnterior]);
        } else {
            $stmtSalesAnterior = $this->pdo->prepare("SELECT COUNT(*) as total FROM property_sales WHERE deleted_at IS NULL AND user_id = ? AND tenant_id = ? AND created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)");
            $stmtSalesAnterior->execute([$userId, $tenantId, $dataInicioAnterior, $dataFimAnterior]);
        }
        $totalSalesAnterior = (int)$stmtSalesAnterior->fetch(PDO::FETCH_ASSOC)['total'];

        if ($isAdmin) {
            $stmtTicket = $this->pdo->prepare("SELECT AVG(property_price) as media FROM property_sales WHERE created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY) AND deleted_at IS NULL AND tenant_id = ?");
            $stmtTicket->execute([$dataInicio, $dataFim, $tenantId]);
        } else {
            $stmtTicket = $this->pdo->prepare("SELECT AVG(property_price) as media FROM property_sales WHERE user_id = ? AND created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY) AND deleted_at IS NULL AND tenant_id = ?");
            $stmtTicket->execute([$userId, $dataInicio, $dataFim, $tenantId]);
        }
        $averageTicket = (float)($stmtTicket->fetch(PDO::FETCH_ASSOC)['media'] ?? 0);

        if ($isAdmin) {
            $stmtTicketAnterior = $this->pdo->prepare("SELECT AVG(property_price) as media FROM property_sales WHERE created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY) AND deleted_at IS NULL AND tenant_id = ?");
            $stmtTicketAnterior->execute([$dataInicioAnterior, $dataFimAnterior, $tenantId]);
        } else {
            $stmtTicketAnterior = $this->pdo->prepare("SELECT AVG(property_price) as media FROM property_sales WHERE user_id = ? AND created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY) AND deleted_at IS NULL AND tenant_id = ?");
            $stmtTicketAnterior->execute([$userId, $dataInicioAnterior, $dataFimAnterior, $tenantId]);
        }
        $averageTicketAnterior = (float)($stmtTicketAnterior->fetch(PDO::FETCH_ASSOC)['media'] ?? 0);

        if ($isAdmin) {
            $stmtCycle = $this->pdo->prepare("SELECT AVG(DATEDIFF(ps.created_at, l.created_at)) as dias_ciclo FROM property_sales ps INNER JOIN leads l ON ps.id = l.linked_sale_id WHERE ps.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY) AND ps.deleted_at IS NULL AND ps.tenant_id = ? AND l.linked_sale_id IS NOT NULL");
            $stmtCycle->execute([$dataInicio, $dataFim, $tenantId]);
        } else {
            $stmtCycle = $this->pdo->prepare("SELECT AVG(DATEDIFF(ps.created_at, l.created_at)) as dias_ciclo FROM property_sales ps INNER JOIN leads l ON ps.id = l.linked_sale_id WHERE ps.user_id = ? AND ps.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY) AND ps.deleted_at IS NULL AND ps.tenant_id = ? AND l.linked_sale_id IS NOT NULL");
            $stmtCycle->execute([$userId, $dataInicio, $dataFim, $tenantId]);
        }
        $result = $stmtCycle->fetch(PDO::FETCH_ASSOC);
        $averageCycleDays = (int)($result['dias_ciclo'] ?? 0);

        if ($isAdmin) {
            $stmtCycleAnterior = $this->pdo->prepare("SELECT AVG(DATEDIFF(ps.created_at, l.created_at)) as dias_ciclo FROM property_sales ps INNER JOIN leads l ON ps.id = l.linked_sale_id WHERE ps.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY) AND ps.deleted_at IS NULL AND ps.tenant_id = ? AND l.linked_sale_id IS NOT NULL");
            $stmtCycleAnterior->execute([$dataInicioAnterior, $dataFimAnterior, $tenantId]);
        } else {
            $stmtCycleAnterior = $this->pdo->prepare("SELECT AVG(DATEDIFF(ps.created_at, l.created_at)) as dias_ciclo FROM property_sales ps INNER JOIN leads l ON ps.id = l.linked_sale_id WHERE ps.user_id = ? AND ps.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY) AND ps.deleted_at IS NULL AND ps.tenant_id = ? AND l.linked_sale_id IS NOT NULL");
            $stmtCycleAnterior->execute([$userId, $dataInicioAnterior, $dataFimAnterior, $tenantId]);
        }
        $resultAnterior = $stmtCycleAnterior->fetch(PDO::FETCH_ASSOC);
        $averageCycleDaysAnterior = (int)($resultAnterior['dias_ciclo'] ?? 0);

        if ($isAdmin) {
            $stmtRevenue = $this->pdo->prepare("SELECT COALESCE(SUM(property_price), 0) as total FROM property_sales WHERE created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY) AND deleted_at IS NULL AND tenant_id = ?");
            $stmtRevenue->execute([$dataInicio, $dataFim, $tenantId]);
        } else {
            $stmtRevenue = $this->pdo->prepare("SELECT COALESCE(SUM(property_price), 0) as total FROM property_sales WHERE user_id = ? AND created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY) AND deleted_at IS NULL AND tenant_id = ?");
            $stmtRevenue->execute([$userId, $dataInicio, $dataFim, $tenantId]);
        }
        $totalRevenue = (float)$stmtRevenue->fetch(PDO::FETCH_ASSOC)['total'];

        if ($isAdmin) {
            $stmtRevenueAnterior = $this->pdo->prepare("SELECT COALESCE(SUM(property_price), 0) as total FROM property_sales WHERE created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY) AND deleted_at IS NULL AND tenant_id = ?");
            $stmtRevenueAnterior->execute([$dataInicioAnterior, $dataFimAnterior, $tenantId]);
        } else {
            $stmtRevenueAnterior = $this->pdo->prepare("SELECT COALESCE(SUM(property_price), 0) as total FROM property_sales WHERE user_id = ? AND created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY) AND deleted_at IS NULL AND tenant_id = ?");
            $stmtRevenueAnterior->execute([$userId, $dataInicioAnterior, $dataFimAnterior, $tenantId]);
        }
        $totalRevenueAnterior = (float)$stmtRevenueAnterior->fetch(PDO::FETCH_ASSOC)['total'];

        if ($isAdmin) {
            $stmtTopBrokers = $this->pdo->prepare("SELECT u.id, u.name, COUNT(ps.id) as vendas, COALESCE(SUM(ps.property_price), 0) as receita, COUNT(DISTINCT l.id) as leads, ROUND((COUNT(ps.id) / COUNT(DISTINCT l.id)) * 100, 2) as taxa_conversao FROM users u LEFT JOIN property_sales ps ON u.id = ps.user_id AND ps.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY) AND ps.deleted_at IS NULL AND ps.tenant_id = ? LEFT JOIN leads l ON u.id = l.assigned_user_id AND l.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY) AND l.tenant_id = ? WHERE u.is_superadmin != 1 AND u.tenant_id = ? GROUP BY u.id, u.name ORDER BY vendas DESC LIMIT 5");
            $stmtTopBrokers->execute([$dataInicio, $dataFim, $tenantId, $dataInicio, $dataFim, $tenantId, $tenantId]);
        } else {
            $stmtTopBrokers = $this->pdo->prepare("SELECT u.id, u.name, COUNT(ps.id) as vendas, COALESCE(SUM(ps.property_price), 0) as receita, COUNT(DISTINCT l.id) as leads, ROUND((COUNT(ps.id) / COUNT(DISTINCT l.id)) * 100, 2) as taxa_conversao FROM users u LEFT JOIN property_sales ps ON u.id = ps.user_id AND ps.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY) AND ps.deleted_at IS NULL AND ps.tenant_id = ? LEFT JOIN leads l ON u.id = l.assigned_user_id AND l.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY) AND l.tenant_id = ? WHERE u.id = ? AND u.tenant_id = ? GROUP BY u.id, u.name");
            $stmtTopBrokers->execute([$dataInicio, $dataFim, $tenantId, $dataInicio, $dataFim, $tenantId, $userId, $tenantId]);
        }
        $topBrokers = array_map(static function ($r) {
            return [
                'id' => (int)$r['id'],
                'name' => $r['name'],
                'vendas' => (int)$r['vendas'],
                'receita' => (float)$r['receita'],
                'leads' => (int)$r['leads'],
                'taxa_conversao' => (float)$r['taxa_conversao'],
            ];
        }, $stmtTopBrokers->fetchAll(PDO::FETCH_ASSOC));

        $parkedProperties = $this->getParkedProperties($tenantId, 30);
        $stoppedProperties = count($parkedProperties);

        return [
            'totalProperties' => (int)$totalProperties,
            'totalLeads' => (int)$totalLeads,
            'totalLeadsPrevious' => (int)$totalLeadsAnterior,
            'totalVisits' => (int)$totalVisits,
            'totalSales' => (int)$totalSales,
            'totalSalesPrevious' => (int)$totalSalesAnterior,
            'conversionRate' => (float)(($totalLeads > 0) ? round(($totalSales / $totalLeads) * 100, 2) : 0),
            'conversionRatePrevious' => (float)(($totalLeadsAnterior > 0) ? round(($totalSalesAnterior / $totalLeadsAnterior) * 100, 2) : 0),
            'averageTicket' => (float)$averageTicket,
            'averageTicketPrevious' => (float)$averageTicketAnterior,
            'averageCycleDays' => (int)$averageCycleDays,
            'averageCycleDaysPrevious' => (int)$averageCycleDaysAnterior,
            'totalRevenue' => (float)$totalRevenue,
            'totalRevenuePrevious' => (float)$totalRevenueAnterior,
            'stoppedProperties' => (int)$stoppedProperties,
            'topBrokers' => $topBrokers,
            'dateInicio' => $dataInicio,
            'dateFim' => $dataFim,
            'dateInicioAnterior' => $dataInicioAnterior,
            'dateFimAnterior' => $dataFimAnterior,
        ];
    }

    private function calcularPeriodoAnterior(string $dataInicio, int $diasPeriodo): array
    {
        $dataFimAnterior = date('Y-m-d', strtotime($dataInicio . ' -1 day'));
        $dataInicioAnterior = date('Y-m-d', strtotime($dataFimAnterior . " -{$diasPeriodo} days"));
        return [$dataInicioAnterior, $dataFimAnterior];
    }

    /**
     * Registra uma visita
     *
     * @param array $payload Dados da visita para persistência
     * @return array Resultado bruto da inserção
     */
    public function trackVisit(array $payload): array
    {
        $propertyId = isset($payload['property_id']) ? (int)$payload['property_id'] : 0;
        $source = isset($payload['source']) ? (string)$payload['source'] : 'unknown';
        $userId = isset($payload['user_id']) ? $payload['user_id'] : null;
        $visitedAt = isset($payload['visited_at']) ? (string)$payload['visited_at'] : date('Y-m-d H:i:s');

        if ($propertyId <= 0) {
            throw new \InvalidArgumentException('Propriedade inválida');
        }

        // Verifica tenant da propriedade
        $property = $this->fetchRow("SELECT tenant_id FROM properties WHERE id = ?", [$propertyId]);
        if (!$property) {
            throw new \RuntimeException('Propriedade não encontrada');
        }

        $propertyTenantId = (int)$property['tenant_id'];

        // Valida user_id, se fornecido, garantindo que pertencer ao mesmo tenant da propriedade
        if ($userId !== null) {
            $userId = (int)$userId;
            $user = $this->fetchRow("SELECT tenant_id FROM users WHERE id = ?", [$userId]);
            if (!$user) {
                throw new \RuntimeException('Usuário não encontrado');
            }
            if ((int)$user['tenant_id'] !== $propertyTenantId) {
                throw new \RuntimeException('Usuário não pertence ao mesmo tenant da propriedade');
            }
        }

        // Insere visita
        $stmt = $this->pdo->prepare(
            "INSERT INTO site_visits (property_id, source, visited_at, user_id, tenant_id) VALUES (?, ?, ?, ?, ?)"
        );

        $stmt->execute([
            $propertyId,
            $source,
            $visitedAt,
            $userId,
            $propertyTenantId,
        ]);

        return [
            'property_tenant_id' => $propertyTenantId,
            'insert_id' => (int)$this->pdo->lastInsertId(),
        ];
    }

    /**
     * Busca relatório de inventário
     *
     * @param int $tenantId
     * @return array
     */
    private function getInventoryReport(int $tenantId): array
    {
        $statsQuery = "SELECT
            COUNT(*) as total,
            SUM(CASE WHEN LOWER(status) IN ('ativo', 'active') THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN LOWER(status) IN ('vendido', 'sold') THEN 1 ELSE 0 END) as sold,
            SUM(CASE WHEN LOWER(status) NOT IN ('ativo', 'active', 'vendido', 'sold') OR status IS NULL THEN 1 ELSE 0 END) as inactive
        FROM properties
        WHERE tenant_id = :tenant_id";

        $stats = $this->fetchRow($statsQuery, [':tenant_id' => $tenantId]);

        $propertiesQuery = "SELECT
            id, title,
            CASE
                WHEN LOWER(type) = 'house' THEN 'Casa'
                WHEN LOWER(type) = 'apartment' THEN 'Apartamento'
                WHEN LOWER(type) = 'land' THEN 'Terreno'
                WHEN LOWER(type) = 'comercial' THEN 'Comercial'
                WHEN LOWER(type) = 'industrial' THEN 'Industrial'
                ELSE type
            END as property_type,
            price, status, assigned_user_id,
            CONCAT(COALESCE(location, ''), ' - ', COALESCE(neighborhood, ''), ', ', COALESCE(city, ''), ' - ', COALESCE(state, '')) as address,
            (SELECT COALESCE(name, email) FROM users WHERE id = properties.assigned_user_id AND tenant_id = :tenant_id) as assigned_user
        FROM properties
        WHERE assigned_user_id NOT IN (SELECT id FROM users WHERE is_superadmin = 1) AND tenant_id = :tenant_id
        ORDER BY id DESC
        LIMIT 1000";

        $properties = $this->fetchAll($propertiesQuery, [':tenant_id' => $tenantId]);

        return [
            'stats' => [
                'total' => (int)($stats['total'] ?? 0),
                'active' => (int)($stats['active'] ?? 0),
                'sold' => (int)($stats['sold'] ?? 0),
                'inactive' => (int)($stats['inactive'] ?? 0),
            ],
            'properties' => $properties,
        ];
    }

    /**
     * Busca relatório de leads
     *
     * @param int $tenantId
     * @param string $period
     * @return array
     */
    private function getLeadsReport(int $tenantId, string $period): array
    {
        $dateFilter = $this->getDateFilter($period);

        $statsQuery = "SELECT
            COUNT(*) as total,
            SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as new,
            SUM(CASE WHEN status = 'converted' OR status = 'convertido' THEN 1 ELSE 0 END) as converted
        FROM leads
        WHERE created_at >= ? AND tenant_id = :tenant_id";

        $stats = $this->fetchRow($statsQuery, [$dateFilter, ':tenant_id' => $tenantId]);

        $leadsQuery = "SELECT
            id, name, email, phone, status, created_at as date
        FROM leads
        WHERE created_at >= ? AND tenant_id = :tenant_id
        ORDER BY created_at DESC
        LIMIT 1000";

        $leads = $this->fetchAll($leadsQuery, [$dateFilter, ':tenant_id' => $tenantId]);

        return [
            'stats' => [
                'total' => (int)($stats['total'] ?? 0),
                'new' => (int)($stats['new'] ?? 0),
                'converted' => (int)($stats['converted'] ?? 0),
                'conversion_rate' => $this->calculateConversionRate((int)($stats['total'] ?? 0), (int)($stats['converted'] ?? 0)),
            ],
            'leads' => $leads,
        ];
    }

    /**
     * Busca relatório de vendas
     *
     * @param int $tenantId
     * @param string $period
     * @return array
     */
    private function getSalesReport(int $tenantId, string $period): array
    {
        $dateFilter = $this->getDateFilter($period);

        $statsQuery = "SELECT
            COUNT(*) as active_negotiations,
            SUM(CASE WHEN status = 'sold' OR status = 'vendido' THEN 1 ELSE 0 END) as closed_sales
        FROM properties
        WHERE created_at >= ? AND tenant_id = :tenant_id";

        $stats = $this->fetchRow($statsQuery, [$dateFilter, ':tenant_id' => $tenantId]);

        $valueQuery = "SELECT SUM(price) as total_value FROM properties WHERE status IN ('sold', 'vendido') AND created_at >= ? AND tenant_id = :tenant_id";
        $valueResult = $this->fetchRow($valueQuery, [$dateFilter, ':tenant_id' => $tenantId]);

        return [
            'stats' => [
                'active_negotiations' => (int)($stats['active_negotiations'] ?? 0),
                'closed_sales' => (int)($stats['closed_sales'] ?? 0),
                'total_value' => (float)($valueResult['total_value'] ?? 0),
            ],
        ];
    }

    /**
     * Busca relatório financeiro
     *
     * @param int $tenantId
     * @param string $period
     * @return array
     */
    private function getFinancialReport(int $tenantId, string $period): array
    {
        $dateFilter = $this->getDateFilter($period);

        $revenueQuery = "SELECT SUM(valor) as total_revenue FROM financeiro WHERE tipo = 'receita' AND data_transacao >= :dateFilter AND deleted_at IS NULL AND tenant_id = :tenant_id";
        $commissionsQuery = "SELECT SUM(valor) as total_commissions FROM financeiro WHERE tipo = 'comissao' AND data_transacao >= :dateFilter AND deleted_at IS NULL AND tenant_id = :tenant_id";
        $expensesQuery = "SELECT SUM(valor) as total_expenses FROM financeiro WHERE tipo = 'despesa' AND data_transacao >= :dateFilter AND deleted_at IS NULL AND tenant_id = :tenant_id";

        $revenue = $this->fetchRow($revenueQuery, [':dateFilter' => $dateFilter, ':tenant_id' => $tenantId]);
        $commissions = $this->fetchRow($commissionsQuery, [':dateFilter' => $dateFilter, ':tenant_id' => $tenantId]);
        $expenses = $this->fetchRow($expensesQuery, [':dateFilter' => $dateFilter, ':tenant_id' => $tenantId]);

        $totalRevenue = (float)($revenue['total_revenue'] ?? 0);
        $totalCommissions = (float)($commissions['total_commissions'] ?? 0);
        $totalExpenses = (float)($expenses['total_expenses'] ?? 0);
        $netProfit = $totalRevenue - $totalCommissions - $totalExpenses;

        $transactionsQuery = "SELECT f.id, f.tipo, f.data_transacao, f.categoria, f.valor, f.status, u.name as user_name, p.title as property_title
            FROM financeiro f
            LEFT JOIN users u ON f.user_id = u.id
            LEFT JOIN properties p ON f.property_id = p.id AND p.tenant_id = :tenant_id
            WHERE f.data_transacao >= :dateFilter AND f.deleted_at IS NULL AND f.tenant_id = :tenant_id
            ORDER BY f.data_transacao DESC
            LIMIT 1000";

        $transactions = $this->fetchAll($transactionsQuery, [':dateFilter' => $dateFilter, ':tenant_id' => $tenantId]);
        foreach ($transactions as &$t) {
            $t['valor'] = (float)$t['valor'];
        }

        $byCategoryQuery = "SELECT categoria, SUM(valor) as total FROM financeiro WHERE data_transacao >= :dateFilter AND deleted_at IS NULL AND tenant_id = :tenant_id GROUP BY categoria ORDER BY total DESC";
        $byCategory = $this->fetchAll($byCategoryQuery, [':dateFilter' => $dateFilter, ':tenant_id' => $tenantId]);
        foreach ($byCategory as &$c) {
            $c['total'] = (float)$c['total'];
        }

        $timeSeriesQuery = "SELECT DATE(data_transacao) as date, SUM(valor) as total FROM financeiro WHERE data_transacao >= :dateFilter AND deleted_at IS NULL AND tenant_id = :tenant_id GROUP BY DATE(data_transacao) ORDER BY DATE(data_transacao) ASC";
        $timeSeries = $this->fetchAll($timeSeriesQuery, [':dateFilter' => $dateFilter, ':tenant_id' => $tenantId]);
        foreach ($timeSeries as &$s) {
            $s['total'] = (float)$s['total'];
        }

        return [
            'stats' => [
                'total_revenue' => $totalRevenue,
                'total_commissions' => $totalCommissions,
                'total_expenses' => $totalExpenses,
                'net_profit' => $netProfit,
                'date_filter' => $dateFilter,
            ],
            'transactions' => $transactions,
            'charts' => [
                'by_category' => $byCategory,
                'time_series' => $timeSeries,
            ],
        ];
    }

    /**
     * Busca relatório de usuários
     *
     * @param int $tenantId
     * @return array
     */
    private function getUsersReport(int $tenantId): array
    {
        $usersQuery = "SELECT
            u.id,
            u.name,
            u.email,
            COUNT(DISTINCT p.id) as properties_count,
            COUNT(DISTINCT l.id) as leads_count,
            SUM(CASE WHEN p.status IN ('sold', 'vendido') THEN 1 ELSE 0 END) as sales_count,
            MAX(u.last_login) as last_login
        FROM users u
        LEFT JOIN properties p ON u.id = p.assigned_user_id AND p.tenant_id = :tenant_id
        LEFT JOIN leads l ON u.id = l.assigned_user_id AND l.tenant_id = :tenant_id
        WHERE u.role IN ('user', 'admin') AND u.username != 'admin' AND u.tenant_id = :tenant_id
        GROUP BY u.id
        ORDER BY properties_count DESC";

        return ['users' => $this->fetchAll($usersQuery, [':tenant_id' => $tenantId])];
    }

    /**
     * Busca relatório de status de propriedades
     *
     * @param int $tenantId
     * @param string $period
     * @return array
     */
    private function getPropertiesStatusReport(int $tenantId, string $period): array
    {
        $dateFilter = $this->getDateFilter($period);

        $statsQuery = "SELECT
            SUM(CASE WHEN LOWER(status) IN ('ativo', 'active') THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN LOWER(status) IN ('inativo', 'inactive') THEN 1 ELSE 0 END) as inactive,
            SUM(CASE WHEN LOWER(status) IN ('vendido', 'sold') THEN 1 ELSE 0 END) as sold,
            SUM(CASE WHEN LOWER(status) IN ('bloqueado', 'blocked') THEN 1 ELSE 0 END) as blocked
        FROM properties
        WHERE created_at >= ? AND tenant_id = :tenant_id";

        $stats = $this->fetchRow($statsQuery, [$dateFilter, ':tenant_id' => $tenantId]);

        return ['stats' => [
            'active' => (int)($stats['active'] ?? 0),
            'inactive' => (int)($stats['inactive'] ?? 0),
            'sold' => (int)($stats['sold'] ?? 0),
            'blocked' => (int)($stats['blocked'] ?? 0),
        ]];
    }

    /**
     * Retorna a data de início para um período
     *
     * @param string $period
     * @return string
     */
    private function getDateFilter(string $period): string
    {
        switch ($period) {
            case 'month':
                return date('Y-m-d', strtotime('first day of this month'));
            case 'quarter':
                $month = ceil(date('m') / 3);
                $startMonth = ($month - 1) * 3 + 1;
                return date('Y-' . str_pad($startMonth, 2, '0', STR_PAD_LEFT) . '-01');
            case 'year':
                return date('Y-01-01');
            case 'custom':
                return date('Y-m-d', strtotime('first day of this month'));
            default:
                return date('Y-m-d', strtotime('first day of this month'));
        }
    }

    /**
     * Executa query e retorna todas as linhas
     *
     * @param string $sql
     * @param array $params
     * @return array
     */
    private function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Executa query e retorna uma linha
     *
     * @param string $sql
     * @param array $params
     * @return array
     */
    private function fetchRow(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Obtém estatísticas agregadas do portfolio para o tenant atual.
     *
     * @param int $tenantId
     * @param int|null $userId
     * @param bool $isAdmin
     * @return array
     */
    public function getPortfolioStats(int $tenantId, ?int $userId = null, bool $isAdmin = false): array
    {
        $tenantId = (int)$tenantId;
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('Tenant inválido');
        }

        $baseWhere = 'WHERE tenant_id = ?';
        $params = [$tenantId];
        if (!$isAdmin && $userId !== null) {
            $baseWhere .= ' AND assigned_user_id = ?';
            $params[] = (int)$userId;
        }

        $translatePropertyType = static function ($type) {
            $translations = [
                'apartment' => 'Apartamento',
                'house' => 'Casa',
                'commercial' => 'Comercial',
                'land' => 'Terreno',
            ];
            return $translations[strtolower((string)$type)] ?? 'Não definido';
        };

        $typeStats = $this->fetchAll(
            "SELECT `type` as property_type, COUNT(*) as quantidade, AVG(price) as preco_medio, MIN(price) as preco_minimo, MAX(price) as preco_maximo, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as ativos, SUM(CASE WHEN status = 'vendido' THEN 1 ELSE 0 END) as vendidos FROM properties {$baseWhere} GROUP BY `type` ORDER BY quantidade DESC",
            $params
        );

        $neighborhoodWhere = "WHERE neighborhood IS NOT NULL AND neighborhood != '' AND tenant_id = ?";
        $neighborhoodParams = [$tenantId];
        if (!$isAdmin && $userId !== null) {
            $neighborhoodWhere .= ' AND assigned_user_id = ?';
            $neighborhoodParams[] = (int)$userId;
        }

        $neighborhoodStats = $this->fetchAll(
            "SELECT neighborhood, COUNT(*) as quantidade, AVG(price) as preco_medio, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as ativos, SUM(CASE WHEN status = 'vendido' THEN 1 ELSE 0 END) as vendidos FROM properties {$neighborhoodWhere} GROUP BY neighborhood ORDER BY quantidade DESC LIMIT 10",
            $neighborhoodParams
        );

        $portfolioRow = $this->fetchRow(
            "SELECT COUNT(*) as total_imoveis, SUM(price) as valor_total, AVG(price) as preco_medio, SUM(CASE WHEN status = 'active' THEN price ELSE 0 END) as valor_ativo, SUM(CASE WHEN status = 'vendido' THEN price ELSE 0 END) as valor_vendido, SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as imoveis_inativos FROM properties {$baseWhere}",
            $params
        );

        $priceBands = [
            ['min' => 0, 'max' => 200000, 'label' => 'Até R$ 200k'],
            ['min' => 200000, 'max' => 500000, 'label' => 'R$ 200k - 500k'],
            ['min' => 500000, 'max' => 1000000, 'label' => 'R$ 500k - 1M'],
            ['min' => 1000000, 'max' => 2000000, 'label' => 'R$ 1M - 2M'],
            ['min' => 2000000, 'max' => PHP_INT_MAX, 'label' => 'Acima de R$ 2M'],
        ];

        $priceBandStats = [];
        foreach ($priceBands as $band) {
            $bandParams = [$band['min'], $band['max'], $tenantId];
            if (!$isAdmin && $userId !== null) {
                $bandParams[] = (int)$userId;
            }

            $result = $this->fetchRow(
                "SELECT COUNT(*) as total FROM properties WHERE price >= ? AND price < ? AND tenant_id = ?" . (!$isAdmin && $userId !== null ? ' AND assigned_user_id = ?' : ''),
                $bandParams
            );

            $priceBandStats[] = [
                'label' => $band['label'],
                'quantity' => (int)($result['total'] ?? 0),
                'range' => [$band['min'], $band['max']],
            ];
        }

        $statusStats = $this->fetchAll(
            "SELECT status, COUNT(*) as total, AVG(price) as preco_medio, SUM(price) as valor_total FROM properties {$baseWhere} GROUP BY status",
            $params
        );

        $trendSql = "SELECT DATE_FORMAT(created_at, '%Y-%m') as mes, COUNT(*) as quantidade, AVG(price) as preco_medio, MIN(price) as preco_minimo, MAX(price) as preco_maximo FROM properties WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) AND tenant_id = ?";
        $trendParams = [$tenantId];
        if (!$isAdmin && $userId !== null) {
            $trendSql .= ' AND assigned_user_id = ?';
            $trendParams[] = (int)$userId;
        }
        $trendSql .= ' GROUP BY DATE_FORMAT(created_at, "%Y-%m") ORDER BY mes ASC';

        $trendStats = $this->fetchAll($trendSql, $trendParams);

        return [
            'success' => true,
            'portfolio' => [
                'total_properties' => (int)($portfolioRow['total_imoveis'] ?? 0),
                'total_value' => (float)($portfolioRow['valor_total'] ?? 0),
                'average_price' => (float)($portfolioRow['preco_medio'] ?? 0),
                'active_value' => (float)($portfolioRow['valor_ativo'] ?? 0),
                'sold_value' => (float)($portfolioRow['valor_vendido'] ?? 0),
                'inactive_properties' => (int)($portfolioRow['imoveis_inativos'] ?? 0),
            ],
            'by_type' => array_map(static function ($row) use ($translatePropertyType) {
                return [
                    'type' => $translatePropertyType($row['property_type'] ?? ''),
                    'quantity' => (int)($row['quantidade'] ?? 0),
                    'average_price' => (float)($row['preco_medio'] ?? 0),
                    'min_price' => (float)($row['preco_minimo'] ?? 0),
                    'max_price' => (float)($row['preco_maximo'] ?? 0),
                    'active' => (int)($row['ativos'] ?? 0),
                    'sold' => (int)($row['vendidos'] ?? 0),
                ];
            }, $typeStats),
            'by_neighborhood' => array_map(static function ($row) {
                return [
                    'name' => $row['neighborhood'] ?? 'Não informado',
                    'quantity' => (int)($row['quantidade'] ?? 0),
                    'average_price' => (float)($row['preco_medio'] ?? 0),
                    'active' => (int)($row['ativos'] ?? 0),
                    'sold' => (int)($row['vendidos'] ?? 0),
                ];
            }, $neighborhoodStats),
            'by_price_band' => $priceBandStats,
            'by_status' => array_map(static function ($row) {
                return [
                    'status' => $row['status'] ?? '',
                    'quantity' => (int)($row['total'] ?? 0),
                    'average_price' => (float)($row['preco_medio'] ?? 0),
                    'total_value' => (float)($row['valor_total'] ?? 0),
                ];
            }, $statusStats),
            'price_trend' => array_map(static function ($row) {
                return [
                    'month' => $row['mes'] ?? '',
                    'quantity' => (int)($row['quantidade'] ?? 0),
                    'average_price' => (float)($row['preco_medio'] ?? 0),
                    'min_price' => (float)($row['preco_minimo'] ?? 0),
                    'max_price' => (float)($row['preco_maximo'] ?? 0),
                ];
            }, $trendStats),
        ];
    }

    /**
     * Conta propriedades por status com filtros opcionais
     * Preserva SQL idêntico ao endpoint legado getPropertiesByStatus.php
     *
     * @param int $tenantId
     * @param string|null $status
     * @param string|null $type
     * @return array
     */
    public function countPropertiesByStatus(int $tenantId, ?string $status = null, ?string $type = null): array
    {
        $sql = 'SELECT status, COUNT(*) as count FROM properties WHERE tenant_id = ?';
        $params = [$tenantId];

        if (!empty($status)) {
            $sql .= ' AND status = ?';
            $params[] = $status;
        }

        if (!empty($type)) {
            $sql .= ' AND type = ?';
            $params[] = $type;
        }

        $sql .= ' GROUP BY status ORDER BY count DESC';

        return $this->fetchAll($sql, $params);
    }

    /**
     * Conta propriedades por tipo com filtro de status opcional
     * Preserva SQL idêntico ao endpoint legado getPropertiesByType.php
     *
     * @param int $tenantId
     * @param string|null $status
     * @return array
     */
    public function countPropertiesByType(int $tenantId, ?string $status = null): array
    {
        $sql = 'SELECT type, COUNT(*) as count FROM properties WHERE tenant_id = ?';
        $params = [$tenantId];

        if (!empty($status)) {
            $sql .= ' AND status = ?';
            $params[] = $status;
        }

        $sql .= ' GROUP BY type ORDER BY count DESC';

        return $this->fetchAll($sql, $params);
    }

    /**
     * Conta propriedades por bairro com filtros opcionais
     * Preserva SQL idêntico ao endpoint legado getPropertiesByNeighborhood.php
     *
     * @param int $tenantId
     * @param string|null $status
     * @param string|null $type
     * @return array
     */
    public function countPropertiesByNeighborhoodAgg(int $tenantId, ?string $status = null, ?string $type = null): array
    {
        $sql = 'SELECT neighborhood, COUNT(*) as count FROM properties WHERE neighborhood IS NOT NULL AND neighborhood != \'\' AND tenant_id = ?';
        $params = [$tenantId];

        if (!empty($status)) {
            $sql .= ' AND status = ?';
            $params[] = $status;
        }

        if (!empty($type)) {
            $sql .= ' AND type = ?';
            $params[] = $type;
        }

        $sql .= ' GROUP BY neighborhood ORDER BY count DESC LIMIT 20';

        return $this->fetchAll($sql, $params);
    }

    /**
     * Conta propriedades por faixas de preço
     * Preserva SQL idêntico ao endpoint legado getPropertiesByPrice.php
     *
     * @param int $tenantId
     * @param array $ranges
     * @param string|null $status
     * @param string|null $type
     * @return array
     */
    public function countPropertiesByPriceRanges(int $tenantId, array $ranges, ?string $status = null, ?string $type = null): array
    {
        $results = [];
        foreach ($ranges as $range) {
            $sql = 'SELECT COUNT(*) as count FROM properties WHERE price >= ? AND price < ? AND tenant_id = ?';
            $params = [$range['min'], $range['max'], $tenantId];

            if (!empty($status)) {
                $sql .= ' AND status = ?';
                $params[] = $status;
            }

            if (!empty($type)) {
                $sql .= ' AND type = ?';
                $params[] = $type;
            }

            $result = $this->fetchRow($sql, $params);
            $results[] = [
                'faixa' => $range['label'],
                'count' => (int)($result['count'] ?? 0)
            ];
        }
        return $results;
    }

    /**
     * Obtém propriedades paradas (sem movimentação há X dias)
     * Preserva SQL idêntico ao endpoint legado getPropertiesParados.php
     *
     * @param int $tenantId
     * @param int $daysThreshold
     * @param string|null $type
     * @return array
     */
    public function getParkedProperties(int $tenantId, int $daysThreshold, ?string $type = null): array
    {
        $dataLimite = date('Y-m-d', strtotime("-{$daysThreshold} days"));

        $sql = 'SELECT p.id, p.title, p.price, p.status, p.created_at
                FROM properties p
                WHERE p.status = \'active\' AND p.created_at < ?
                AND p.tenant_id = ?';
        $params = [$dataLimite, $tenantId];

        if (!empty($type)) {
            $sql .= ' AND p.type = ?';
            $params[] = $type;
        }

        $sql .= ' ORDER BY p.created_at ASC LIMIT 50';

        return $this->fetchAll($sql, $params);
    }

    /**
     * Obtém estatísticas de vendas/aluguéis por período
     * Preserva SQL idêntico ao endpoint legado getPropertiesSoldRented.php
     *
     * @param int $tenantId
     * @param string $dataInicio
     * @param string $dataFim
     * @param string|null $type
     * @return array
     */
    public function getSoldRentedStats(int $tenantId, string $dataInicio, string $dataFim, ?string $type = null): array
    {
        $sql = 'SELECT ps.status,
                COUNT(*) as count,
                SUM(ps.property_price) as valor_total,
                AVG(ps.property_price) as valor_medio,
                p.type
        FROM property_sales ps
        JOIN properties p ON ps.property_id = p.id
        WHERE ps.created_at BETWEEN ? AND ?
          AND ps.deleted_at IS NULL
          AND ps.tenant_id = ?
          AND p.tenant_id = ?';

        $params = [$dataInicio, $dataFim, $tenantId, $tenantId];

        if (!empty($type)) {
            $sql .= ' AND p.type = ?';
            $params[] = $type;
        }

        $sql .= ' GROUP BY ps.status ORDER BY count DESC';

        return $this->fetchAll($sql, $params);
    }

    /**
     * Busca dados de gráficos do dashboard (vendas, leads, categorias, bairros)
     *
     * @param array $filters Filtros com tenantId, dataInicio, dataFim, period, type
     * @return array Dados brutos para gráficos
     */
    public function getDashboardCharts(array $filters = []): array
    {
        $tenantId = isset($filters['tenantId']) ? (int)$filters['tenantId'] : 0;
        $dataInicio = isset($filters['dataInicio']) ? (string)$filters['dataInicio'] : '';
        $dataFim = isset($filters['dataFim']) ? (string)$filters['dataFim'] : '';
        $period = isset($filters['period']) ? (int)$filters['period'] : 90;
        $type = isset($filters['type']) ? (string)$filters['type'] : 'all';

        if ($tenantId <= 0 || $dataInicio === '' || $dataFim === '') {
            throw new \InvalidArgumentException('Filtros inválidos: tenantId, dataInicio, dataFim são obrigatórios');
        }

        $charts = [];

        // =====================
        // 1. Sales Trend (por dia ou semana)
        // =====================
        if ($type === 'all' || $type === 'sales_trend') {
            if ($period <= 31) {
                // Por dia
                $sqlSalesTrend = "
                    SELECT 
                        DATE(created_at) as data,
                        COUNT(*) as vendas,
                        COALESCE(SUM(property_price), 0) as receita
                    FROM property_sales
                    WHERE created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                        AND tenant_id = ?
                        AND deleted_at IS NULL
                    GROUP BY DATE(created_at)
                    ORDER BY data ASC
                ";
            } else {
                // Por semana
                $sqlSalesTrend = "
                    SELECT 
                        DATE_FORMAT(created_at, '%Y-W') as data,
                        COUNT(*) as vendas,
                        COALESCE(SUM(property_price), 0) as receita
                    FROM property_sales
                    WHERE created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                        AND tenant_id = ?
                        AND deleted_at IS NULL
                    GROUP BY WEEK(created_at), YEAR(created_at)
                    ORDER BY data ASC
                ";
            }

            $charts['sales_trend'] = $this->fetchAll($sqlSalesTrend, [$dataInicio, $dataFim, $tenantId]);
        }

        // =====================
        // 2. Lead Funnel (leads criados no período)
        // =====================
        if ($type === 'all' || $type === 'lead_funnel') {
            $sqlLeadFunnelPeriod = "
                SELECT 
                    'Leads' as stage,
                    COUNT(*) as quantidade
                FROM leads
                WHERE created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                    AND tenant_id = ?
            ";

            $leadFunnelPeriod = $this->fetchAll($sqlLeadFunnelPeriod, [$dataInicio, $dataFim, $tenantId]);

            // Se não há leads no período, pega total geral
            if (empty($leadFunnelPeriod) || (isset($leadFunnelPeriod[0]['quantidade']) && $leadFunnelPeriod[0]['quantidade'] == 0)) {
                $sqlLeadFunnelTotal = "
                    SELECT 
                        'Leads' as stage,
                        COUNT(*) as quantidade
                    FROM leads
                    WHERE tenant_id = ?
                ";
                $charts['lead_funnel'] = $this->fetchAll($sqlLeadFunnelTotal, [$tenantId]);
            } else {
                $charts['lead_funnel'] = $leadFunnelPeriod;
            }
        }

        // =====================
        // 3. Category Performance (por tipo de propriedade)
        // =====================
        if ($type === 'all' || $type === 'category_performance') {
            $sqlCategoryPerf = "
                SELECT 
                    p.type as categoria,
                    COUNT(DISTINCT p.id) as total_imoveis,
                    0 as visitas,
                    COALESCE(COUNT(DISTINCT ps.id), 0) as vendas,
                    COALESCE(SUM(ps.property_price), 0) as receita
                FROM properties p
                LEFT JOIN property_sales ps ON p.id = ps.property_id 
                    AND ps.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                    AND ps.deleted_at IS NULL
                    AND ps.tenant_id = ?
                WHERE p.tenant_id = ?
                GROUP BY p.type
                HAVING total_imoveis > 0
                ORDER BY vendas DESC, total_imoveis DESC
                LIMIT 10
            ";
            $charts['category_performance'] = $this->fetchAll($sqlCategoryPerf, [$dataInicio, $dataFim, $tenantId, $tenantId]);
        }

        // =====================
        // 4. Neighborhood Sales (vendas por bairro)
        // =====================
        if ($type === 'all' || $type === 'neighborhood') {
            $sqlNeighborhoodPeriod = "
                SELECT 
                    COALESCE(p.neighborhood, 'Sem bairro') as bairro,
                    COUNT(DISTINCT ps.id) as vendas,
                    COALESCE(SUM(ps.property_price), 0) as receita,
                    COALESCE(AVG(ps.property_price), 0) as ticket_medio
                FROM properties p
                LEFT JOIN property_sales ps ON p.id = ps.property_id 
                    AND ps.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                    AND ps.deleted_at IS NULL
                    AND ps.tenant_id = ?
                WHERE p.status IN ('vendido', 'alugado')
                    AND p.tenant_id = ?
                GROUP BY p.neighborhood
                ORDER BY vendas DESC, p.neighborhood ASC
                LIMIT 5
            ";

            $neighborhoodData = $this->fetchAll($sqlNeighborhoodPeriod, [$dataInicio, $dataFim, $tenantId, $tenantId]);

            // Se nenhuma venda no período, busca bairros com mais propriedades
            if (empty($neighborhoodData)) {
                $sqlNeighborhoodTotal = "
                    SELECT 
                        COALESCE(neighborhood, 'Sem bairro') as bairro,
                        0 as vendas,
                        0 as receita,
                        0 as ticket_medio
                    FROM properties
                    WHERE neighborhood IS NOT NULL
                        AND tenant_id = ?
                    GROUP BY neighborhood
                    ORDER BY COUNT(*) DESC
                    LIMIT 5
                ";
                $charts['neighborhood_sales'] = $this->fetchAll($sqlNeighborhoodTotal, [$tenantId]);
            } else {
                $charts['neighborhood_sales'] = $neighborhoodData;
            }
        }

        return $charts;
    }

    /**
     * Calcula taxa de conversão de leads
     *
     * @param int $total
     * @param int $converted
     * @return float
     */
    private function calculateConversionRate(int $total, int $converted): float
    {
        return $total > 0 ? round(($converted / $total) * 100, 1) : 0;
    }
}
