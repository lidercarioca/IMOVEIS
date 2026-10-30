<?php

namespace App\Infrastructure\Billing;

use App\Models\BillingHistoryModel;
use PDO;

/**
 * PaymentRepository
 * Adapter que reutiliza `BillingHistoryModel` para operações de pagamento.
 * Evita duplicação de SQL; delega ao modelo existente.
 */
class PaymentRepository
{
    private BillingHistoryModel $model;

    public function __construct(?BillingHistoryModel $model = null, ?PDO $pdo = null)
    {
        if ($model !== null) {
            $this->model = $model;
            return;
        }

        // Determinar instância de PDO de forma explícita e segura.
        if ($pdo === null) {
            $pdo = $GLOBALS['pdo'] ?? null;
        }

        if (!$pdo instanceof PDO) {
            throw new \InvalidArgumentException('PDO instance required to construct PaymentRepository when BillingHistoryModel is not provided');
        }

        $this->model = new BillingHistoryModel($pdo);
    }

    public function createPayment(int $tenantId, array $data): array
    {
        return $this->model->recordPayment($tenantId, $data);
    }

    public function findPaymentByStripeReferenceGlobal(string $stripeReference): ?array
    {
        return $this->model->findPaymentByStripeReferenceGlobal($stripeReference);
    }

    public function getPaymentById(int $paymentId, int $tenantId): ?array
    {
        return $this->model->getPaymentById($paymentId, $tenantId);
    }

    public function updatePaymentStatus(int $paymentId, string $status, int $tenantId): array
    {
        return $this->model->updatePaymentStatus($paymentId, $status, $tenantId);
    }

    public function listPayments(int $tenantId, array $filters = [], int $page = 1, int $limit = 20): array
    {
        return $this->model->listPayments($tenantId, $filters, $page, $limit);
    }

    /**
     * Retorna visão agregada de billing compatível com o legacy `getInfoCobranca`.
     *
     * Semântica SQL preservada 1:1 (LEFT JOIN, COUNT(p.id), SUM(CASE WHEN p.status='pago' THEN p.valor ELSE 0 END), MAX(p.data_pagamento)).
     *
     * Retorno:
     * - array associativo quando tenant existe;
     * - false quando tenant inexistente ou erro de DB (paridade com legacy).
     *
     * Captura apenas PDOException para preservar propagação de erros de aplicação não-relacionados ao DB.
     *
     * @param int $tenantId
     * @return array|false
     */
    public function getBillingOverview(int $tenantId): array|false
    {
        $sql = "SELECT
                    t.id,
                    t.name,
                    t.status,
                    t.plano,
                    t.limite_usuarios,
                    t.data_expiracao,
                    t.created_at,
                    COUNT(p.id) AS total_pagamentos,
                    SUM(CASE WHEN p.status = 'pago' THEN p.valor ELSE 0 END) AS total_pago,
                    MAX(p.data_pagamento) AS ultimo_pagamento
                FROM tenants t
                LEFT JOIN pagamentos p ON p.tenant_id = t.id
                WHERE t.id = :tenant_id
                GROUP BY t.id
                LIMIT 1";

        try {
            // Usa BaseModel::query (prepared + fetchAll PDO::FETCH_ASSOC)
            $rows = $this->model->query($sql, [':tenant_id' => $tenantId]);

            if (empty($rows)) {
                // Tenant inexistente → parity com legacy
                return false;
            }

            $row = $rows[0];

            // Normalizações mínimas e explícitas
            $row['total_pagamentos'] = isset($row['total_pagamentos']) ? (int)$row['total_pagamentos'] : 0;
            $row['total_pago'] = isset($row['total_pago']) ? (float)$row['total_pago'] : 0.0;
            $row['ultimo_pagamento'] = $row['ultimo_pagamento'] !== null ? (string)$row['ultimo_pagamento'] : null;

            return $row;
        } catch (\PDOException $e) {
            // Paridade com legacy: log e retorno false em erro de DB
            error_log("PaymentRepository::getBillingOverview PDOException: " . $e->getMessage());
            return false;
        }
    }
}
