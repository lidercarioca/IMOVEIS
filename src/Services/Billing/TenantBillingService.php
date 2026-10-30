<?php

namespace App\Services\Billing;

use App\Infrastructure\Billing\PaymentRepository;
use App\Infrastructure\Billing\TenantRepository;
use App\Infrastructure\Billing\StripeEventRepository;
use PDO;
use DateTimeInterface;
use RuntimeException;

/**
 * TenantBillingService
 *
 * Camada de regras de negócio para billing, responsável por orquestrar
 * operações de pagamento, expiração e identificação Stripe.
 *
 * Regras:
 * - Nenhuma query direta aqui; usar repositórios
 * - Transações PDO quando necessário
 */
class TenantBillingService
{
    private PaymentRepository $paymentRepo;
    private TenantRepository $tenantRepo;
    private ?StripeEventRepository $eventRepo;
    private PDO $pdo;

    public function __construct(PaymentRepository $paymentRepo, TenantRepository $tenantRepo, ?StripeEventRepository $eventRepo = null, ?PDO $pdo = null)
    {
        $this->paymentRepo = $paymentRepo;
        $this->tenantRepo = $tenantRepo;
        $this->eventRepo = $eventRepo;

        if ($pdo === null) {
            $this->pdo = $GLOBALS['pdo'] ?? throw new \InvalidArgumentException('PDO is required');
        } else {
            $this->pdo = $pdo;
        }
    }

    /**
     * Registrar pagamento: persiste pagamento e atualiza campos do tenant quando aplicável
     * @param int $tenantId
     * @param array $paymentData Dados esperados por BillingHistoryModel->recordPayment.
     *                             Campos opcionais suportados: stripe_reference, stripe_reference_type, stripe_event_id.
     * @param array $tenantBillingUpdates Campos para updateBillingFields (opcional)
     * @return array Resultado do registro (delegado ao PaymentRepository)
     * @throws \Exception em caso de falha
     */
    public function registerPayment(int $tenantId, array $paymentData, array $tenantBillingUpdates = []): array
    {
        try {
            $this->pdo->beginTransaction();

            $result = $this->paymentRepo->createPayment($tenantId, $paymentData);

            if (empty($result['success'])) {
                throw new RuntimeException('Failed to record payment: ' . ($result['error'] ?? 'unknown'));
            }

            // Se o caller NÃO forneceu uma `data_expiracao` explícita, calcular seguindo a
            // semântica legacy: DATE_ADD(GREATEST(COALESCE(data_expiracao, NOW()), NOW()), INTERVAL ? DAY)
            // usar $paymentData['dias_expiracao'] quando presente, senão default 30
            if (!array_key_exists('data_expiracao', $tenantBillingUpdates) || $tenantBillingUpdates['data_expiracao'] === null) {
                $days = isset($paymentData['dias_expiracao']) ? (int)$paymentData['dias_expiracao'] : 30;

                $tenant = $this->tenantRepo->getTenantById($tenantId);
                $nowTs = time();
                $baseTs = $nowTs;

                if ($tenant && !empty($tenant['data_expiracao'])) {
                    $existingTs = strtotime((string)$tenant['data_expiracao']);
                    if ($existingTs !== false && $existingTs > $nowTs) {
                        $baseTs = $existingTs;
                    }
                }

                $newExpiryTs = strtotime('+' . max(0, $days) . ' days', $baseTs);
                $tenantBillingUpdates['data_expiracao'] = date('Y-m-d H:i:s', $newExpiryTs);
            }

            if (!empty($tenantBillingUpdates)) {
                $ok = $this->tenantRepo->updateBillingFields($tenantId, $tenantBillingUpdates);
                if (!$ok) {
                    throw new RuntimeException('Failed to update tenant billing fields');
                }

                // Após aplicar o novo plano/fields, sincronizar limite_usuarios usando o método existente.
                $ok2 = $this->tenantRepo->updateUserLimitFromPlan($tenantId);
                if ($ok2 === false) {
                    throw new RuntimeException('Failed to synchronize user limit from plan');
                }
            }

            $this->pdo->commit();

            return $result;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Atualiza data de expiração do tenant
     * @param int $tenantId
     * @param DateTimeInterface $expiration
     * @return bool
     */
    public function updateExpiration(int $tenantId, DateTimeInterface $expiration): bool
    {
        $fields = ['data_expiracao' => $expiration->format('Y-m-d H:i:s')];
        return $this->tenantRepo->updateBillingFields($tenantId, $fields);
    }

    /**
     * Sincroniza identificadores Stripe no tenant
     * @param int $tenantId
     * @param string|null $customerId
     * @param string|null $subscriptionId
     * @return bool
     */
    public function syncStripeIdentifiers(int $tenantId, ?string $customerId, ?string $subscriptionId): bool
    {
        return $this->tenantRepo->updateStripeIdentifiers($tenantId, $customerId, $subscriptionId);
    }

    public function findPaymentByStripeReferenceGlobal(string $stripeReference): ?array
    {
        return $this->paymentRepo->findPaymentByStripeReferenceGlobal($stripeReference);
    }

    /**
     * Expose o TenantRepository usado internamente para casos de integração controlada.
     * Método público mínimo para evitar reflexão frágil em serviços que dependem dele.
     * @return TenantRepository
     */
    public function getTenantRepository(): TenantRepository
    {
        return $this->tenantRepo;
    }

    /**
     * Ativa o tenant
     * @param int $tenantId
     * @param array $options campos opcionais para atualizar (ex: plano, data_expiracao)
     * @return bool
     */
    public function activateTenant(int $tenantId, array $options = []): bool
    {
        try {
            $this->pdo->beginTransaction();

            if (!empty($options)) {
                $ok = $this->tenantRepo->updateBillingFields($tenantId, $options);
                if (!$ok) {
                    throw new RuntimeException('Failed to update billing fields during activate');
                }
            }

            // Após aplicar billing fields, sincronizar limite_usuarios antes de ativar
            // Executar dentro da mesma transação; falha aqui deve provocar rollback.
            $limitOk = $this->tenantRepo->updateUserLimitFromPlan($tenantId);
            if ($limitOk === false) {
                throw new RuntimeException('Failed to synchronize user limit during activateTenant');
            }

            $ok = $this->tenantRepo->activate($tenantId);
            if (!$ok) {
                throw new RuntimeException('Failed to set tenant active');
            }

            $this->pdo->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Desativa o tenant (marca inactive)
     * @param int $tenantId
     * @return bool
     */
    public function deactivateTenant(int $tenantId): bool
    {
        try {
            $this->pdo->beginTransaction();

            $ok = $this->tenantRepo->deactivate($tenantId);
            if (!$ok) {
                throw new RuntimeException('Failed to deactivate tenant');
            }

            $this->pdo->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
