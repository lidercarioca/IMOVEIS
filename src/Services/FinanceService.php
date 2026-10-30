<?php

namespace App\Services;

use App\Auth\AuthContext;
use App\Infrastructure\Finance\FinancialTransactionRepository;
use App\Infrastructure\Finance\CommissionRepository;
use App\Security\TenantContext;
use Exception;
use PDO;

/**
 * FinanceService
 * 
 * Serviço de lógica de finanças e comissões
 * Responsável por:
 * - Cálculos financeiros
 * - Gestão de comissões
 * - Relatórios e estatísticas
 * - Movimentações entre contas
 */
class FinanceService
{
    protected PDO $pdo;
    protected FinancialTransactionRepository $financialRepository;
    protected CommissionRepository $commissionRepository;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->financialRepository = new FinancialTransactionRepository($this->pdo);
        $this->commissionRepository = new CommissionRepository($this->pdo);
    }

    private function getTenantId(): int
    {
        return (int) TenantContext::getTenantId();
    }

    private function getCurrentUserId(): ?int
    {
        $authContext = AuthContext::getInstance();
        if ($authContext->isAuthenticated()) {
            return $authContext->getUserId();
        }

        $sessionUserId = $_SESSION['user_id'] ?? null;
        if ($sessionUserId === null || $sessionUserId === '') {
            return null;
        }

        return (int) $sessionUserId;
    }

    private function isCurrentUserAdmin(): bool
    {
        $authContext = AuthContext::getInstance();
        if ($authContext->isAuthenticated()) {
            return $authContext->isAdmin();
        }

        $role = $_SESSION['role'] ?? null;
        if ($role === null) {
            return false;
        }

        $normalizedRole = strtolower(trim((string) $role));
        return in_array($normalizedRole, ['admin', 'administrator', 'superadmin'], true);
    }

    /**
     * Obter resumo do mês especificado
     * 
     * @param string $mes Formato: YYYY-MM
     * @param ?int $userId Filtrar por usuário (opcional)
     * @return array
     */
    public function getMonthlySummary(string $mes, ?int $userId = null): array
    {
        try {
            // Delegando SQL para repository
            $result = $this->financialRepository->getMonthlySummary($mes, $this->getTenantId(), $userId);

            return [
                'success' => true,
                'data' => [
                    'mes' => $mes,
                    'total_receita' => (float)($result['total_receita'] ?? 0),
                    'total_despesa' => (float)($result['total_despesa'] ?? 0),
                    'total_comissao' => (float)($result['total_comissao'] ?? 0),
                    'lucro_liquido' => (float)($result['total_receita'] ?? 0) - (float)($result['total_despesa'] ?? 0) - (float)($result['total_comissao'] ?? 0),
                    'total_transacoes' => (int)($result['total_transacoes'] ?? 0)
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Erro ao buscar resumo mensal: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Calcular comissão automaticamente ao vender um imóvel
     * 
     * @param int $saleId ID da venda
     * @param int $userId ID do vendedor
     * @param int $propertyId ID da propriedade
     * @param float $saleValue Valor total da venda
     * @param float $commissionPercent Percentual de comissão (ex: 6.5)
     * @param ?string $approvalNotes Notas (opcional)
     * @return array
     */
    public function calculateCommission(
        int $saleId,
        int $userId,
        int $propertyId,
        float $saleValue,
        float $commissionPercent,
        ?string $approvalNotes = null
    ): array {
        try {
            // Validar percentual
            if ($commissionPercent < 0 || $commissionPercent > 100) {
                throw new Exception('Percentual de comissão deve estar entre 0 e 100');
            }

            $commissionValue = round($saleValue * ($commissionPercent / 100), 2);

            // Inserir comissão pendente de aprovação (delegado para repository)
            $commissionId = $this->commissionRepository->insertCommission([
                'sale_id' => $saleId,
                'user_id' => $userId,
                'property_id' => $propertyId,
                'sale_value' => $saleValue,
                'commission_percentage' => $commissionPercent,
                'commission_value' => $commissionValue
            ], $this->getTenantId());

            return [
                'success' => true,
                'data' => [
                    'commission_id' => $commissionId,
                    'sale_value' => $saleValue,
                    'commission_percentage' => $commissionPercent,
                    'commission_value' => $commissionValue,
                    'status' => 'pendente'
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Erro ao calcular comissão: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Aprovar uma comissão pendente
     * 
     * @param int $commissionId ID da comissão
     * @param int $approvedBy ID do usuário aprovador (admin/gerente)
     * @param ?string $notes Notas de aprovação (opcional)
     * @return array
     */
    public function approveCommission(int $commissionId, int $approvedBy, ?string $notes = null): array
    {
        try {
            $tenantId = $this->getTenantId();
            if ($tenantId <= 0) {
                throw new Exception('Tenant inválido');
            }
            if ($commissionId <= 0) {
                throw new Exception('ID da comissão inválido');
            }

            $this->pdo->beginTransaction();
            $success = $this->commissionRepository->approveCommission($commissionId, $approvedBy, $tenantId, $notes ?? '');

            if (!$success) {
                $this->pdo->rollBack();
                throw new Exception('Erro ao aprovar comissão ou comissão não encontrada');
            }

            $this->pdo->commit();
            return [
                'success' => true,
                'message' => 'Comissão aprovada com sucesso',
                'data' => [
                    'commission_id' => $commissionId,
                    'status' => 'aprovada',
                    'approved_at' => date('Y-m-d H:i:s')
                ]
            ];
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return [
                'success' => false,
                'error' => 'Erro ao aprovar comissão: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Processar pagamento de uma comissão
     * 
     * @param int $commissionId ID da comissão
     * @param string $method Método de pagamento (transferência|cheque|dinheiro)
     * @param ?string $notes Notas (opcional)
     * @return array
     */
    public function payCommission(int $commissionId, string $method = 'transferência', ?string $notes = null): array
    {
        try {
            $tenantId = $this->getTenantId();
            if ($tenantId <= 0) {
                throw new Exception('Tenant inválido');
            }
            if ($commissionId <= 0) {
                throw new Exception('ID da comissão inválido');
            }

            // Validar método de pagamento
            $validMethods = ['transferência', 'cheque', 'dinheiro'];
            if (!in_array($method, $validMethods, true)) {
                throw new Exception('Método de pagamento inválido');
            }

            $this->pdo->beginTransaction();
            $success = $this->commissionRepository->payCommission($commissionId, $tenantId, $method);

            if (!$success) {
                $this->pdo->rollBack();
                throw new Exception('Erro ao pagar comissão ou comissão não está aprovada');
            }

            $commission = $this->commissionRepository->getCommissionById($commissionId, $tenantId);
            if (!$commission) {
                $this->pdo->rollBack();
                throw new Exception('Comissão não encontrada após atualização');
            }

            $logged = $this->logCommissionPayment($commission, $method);
            if (!$logged) {
                $this->pdo->rollBack();
                throw new Exception('Erro ao registrar auditoria de pagamento');
            }

            $this->pdo->commit();
            return [
                'success' => true,
                'message' => 'Comissão paga com sucesso',
                'data' => [
                    'commission_id' => $commissionId,
                    'status' => 'paga',
                    'paid_at' => date('Y-m-d H:i:s'),
                    'paid_method' => $method
                ]
            ];
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return [
                'success' => false,
                'error' => 'Erro ao pagar comissão: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Cancelar uma comissão
     * 
     * @param int $commissionId ID da comissão
     * @param ?string $reason Motivo do cancelamento (opcional)
     * @return array
     */
    public function cancelCommission(int $commissionId, ?string $reason = null): array
    {
        try {
            // Cancelar via repository
            $success = $this->commissionRepository->cancelCommission($commissionId, $this->getTenantId(), $reason ?? '');

            if (!$success) {
                throw new Exception('Erro ao cancelar comissão ou comissão não encontrada');
            }

            return [
                'success' => true,
                'message' => 'Comissão cancelada com sucesso'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Erro ao cancelar comissão: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obter estatísticas de comissões por vendedor
     * 
     * @param ?int $userId Filtrar por usuário (opcional)
     * @param ?string $startDate Data inicial (YYYY-MM-DD)
     * @param ?string $endDate Data final (YYYY-MM-DD)
     * @return array
     */
    public function getCommissionStats(?int $userId = null, ?string $startDate = null, ?string $endDate = null): array
    {
        try {
            if (!$startDate) {
                $startDate = date('Y-m-d', strtotime('-30 days'));
            }
            if (!$endDate) {
                $endDate = date('Y-m-d');
            }

            // Buscar estatísticas via repository
            $results = $this->commissionRepository->getCommissionStats($this->getTenantId(), $userId, $startDate, $endDate);

            // Formatar resultados
            $stats = [];
            foreach ($results as $row) {
                $stats[] = [
                    'user_id' => (int)($userId ?? 0),
                    'status' => $row['status'],
                    'count' => (int)($row['count'] ?? 0),
                    'total_value' => (float)($row['total_value'] ?? 0),
                    'average_value' => (float)($row['average_value'] ?? 0)
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'period' => ['start' => $startDate, 'end' => $endDate],
                    'stats' => $stats
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Erro ao buscar estatísticas: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obter estatísticas de comissões por vendedor com retorno compatível com o endpoint legado
     * 
     * @param ?int $userId Filtrar por usuário (opcional)
     * @param ?string $startDate Data inicial (YYYY-MM-DD)
     * @param ?string $endDate Data final (YYYY-MM-DD)
     * @return array
     */
    public function getCommissionStatsForApi(?int $userId = null, ?string $startDate = null, ?string $endDate = null): array
    {
        try {
            if (empty($startDate)) {
                $startDate = date('Y-m-d', strtotime('-30 days'));
            }
            if (empty($endDate)) {
                $endDate = date('Y-m-d');
            }

            $rows = $this->commissionRepository->getCommissionStats($this->getTenantId(), $userId, $startDate, $endDate);

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
            return [
                'success' => false,
                'error' => 'Erro ao buscar estatísticas: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obter resumo de folha de pagamento
     * 
     * @param ?string $mes Formato YYYY-MM
     * @return array
     */
    public function getPayrollSummary(?string $mes = null, ?int $userId = null): array
    {
        try {
            if (!$mes) {
                $mes = date('Y-m');
            }

            // Buscar resumo via repository
            $results = $this->commissionRepository->getPayrollSummary($mes, $this->getTenantId(), $userId);

            $totalPaid = 0;
            $totalApproved = 0;
            $totalPending = 0;

            $payroll = array_map(function ($row) use (&$totalPaid, &$totalApproved, &$totalPending) {
                $paid = (float)($row['paid'] ?? 0);
                $approved = (float)($row['pending'] ?? 0);
                $pending = (float)($row['total_commission'] ?? 0) - $paid - $approved;

                $totalPaid += $paid;
                $totalApproved += $approved;
                $totalPending += $pending;

                return [
                    'user_id' => (int)($row['user_id'] ?? 0),
                    'user_name' => $row['seller_name'] ?? '',
                    'total_commissions' => (int)($row['total_sales'] ?? 0),
                    'paid' => $paid,
                    'approved_pending' => $approved,
                    'pending_approval' => $pending
                ];
            }, $results);

            return [
                'success' => true,
                'data' => [
                    'mes' => $mes,
                    'payroll' => $payroll,
                    'summary' => [
                        'total_paid' => $totalPaid,
                        'total_approved_pending' => $totalApproved,
                        'total_pending_approval' => $totalPending,
                        'grand_total' => $totalPaid + $totalApproved + $totalPending
                    ]
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Erro ao buscar resumo de folha: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obter resumo de folha de pagamento com retorno compatível com o endpoint legado
     * 
     * @param string $mes Formato YYYY-MM
     * @param ?int $userId Filtrar por usuário (opcional)
     * @return array
     */
    public function getPayrollSummaryForApi(string $mes, ?int $userId = null): array
    {
        try {
            $rows = $this->commissionRepository->getPayrollSummary($mes, $this->getTenantId(), $userId);

            return [
                'success' => true,
                'month' => $mes,
                'data' => $rows
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Erro ao buscar resumo de folha: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Registrar pagamento de comissão no financeiro
     * 
     * @param array $commission Dados da comissão
     * @param string $method Método de pagamento
     * @return bool
     */
    private function logCommissionPayment(array $commission, string $method): bool
    {
        try {
            $description = "Pagamento de comissão para vendedor ID {$commission['user_id']} - Método: {$method}";

            // Delegando INSERT para repository
            return $this->financialRepository->logCommissionPayment(
                $this->getTenantId(),
                $commission['user_id'],
                $commission['property_id'] ?? null,
                $description,
                $commission['commission_value']
            );
        } catch (Exception $e) {
            // Log sem retornar erro
            return false;
        }
    }

    public function createTransaction(array $data, bool $isAdmin = false): array
    {
        try {
            if ($isAdmin) {
                $this->financialRepository->ensureTableExists();
            }

            $allowedTypes = ['receita', 'despesa', 'comissao'];
            if (empty($data['tipo']) || !in_array($data['tipo'], $allowedTypes, true)) {
                throw new Exception('Tipo inválido');
            }

            $valor = isset($data['valor']) ? (float)$data['valor'] : 0;
            if ($valor <= 0) {
                throw new Exception('Valor deve ser maior que zero');
            }

            if (empty($data['data_transacao']) || ! \DateTime::createFromFormat('Y-m-d', $data['data_transacao'])) {
                throw new Exception('Data inválida');
            }

            $userId = (int)($data['user_id'] ?? 0);
            if ($userId <= 0) {
                throw new Exception('Usuário inválido');
            }

            $tenantId = $this->getTenantId();
            $userRepository = new \App\Infrastructure\User\UserRepository($this->pdo);
            if (!$userRepository->existsInTenant($userId, $tenantId)) {
                throw new Exception('Usuário não pertence a este tenant');
            }

            if (!empty($data['property_id'])) {
                $propertyRepository = new \App\Repository\PropertyRepository($this->pdo);
                $property = $propertyRepository->findPropertyForController((int)$data['property_id'], $tenantId, null, $isAdmin);
                if (!$property) {
                    throw new Exception('Propriedade não encontrada');
                }
            }

            $transactionId = $this->financialRepository->createTransaction([
                'tipo' => $data['tipo'],
                'property_id' => $data['property_id'] ?? null,
                'user_id' => $userId,
                'descricao' => $data['descricao'] ?? '',
                'valor' => $valor,
                'data_transacao' => $data['data_transacao'],
                'categoria' => $data['categoria'] ?? 'Outra',
                'status' => $data['status'] ?? 'pendente'
            ], $tenantId);

            $transaction = $this->financialRepository->getTransactionById($transactionId, $tenantId);
            if ($transaction) {
                $transaction['valor'] = (float)$transaction['valor'];
            }

            return [
                'success' => true,
                'message' => 'Transação registrada com sucesso',
                'data' => $transaction
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function updateTransaction(int $transactionId, array $data, int $currentUserId, bool $isAdmin): array
    {
        try {
            $tenantId = $this->getTenantId();
            $transaction = $this->financialRepository->getTransactionById($transactionId, $tenantId);

            if (!$transaction) {
                throw new Exception('Transação não encontrada');
            }

            if (!$isAdmin && intval($transaction['user_id']) !== $currentUserId) {
                throw new Exception('Permissão negada');
            }

            $allowedTypes = ['receita', 'despesa', 'comissao'];
            if (isset($data['tipo']) && !in_array($data['tipo'], $allowedTypes, true)) {
                throw new Exception('Tipo inválido');
            }

            if (isset($data['valor']) && (float)$data['valor'] <= 0) {
                throw new Exception('Valor deve ser maior que zero');
            }

            if (isset($data['data_transacao']) && !\DateTime::createFromFormat('Y-m-d', $data['data_transacao'])) {
                throw new Exception('Data inválida');
            }

            $success = $this->financialRepository->updateTransaction($transactionId, $data, $tenantId);
            if (!$success) {
                throw new Exception('Nenhum registro foi atualizado');
            }

            $transaction = $this->financialRepository->getTransactionById($transactionId, $tenantId);
            if ($transaction) {
                $transaction['valor'] = (float)$transaction['valor'];
            }

            return [
                'success' => true,
                'message' => 'Transação atualizada com sucesso',
                'data' => $transaction
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function deleteTransaction(int $transactionId, int $currentUserId, bool $isAdmin): array
    {
        try {
            $tenantId = $this->getTenantId();
            $transaction = $this->financialRepository->getTransactionById($transactionId, $tenantId);

            if (!$transaction) {
                throw new Exception('Transação não encontrada');
            }

            if (!$isAdmin && intval($transaction['user_id']) !== $currentUserId) {
                throw new Exception('Sem permissão para deletar esta transação');
            }

            $saleId = null;
            if (!empty($transaction['property_id'])) {
                $sale = $this->commissionRepository->getSaleByPropertyId((int)$transaction['property_id'], $tenantId);
                $saleId = $sale['id'] ?? null;
            }

            $this->financialRepository->softDeleteTransaction($transactionId, $tenantId);

            if ($saleId) {
                $this->commissionRepository->softDeleteSaleById($saleId, $tenantId);
            }

            return [
                'success' => true,
                'message' => 'Transação deletada com sucesso. Venda e imóvel atualizados'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function listTransactions(array $filters, int $currentUserId, bool $isAdmin): array
    {
        try {
            if (!$isAdmin) {
                $filters['user_id'] = $currentUserId;
            }

            $tenantId = $this->getTenantId();
            $transactions = $this->financialRepository->listTransactions($filters, $tenantId);

            foreach ($transactions as &$transaction) {
                $transaction['valor'] = (float)$transaction['valor'];
            }

            if (($filters['modo'] ?? 'resumo') === 'resumo') {
                $summary = [
                    'total_receitas' => 0.0,
                    'total_despesas' => 0.0,
                    'total_comissoes' => 0.0,
                    'lucro_liquido' => 0.0
                ];

                foreach ($transactions as $row) {
                    if ($row['tipo'] === 'receita') {
                        $summary['total_receitas'] += $row['valor'];
                    } elseif ($row['tipo'] === 'despesa') {
                        $summary['total_despesas'] += $row['valor'];
                    } elseif ($row['tipo'] === 'comissao') {
                        $summary['total_comissoes'] += $row['valor'];
                    }
                }
                $summary['lucro_liquido'] = $summary['total_receitas'] - $summary['total_despesas'] - $summary['total_comissoes'];

                return [
                    'success' => true,
                    'data' => $summary
                ];
            }

            return [
                'success' => true,
                'data' => $transactions,
                'total' => count($transactions)
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function deleteCommission(int $commissionId, bool $isAdmin): array
    {
        try {
            if (!$isAdmin) {
                throw new Exception('Apenas administradores podem deletar comissões');
            }

            $tenantId = $this->getTenantId();
            $commission = $this->commissionRepository->getCommissionById($commissionId, $tenantId);
            if (!$commission) {
                throw new Exception('Comissão não encontrada');
            }

            $propertyId = $commission['property_id'] ?? null;
            $saleId = $commission['sale_id'] ?? null;

            if (!empty($propertyId)) {
                $propertyRepository = new \App\Repository\PropertyRepository($this->pdo);
                $property = $propertyRepository->findPropertyForController((int)$propertyId, $tenantId, null, true);
                if (!$property) {
                    throw new Exception('Imóvel não encontrado no tenant atual');
                }
            }

            if (!empty($saleId)) {
                $sale = $this->commissionRepository->getSaleByIdIncludingDeleted((int)$saleId, $tenantId);
                if (!$sale) {
                    throw new Exception('Venda não encontrada no tenant atual');
                }

                if (!empty($sale['deleted_at'])) {
                    $this->commissionRepository->softDeleteCommission($commissionId, $tenantId);

                    return [
                        'success' => true,
                        'message' => 'Comissão encerrada; venda já estava revertida no tenant atual'
                    ];
                }
            }

            $this->commissionRepository->softDeleteCommission($commissionId, $tenantId);

            if (!empty($saleId)) {
                $this->commissionRepository->softDeleteSaleById((int)$saleId, $tenantId);
                $this->financialRepository->softDeleteTransactionsBySaleId((int)$saleId, $tenantId);
            }

            if ($propertyId) {
                $propertyService = new \App\Service\PropertyService($this->pdo);
                $propertyService->updatePropertyController(
                    'status = :status',
                    [
                        ':status' => 'active',
                        ':id' => $propertyId,
                        ':tenant_id' => $tenantId
                    ]
                );
            }

            return [
                'success' => true,
                'message' => 'Comissão, venda e transações deletadas com sucesso. Imóvel marcado como ativo'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function getCommissionAudit(int $commissionId, bool $includeCalculations): array
    {
        try {
            $tenantId = $this->getTenantId();
            $commission = $this->commissionRepository->getCommissionById($commissionId, $tenantId);

            if (!$commission) {
                throw new Exception('Comissão não encontrada');
            }

            $currentUserId = $this->getCurrentUserId();
            if (!$this->isCurrentUserAdmin() && (string)($commission['user_id'] ?? '') !== (string)($currentUserId ?? '')) {
                throw new Exception('Sem permissão');
            }

            $property = null;
            if (!empty($commission['property_id'])) {
                $propertyRepository = new \App\Repository\PropertyRepository($this->pdo);
                $propertyData = $propertyRepository->findPropertyForController((int)$commission['property_id'], $tenantId, null, $this->isCurrentUserAdmin());
                if (!$propertyData) {
                    throw new Exception('Propriedade não encontrada no tenant atual');
                }
                $property = [
                    'id' => $propertyData['id'] ?? null,
                    'title' => $propertyData['title'] ?? null,
                    'price' => $propertyData['price'] ?? null
                ];
            }

            $sale = null;
            if (!empty($commission['sale_id'])) {
                $sale = $this->commissionRepository->getSaleById((int)$commission['sale_id'], $tenantId);
            }

            $config = null;
            if (!empty($commission['property_id'])) {
                $config = $this->commissionRepository->getCommissionSettingsByPropertyId((int)$commission['property_id'], $tenantId);
            }

            $userIds = array_values(array_unique(array_filter([
                $commission['user_id'] ?? null,
                $commission['approved_by'] ?? null,
                $sale['created_by'] ?? null
            ], static fn($value) => $value !== null && $value !== '')));

            $users = [];
            if (!empty($userIds)) {
                $userRepository = new \App\Infrastructure\User\UserRepository($this->pdo);
                foreach ($userRepository->getUsersByIds($userIds, $tenantId) as $u) {
                    $users[$u['id']] = $u;
                }
            }

            $audit = [
                'commission' => $commission,
                'sale' => $sale,
                'property' => $property,
                'users' => [
                    'vendedor' => $users[$commission['user_id']] ?? null,
                    'approved_by' => $commission['approved_by'] && isset($users[$commission['approved_by']]) ? $users[$commission['approved_by']] : null,
                    'created_by' => isset($users[$sale['created_by']]) ? $users[$sale['created_by']] : null
                ],
                'timeline' => [
                    'created_at' => $commission['created_at'],
                    'approved_at' => $commission['approved_at'],
                    'paid_at' => $commission['paid_at'],
                    'cancelled_at' => $commission['cancelled_at'] ?? null,
                    'updated_at' => $commission['updated_at'],
                    'deleted_at' => $commission['deleted_at']
                ]
            ];

            if ($includeCalculations && $config) {
                $audit['calculations'] = [
                    'sale_value' => $sale['sale_price'] ?? 0,
                    'commission_percentage' => $commission['commission_percentage'],
                    'commission_value' => $commission['commission_value'],
                    'commission_settings_applied' => [
                        'base_percentage' => $config['base_percentage'],
                        'bonus_percentage' => $config['bonus_percentage'],
                        'min_commission' => $config['min_commission'],
                        'apply_tax' => $config['apply_tax'],
                        'tax_percentage' => $config['tax_percentage']
                    ],
                    'calculation_breakdown' => [
                        'step1_base' => ($sale['sale_price'] ?? 0) * (($config['base_percentage'] ?? 0) / 100),
                        'step2_bonus' => ($sale['sale_price'] ?? 0) * (($config['bonus_percentage'] ?? 0) / 100),
                        'step3_total' => $commission['commission_value'],
                        'step4_notes' => 'Comissão aplicada conforme configuração do imóvel'
                    ]
                ];
            }

            $audit['activity_log'] = $this->commissionRepository->getCommissionLogs($commissionId, $tenantId);

            return [
                'success' => true,
                'audit' => $audit
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Erro ao carregar auditoria',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getCommissionReport(array $filters): array
    {
        try {
            if (!$this->isCurrentUserAdmin()) {
                $filters['user_id'] = $this->getCurrentUserId();
            }

            $tenantId = $this->getTenantId();
            $commissions = $this->commissionRepository->getCommissionReportData($filters, $tenantId);

            $totals = [
                'total_comissions' => 0,
                'total_value' => 0,
                'total_pending' => 0,
                'total_approved' => 0,
                'total_paid' => 0,
                'by_status' => []
            ];

            foreach ($commissions as $c) {
                $totals['total_comissions'] += 1;
                $totals['total_value'] += (float)$c['commission_value'];
                if ($c['status'] === 'pendente') $totals['total_pending'] += (float)$c['commission_value'];
                if ($c['status'] === 'aprovada') $totals['total_approved'] += (float)$c['commission_value'];
                if ($c['status'] === 'paga') $totals['total_paid'] += (float)$c['commission_value'];
                if (!isset($totals['by_status'][$c['status']])) {
                    $totals['by_status'][$c['status']] = 0;
                }
                $totals['by_status'][$c['status']] += (float)$c['commission_value'];
            }

            foreach ($commissions as &$commission) {
                $commission['sale_value'] = (float)$commission['sale_value'];
                $commission['commission_percentage'] = (float)$commission['commission_percentage'];
                $commission['commission_value'] = (float)$commission['commission_value'];
            }

            return [
                'success' => true,
                'data' => $commissions,
                'totals' => $totals,
                'filters' => [
                    'start_date' => $filters['start_date'] ?? date('Y-m-01'),
                    'end_date' => $filters['end_date'] ?? date('Y-m-d'),
                    'user_id' => $filters['user_id'] ?? null,
                    'status' => $filters['status'] ?? null
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Erro ao gerar relatório',
                'details' => $e->getMessage()
            ];
        }
    }

    public function listCommissions(array $filters, int $currentUserId, bool $isAdmin): array
    {
        try {
            if (!$isAdmin) {
                $filters['user_id'] = $currentUserId;
            }

            $tenantId = $this->getTenantId();
            $result = $this->commissionRepository->getCommissionsByFilters($filters, $tenantId);
            $total = $this->commissionRepository->countCommissionsByFilters($filters, $tenantId);

            return [
                'success' => true,
                'data' => $result,
                'pagination' => [
                    'page' => (int)($filters['page'] ?? 1),
                    'limit' => (int)($filters['limit'] ?? 20),
                    'total_records' => $total,
                    'total_pages' => (int)ceil($total / max(1, (int)($filters['limit'] ?? 20)))
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
