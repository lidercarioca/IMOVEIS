<?php

namespace App\Infrastructure\Finance;

use PDO;
use Exception;

/**
 * FinancialTransactionRepository
 * 
 * Encapsula TODOS os SELECT/INSERT/UPDATE/DELETE da tabela 'financeiro'
 * 
 * Responsabilidades:
 * - Preparar statements
 * - Executar queries
 * - Retornar resultados
 * 
 * SEM responsabilidades de negócio
 */
class FinancialTransactionRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Obter resumo mensal da tabela financeiro
     * 
     * @param string $mes Formato: YYYY-MM
     * @param ?int $userId Filtrar por user_id (opcional)
     * @return array Resultado com SUM(receita, despesa, comissao) e COUNT
     */
    public function getMonthlySummary(string $mes, int $tenantId, ?int $userId = null): array
    {
        $dateStart = $mes . '-01';
        $dateEnd = date('Y-m-t', strtotime($dateStart));

        $where = "WHERE DATE(f.data_transacao) BETWEEN ? AND ? AND f.tenant_id = ?";
        $params = [$dateStart, $dateEnd, $tenantId];

        if ($userId) {
            $where .= " AND f.user_id = ?";
            $params[] = $userId;
        }

        $sql = "SELECT 
                    SUM(CASE WHEN f.tipo = 'receita' THEN f.valor ELSE 0 END) as total_receita,
                    SUM(CASE WHEN f.tipo = 'despesa' THEN f.valor ELSE 0 END) as total_despesa,
                    SUM(CASE WHEN f.tipo = 'comissao' THEN f.valor ELSE 0 END) as total_comissao,
                    COUNT(*) as total_transacoes
                FROM financeiro f
                {$where}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Registrar pagamento de comissão na tabela financeiro
     * 
     * INSERT INTO financeiro (user_id, property_id, tipo, descricao, valor, data_transacao, categoria, status)
     * 
     * @param int $userId ID do usuário/vendedor
     * @param ?int $propertyId ID da propriedade (nullable)
     * @param string $description Descrição do pagamento
     * @param float $amount Valor do pagamento
     * @return bool True se INSERT bem-sucedido
     */
    public function logCommissionPayment(int $tenantId, int $userId, ?int $propertyId, string $description, float $amount): bool
    {
        try {
            $sql = "INSERT INTO financeiro (tenant_id, user_id, property_id, tipo, descricao, valor, data_transacao, categoria, status)
                    VALUES (?, ?, ?, 'despesa', ?, ?, NOW(), 'Comissão de Corretor', 'concluído')";

            $stmt = $this->pdo->prepare($sql);

            return $stmt->execute([
                $tenantId,
                $userId,
                $propertyId,
                $description,
                $amount
            ]);
        } catch (Exception $e) {
            // Log sem retornar erro (side effect logging)
            return false;
        }
    }

    public function createTransaction(array $data, int $tenantId): int
    {
        $insertFields = ['tenant_id', 'tipo', 'property_id', 'user_id', 'descricao', 'valor', 'data_transacao', 'categoria', 'status'];
        $params = [
            ':tenant_id' => $tenantId,
            ':tipo' => $data['tipo'],
            ':property_id' => $data['property_id'] ?? null,
            ':user_id' => $data['user_id'] ?? 0,
            ':descricao' => $data['descricao'] ?? '',
            ':valor' => $data['valor'],
            ':data_transacao' => $data['data_transacao'],
            ':categoria' => $data['categoria'] ?? 'Outra',
            ':status' => $data['status'] ?? 'pendente',
        ];

        foreach (['reference_sale_id', 'reference_commission_id', 'reversal_of_id'] as $field) {
            if (array_key_exists($field, $data)) {
                $insertFields[] = $field;
                $params[':' . $field] = $data[$field];
            }
        }

        $placeholders = implode(', ', array_map(static fn(string $field): string => ':' . $field, $insertFields));
        $sql = 'INSERT INTO financeiro (' . implode(', ', $insertFields) . ') VALUES (' . $placeholders . ')';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int)$this->pdo->lastInsertId();
    }

    public function updateTransaction(int $transactionId, array $data, int $tenantId): bool
    {
        $fields = [];
        $params = [':id' => $transactionId, ':tenant_id' => $tenantId];

        if (isset($data['tipo'])) {
            $fields[] = 'tipo = :tipo';
            $params[':tipo'] = $data['tipo'];
        }
        if (array_key_exists('descricao', $data)) {
            $fields[] = 'descricao = :descricao';
            $params[':descricao'] = $data['descricao'];
        }
        if (isset($data['valor'])) {
            $fields[] = 'valor = :valor';
            $params[':valor'] = $data['valor'];
        }
        if (isset($data['data_transacao'])) {
            $fields[] = 'data_transacao = :data_transacao';
            $params[':data_transacao'] = $data['data_transacao'];
        }
        if (isset($data['categoria'])) {
            $fields[] = 'categoria = :categoria';
            $params[':categoria'] = $data['categoria'];
        }
        if (isset($data['status'])) {
            $fields[] = 'status = :status';
            $params[':status'] = $data['status'];
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = 'updated_at = NOW()';
        $sql = 'UPDATE financeiro SET ' . implode(', ', $fields) . ' WHERE id = :id AND tenant_id = :tenant_id';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function softDeleteTransaction(int $transactionId, int $tenantId): bool
    {
        $sql = "UPDATE financeiro SET deleted_at = NOW() WHERE id = :id AND tenant_id = :tenant_id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id' => $transactionId,
            ':tenant_id' => $tenantId
        ]);
    }

    public function getTransactionById(int $transactionId, int $tenantId): ?array
    {
        $sql = "SELECT * FROM financeiro WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id' => $transactionId,
            ':tenant_id' => $tenantId
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function listTransactions(array $filters, int $tenantId): array
    {
        $sql = "SELECT f.*, p.title as property_title, u.name as user_name
                FROM financeiro f
                LEFT JOIN properties p ON f.property_id = p.id
                LEFT JOIN users u ON f.user_id = u.id
                WHERE f.deleted_at IS NULL AND f.tenant_id = :tenant_id";

        $params = [':tenant_id' => $tenantId];

        if (!empty($filters['tipo'])) {
            $sql .= ' AND f.tipo = :tipo';
            $params[':tipo'] = $filters['tipo'];
        }
        if (!empty($filters['categoria'])) {
            $sql .= ' AND f.categoria = :categoria';
            $params[':categoria'] = $filters['categoria'];
        }
        if (!empty($filters['status'])) {
            $sql .= ' AND f.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['mes'])) {
            if ($filters['mes'] === '90dias') {
                $sql .= ' AND f.data_transacao >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)';
            } elseif ($filters['mes'] !== 'todos') {
                $sql .= " AND DATE_FORMAT(f.data_transacao, '%Y-%m') = :mes";
                $params[':mes'] = $filters['mes'];
            }
        }

        if (!empty($filters['user_id'])) {
            $sql .= ' AND f.user_id = :user_id';
            $params[':user_id'] = $filters['user_id'];
        }

        $sql .= ' ORDER BY f.data_transacao DESC';

        if (!empty($filters['page']) && !empty($filters['limit'])) {
            $limit = (int)$filters['limit'];
            $offset = ((int)$filters['page'] - 1) * $limit;
            $sql .= ' LIMIT :limit OFFSET :offset';
            $params[':limit'] = $limit;
            $params[':offset'] = $offset;
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            if (is_int($value)) {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function ensureTableExists(): bool
    {
        $sql = "CREATE TABLE IF NOT EXISTS financeiro (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL DEFAULT 1,
            property_id INT,
            user_id INT NOT NULL,
            tipo VARCHAR(50),
            descricao VARCHAR(255),
            valor DECIMAL(10, 2) NOT NULL,
            data_transacao DATE NOT NULL,
            categoria VARCHAR(100),
            status VARCHAR(50) DEFAULT 'pendente',
            deleted_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_tenant_id (tenant_id),
            KEY idx_user_id (user_id),
            KEY idx_property_id (property_id),
            KEY idx_data_transacao (data_transacao),
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE ON UPDATE CASCADE,
            FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE SET NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci";

        $stmt = $this->pdo->prepare($sql);
        return (bool)$stmt->execute();
    }

    public function softDeleteTransactionsBySaleId(int $saleId, int $tenantId): bool
    {
        $sql = "UPDATE financeiro f
                JOIN property_sales ps ON f.property_id = ps.property_id
                SET f.deleted_at = NOW()
                WHERE ps.id = :sale_id
                AND f.tipo IN ('receita', 'comissao')
                AND f.tenant_id = :finance_tenant_id
                AND ps.tenant_id = :sale_tenant_id";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':sale_id' => $saleId,
            ':finance_tenant_id' => $tenantId,
            ':sale_tenant_id' => $tenantId
        ]);
    }
}
