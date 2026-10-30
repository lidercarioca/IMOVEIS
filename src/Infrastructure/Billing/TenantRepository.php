<?php

namespace App\Infrastructure\Billing;

use PDO;

/**
 * TenantRepository (Billing)
 *
 * Acesso à tabela `tenants` para operações relacionadas a billing.
 * Somente SQL; sem lógica de negócio.
 */
class TenantRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getTenantById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tenants WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ?: null;
    }

    public function getTenantData(int $tenantId): ?array
    {
        return $this->getTenantById($tenantId);
    }

    public function getActivePlanCatalog(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT code, name, description, max_users, max_properties, monthly_price, trial_days, is_trial, is_active, display_order, stripe_price_id FROM plans WHERE is_active = 1 ORDER BY display_order ASC, id ASC'
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $catalog = [];
        foreach ($rows as $row) {
            $code = (string)($row['code'] ?? '');
            if ($code === '') {
                continue;
            }

            $catalog[$code] = [
                'nome' => $row['name'] ?? ucfirst($code),
                'descricao' => $row['description'] ?? '',
                'limite_usuarios' => (int)($row['max_users'] ?? 0),
                'limite_imoveis' => (int)($row['max_properties'] ?? 0),
                'valor_mensal' => isset($row['monthly_price']) ? (float)$row['monthly_price'] : 0.0,
                'dias_trial' => isset($row['trial_days']) ? (int)$row['trial_days'] : null,
                'is_trial' => (bool)($row['is_trial'] ?? false),
                'is_active' => (bool)($row['is_active'] ?? true),
                'display_order' => (int)($row['display_order'] ?? 0),
                'stripe_price_id' => $row['stripe_price_id'] ?? null,
            ];
        }

        return $catalog;
    }

    public function getPlanByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare('SELECT code, name, description, max_users, max_properties, monthly_price, trial_days, is_trial, is_active, display_order, stripe_price_id FROM plans WHERE code = ? LIMIT 1');
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return [
            'code' => $row['code'],
            'nome' => $row['name'],
            'descricao' => $row['description'],
            'limite_usuarios' => (int)($row['max_users'] ?? 0),
            'limite_imoveis' => (int)($row['max_properties'] ?? 0),
            'valor_mensal' => isset($row['monthly_price']) ? (float)$row['monthly_price'] : 0.0,
            'dias_trial' => isset($row['trial_days']) ? (int)$row['trial_days'] : null,
            'is_trial' => (bool)($row['is_trial'] ?? false),
            'is_active' => (bool)($row['is_active'] ?? true),
            'display_order' => (int)($row['display_order'] ?? 0),
            'stripe_price_id' => $row['stripe_price_id'] ?? null,
        ];
    }

    public function isPlanDowngrade(string $currentPlan, string $selectedPlan, ?array $planCatalog = null): bool
    {
        $catalog = $planCatalog ?? $this->getActivePlanCatalog();

        if (!isset($catalog[$currentPlan], $catalog[$selectedPlan])) {
            return false;
        }

        return ((float)($catalog[$selectedPlan]['valor_mensal'] ?? 0.0)) < ((float)($catalog[$currentPlan]['valor_mensal'] ?? 0.0));
    }

    public function getDaysRemaining(int $tenantId): ?int
    {
        $tenant = $this->getTenantById($tenantId);
        if (!$tenant || empty($tenant['data_expiracao'])) {
            return null;
        }

        $expiryTs = strtotime((string)$tenant['data_expiracao']);
        if ($expiryTs === false) {
            return null;
        }

        return (int)ceil(($expiryTs - time()) / 86400);
    }

    public function isExpired(int $tenantId): bool
    {
        $tenant = $this->getTenantById($tenantId);
        if (!$tenant || empty($tenant['data_expiracao'])) {
            return false;
        }

        $expiryTs = strtotime((string)$tenant['data_expiracao']);
        if ($expiryTs === false) {
            return false;
        }

        return $expiryTs < time();
    }

    public function shouldShowExpiryWarning(int $tenantId): bool
    {
        $tenant = $this->getTenantById($tenantId);
        if (!$tenant || empty($tenant['data_expiracao'])) {
            return false;
        }

        $expiryTs = strtotime((string)$tenant['data_expiracao']);
        if ($expiryTs === false) {
            return false;
        }

        $now = time();
        if ($expiryTs <= $now) {
            return false;
        }

        if ($expiryTs >= strtotime('+4 days', $now)) {
            return false;
        }

        if ($expiryTs <= strtotime('-1 days', $now)) {
            return false;
        }

        return true;
    }

    public function checkTenantActiveStatus(int $tenantId): array
    {
        // Read-only check: does NOT perform any DB writes. Use normalizeTenantStatus() to apply changes.
        $tenant = $this->getTenantById($tenantId);

        if (!$tenant) {
            return [
                'ativo' => false,
                'motivo' => 'Tenant não encontrado',
                'tenant' => null,
            ];
        }

        // If expired, report inactive without modifying DB
        if (!empty($tenant['data_expiracao']) && strtotime((string)$tenant['data_expiracao']) < time()) {
            return [
                'ativo' => false,
                'motivo' => 'Plano expirado',
                'tenant' => $tenant,
            ];
        }

        if ($tenant['status'] === 'inactive') {
            return [
                'ativo' => false,
                'motivo' => 'Tenant bloqueado',
                'tenant' => $tenant,
            ];
        }

        if ($tenant['status'] === 'trial' || $tenant['status'] === 'active') {
            return [
                'ativo' => true,
                'motivo' => 'Acesso permitido',
                'tenant' => $tenant,
            ];
        }

        return [
            'ativo' => false,
            'motivo' => 'Status do tenant inválido: ' . ($tenant['status'] ?? 'unknown'),
            'tenant' => $tenant,
        ];
    }

    /**
     * Normalize tenant status by applying DB updates when necessary.
     * This method performs writes and must be called explicitly when normalization is desired.
     */
    public function normalizeTenantStatus(int $tenantId): array
    {
        $tenant = $this->getTenantById($tenantId);

        if (!$tenant) {
            return [
                'ativo' => false,
                'motivo' => 'Tenant não encontrado',
                'tenant' => null,
            ];
        }

        // Promote trial -> active with timestamp
        if ($tenant['status'] === 'trial') {
            $updatedAt = date('Y-m-d H:i:s');
            $update = $this->pdo->prepare("UPDATE tenants SET status = 'active', updated_at = ? WHERE id = ?");
            $update->execute([$updatedAt, $tenantId]);
            $tenant['status'] = 'active';
        }

        // Expired -> inactive
        if (!empty($tenant['data_expiracao']) && strtotime((string)$tenant['data_expiracao']) < time()) {
            if ($tenant['status'] !== 'inactive') {
                $updatedAt = date('Y-m-d H:i:s');
                $update = $this->pdo->prepare("UPDATE tenants SET status = 'inactive', updated_at = ? WHERE id = ?");
                $update->execute([$updatedAt, $tenantId]);
                $tenant['status'] = 'inactive';
            }

            return [
                'ativo' => false,
                'motivo' => 'Plano expirado',
                'tenant' => $tenant,
            ];
        }

        if ($tenant['status'] === 'inactive') {
            return [
                'ativo' => false,
                'motivo' => 'Tenant bloqueado',
                'tenant' => $tenant,
            ];
        }

        if ($tenant['status'] === 'active') {
            return [
                'ativo' => true,
                'motivo' => 'Acesso permitido',
                'tenant' => $tenant,
            ];
        }

        return [
            'ativo' => false,
            'motivo' => 'Status do tenant inválido: ' . ($tenant['status'] ?? 'unknown'),
            'tenant' => $tenant,
        ];
    }

    public function hasTrialHistory(int $tenantId): bool
    {
        $sql = 'SELECT COUNT(*) FROM pagamentos WHERE tenant_id = ? AND (plano = ? OR status = ?)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$tenantId, 'trial', 'pago']);
        return (bool)$stmt->fetchColumn();
    }

    public function activateTrial(int $tenantId, int $daysTrial = 7): bool
    {
        $trialLimit = 2;
        $plan = $this->pdo->prepare('SELECT max_users FROM plans WHERE code = ? LIMIT 1');
        $plan->execute(['trial']);
        $row = $plan->fetch(PDO::FETCH_ASSOC);
        if ($row && isset($row['max_users'])) {
            $trialLimit = (int)$row['max_users'];
        }

        $expirationDate = date('Y-m-d H:i:s', strtotime('+' . max(0, $daysTrial) . ' days'));
        $updatedAt = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            "UPDATE tenants SET status = 'active', plano = 'trial', limite_usuarios = ?, data_expiracao = ?, updated_at = ? WHERE id = ?"
        );

        return $stmt->execute([$trialLimit, $expirationDate, $updatedAt, $tenantId]);
    }

    /**
     * Atualiza o limite_usuarios do tenant com base no plano atual do tenant.
     * Retorna true se o update foi executado com sucesso, false caso o tenant/plano não exista ou falha.
     * @param int $tenantId
     * @return bool
     */
    public function updateUserLimitFromPlan(int $tenantId): bool
    {
        $tenant = $this->getTenantById($tenantId);
        if (!$tenant || empty($tenant['plano'])) {
            return false;
        }

        $planCode = (string)$tenant['plano'];
        $plan = $this->getPlanByCode($planCode);
        if (!$plan) {
            return false;
        }

        $novoLimite = (int)($plan['limite_usuarios'] ?? 0);

        $stmt = $this->pdo->prepare('UPDATE tenants SET limite_usuarios = ? WHERE id = ?');
        return (bool)$stmt->execute([$novoLimite, $tenantId]);
    }

    public function hasStripeCustomer(int $tenantId, array $stripeConfig = []): bool
    {
        if (!$tenantId || empty($stripeConfig['secret_key'] ?? '')) {
            return false;
        }

        $tenant = $this->getTenantById($tenantId);
        if (!$tenant) {
            return false;
        }

        return !empty($tenant['stripe_customer_id']);
    }

    public function findByStripeSubscriptionId(string $subscriptionId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tenants WHERE stripe_subscription_id = ? LIMIT 1');
        $stmt->execute([$subscriptionId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ?: null;
    }

    public function findByStripeCustomerId(string $customerId): array
    {
        $customerId = trim($customerId);
        if ($customerId === '') {
            return [];
        }

        $stmt = $this->pdo->prepare('SELECT * FROM tenants WHERE stripe_customer_id = ?');
        $stmt->execute([$customerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateStripeIdentifiers(int $tenantId, ?string $customerId, ?string $subscriptionId): bool
    {
        $existing = $this->getTenantById($tenantId);

        $nextCustomerId = $customerId !== null && trim((string)$customerId) !== ''
            ? trim((string)$customerId)
            : ($existing['stripe_customer_id'] ?? null);

        $nextSubscriptionId = $subscriptionId !== null && trim((string)$subscriptionId) !== ''
            ? trim((string)$subscriptionId)
            : ($existing['stripe_subscription_id'] ?? null);

        $updatedAt = date('Y-m-d H:i:s');
        $sql = 'UPDATE tenants SET stripe_customer_id = ?, stripe_subscription_id = ?, updated_at = ? WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$nextCustomerId, $nextSubscriptionId, $updatedAt, $tenantId]);
    }

    /**
     * Atualiza campos de billing permitidos (somente colunas explícitas)
     * @param int $tenantId
     * @param array $fields (ex: ['plano' => 'pro','data_expiracao' => '...'])
     */
    public function updateBillingFields(int $tenantId, array $fields): bool
    {
        $allowed = ['plano', 'data_expiracao', 'stripe_price_id', 'billing_cycle', 'billing_period', 'status'];
        $set = [];
        $params = [];

        foreach ($fields as $k => $v) {
            if (in_array($k, $allowed, true)) {
                $set[] = "$k = :$k";
                $params[":$k"] = $v;
            }
        }

        if (empty($set)) {
            return false;
        }

        $params[':id'] = $tenantId;
        $params[':updated_at'] = date('Y-m-d H:i:s');
        $sql = 'UPDATE tenants SET ' . implode(', ', $set) . ', updated_at = :updated_at WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function markInactive(int $tenantId): bool
    {
        $stmt = $this->pdo->prepare("UPDATE tenants SET status = 'inactive', updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$tenantId]);
    }

    /**
     * Atualizar status do tenant
     *
     * @param int $id ID do tenant
     * @param string $status Novo status
     * @return bool true se sucesso
     */
    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->pdo->prepare('UPDATE tenants SET status = ?, updated_at = NOW() WHERE id = ?');
        return $stmt->execute([$status, $id]);
    }

    /**
     * Ativa o tenant (status = 'active')
     * @param int $id
     * @return bool
     */
    public function activate(int $id): bool
    {
        return $this->updateStatus($id, 'active');
    }

    /**
     * Desativa o tenant (status = 'inactive')
     * @param int $id
     * @return bool
     */
    public function deactivate(int $id): bool
    {
        return $this->markInactive($id);
    }
}
