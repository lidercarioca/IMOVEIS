<?php

namespace App\Service;

use PDO;
use RuntimeException;
use App\Repository\ReportRepository;

/**
 * ReportService - Camada de Serviço para relatórios
 *
 * Responsável por:
 * - orquestrar regras de negócio de relatórios
 * - validar parâmetros e filtros
 * - aplicar isolamento de tenant quando necessário
 * - delegar acesso a dados para ReportRepository
 *
 * NÃO é responsável por:
 * - execução direta de SQL
 * - persistência de dados brutos
 */
class ReportService
{
    private PDO $pdo;
    private ReportRepository $repository;

    /**
     * Construtor
     *
     * @param PDO $pdo Conexão com banco de dados
     * @param ReportRepository|null $repository Repositório de dados de relatórios
     */
    public function __construct(PDO $pdo, ?ReportRepository $repository = null)
    {
        $this->pdo = $pdo;
        $this->repository = $repository ?? new ReportRepository($pdo);
    }

    /**
     * Obtém estatísticas do dashboard
     *
     * @param array $filters Filtros opcionais
     * @return array Dados processados do dashboard
     */
    public function getDashboardStats(array $filters = []): array
    {
        $tenantId = isset($filters['tenantId']) ? (int)$filters['tenantId'] : 0;
        $period = isset($filters['period']) ? (int)$filters['period'] : 7;
        $isAdmin = isset($filters['isAdmin']) ? (bool)$filters['isAdmin'] : false;
        $userId = isset($filters['userId']) ? (int)$filters['userId'] : null;

        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('Tenant inválido');
        }

        $period = in_array($period, [7, 30, 90], true) ? $period : 7;
        $startDate = date('Y-m-d', strtotime("-{$period} days"));

        $rawData = $this->repository->getDashboardStats([
            'tenantId' => $tenantId,
            'startDate' => $startDate,
            'isAdmin' => $isAdmin,
            'userId' => $userId,
        ]);

        $stats = [];
        $currentDate = new \DateTime($startDate);
        $endDate = new \DateTime();

        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');
            $stats[$dateStr] = [
                'date' => $dateStr,
                'leads' => 0,
                'new_properties' => 0,
                'closed_deals' => 0,
            ];
            $currentDate->modify('+1 day');
        }

        foreach ($rawData['leadStats'] as $item) {
            if (isset($stats[$item['date']])) {
                $stats[$item['date']]['leads'] = (int)$item['total_leads'];
            }
        }

        foreach ($rawData['propertyStats'] as $item) {
            if (isset($stats[$item['date']])) {
                $stats[$item['date']]['new_properties'] = (int)$item['new_properties'];
            }
        }

        foreach ($rawData['dealsStats'] as $item) {
            if (isset($stats[$item['date']])) {
                $stats[$item['date']]['closed_deals'] = (int)$item['closed_deals'];
            }
        }

        $finalStats = array_values($stats);

        $categories = [
            'Apartamentos' => 0,
            'Casas' => 0,
            'Comercial' => 0,
            'Terrenos' => 0,
        ];

        foreach ($rawData['categoryStats'] as $item) {
            if (!empty($item['categoria']) && array_key_exists($item['categoria'], $categories)) {
                $categories[$item['categoria']] = (int)$item['total'];
            }
        }

        return [
            'success' => true,
            'data' => $finalStats,
            'categories' => $categories,
        ];
    }

    /**
     * Obtém relatórios definidos pelo domínio
     *
     * @param array $criteria Critérios de relatório
     * @return array Dados processados dos relatórios
     */
    public function getReports(array $criteria = []): array
    {
        $type = isset($criteria['type']) ? trim((string)$criteria['type']) : '';
        $period = isset($criteria['period']) ? trim((string)$criteria['period']) : 'month';
        $tenantId = isset($criteria['tenantId']) ? (int)$criteria['tenantId'] : 0;

        if ($type === '') {
            throw new \InvalidArgumentException('Tipo de relatório inválido');
        }

        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('Tenant inválido');
        }

        return [
            'success' => true,
            'data' => $this->repository->getReports([
                'type' => $type,
                'period' => $period,
                'tenantId' => $tenantId,
            ]),
        ];
    }

    /**
     * Obtém estatísticas do portfolio
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

        return $this->repository->getPortfolioStats($tenantId, $userId, $isAdmin);
    }

    /**
     * Obtém contagem de propriedades por status
     * Wrapper que delega ao repository
     *
     * @param int $tenantId
     * @param string|null $status
     * @param string|null $type
     * @return array
     */
    public function getPropertiesByStatusReport(int $tenantId, ?string $status = null, ?string $type = null): array
    {
        return $this->repository->countPropertiesByStatus($tenantId, $status, $type);
    }

    /**
     * Obtém contagem de propriedades por tipo
     * Wrapper que delega ao repository
     *
     * @param int $tenantId
     * @param string|null $status
     * @return array
     */
    public function getPropertiesByTypeReport(int $tenantId, ?string $status = null): array
    {
        return $this->repository->countPropertiesByType($tenantId, $status);
    }

    /**
     * Obtém contagem de propriedades por bairro
     * Wrapper que delega ao repository
     *
     * @param int $tenantId
     * @param string|null $status
     * @param string|null $type
     * @return array
     */
    public function getPropertiesByNeighborhoodReport(int $tenantId, ?string $status = null, ?string $type = null): array
    {
        return $this->repository->countPropertiesByNeighborhoodAgg($tenantId, $status, $type);
    }

    /**
     * Obtém contagem de propriedades por faixas de preço
     * Wrapper que delega ao repository
     *
     * @param int $tenantId
     * @param array $ranges
     * @param string|null $status
     * @param string|null $type
     * @return array
     */
    public function getPropertiesByPriceReport(int $tenantId, array $ranges, ?string $status = null, ?string $type = null): array
    {
        return $this->repository->countPropertiesByPriceRanges($tenantId, $ranges, $status, $type);
    }

    /**
     * Obtém propriedades paradas
     * Wrapper que delega ao repository
     *
     * @param int $tenantId
     * @param int $daysThreshold
     * @param string|null $type
     * @return array
     */
    public function getParkedPropertiesReport(int $tenantId, int $daysThreshold, ?string $type = null): array
    {
        return $this->repository->getParkedProperties($tenantId, $daysThreshold, $type);
    }

    /**
     * Obtém estatísticas de vendas/aluguéis
     * Wrapper que delega ao repository
     *
     * @param int $tenantId
     * @param string $dataInicio
     * @param string $dataFim
     * @param string|null $type
     * @return array
     */
    public function getSoldRentedReport(int $tenantId, string $dataInicio, string $dataFim, ?string $type = null): array
    {
        return $this->repository->getSoldRentedStats($tenantId, $dataInicio, $dataFim, $type);
    }

    /**
     * Obtém KPIs filtrados do dashboard
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

        $payload = $this->repository->getExportDashboardData($tenantId, $period, $userId, $isAdmin, $reportType);

        return [
            'title' => $payload['title'] ?? 'Relatório Resumido',
            'data' => $payload['data'] ?? [],
        ];
    }

    public function getExportSummarySnapshot(int $tenantId, int $period, ?int $userId = null, ?string $categoria = null, ?string $regiao = null, bool $isAdmin = false): array
    {
        $tenantId = (int)$tenantId;
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('Tenant inválido');
        }

        return $this->repository->getExportSummarySnapshot($tenantId, $period, $userId, $categoria, $regiao, $isAdmin);
    }

    public function getFilteredDashboardKPIs(int $tenantId, int $period, ?int $userId = null, ?string $categoria = null, ?string $regiao = null): array
    {
        $tenantId = (int)$tenantId;
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('Tenant inválido');
        }

        $payload = $this->repository->getFilteredDashboardKPIs($tenantId, $period, $userId, $categoria, $regiao);

        return [
            'success' => true,
            'filters' => $payload['filters'] ?? [],
            'date_range' => $payload['date_range'] ?? [],
            'kpis' => $payload['kpis'] ?? [],
            'top_brokers' => $payload['top_brokers'] ?? [],
        ];
    }

    /**
     * Obtém ranking de corretores
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

        return [
            'success' => true,
            'data' => $this->repository->getBrokerRanking($tenantId),
        ];
    }

    /**
     * Obtém taxa de conversão por corretor
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

        return [
            'success' => true,
            'data' => $this->repository->getBrokerConversionRate($tenantId),
        ];
    }

    /**
     * Obtém ticket médio por período
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

        return [
            'success' => true,
            'data' => $this->repository->getAverageTicket($tenantId, $periodo, $dataInicio, $dataFim),
        ];
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

        return [
            'success' => true,
            'data' => $this->repository->getLeadsByStatus($tenantId, $status, $periodo),
        ];
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

        return [
            'success' => true,
            'data' => $this->repository->getLeadsByOrigin($tenantId, $status),
        ];
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

        return [
            'success' => true,
            'data' => $this->repository->getLeadsByBroker($tenantId),
        ];
    }

    /**
     * Obtém tempo médio em leads fechados
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

        return [
            'success' => true,
            'data' => $this->repository->getLeadsAverageTime($tenantId),
        ];
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

        return [
            'success' => true,
            'data' => $this->repository->getLeadsConversionRate($tenantId),
        ];
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

        $payload = $this->repository->getLeadsFunnel($tenantId, $periodo, $userId, $isAdmin);

        return array_merge([
            'success' => true,
        ], $payload);
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

        return [
            'success' => true,
            'data' => $this->repository->getLeadsLost($tenantId),
        ];
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

        return [
            'success' => true,
            'data' => $this->repository->getSalesByBroker($tenantId),
        ];
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

        return [
            'success' => true,
            'data' => $this->repository->getSalesByPeriod($tenantId, $periodo, $dataInicio, $dataFim),
        ];
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

        return [
            'success' => true,
            'data' => $this->repository->getSalesByType($tenantId, $periodo, $dataInicio, $dataFim),
        ];
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

        return [
            'success' => true,
            'data' => $this->repository->getSalesCycle($tenantId, $periodo, $dataInicio, $dataFim),
        ];
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

        return [
            'success' => true,
            'data' => $this->repository->getSalesPipeline($tenantId),
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

        return [
            'success' => true,
            'data' => $this->repository->getRevenueAndCommissions($tenantId, $periodo, $dataInicio, $dataFim),
        ];
    }

    /**
     * Obtém estatísticas de visitas
     *
     * @param array $filters Filtros de período e tenant
     * @return array Dados processados de visitas
     */
    public function getVisitStats(array $filters = []): array
    {
        $tenantId = isset($filters['tenantId']) ? (int)$filters['tenantId'] : 0;
        $period = isset($filters['period']) ? $filters['period'] : '30';

        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('Tenant inválido');
        }

        $periodDays = is_numeric($period) ? (int)$period : $period;

        $rawData = $this->repository->getVisitStats([
            'tenantId' => $tenantId,
            'period' => $period,
        ]);

        $totalVisits = isset($rawData['total_visits']) ? (int)$rawData['total_visits'] : 0;
        $visitsToday = isset($rawData['visits_today']) ? (int)$rawData['visits_today'] : 0;
        $visitsLastMonth = isset($rawData['visits_last_month']) ? (int)$rawData['visits_last_month'] : 0;

        $percentChange = 0;
        if ($visitsLastMonth > 0) {
            $percentChange = round((($totalVisits - $visitsLastMonth) / $visitsLastMonth) * 100, 1);
        }

        return [
            'success' => true,
            'data' => [
                'total_visits' => $totalVisits,
                'visits_today' => $visitsToday,
                'visits_last_period' => $visitsLastMonth,
                'percent_change' => $percentChange,
                'top_pages' => $rawData['top_pages'] ?? [],
                'period_days' => $periodDays,
            ],
        ];
    }

    /**
     * Obtém vendas pendentes
     *
     * @param array $filters Filtros de tenant e usuário
     * @return array Dados processados de vendas pendentes
     */
    public function getPendingSales(array $filters = []): array
    {
        $tenantId = isset($filters['tenantId']) ? (int)$filters['tenantId'] : 0;

        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('Tenant inválido');
        }

        $sales = $this->repository->getPendingSales([
            'tenantId' => $tenantId,
        ]);

        return [
            'success' => true,
            'data' => $sales,
            'count' => count($sales),
        ];
    }

    /**
     * Obtém metas e objetivos do período
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

        $raw = $this->repository->getGoalsAndTargets($tenantId, $period, $isAdmin, $userId);
        $goalsConfig = $raw['goalsConfig'] ?? [];
        $dateRange = $raw['date_range'] ?? ['start' => date('Y-m-d', strtotime("-{$period} days")), 'end' => date('Y-m-d')];
        $metricData = $raw['data'] ?? [];
        $goals = [];

        $goalOrder = ['leads', 'sales', 'revenue', 'conversion', 'properties'];
        foreach ($goalOrder as $goalId) {
            $config = $goalsConfig[$goalId] ?? ['goal_id' => $goalId, 'goal_name' => ucfirst($goalId), 'target_value' => 0, 'period_days' => 30];
            $goal = $metricData[$goalId] ?? ['real' => 0, 'target' => (float)($config['target_value'] ?? 0), 'percent' => 0];
            $real = $goal['real'] ?? 0;
            $target = $goal['target'] ?? (float)($config['target_value'] ?? 0);

            if ($goalId === 'revenue') {
                $percent = min(round(($real / $target) * 100), 100);
            } elseif ($goalId === 'conversion') {
                $realConversion = is_numeric($real) ? (float)$real : 0.0;
                $percent = min(round(($realConversion / $target) * 100), 100);
            } else {
                $percent = min(round(($real / $target) * 100), 100);
            }

            $status = $percent >= 100 ? 'completed' : ($percent >= 75 ? 'on_track' : 'at_risk');

            $goalItem = [
                'id' => $goalId,
                'title' => $config['goal_name'] ?? $goalId,
                'icon' => ['leads' => 'user-friends', 'sales' => 'handshake', 'revenue' => 'money-bill-wave', 'conversion' => 'chart-pie', 'properties' => 'home'][$goalId],
                'color' => ['leads' => '#10b981', 'sales' => '#f59e0b', 'revenue' => '#3b82f6', 'conversion' => '#8b5cf6', 'properties' => '#ec4899'][$goalId],
                'real' => $real,
                'target' => $target,
                'percent' => $percent,
                'status' => $status,
                'unit' => ['leads' => 'leads', 'sales' => 'vendas', 'revenue' => 'currency', 'conversion' => 'percent', 'properties' => 'properties'][$goalId],
            ];

            if ($goalId === 'revenue') {
                $goalItem['real_formatted'] = 'R$ ' . number_format($real, 0, ',', '.');
                $goalItem['target_formatted'] = 'R$ ' . number_format($target, 0, ',', '.');
            }

            $goals[] = $goalItem;
        }

        $overallPercent = round(array_sum(array_column($goals, 'percent')) / count($goals));
        $overallStatus = $overallPercent >= 100 ? 'completed' : ($overallPercent >= 75 ? 'on_track' : 'at_risk');

        return [
            'success' => true,
            'period' => (int)$period,
            'date_range' => [
                'start' => $dateRange['start'] ?? date('Y-m-d', strtotime("-{$period} days")),
                'end' => $dateRange['end'] ?? date('Y-m-d'),
            ],
            'goals' => $goals,
            'overall' => [
                'percent' => $overallPercent,
                'status' => $overallStatus,
                'completed_goals' => count(array_filter($goals, fn($goal) => $goal['status'] === 'completed')),
                'on_track_goals' => count(array_filter($goals, fn($goal) => $goal['status'] === 'on_track')),
                'at_risk_goals' => count(array_filter($goals, fn($goal) => $goal['status'] === 'at_risk')),
            ],
        ];
    }

    /**
     * Obtém feed de atividades por tenant
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

        $normalizedLimit = (int)$limit;
        $normalizedOffset = (int)$offset;

        if ($normalizedLimit > 5) {
            $normalizedLimit = 5;
        }

        if ($normalizedLimit < 1) {
            $normalizedLimit = 20;
        }

        if ($normalizedOffset < 0) {
            $normalizedOffset = 0;
        }

        $raw = $this->repository->getActivityFeed($tenantId, $normalizedLimit, $normalizedOffset, $isAdmin, $userId);

        $activities = [];
        foreach ($raw['activities'] ?? [] as $activity) {
            $activities[] = [
                'type' => $activity['activity_type'] ?? null,
                'id' => (int)($activity['activity_id'] ?? 0),
                'description' => $activity['description'] ?? '',
                'user_name' => $activity['user_name'] ?? 'Sistema',
                'user_id' => (int)($activity['user_id'] ?? 0),
                'icon' => $activity['icon'] ?? '',
                'color' => $activity['color'] ?? '',
                'timestamp' => $activity['timestamp'] ?? null,
                'time_ago' => self::timeAgo($activity['timestamp'] ?? null),
            ];
        }

        $total = (int)($raw['total'] ?? 0);

        return [
            'success' => true,
            'data' => $activities,
            'pagination' => [
                'total' => $total,
                'limit' => $normalizedLimit,
                'offset' => $normalizedOffset,
                'has_more' => ($normalizedOffset + $normalizedLimit) < $total,
            ],
        ];
    }

    /**
     * Calcula tempo passado em português
     */
    private static function timeAgo(?string $timestamp): string
    {
        if (empty($timestamp)) {
            return 'Agora mesmo';
        }

        $time = strtotime($timestamp);
        if ($time === false) {
            return 'Agora mesmo';
        }

        $diff = time() - $time;

        if ($diff < 60) {
            return 'Agora mesmo';
        }

        if ($diff < 3600) {
            return round($diff / 60) . ' min';
        }

        if ($diff < 86400) {
            return round($diff / 3600) . ' h';
        }

        if ($diff < 604800) {
            return round($diff / 86400) . ' d';
        }

        return date('d/m/Y', $time);
    }

    /**
     * Obtém snapshot do dashboard em tempo real
     *
     * @param int $tenantId
     * @param int $userId
     * @param int $period
     * @return array
     */
    public function getRealtimeDashboardSnapshot(int $tenantId, int $userId, int $period): array
    {
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('Tenant inválido');
        }

        $period = max(1, min($period, 365));

        $kpis = $this->repository->getRealtimeDashboardKPIs($tenantId, $userId, $period);
        $brokers = $this->repository->getRealtimeTopBrokers($tenantId, $period);
        $recentLeads = $this->repository->getRealtimeRecentLeads($tenantId, $userId, 5);

        return [
            'kpis' => $kpis,
            'brokers' => $brokers,
            'recentLeads' => $recentLeads,
        ];
    }

    /**
     * Obtém KPIs do dashboard
     *
     * @param array $filters Filtros de tenant e período
     * @return array Dados processados de KPIs
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

        if (!$isAdmin && !$userId) {
            throw new \RuntimeException('Sessão não inicializada - user_id não encontrado');
        }

        $period = max(1, min($period, 365));

        $data = $this->repository->getDashboardKPIs([
            'tenantId' => $tenantId,
            'period' => $period,
            'userId' => $userId,
            'isAdmin' => $isAdmin,
        ]);

        $dataInicio = $data['dateInicio'] ?? date('Y-m-d', strtotime("-{$period} days"));
        $dataFim = $data['dateFim'] ?? date('Y-m-d');
        $dataInicioAnterior = $data['dateInicioAnterior'] ?? date('Y-m-d', strtotime($dataInicio . ' -1 day'));
        $dataFimAnterior = $data['dateFimAnterior'] ?? date('Y-m-d', strtotime($dataInicio . ' -1 day'));

        $totalLeads = (int)($data['totalLeads'] ?? 0);
        $totalLeadsPrevious = (int)($data['totalLeadsPrevious'] ?? 0);
        $totalSales = (int)($data['totalSales'] ?? 0);
        $totalSalesPrevious = (int)($data['totalSalesPrevious'] ?? 0);
        $averageTicket = (float)($data['averageTicket'] ?? 0);
        $averageTicketPrevious = (float)($data['averageTicketPrevious'] ?? 0);
        $averageCycleDays = (int)($data['averageCycleDays'] ?? 0);
        $averageCycleDaysPrevious = (int)($data['averageCycleDaysPrevious'] ?? 0);
        $totalRevenue = (float)($data['totalRevenue'] ?? 0);
        $totalRevenuePrevious = (float)($data['totalRevenuePrevious'] ?? 0);
        $conversionRate = (float)(($totalLeads > 0) ? round(($totalSales / $totalLeads) * 100, 2) : 0);
        $conversionRatePrevious = (float)(($totalLeadsPrevious > 0) ? round(($totalSalesPrevious / $totalLeadsPrevious) * 100, 2) : 0);

        return [
            'success' => true,
            'period' => $period,
            'date_range' => [
                'current' => ['start' => $dataInicio, 'end' => $dataFim],
                'previous' => ['start' => $dataInicioAnterior, 'end' => $dataFimAnterior],
            ],
            'kpis' => [
                'total_properties' => (int)($data['totalProperties'] ?? 0),
                'total_leads' => [
                    'current' => $totalLeads,
                    'previous' => $totalLeadsPrevious,
                    'variation' => $this->calcularVariacao($totalLeads, $totalLeadsPrevious),
                    'trend' => $this->calcularVariacao($totalLeads, $totalLeadsPrevious) >= 0 ? 'up' : 'down',
                ],
                'total_visits' => (int)($data['totalVisits'] ?? 0),
                'total_sales' => [
                    'current' => $totalSales,
                    'previous' => $totalSalesPrevious,
                    'variation' => $this->calcularVariacao($totalSales, $totalSalesPrevious),
                    'trend' => $this->calcularVariacao($totalSales, $totalSalesPrevious) >= 0 ? 'up' : 'down',
                ],
                'conversion_rate' => [
                    'current' => $conversionRate,
                    'previous' => $conversionRatePrevious,
                    'variation' => $this->calcularVariacao($conversionRate, $conversionRatePrevious),
                    'trend' => $this->calcularVariacao($conversionRate, $conversionRatePrevious) >= 0 ? 'up' : 'down',
                ],
                'average_ticket' => [
                    'current' => $averageTicket,
                    'previous' => $averageTicketPrevious,
                    'variation' => $this->calcularVariacao($averageTicket, $averageTicketPrevious),
                    'trend' => $this->calcularVariacao($averageTicket, $averageTicketPrevious) >= 0 ? 'up' : 'down',
                ],
                'average_cycle_days' => [
                    'current' => $averageCycleDays,
                    'previous' => $averageCycleDaysPrevious,
                    'variation' => $this->calcularVariacao($averageCycleDays, $averageCycleDaysPrevious),
                    'trend' => $this->calcularVariacao($averageCycleDays, $averageCycleDaysPrevious) <= 0 ? 'down' : 'up',
                ],
                'total_revenue' => [
                    'current' => $totalRevenue,
                    'previous' => $totalRevenuePrevious,
                    'variation' => $this->calcularVariacao($totalRevenue, $totalRevenuePrevious),
                    'trend' => $this->calcularVariacao($totalRevenue, $totalRevenuePrevious) >= 0 ? 'up' : 'down',
                ],
                'stopped_properties' => (int)($data['stoppedProperties'] ?? 0),
            ],
            'top_brokers' => $data['topBrokers'] ?? [],
        ];
    }

    private function calcularVariacao(float $valorAtual, float $valorAnterior): float
    {
        if ($valorAnterior == 0) {
            return ($valorAtual > 0) ? 100.0 : 0.0;
        }

        return round((($valorAtual - $valorAnterior) / $valorAnterior) * 100, 2);
    }

    /**
     * Obtém dados dos gráficos do dashboard
     *
     * @param array $filters Filtros de tenant, período, tipo
     * @return array Dados processados dos gráficos formatados para frontend
     */
    public function getDashboardCharts(array $filters = []): array
    {
        $tenantId = isset($filters['tenantId']) ? (int)$filters['tenantId'] : 0;
        $period = isset($filters['period']) ? (int)$filters['period'] : 90;
        $type = isset($filters['type']) ? (string)$filters['type'] : 'all';

        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('Tenant inválido');
        }

        // Validar período (limite 1-365 dias)
        $period = max(1, min((int)$period, 365));

        // Calcular datas
        $dataFim = date('Y-m-d');
        $dataInicio = date('Y-m-d', strtotime("-{$period} days"));

        // Chamar repository para obter dados brutos
        $rawCharts = $this->repository->getDashboardCharts([
            'tenantId' => $tenantId,
            'dataInicio' => $dataInicio,
            'dataFim' => $dataFim,
            'period' => $period,
            'type' => $type,
        ]);

        $charts = [];

        // =====================
        // Processar Sales Trend
        // =====================
        if ($type === 'all' || $type === 'sales_trend') {
            if (isset($rawCharts['sales_trend'])) {
                $charts['sales_trend'] = [
                    'label' => 'Tendência de Vendas',
                    'type' => 'area',
                    'data' => array_map(function ($row) {
                        return [
                            'label' => $row['data'],
                            'vendas' => (int)$row['vendas'],
                            'receita' => (float)$row['receita']
                        ];
                    }, $rawCharts['sales_trend'])
                ];
            }
        }

        // =====================
        // Processar Lead Funnel
        // =====================
        if ($type === 'all' || $type === 'lead_funnel') {
            if (isset($rawCharts['lead_funnel'])) {
                $charts['lead_funnel'] = [
                    'label' => 'Funil de Leads',
                    'type' => 'bar',
                    'data' => array_map(function ($row) {
                        return [
                            'stage' => $row['stage'],
                            'quantidade' => (int)$row['quantidade'],
                            'imoveis' => (int)($row['imoveis_associados'] ?? 0)
                        ];
                    }, $rawCharts['lead_funnel'])
                ];
            }
        }

        // =====================
        // Processar Category Performance
        // =====================
        if ($type === 'all' || $type === 'category_performance') {
            if (isset($rawCharts['category_performance'])) {
                $charts['category_performance'] = [
                    'label' => 'Desempenho por Categoria',
                    'type' => 'mixed',
                    'data' => array_map(function ($row) {
                        return [
                            'categoria' => $row['categoria'],
                            'imoveis' => (int)$row['total_imoveis'],
                            'visitas' => (int)$row['visitas'],
                            'vendas' => (int)$row['vendas'],
                            'receita' => (float)$row['receita']
                        ];
                    }, $rawCharts['category_performance'])
                ];
            }
        }

        // =====================
        // Processar Neighborhood Sales
        // =====================
        if ($type === 'all' || $type === 'neighborhood') {
            if (isset($rawCharts['neighborhood_sales'])) {
                $charts['neighborhood_sales'] = [
                    'label' => 'Vendas por Bairro (Top 5)',
                    'type' => 'pie',
                    'data' => array_map(function ($row) {
                        return [
                            'bairro' => $row['bairro'] ?? 'Sem bairro',
                            'vendas' => (int)($row['vendas'] ?? 0),
                            'receita' => (float)($row['receita'] ?? 0),
                            'ticket_medio' => (float)($row['ticket_medio'] ?? 0)
                        ];
                    }, $rawCharts['neighborhood_sales'])
                ];
            }
        }

        return [
            'success' => true,
            'period' => $period,
            'date_range' => [
                'start' => $dataInicio,
                'end' => $dataFim
            ],
            'data' => [
                'charts' => $charts,
            ],
            'charts' => $charts,
        ];
    }

    /**
     * Registra uma visita no domínio de relatórios
     *
     * @param array $payload Dados da visita
     * @return array Resultado processado da operação
     */
    public function trackVisit(array $payload): array
    {
        $propertyId = isset($payload['property_id']) ? (int)$payload['property_id'] : 0;
        $source = isset($payload['source']) ? (string)$payload['source'] : 'unknown';
        $userId = isset($payload['user_id']) ? $payload['user_id'] : null;
        $visitedAt = isset($payload['visited_at']) ? (string)$payload['visited_at'] : date('Y-m-d H:i:s');

        if ($propertyId <= 0) {
            throw new \InvalidArgumentException('ID da propriedade inválido');
        }

        $result = $this->repository->trackVisit([
            'property_id' => $propertyId,
            'source' => $source,
            'user_id' => $userId,
            'visited_at' => $visitedAt,
        ]);

        // Registro de auditoria (mantém comportamento anterior)
        if (function_exists('log_user_action')) {
            log_user_action('property_visit_tracked', [
                'property_id' => $propertyId,
                'source' => $source,
                'user_id' => $userId,
                'tenant_id' => $result['property_tenant_id'] ?? null,
            ]);
        }

        return [
            'success' => true,
            'message' => 'Visita registrada com sucesso',
        ];
    }
}
