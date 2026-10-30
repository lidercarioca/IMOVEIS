<?php

namespace App\Infrastructure\Agendamento;

use PDO;

/**
 * AgendamentoRepository - infraestrutura SQL do domínio Agendamentos
 *
 * Responsável apenas por encapsular o SQL legado já existente,
 * sem alterar comportamento e sem introduzir regras de negócio.
 */
class AgendamentoRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Lista agendamentos conforme filtros legados.
     */
    public function list(array $options = []): array
    {
        $tenantId = (int)($options['tenantId'] ?? 0);
        $userId = $options['userId'] ?? null;
        $isAdmin = (bool)($options['isAdmin'] ?? false);
        $filtro = $options['filtro'] ?? 'proximos';
        $mes = $options['mes'] ?? date('Y-m');
        $status = $options['status'] ?? null;
        $id = $options['id'] ?? null;

        if ($id !== null) {
            return $this->findById((int)$id, $tenantId) ? [$this->findById((int)$id, $tenantId)] : [];
        }

        $sql = "SELECT a.*, p.title as property_title, u.name as user_name, u.id as agente_id
                FROM agendamentos a
                LEFT JOIN properties p ON a.property_id = p.id AND p.tenant_id = a.tenant_id
                LEFT JOIN leads l ON a.leads_id = l.id AND l.tenant_id = a.tenant_id
                LEFT JOIN users u ON a.user_id = u.id AND u.tenant_id = a.tenant_id
                WHERE a.deleted_at IS NULL AND a.tenant_id = ?";

        $params = [$tenantId];

        if (!$isAdmin) {
            $sql .= " AND a.user_id = ?";
            $params[] = $userId;
        }

        switch ($filtro) {
            case 'proximos':
                $sql .= " AND a.data_agendamento >= DATE(NOW())";
                break;
            case 'passados':
                $sql .= " AND a.data_agendamento < DATE(NOW())";
                break;
            case 'mes':
                $yearMonth = explode('-', $mes);
                $year = (int)($yearMonth[0] ?? date('Y'));
                $month = (int)($yearMonth[1] ?? date('m'));
                $sql .= " AND YEAR(a.data_agendamento) = ? AND MONTH(a.data_agendamento) = ?";
                $params[] = $year;
                $params[] = $month;
                break;
        }

        if ($status !== null && $status !== 'todos') {
            $sql .= " AND a.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY a.data_agendamento ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca um agendamento pelo id no tenant.
     */
    public function findById(int $id, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT a.*, p.title as property_title, u.name as user_name
             FROM agendamentos a
             LEFT JOIN properties p ON a.property_id = p.id AND p.tenant_id = a.tenant_id
             LEFT JOIN leads l ON a.leads_id = l.id AND l.tenant_id = a.tenant_id
             LEFT JOIN users u ON a.user_id = u.id AND u.tenant_id = a.tenant_id
             WHERE a.id = ? AND a.tenant_id = ? AND a.deleted_at IS NULL"
        );
        $stmt->execute([$id, $tenantId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Busca o owner do agendamento mantendo a regra legacy de existência por tenant.
     */
    public function findOwnerById(int $id, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT user_id FROM agendamentos WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$id, $tenantId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Busca nome e email do lead pelo id.
     */
    public function findLeadNameEmail(int $leadId, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT name, email, phone FROM leads WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$leadId, $tenantId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function propertyBelongsToTenant(int $propertyId, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM properties WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$propertyId, $tenantId]);

        return (bool) $stmt->fetchColumn();
    }

    public function leadBelongsToTenant(int $leadId, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM leads WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$leadId, $tenantId]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Busca agendamentos de hoje no tenant e usuário atual.
     */
    public function findTodaySchedule(int $tenantId, ?int $userId = null, bool $isAdmin = false): array
    {
        $sql = "SELECT a.id, a.property_id, a.leads_id, a.data_agendamento, a.status, a.descricao,
                    p.title as property_title, p.price, p.neighborhood, u.name as agent_name
                FROM agendamentos a
                JOIN properties p ON a.property_id = p.id AND p.tenant_id = :tenant_id_property
                LEFT JOIN users u ON a.user_id = u.id AND u.tenant_id = :tenant_id_user
                WHERE DATE(a.data_agendamento) = CURDATE()
                  AND a.tenant_id = :tenant_id_agendamento
                  AND a.deleted_at IS NULL";

        $params = [
            ':tenant_id_property' => $tenantId,
            ':tenant_id_user' => $tenantId,
            ':tenant_id_agendamento' => $tenantId,
        ];

        if (!$isAdmin) {
            $sql .= ' AND a.user_id = :user_id';
            $params[':user_id'] = $userId;
        }

        $sql .= ' ORDER BY a.data_agendamento ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca contagem de agendamentos pendentes por dias.
     */
    public function findPendingByDays(int $tenantId, ?int $userId = null, bool $isAdmin = false, int $limit = 7): array
    {
        $sql = "SELECT COUNT(*) as total, DATEDIFF(DATE(a.data_agendamento), CURDATE()) as dias_para
                FROM agendamentos a
                WHERE DATE(a.data_agendamento) > CURDATE()
                  AND a.status = 'pending'
                  AND a.tenant_id = :tenant_id";

        $params = [':tenant_id' => $tenantId];

        if (!$isAdmin) {
            $sql .= ' AND a.user_id = :user_id';
            $params[':user_id'] = $userId;
        }

        $sql .= ' GROUP BY dias_para ORDER BY dias_para ASC LIMIT :limit';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca taxa de comparecimento dos últimos 30 dias.
     */
    public function findAttendance30Days(int $tenantId, ?int $userId = null, bool $isAdmin = false): array
    {
        $sql = "SELECT COUNT(CASE WHEN status = 'completed' THEN 1 END) as attended,
                       COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled,
                       COUNT(*) as total
                FROM agendamentos
                WHERE DATE(data_agendamento) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                  AND DATE(data_agendamento) <= CURDATE()
                  AND tenant_id = :tenant_id";

        $params = [':tenant_id' => $tenantId];

        if (!$isAdmin) {
            $sql .= ' AND user_id = :user_id';
            $params[':user_id'] = $userId;
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Busca agendamentos próximos do dia de hoje.
     */
    public function findUpcoming(int $tenantId, ?int $userId = null, bool $isAdmin = false): array
    {
        $sql = 'SELECT a.*, p.title as property_title, u.name as user_name
                FROM agendamentos a
                LEFT JOIN properties p ON a.property_id = p.id AND p.tenant_id = a.tenant_id
                LEFT JOIN leads l ON a.leads_id = l.id AND l.tenant_id = a.tenant_id
                LEFT JOIN users u ON a.user_id = u.id AND u.tenant_id = a.tenant_id
                WHERE DATE(a.data_agendamento) >= CURDATE() AND a.deleted_at IS NULL AND a.tenant_id = ?';

        $params = [$tenantId];

        if (!$isAdmin) {
            $sql .= ' AND a.user_id = ?';
            $params[] = $userId;
        }

        $sql .= ' ORDER BY a.data_agendamento ASC LIMIT 20';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca leads com propriedades para o modal de criação.
     */
    public function findLeadsWithProperties(int $tenantId, ?int $userId = null, bool $isAdmin = false): array
    {
        $sql = "SELECT 
                    l.id,
                    l.name,
                    l.email,
                    l.phone as telefone,
                    l.property_id,
                    p.title as property_title,
                    l.status
                FROM leads l
                LEFT JOIN properties p ON p.id = l.property_id AND p.tenant_id = ?
                WHERE 1=1 AND l.tenant_id = ?";

        $params = [$tenantId, $tenantId];

        if (!$isAdmin) {
            $sql .= " AND l.assigned_user_id = ?";
            $params[] = $userId;
        }

        $sql .= " ORDER BY l.name ASC
                  LIMIT 100";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cria um agendamento preservando o SQL legado.
     */
    public function create(array $data): int
    {
        $sql = 'INSERT INTO agendamentos (tenant_id, property_id, leads_id, phone, data_agendamento, user_id, descricao, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())';
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute([
            $data['tenant_id'],
            $data['property_id'],
            $data['leads_id'] ?? null,
            $data['phone'] ?? null,
            $data['data_agendamento'],
            $data['user_id'],
            $data['descricao'] ?? null,
        ]);

        return $result ? (int)$this->pdo->lastInsertId() : 0;
    }

    /**
     * Atualiza campos de agendamento preservando a lógica legada.
     */
    public function update(int $id, int $tenantId, array $data): bool
    {
        $updates = [];
        $values = [];

        if (isset($data['data_agendamento']) && !empty($data['data_agendamento'])) {
            $updates[] = 'data_agendamento = ?';
            $values[] = $data['data_agendamento'];
        }

        if (isset($data['status']) && !empty($data['status'])) {
            $updates[] = 'status = ?';
            $values[] = $data['status'];
        }

        if (array_key_exists('descricao', $data)) {
            $updates[] = 'descricao = ?';
            $values[] = $data['descricao'];
        }

        if (empty($updates)) {
            return false;
        }

        $updates[] = 'updated_at = NOW()';
        $values[] = $id;
        $values[] = $tenantId;

        $sql = 'UPDATE agendamentos SET ' . implode(', ', $updates) . ' WHERE id = ? AND tenant_id = ?';
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute($values);
    }

    /**
     * Soft delete do agendamento, preservando a regra legada.
     */
    public function softDelete(int $id, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE agendamentos SET deleted_at = NOW() WHERE id = ? AND tenant_id = ?');
        return $stmt->execute([$id, $tenantId]);
    }

    /**
     * Verifica se o agendamento pertence ao tenant.
     */
    public function belongsToTenant(int $id, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM agendamentos WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$id, $tenantId]);

        return (bool)$stmt->fetchColumn();
    }

    /**
     * Verifica se o agendamento pertence ao usuário informado.
     */
    public function belongsToUser(int $id, int $userId, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM agendamentos WHERE id = ? AND user_id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$id, $userId, $tenantId]);

        return (bool)$stmt->fetchColumn();
    }
}
