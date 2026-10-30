<?php

namespace App\Models;

use PDO;
use Exception;

/**
 * BillingHistoryModel
 * 
 * Gerencia histórico de pagamentos e faturamento
 * Funcionalidades:
 * - Listar pagamentos com filtros
 * - Relatório de receita
 * - Exportação de dados
 */
class BillingHistoryModel extends BaseModel
{
    protected string $table = 'pagamentos';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'tenant_id',
        'valor',
        'status',
        'data_pagamento',
        'plano'
    ];

    public function __construct(?PDO $pdo = null)
    {
        parent::__construct($pdo);
    }

    /**
     * Listar pagamentos de um tenant com paginação e filtros
     * 
     * @param int $tenantId ID do tenant
     * @param array $filters ['mes' => 'YYYY-MM', 'status' => 'pago|pendente', 'plano' => 'basico|profissional|premium']
     * @param int $page Página atual
     * @param int $limit Itens por página
     * @return array ['data' => array, 'pagination' => array]
     */
    public function listPayments(int $tenantId, array $filters = [], int $page = 1, int $limit = 20): array
    {
        try {
            // Construir query base
            $where = "WHERE tenant_id = :tenant_id";
            $params = [':tenant_id' => $tenantId];

            // Filtro por mês
            if (!empty($filters['mes'])) {
                $mes = $filters['mes']; // Formato: YYYY-MM
                $dataInicio = $mes . '-01';
                $dataFim = date('Y-m-t', strtotime($dataInicio));
                $where .= " AND DATE(data_pagamento) BETWEEN :data_inicio AND :data_fim";
                $params[':data_inicio'] = $dataInicio;
                $params[':data_fim'] = $dataFim;
            }

            // Filtro por status
            if (!empty($filters['status'])) {
                $where .= " AND status = :status";
                $params[':status'] = $filters['status'];
            }

            // Filtro por plano
            if (!empty($filters['plano'])) {
                $where .= " AND plano = :plano";
                $params[':plano'] = $filters['plano'];
            }

            // Contar total
            $countSql = "SELECT COUNT(*) as total FROM {$this->table} {$where}";
            $countStmt = $this->pdo->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Validar paginação
            $offset = ($page - 1) * $limit;

            // Buscar dados com paginação
            $sql = "SELECT 
                        id,
                        tenant_id,
                        valor,
                        status,
                        DATE_FORMAT(data_pagamento, '%d/%m/%Y') as data_pagamento,
                        DATE_FORMAT(data_pagamento, '%d/%m/%Y %H:%i') as data_pagamento_completa,
                        plano,
                        created_at
                    FROM {$this->table}
                    {$where}
                    ORDER BY data_pagamento DESC
                    LIMIT :offset, :limit";

            $stmt = $this->pdo->prepare($sql);

            // Fazer bind manual para LIMIT
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => (int)$total,
                    'pages' => ceil($total / $limit)
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Erro ao listar pagamentos: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obter resumo de receita por período
     * 
     * @param int $tenantId ID do tenant
     * @param string $periodo 'mes' | 'trimestre' | 'ano' | 'todos'
     * @param string $dataInicio Data inicial (opcional, formato Y-m-d)
     * @param string $dataFim Data final (opcional, formato Y-m-d)
     * @return array
     */
    public function getRevenueReport(int $tenantId, string $periodo = 'mes', ?string $dataInicio = null, ?string $dataFim = null): array
    {
        try {
            // Definir intervalo de datas
            if ($dataInicio && $dataFim) {
                $inicio = $dataInicio;
                $fim = $dataFim;
            } else {
                switch ($periodo) {
                    case 'todos':
                        $inicio = '2000-01-01';
                        $fim = date('Y-m-d');
                        break;
                    case 'ano':
                        $inicio = date('Y-01-01');
                        $fim = date('Y-m-d');
                        break;
                    case 'trimestre':
                        $trimestre = ceil(date('n') / 3);
                        $mesInicio = ($trimestre - 1) * 3 + 1;
                        $inicio = date('Y-' . str_pad($mesInicio, 2, '0', STR_PAD_LEFT) . '-01');
                        $fim = date('Y-m-d');
                        break;
                    case 'mes':
                    default:
                        $inicio = date('Y-m-01');
                        $fim = date('Y-m-d');
                }
            }

            // Query de resumo geral
            $sql = "SELECT 
                        COUNT(*) as total_pagamentos,
                        SUM(CASE WHEN status = 'pago' THEN valor ELSE 0 END) as valor_pago,
                        SUM(CASE WHEN status = 'pendente' THEN valor ELSE 0 END) as valor_pendente,
                        SUM(valor) as valor_total,
                        MIN(data_pagamento) as primeira_data,
                        MAX(data_pagamento) as ultima_data
                    FROM {$this->table}
                    WHERE tenant_id = :tenant_id 
                    AND DATE(data_pagamento) BETWEEN :data_inicio AND :data_fim";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':tenant_id' => $tenantId,
                ':data_inicio' => $inicio,
                ':data_fim' => $fim
            ]);
            $summary = $stmt->fetch(PDO::FETCH_ASSOC);

            // Query de receita por plano
            $sqlByPlan = "SELECT 
                            plano,
                            COUNT(*) as quantidade,
                            SUM(CASE WHEN status = 'pago' THEN valor ELSE 0 END) as valor_pago,
                            SUM(valor) as valor_total
                        FROM {$this->table}
                        WHERE tenant_id = :tenant_id 
                        AND DATE(data_pagamento) BETWEEN :data_inicio AND :data_fim
                        GROUP BY plano
                        ORDER BY valor_total DESC";

            $stmtPlan = $this->pdo->prepare($sqlByPlan);
            $stmtPlan->execute([
                ':tenant_id' => $tenantId,
                ':data_inicio' => $inicio,
                ':data_fim' => $fim
            ]);
            $byPlan = $stmtPlan->fetchAll(PDO::FETCH_ASSOC);

            // Query de receita por mês
            $sqlByMonth = "SELECT 
                            DATE_FORMAT(data_pagamento, '%Y-%m') as mes,
                            COUNT(*) as quantidade,
                            SUM(CASE WHEN status = 'pago' THEN valor ELSE 0 END) as valor_pago,
                            SUM(valor) as valor_total
                        FROM {$this->table}
                        WHERE tenant_id = :tenant_id 
                        AND DATE(data_pagamento) BETWEEN :data_inicio AND :data_fim
                        GROUP BY DATE_FORMAT(data_pagamento, '%Y-%m')
                        ORDER BY mes DESC";

            $stmtMonth = $this->pdo->prepare($sqlByMonth);
            $stmtMonth->execute([
                ':tenant_id' => $tenantId,
                ':data_inicio' => $inicio,
                ':data_fim' => $fim
            ]);
            $byMonth = $stmtMonth->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'periodo' => $periodo,
                'data_inicio' => $inicio,
                'data_fim' => $fim,
                'summary' => [
                    'total_pagamentos' => (int)($summary['total_pagamentos'] ?? 0),
                    'valor_pago' => (float)($summary['valor_pago'] ?? 0),
                    'valor_pendente' => (float)($summary['valor_pendente'] ?? 0),
                    'valor_total' => (float)($summary['valor_total'] ?? 0),
                    'ticket_medio' => $summary['total_pagamentos'] > 0
                        ? (float)($summary['valor_total'] ?? 0) / (int)($summary['total_pagamentos'] ?? 1)
                        : 0,
                    'taxa_pagamento' => $summary['valor_total'] > 0
                        ? ((float)($summary['valor_pago'] ?? 0) / (float)($summary['valor_total'] ?? 1)) * 100
                        : 0
                ],
                'by_plan' => $byPlan,
                'by_month' => $byMonth
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Erro ao gerar relatório: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Registrar novo pagamento
     * 
     * @param int $tenantId ID do tenant
     * @param array $data Dados do pagamento
     * @return array
     */
    public function recordPayment(int $tenantId, array $data): array
    {
        try {
            if (empty($data['valor']) || empty($data['plano'])) {
                throw new Exception('Valor e plano são obrigatórios');
            }

            $sql = "INSERT INTO {$this->table} 
                    (tenant_id, valor, status, data_pagamento, plano)
                    VALUES (:tenant_id, :valor, :status, :data_pagamento, :plano)";

            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([
                ':tenant_id' => $tenantId,
                ':valor' => $data['valor'],
                ':status' => $data['status'] ?? 'pago',
                ':data_pagamento' => $data['data_pagamento'] ?? date('Y-m-d H:i:s'),
                ':plano' => $data['plano']
            ]);

            if ($result) {
                return [
                    'success' => true,
                    'id' => $this->pdo->lastInsertId(),
                    'message' => 'Pagamento registrado com sucesso'
                ];
            }

            throw new Exception('Erro ao inserir pagamento');
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Erro ao registrar pagamento: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Buscar pagamento por ID
     * 
     * @param int $paymentId ID do pagamento
     * @param int $tenantId ID do tenant (para segurança)
     * @return array|false
     */
    public function getPaymentById(int $paymentId, int $tenantId): ?array
    {
        try {
            $sql = "SELECT * FROM {$this->table} 
                    WHERE id = :id AND tenant_id = :tenant_id";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':id' => $paymentId,
                ':tenant_id' => $tenantId
            ]);

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Exception $e) {
            error_log("Erro ao buscar pagamento: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Atualizar status de pagamento
     * 
     * @param int $paymentId ID do pagamento
     * @param string $status Novo status
     * @param int $tenantId ID do tenant
     * @return array
     */
    public function updatePaymentStatus(int $paymentId, string $status, int $tenantId): array
    {
        try {
            if (!in_array($status, ['pago', 'pendente', 'cancelado'])) {
                throw new Exception('Status inválido');
            }

            $sql = "UPDATE {$this->table} 
                    SET status = :status, updated_at = NOW()
                    WHERE id = :id AND tenant_id = :tenant_id";

            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([
                ':status' => $status,
                ':id' => $paymentId,
                ':tenant_id' => $tenantId
            ]);

            if ($result && $stmt->rowCount() > 0) {
                return [
                    'success' => true,
                    'message' => 'Status atualizado com sucesso'
                ];
            }

            throw new Exception('Pagamento não encontrado');
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Erro ao atualizar status: ' . $e->getMessage()
            ];
        }
    }
}
