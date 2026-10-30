<?php

namespace App\Models;

use PDO;
use Exception;
use App\Infrastructure\Finance\CommissionRepository;
use App\Security\TenantContext;

/**
 * CommissionModel
 * 
 * Gerencia comissões de vendedores
 * Funcionalidades:
 * - Listagem e filtros
 * - Aprovação de comissões
 * - Pagamento
 * - Estatísticas e relatórios
 */
class CommissionModel extends BaseModel
{
    protected string $table = 'commissions';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'sale_id',
        'user_id',
        'property_id',
        'sale_value',
        'commission_percentage',
        'commission_value',
        'status',
        'approval_notes',
        'approved_at',
        'approved_by',
        'paid_at',
        'paid_method'
    ];

    private CommissionRepository $repository;

    public function __construct(?PDO $pdo = null)
    {
        parent::__construct($pdo);
        $this->repository = new CommissionRepository($this->pdo);
    }

    private function getTenantId(): int
    {
        return (int) TenantContext::getTenantId();
    }

    /**
     * Criar comissão automaticamente ao vender um imóvel
     * 
     * @param array $data Dados da comissão
     * @return array|false
     */
    public function createFromSale($data)
    {
        try {
            // Validações básicas
            if (empty($data['sale_id']) || empty($data['user_id']) || empty($data['sale_value'])) {
                throw new Exception('Dados incompletos para criar comissão');
            }

            $tenantId = $this->getTenantId();

            if ($this->repository->existsBySaleId((int)$data['sale_id'], $tenantId)) {
                throw new Exception('Comissão já existe para esta venda');
            }

            $commission_percentage = $data['commission_percentage'] ?? null;
            if ($commission_percentage === null || $commission_percentage === '') {
                $commission_percentage = !empty($data['property_id'])
                    ? $this->repository->getPropertyCommissionPercentage((int)$data['property_id'], $tenantId)
                    : null;
            }

            if ($commission_percentage === null || $commission_percentage === '') {
                return false;
            }

            $commission_percentage = (float)$commission_percentage;
            if (!is_finite($commission_percentage) || $commission_percentage < 0) {
                return false;
            }

            $commission_value = ($data['sale_value'] * $commission_percentage) / 100;

            $commissionId = $this->repository->insertCommission([
                'sale_id' => $data['sale_id'],
                'user_id' => $data['user_id'],
                'property_id' => $data['property_id'],
                'sale_value' => $data['sale_value'],
                'commission_percentage' => $commission_percentage,
                'commission_value' => $commission_value
            ], $tenantId);

            if ($commissionId !== false) {
                return [
                    'id' => $commissionId,
                    'commission_value' => $commission_value,
                    'status' => 'pendente'
                ];
            }

            return false;
        } catch (Exception $e) {
            error_log("Erro ao criar comissão: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obter comissões com filtros avançados
     * 
     * @param array $filters Filtros (user_id, status, start_date, end_date, page, limit)
     * @return array
     */
    public function getCommissionsByFilters($filters = [])
    {
        try {
            $filters = (array)$filters;
            $page = max(1, (int)($filters['page'] ?? 1));
            $limit = max(0, (int)($filters['limit'] ?? 20));
            $offset = $limit > 0 ? ($page - 1) * $limit : 0;

            // Pass prepared limit/offset to repository; repository will not compute them
            if ($limit > 0) {
                $filters['limit'] = $limit;
                $filters['offset'] = $offset;
            }

            $tenantId = $this->getTenantId();
            $data = $this->repository->getCommissionsByFilters($filters, $tenantId);
            $total = $this->repository->countCommissionsByFilters($filters, $tenantId);

            return [
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'page' => (int)$page,
                    'limit' => (int)$limit,
                    'total_records' => $total,
                    'total_pages' => $limit > 0 ? (int)ceil($total / $limit) : 0
                ]
            ];
        } catch (Exception $e) {
            error_log("Erro ao buscar comissões (model): " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Obter comissões pendentes de aprovação
     * 
     * @return array
     */
    public function getPendingCommissions()
    {
        return $this->repository->getPendingCommissions($this->getTenantId());
    }

    /**
     * Obter estatísticas de comissões por vendedor
     * 
     * @param int $userId ID do vendedor
     * @param string $startDate Data inicial (YYYY-MM-DD)
     * @param string $endDate Data final (YYYY-MM-DD)
     * @return array
     */
    public function getCommissionStats($userId = null, $startDate = null, $endDate = null)
    {
        try {
            // Apply defaults for period (business rule)
            if (empty($startDate)) {
                $startDate = date('Y-m-d', strtotime('-30 days'));
            }
            if (empty($endDate)) {
                $endDate = date('Y-m-d');
            }

            $rows = $this->repository->getCommissionStats($this->getTenantId(), $userId, $startDate, $endDate);

            $total = [
                'total_commissions' => 0,
                'total_value' => 0,
                'by_status' => []
            ];

            foreach ($rows as $row) {
                $count = isset($row['count']) ? (int)$row['count'] : 0;
                $value = isset($row['total_value']) ? (float)$row['total_value'] : 0.0;

                $total['total_commissions'] += $count;
                $total['total_value'] += $value;
                $status = $row['status'] ?? 'unknown';
                $total['by_status'][$status] = [
                    'count' => $count,
                    'value' => $value,
                    'average' => isset($row['average_value']) ? (float)$row['average_value'] : 0
                ];
            }

            return [
                'success' => true,
                'period' => ['start' => $startDate, 'end' => $endDate],
                'stats' => $total,
                'details' => $rows
            ];
        } catch (Exception $e) {
            error_log("Erro ao obter estatísticas de comissão (model): " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Aprovar comissão
     * 
     * @param int $commissionId ID da comissão
     * @param int $approvedBy ID do gerente
     * @param string $notes Observações
     * @return bool
     */
    public function approveCommission($commissionId, $approvedBy, $notes = '')
    {
        return $this->repository->approveCommission($commissionId, $approvedBy, $this->getTenantId(), $notes);
    }

    /**
     * Marcar comissão como paga
     * 
     * @param int $commissionId ID da comissão
     * @param string $method Método de pagamento
     * @return bool
     */
    public function payCommission($commissionId, $method = 'transferência')
    {
        return $this->repository->payCommission($commissionId, $this->getTenantId(), $method);
    }

    /**
     * Cancelar comissão (soft delete)
     * 
     * @param int $commissionId ID da comissão
     * @param string $reason Motivo do cancelamento
     * @return bool
     */
    public function cancelCommission($commissionId, $reason = '')
    {
        return $this->repository->cancelCommission($commissionId, $this->getTenantId(), $reason);
    }

    /**
     * Obter resumo de comissões para relatório de folha
     * 
     * @param string $month YYYY-MM
     * @param int $userId (opcional)
     * @return array
     */
    public function getPayrollSummary($month, $userId = null)
    {
        try {
            $rows = $this->repository->getPayrollSummary($month, $this->getTenantId(), $userId);
            return [
                'success' => true,
                'month' => $month,
                'data' => $rows
            ];
        } catch (Exception $e) {
            error_log("Erro ao obter resumo de folha (model): " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Obter comissão por sale_id
     * 
     * @param int $saleId ID da venda
     * @return array|null
     */
    public function getCommissionBySaleId($saleId)
    {
        return $this->repository->getCommissionBySaleId($saleId, $this->getTenantId());
    }
}
