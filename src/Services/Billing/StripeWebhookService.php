<?php

namespace App\Services\Billing;

use App\Infrastructure\Billing\StripeEventRepository;
use App\Infrastructure\Billing\StripeApiClient;
use App\Infrastructure\Billing\TenantRepository;
use App\Services\Billing\BillingNotificationService;
use App\Services\Billing\TenantBillingService;
use App\Services\Billing\StripeWebhookValidator;
use RuntimeException;

/**
 * Orquestra processamento de webhooks Stripe.
 * - Valida assinatura
 * - Persiste evento pendente (via StripeEventRepository)
 * - Chama TenantBillingService quando necessário
 */
class StripeWebhookService
{
    private StripeWebhookValidator $validator;
    private StripeApiClient $apiClient;
    private ?StripeEventRepository $eventRepo;
    private TenantBillingService $billingService;
    private BillingNotificationService $notificationService;
    private ?TenantRepository $tenantRepository = null;

    public function __construct(
        StripeWebhookValidator $validator,
        StripeApiClient $apiClient,
        ?StripeEventRepository $eventRepo,
        TenantBillingService $billingService,
        BillingNotificationService $notificationService,
        ?TenantRepository $tenantRepository = null
    ) {
        $this->validator = $validator;
        $this->apiClient = $apiClient;
        $this->eventRepo = $eventRepo;
        $this->billingService = $billingService;
        $this->notificationService = $notificationService;
        $this->tenantRepository = $tenantRepository ?? ($billingService->getTenantRepository() ?? null);
    }

    /**
     * Processa webhook raw payload e header
     * @param string $rawPayload
     * @param string $sigHeader
     * @param string $webhookSecret
     * @return array
     */
    public function handle(string $rawPayload, string $sigHeader, string $webhookSecret): array
    {
        if (!$this->validator->validateSignature($rawPayload, $sigHeader, $webhookSecret)) {
            return ['success' => false, 'message' => 'Invalid signature', 'client_error' => true];
        }

        try {
            $event = $this->validator->parsePayload($rawPayload);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Invalid event payload', 'client_error' => true];
        }

        $eventId = $this->getCanonicalEventId($event);
        $type = $event['type'] ?? null;

        if (!$eventId || !$type) {
            return ['success' => false, 'message' => 'Invalid event payload', 'client_error' => true];
        }

        $tenantId = $this->extractTenantId($event);

        if ($this->eventRepo !== null) {
            $existing = $this->eventRepo->findByEventId($eventId);
            if ($existing) {
                $status = $existing['status'] ?? null;
                if ($status === 'processed') {
                    $this->logEventLookup($existing, $eventId);
                    return ['success' => true, 'message' => 'Event already processed', 'processed' => true];
                }

                if ($status === 'pending') {
                    $this->logEventLookup($existing, $eventId);
                    return ['success' => false, 'message' => 'Event is already pending processing', 'retryable' => true];
                }

                if ($status === 'failed') {
                    $retryCount = isset($existing['retry_count']) ? (int)$existing['retry_count'] : 0;
                    if ($retryCount >= 5) {
                        error_log('StripeWebhookService: event failed too many times ' . $eventId . ' retry_count=' . $retryCount);
                        return ['success' => false, 'message' => 'Event failed too many times', 'retryable' => false];
                    }

                    try {
                        if (method_exists($this->eventRepo, 'markPending')) {
                            $this->eventRepo->markPending($eventId);
                        }
                    } catch (\Throwable $e) {
                        error_log('StripeWebhookService: failed to reset failed event to pending ' . $eventId . ' - ' . $e->getMessage());
                    }
                }
            } else {
                try {
                    $payloadJson = json_encode($event, JSON_UNESCAPED_UNICODE);
                    if ($payloadJson === false) {
                        throw new RuntimeException('Unable to encode event payload');
                    }

                    $this->eventRepo->createPendingEvent(
                        $eventId,
                        $payloadJson,
                        $tenantId
                    );
                } catch (\Throwable $e) {
                    error_log('StripeWebhookService: failed to enqueue Stripe event ' . $eventId . ' - ' . $e->getMessage());
                    return ['success' => false, 'message' => 'Processing error: failed to record event', 'retryable' => true];
                }
            }
        }

        try {
            $result = $this->dispatchEvent($type, $event, $eventId);

            if ($this->eventRepo !== null) {
                if (!empty($result['success']) && (!array_key_exists('processed', $result) || !empty($result['processed']))) {
                    $this->eventRepo->markProcessed($eventId);
                } else {
                    // If result indicates retryable, keep the event reprocessable by the Stripe retry mechanism.
                    $failureReason = $result['failure_reason'] ?? null;
                    $isRetryableFlag = !empty($result['retryable']);

                    $transientReasons = ['TENANT_NOT_YET_MATERIALIZED', 'CUSTOMER_NOT_YET_MAPPED', 'OUT_OF_ORDER_EVENT', 'STRIPE_API_TIMEOUT'];

                    $treatAsTransient = $isRetryableFlag || ($failureReason !== null && in_array($failureReason, $transientReasons, true));
                    // If subscription fetch failed earlier, ambiguous customer should be treated as transient
                    if (!$treatAsTransient && $failureReason === 'CUSTOMER_AMBIGUOUS' && !empty($result['subscription_fetch_failed'])) {
                        $treatAsTransient = true;
                    }

                    if ($treatAsTransient) {
                        // Treat as transient: increment retry count and mark pending so Stripe can retry delivery.
                        $this->eventRepo->incrementRetryCount($eventId);
                        if (method_exists($this->eventRepo, 'markPending')) {
                            $this->eventRepo->markPending($eventId);
                        }
                        // Ensure callers see a retryable flag when we consider the failure transient
                        $result['retryable'] = true;
                    } else {
                        // Terminal failures (client errors / ambiguous data) should be marked failed.
                        if (empty($result['success']) && !empty($result['client_error'])) {
                            $this->eventRepo->markFailed($eventId, $result['message'] ?? 'Processing failed (client error)');
                        } elseif (empty($result['success'])) {
                            // Default to transient when unknown: increment retry and leave pending.
                            $this->eventRepo->incrementRetryCount($eventId);
                            $this->eventRepo->markPending($eventId);
                        }
                    }

                    // Debug: log ambiguous+subscription_fetch_failed cases to assist test troubleshooting
                    if (($failureReason === 'CUSTOMER_AMBIGUOUS') && !empty($result['subscription_fetch_failed'])) {
                        error_log('DEBUG_AMBIGUOUS_SUB_FETCH: ' . json_encode($result));
                    }
                }
            }

            return $result;
        } catch (\Throwable $e) {
            if ($this->eventRepo !== null) {
                // For unexpected exceptions treat as transient: increment retry counter and keep pending
                $this->eventRepo->incrementRetryCount($eventId);
                if (method_exists($this->eventRepo, 'markPending')) {
                    $this->eventRepo->markPending($eventId);
                }
            }

            return ['success' => false, 'message' => 'Processing error: ' . $e->getMessage(), 'retryable' => true];
        }
    }

    private function dispatchEvent(string $type, array $event, string $eventId): array
    {
        $object = $event['data']['object'] ?? [];

        switch ($type) {
            case 'checkout.session.completed':
                return $this->handleCheckoutSessionCompleted($object, $eventId);
            case 'invoice.paid':
            case 'invoice.payment_succeeded':
                return $this->handleInvoicePaid($object, $eventId);
            case 'invoice.created':
                return $this->handleInvoiceCreated($object);
            case 'invoice.finalized':
                return $this->handleInvoiceFinalized($object);
            case 'payment_intent.succeeded':
                return [
                    'success' => true,
                    'message' => 'Payment intent succeeded received',
                    'payment_intent' => $object['id'] ?? null,
                    'stripe_reference' => $object['id'] ?? null,
                    'stripe_reference_type' => 'payment_intent',
                    'processed' => true,
                ];
            case 'payment_intent.payment_failed':
                return $this->handlePaymentIntentFailed($object);
            case 'invoice.payment_failed':
                return $this->handleInvoicePaymentFailed($object);
            case 'charge.refunded':
                return $this->handleChargeRefunded($object);
            case 'charge.dispute.created':
                return $this->handleChargeDisputeCreated($object);
            case 'customer.subscription.created':
                return $this->handleSubscriptionCreated($object);
            case 'customer.subscription.updated':
                return $this->handleSubscriptionUpdated($object);
            case 'customer.subscription.deleted':
                return $this->handleSubscriptionDeleted($object);
            case 'customer.subscription.trial_will_end':
                return $this->handleTrialWillEnd($object);
            default:
                return ['success' => true, 'message' => 'Evento recebido e ignorado', 'processed' => true];
        }
    }

    private function handleCheckoutSessionCompleted(array $session, string $eventId): array
    {
        $metadata = $this->resolveMetadata($session, $session['subscription'] ?? null);
        $tenantId = !empty($metadata['tenant_id']) ? (int)$metadata['tenant_id'] : null;
        $tenantPlan = $metadata['tenant_plan'] ?? null;
        $paymentStatus = $session['payment_status'] ?? null;
        $sessionId = $session['id'] ?? null;

        if ($paymentStatus !== 'paid') {
            return ['success' => true, 'message' => 'Checkout session completed received, but payment is not paid yet.', 'processed' => true];
        }

        if (!$tenantId || !$tenantPlan || !$sessionId) {
            return ['success' => false, 'message' => 'Checkout session completed received but missing required metadata.', 'client_error' => true];
        }

        $this->billingService->syncStripeIdentifiers($tenantId, $session['customer'] ?? null, $session['subscription'] ?? null);

        $billingUpdates = ['plano' => $tenantPlan, 'status' => 'active'];
        $expiration = $this->resolveExpirationFromSession($session);
        if ($expiration !== null) {
            $billingUpdates['data_expiracao'] = $expiration;
        }

        $tenantUpdated = $this->tenantRepository?->updateBillingFields($tenantId, $billingUpdates) ?? true;
        if (!$tenantUpdated) {
            return ['success' => false, 'message' => 'Checkout session completed received but tenant provisioning failed.', 'retryable' => true];
        }

        // Sincronizar limite_usuarios com base no plano atual usando o repositório existente.
        // Não duplicar lógica de cálculo de limite; confiar em TenantRepository::updateUserLimitFromPlan().
        $limitOk = $this->tenantRepository?->updateUserLimitFromPlan($tenantId) ?? true;
        if ($limitOk === false) {
            // Tratar como falha retryable para permitir reprocessamento do webhook.
            return ['success' => false, 'message' => 'Checkout provisioning failed to sync user limit', 'retryable' => true];
        }

        return ['success' => true, 'message' => 'Checkout session completed received and tenant provisioned.', 'processed' => true, 'tenant_id' => $tenantId, 'strategy' => 'checkout_session_provisioning'];
    }

    private function handleInvoicePaid(array $invoice, string $eventId): array
    {
        $invoiceId = $invoice['id'] ?? null;
        if (!$invoiceId) {
            return ['success' => false, 'message' => 'Invoice paid received without invoice identifier.', 'client_error' => true];
        }

        $existingInvoicePayment = $this->billingService->findPaymentByStripeReferenceGlobal($invoiceId);
        if ($existingInvoicePayment) {
            return ['success' => true, 'message' => 'Invoice already processed. Payment is idempotent.', 'processed' => true, 'idempotent' => true, 'tenant_id' => (int)$existingInvoicePayment['tenant_id']];
        }

        $tenantResolution = $this->resolveInvoiceTenantContext($invoice, $eventId);
        $tenantId = !empty($tenantResolution['tenant_id']) ? (int)$tenantResolution['tenant_id'] : null;
        $tenantPlan = $tenantResolution['tenant_plan'] ?? null;

        if (!$tenantId || !$tenantPlan) {
            return [
                'success' => false,
                'message' => 'Invoice paid received but tenant could not be resolved.',
                'client_error' => true,
                'failure_reason' => $tenantResolution['failure_reason'] ?? 'TENANT_RESOLUTION_FAILED',
                'strategy' => $tenantResolution['strategy'] ?? 'none',
                'invoice_id' => $invoiceId,
                'customer_id' => $invoice['customer'] ?? null,
                'subscription_id' => $invoice['subscription'] ?? null,
                'subscription_fetch_failed' => $tenantResolution['subscription_fetch_failed'] ?? false,
            ];
        }

        $this->billingService->syncStripeIdentifiers($tenantId, $invoice['customer'] ?? null, $invoice['subscription'] ?? null);

        $amountPaid = $invoice['amount_paid'] ?? null;
        $amount = is_numeric($amountPaid) ? (int)$amountPaid / 100.0 : 0.0;
        $billingUpdates = ['plano' => $tenantPlan, 'status' => 'active'];
        $expiration = $this->resolveExpirationFromInvoice($invoice);
        if ($expiration !== null) {
            $billingUpdates['data_expiracao'] = $expiration;
        }

        $result = $this->billingService->registerPayment($tenantId, [
            'valor' => $amount,
            'plano' => $tenantPlan,
            'status' => 'pago',
            'data_pagamento' => date('Y-m-d H:i:s'),
            'stripe_reference' => $invoiceId,
            'stripe_reference_type' => 'invoice',
            'stripe_event_id' => $eventId,
        ], $billingUpdates);

        if ($result['success']) {
            return ['success' => true, 'message' => 'Invoice paid received and payment processed.', 'processed' => true, 'tenant_id' => $tenantId, 'tenant_plan' => $tenantPlan, 'strategy' => $tenantResolution['strategy'] ?? 'metadata'];
        }

        return ['success' => false, 'message' => 'Invoice received but payment processing failed: ' . ($result['error'] ?? 'unknown'), 'error' => $result['error'] ?? 'unknown', 'retryable' => true];
    }

    private function handleInvoicePaymentFailed(array $invoice): array
    {
        $metadata = $this->resolveMetadata($invoice, $invoice['subscription'] ?? null);
        $tenantId = !empty($metadata['tenant_id']) ? (int)$metadata['tenant_id'] : null;
        $invoiceId = $invoice['id'] ?? null;
        $amountPaid = $invoice['amount_paid'] ?? null;

        if (!$invoiceId || !$tenantId) {
            return ['success' => false, 'message' => 'Invoice payment failed received but missing required metadata.', 'client_error' => true];
        }

        $notificationResult = $this->notificationService->sendFailedPaymentNotification(
            $tenantId,
            $invoiceId,
            is_numeric($amountPaid) ? (int)$amountPaid / 100.0 : null
        );

        $emailNotification = !empty($notificationResult['success']) ? 'sent' : 'failed';

        if ($this->billingService->deactivateTenant($tenantId)) {
            return [
                'success' => true,
                'message' => 'Invoice payment failed processed, tenant marked as inactive.',
                'processed' => true,
                'email_notification' => $emailNotification,
            ];
        }

        return [
            'success' => false,
            'message' => 'Invoice payment failed processing, but failed to mark tenant inactive.',
            'error' => 'Failed to update tenant status after payment failure.',
            'retryable' => true,
            'email_notification' => $emailNotification,
        ];
    }

    private function handleInvoiceCreated(array $invoice): array
    {
        $tenantId = !empty($invoice['metadata']['tenant_id']) ? (int)$invoice['metadata']['tenant_id'] : null;
        $invoiceId = $invoice['id'] ?? null;

        if (!$invoiceId || !$tenantId) {
            return ['success' => true, 'message' => 'Invoice created received, no tenant metadata available.', 'processed' => true];
        }

        return ['success' => true, 'message' => 'Invoice created received for tenant ' . $tenantId, 'processed' => true];
    }

    private function handleInvoiceFinalized(array $invoice): array
    {
        $tenantId = !empty($invoice['metadata']['tenant_id']) ? (int)$invoice['metadata']['tenant_id'] : null;
        $invoiceId = $invoice['id'] ?? null;

        if (!$invoiceId || !$tenantId) {
            return ['success' => true, 'message' => 'Invoice finalized received, no tenant metadata available.', 'processed' => true];
        }

        return ['success' => true, 'message' => 'Invoice finalized received for tenant ' . $tenantId, 'processed' => true];
    }

    private function handlePaymentIntentFailed(array $paymentIntent): array
    {
        $tenantId = !empty($paymentIntent['metadata']['tenant_id']) ? (int)$paymentIntent['metadata']['tenant_id'] : null;
        $paymentIntentId = $paymentIntent['id'] ?? null;

        if (!$paymentIntentId) {
            return ['success' => true, 'message' => 'Payment intent failure received without payment intent ID.', 'processed' => true];
        }

        return ['success' => true, 'message' => 'Payment intent payment_failed received' . ($tenantId ? ' for tenant ' . $tenantId : ''), 'processed' => true];
    }

    private function handleChargeRefunded(array $charge): array
    {
        $tenantId = !empty($charge['metadata']['tenant_id']) ? (int)$charge['metadata']['tenant_id'] : null;
        $chargeId = $charge['id'] ?? null;

        return ['success' => true, 'message' => 'Charge refunded received' . ($chargeId ? ' for charge ' . $chargeId : ''), 'processed' => true];
    }

    private function handleChargeDisputeCreated(array $charge): array
    {
        $tenantId = !empty($charge['metadata']['tenant_id']) ? (int)$charge['metadata']['tenant_id'] : null;
        $chargeId = $charge['id'] ?? null;

        return ['success' => true, 'message' => 'Charge dispute created received' . ($chargeId ? ' for charge ' . $chargeId : ''), 'processed' => true];
    }

    private function handleSubscriptionUpdated(array $subscription): array
    {
        $metadata = $this->resolveMetadata($subscription, $subscription['id'] ?? null);
        $tenantId = !empty($metadata['tenant_id']) ? (int)$metadata['tenant_id'] : null;
        $tenantPlan = $metadata['tenant_plan'] ?? null;

        if (!$tenantId) {
            return ['success' => false, 'message' => 'Tenant ID not found in subscription metadata.', 'client_error' => true];
        }

        $this->billingService->syncStripeIdentifiers($tenantId, $subscription['customer'] ?? null, $subscription['id'] ?? null);

        $status = $subscription['status'] ?? null;
        $currentPeriodEnd = $subscription['current_period_end'] ?? null;
        $fields = [];

        if ($tenantPlan !== null) {
            $fields['plano'] = $tenantPlan;
        }

        if ($status === 'active') {
            if ($currentPeriodEnd) {
                $fields['data_expiracao'] = date('Y-m-d H:i:s', (int)$currentPeriodEnd);
            } else {
                $fields['data_expiracao'] = date('Y-m-d H:i:s', time() + 30 * 86400);
            }

            if ($this->billingService->activateTenant($tenantId, $fields)) {
                return ['success' => true, 'message' => 'Tenant subscription updated and activated.', 'processed' => true];
            }

            return ['success' => false, 'message' => 'Failed to update tenant subscription status.', 'retryable' => true];
        }

        if (in_array($status, ['canceled', 'incomplete_expired', 'unpaid', 'past_due', 'paused'], true)) {
            if ($this->billingService->deactivateTenant($tenantId)) {
                return ['success' => true, 'message' => 'Tenant marked inactive due to subscription status: ' . $status, 'processed' => true];
            }

            return ['success' => false, 'message' => 'Failed to deactivate tenant after subscription update.', 'retryable' => true];
        }

        return ['success' => true, 'message' => 'Subscription update received but not actionable: ' . $status, 'processed' => true];
    }

    private function handleSubscriptionDeleted(array $subscription): array
    {
        $metadata = $this->resolveMetadata($subscription, $subscription['id'] ?? null);
        $tenantId = !empty($metadata['tenant_id']) ? (int)$metadata['tenant_id'] : null;

        if (!$tenantId) {
            return ['success' => false, 'message' => 'Subscription deleted received but tenant ID not found.', 'client_error' => true];
        }

        $notificationResult = $this->notificationService->sendSubscriptionCancellationNotification(
            $tenantId,
            $subscription['id'] ?? null
        );
        if ($this->billingService->deactivateTenant($tenantId)) {
            return [
                'success' => true,
                'message' => 'Subscription deleted received and tenant marked inactive.',
                'processed' => true,
                'email_notification' => $notificationResult['email_sent'] ? 'sent' : 'failed',
            ];
        }

        return [
            'success' => false,
            'message' => 'Subscription deleted received but tenant could not be marked inactive.',
            'error' => 'Failed to mark tenant inactive on subscription deletion.',
            'retryable' => true,
            'email_notification' => $notificationResult['email_sent'] ? 'sent' : 'failed',
        ];
    }

    private function resolveMetadata(array $source, ?string $subscriptionId = null): array
    {
        if (!empty($source['metadata']) && is_array($source['metadata'])) {
            return $source['metadata'];
        }

        $subscriptionFetchFailed = false;
        if ($subscriptionId) {
            try {
                $subscription = $this->apiClient->fetchSubscription($subscriptionId);
                if (!empty($subscription['metadata']) && is_array($subscription['metadata'])) {
                    return $subscription['metadata'];
                }

                return ['__failure_reason' => 'SUBSCRIPTION_MISSING', '__strategy' => 'subscription_metadata'];
            } catch (\Throwable $e) {
                $reason = $this->classifyStripeFailure($e);
                return ['__failure_reason' => $reason, '__strategy' => 'subscription_metadata'];
            }
        }

        return ['__failure_reason' => 'METADATA_MISSING', '__strategy' => 'direct_metadata'];
    }

    private function handleSubscriptionCreated(array $subscription): array
    {
        $metadata = $this->resolveMetadata($subscription, $subscription['id'] ?? null);
        $tenantId = !empty($metadata['tenant_id']) ? (int)$metadata['tenant_id'] : null;
        $tenantPlan = $metadata['tenant_plan'] ?? null;

        if (!$tenantId) {
            return ['success' => false, 'message' => 'Subscription created received but missing tenant metadata.', 'client_error' => true];
        }

        $this->billingService->syncStripeIdentifiers($tenantId, $subscription['customer'] ?? null, $subscription['id'] ?? null);

        $fields = [];
        if ($tenantPlan !== null) {
            $fields['plano'] = $tenantPlan;
        }

        $expiration = $this->resolveExpirationFromSubscription($subscription);
        if ($expiration !== null) {
            $fields['data_expiracao'] = $expiration;
        }

        if (!empty($fields)) {
            $fields['status'] = 'active';
            if ($this->billingService->activateTenant($tenantId, $fields)) {
                return ['success' => true, 'message' => 'Subscription created processed and tenant activated.', 'processed' => true];
            }

            return ['success' => false, 'message' => 'Failed to activate tenant after subscription created.', 'retryable' => true];
        }

        return ['success' => true, 'message' => 'Subscription created received and synced.', 'processed' => true];
    }

    private function handleTrialWillEnd(array $subscription): array
    {
        $metadata = $this->resolveMetadata($subscription, $subscription['id'] ?? null);
        $tenantId = !empty($metadata['tenant_id']) ? (int)$metadata['tenant_id'] : null;

        if (!$tenantId) {
            return ['success' => false, 'message' => 'Trial will end received but missing tenant metadata.', 'client_error' => true];
        }

        $this->billingService->syncStripeIdentifiers($tenantId, $subscription['customer'] ?? null, $subscription['id'] ?? null);

        return ['success' => true, 'message' => 'Subscription trial will end received.', 'processed' => true];
    }

    private function resolveExpirationFromSession(array $session): ?string
    {
        if (!empty($session['subscription'])) {
            try {
                $subscription = $this->apiClient->fetchSubscription($session['subscription']);
                return $this->resolveExpirationFromSubscription($subscription);
            } catch (\Throwable $e) {
                return null;
            }
        }

        return date('Y-m-d H:i:s', time() + 30 * 86400);
    }

    private function resolveExpirationFromInvoice(array $invoice): ?string
    {
        if (!empty($invoice['lines']['data'][0]['period']['end'])) {
            return date('Y-m-d H:i:s', (int)$invoice['lines']['data'][0]['period']['end']);
        }

        if (!empty($invoice['subscription'])) {
            try {
                $subscription = $this->apiClient->fetchSubscription($invoice['subscription']);
                return $this->resolveExpirationFromSubscription($subscription);
            } catch (\Throwable $e) {
                return null;
            }
        }

        return date('Y-m-d H:i:s', time() + 30 * 86400);
    }

    private function resolveExpirationFromSubscription(array $subscription): ?string
    {
        // Preserve existing root-level behavior first
        if (!empty($subscription['current_period_end']) && is_numeric($subscription['current_period_end'])) {
            return date('Y-m-d H:i:s', (int)$subscription['current_period_end']);
        }

        // If root-level current_period_end is absent, attempt to find it inside items.data[]
        if (!empty($subscription['items']) && is_array($subscription['items'])) {
            $items = $subscription['items']['data'] ?? $subscription['items'];
            if (is_array($items)) {
                // Prefer the first valid current_period_end found in items.data[].
                // NOTE: Project contains no explicit rule for multiple items; choosing
                // the first item is the minimal, least-invasive behavior change.
                foreach ($items as $it) {
                    if (!empty($it['current_period_end']) && is_numeric($it['current_period_end'])) {
                        return date('Y-m-d H:i:s', (int)$it['current_period_end']);
                    }
                }
            }
        }

        // Existing fallback: use cancel_at when present
        if (!empty($subscription['cancel_at']) && is_numeric($subscription['cancel_at'])) {
            return date('Y-m-d H:i:s', (int)$subscription['cancel_at']);
        }

        return null;
    }
    private function resolveInvoiceTenantContext(array $invoice, string $eventId): array
    {
        $invoiceId = $invoice['id'] ?? null;
        $customerId = !empty($invoice['customer']) ? trim((string)$invoice['customer']) : null;
        $subscriptionId = !empty($invoice['subscription']) ? trim((string)$invoice['subscription']) : null;

        $subscriptionFetchFailed = false;

        if ($subscriptionId) {
            try {
                $subscription = $this->apiClient->fetchSubscription($subscriptionId);
                $subscriptionMetadata = is_array($subscription['metadata'] ?? null) ? $subscription['metadata'] : [];
                if (!empty($subscriptionMetadata)) {
                    $tenantId = $this->coerceTenantId($subscriptionMetadata['tenant_id'] ?? null);
                    if ($tenantId !== null) {
                        $resolved = $this->resolveTenantContextCandidate($tenantId, $subscriptionMetadata['tenant_plan'] ?? null, 'subscription_metadata', $eventId, $invoiceId, $customerId, $subscriptionId, $subscriptionFetchFailed);
                        if ($resolved !== null) {
                            return $resolved;
                        }
                    }
                }

                $this->logTenantResolutionFailure($eventId, $invoiceId, $customerId, $subscriptionId, 'subscription_metadata', 'SUBSCRIPTION_MISSING');
            } catch (\Throwable $e) {
                $reason = $this->classifyStripeFailure($e);
                $subscriptionFetchFailed = true;
                $this->logTenantResolutionFailure($eventId, $invoiceId, $customerId, $subscriptionId, 'subscription_metadata', $reason);
            }
        }

        $metadataCandidates = [
            [
                'metadata' => is_array($invoice['metadata'] ?? null) ? $invoice['metadata'] : [],
                'strategy' => 'metadata',
            ],
            [
                'metadata' => is_array($invoice['parent']['subscription_details']['metadata'] ?? null) ? $invoice['parent']['subscription_details']['metadata'] : [],
                'strategy' => 'parent_subscription_details',
            ],
        ];

        foreach ($metadataCandidates as $candidate) {
            $metadata = $candidate['metadata'];
            if (empty($metadata)) {
                continue;
            }

            $tenantId = $this->coerceTenantId($metadata['tenant_id'] ?? null);
            if ($tenantId === null) {
                continue;
            }

            $resolved = $this->resolveTenantContextCandidate($tenantId, $metadata['tenant_plan'] ?? null, $candidate['strategy'], $eventId, $invoiceId, $customerId, $subscriptionId, $subscriptionFetchFailed);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        $lineItemsTenantResolution = $this->resolveTenantFromInvoiceLines($invoice, $eventId, $invoiceId, $customerId, $subscriptionId);
        if ($lineItemsTenantResolution !== null) {
            return $lineItemsTenantResolution;
        }

        if (!$customerId) {
            $this->logTenantResolutionFailure($eventId, $invoiceId, null, $subscriptionId, 'customer', 'CUSTOMER_MISSING');
            return ['tenant_id' => null, 'tenant_plan' => null, 'strategy' => 'customer', 'failure_reason' => 'CUSTOMER_MISSING'];
        }

        if ($this->tenantRepository === null) {
            $this->logTenantResolutionFailure($eventId, $invoiceId, $customerId, $subscriptionId, 'customer', 'TENANT_RESOLUTION_FAILED');
            return ['tenant_id' => null, 'tenant_plan' => null, 'strategy' => 'customer', 'failure_reason' => 'TENANT_RESOLUTION_FAILED'];
        }

        $tenants = $this->tenantRepository->findByStripeCustomerId($customerId);
        $count = count($tenants);

        if ($count === 0) {
            $this->logTenantResolutionFailure($eventId, $invoiceId, $customerId, $subscriptionId, 'customer', 'CUSTOMER_NOT_YET_MAPPED');
            return ['tenant_id' => null, 'tenant_plan' => null, 'strategy' => 'customer', 'failure_reason' => 'CUSTOMER_NOT_YET_MAPPED', 'subscription_fetch_failed' => $subscriptionFetchFailed];
        }

        if ($count > 1) {
            $this->logTenantResolutionFailure($eventId, $invoiceId, $customerId, $subscriptionId, 'customer', 'CUSTOMER_AMBIGUOUS');
            return ['tenant_id' => null, 'tenant_plan' => null, 'strategy' => 'customer', 'failure_reason' => 'CUSTOMER_AMBIGUOUS', 'subscription_fetch_failed' => $subscriptionFetchFailed];
        }

        $tenant = $tenants[0];
        $tenantId = !empty($tenant['id']) ? (int)$tenant['id'] : null;
        if (!$tenantId) {
            $this->logTenantResolutionFailure($eventId, $invoiceId, $customerId, $subscriptionId, 'customer', 'TENANT_RESOLUTION_FAILED');
            return ['tenant_id' => null, 'tenant_plan' => $tenant['plano'] ?? null, 'strategy' => 'customer', 'failure_reason' => 'TENANT_RESOLUTION_FAILED', 'subscription_fetch_failed' => $subscriptionFetchFailed];
        }

        $resolved = $this->resolveTenantContextCandidate($tenantId, $tenant['plano'] ?? null, 'customer', $eventId, $invoiceId, $customerId, $subscriptionId, $subscriptionFetchFailed);
        if ($resolved !== null) {
            return $resolved;
        }

        return ['tenant_id' => $tenantId, 'tenant_plan' => $tenant['plano'] ?? null, 'strategy' => 'customer', 'failure_reason' => null, 'subscription_fetch_failed' => $subscriptionFetchFailed];
    }

    private function resolveTenantContextCandidate(int $tenantId, $tenantPlan, string $strategy, string $eventId, ?string $invoiceId, ?string $customerId, ?string $subscriptionId, bool $subscriptionFetchFailed = false): ?array
    {
        if ($this->tenantRepository !== null) {
            $tenant = $this->tenantRepository->getTenantById($tenantId);
            if (!$tenant) {
                $this->logTenantResolutionFailure($eventId, $invoiceId, $customerId, $subscriptionId, $strategy, 'TENANT_NOT_YET_MATERIALIZED');
                return [
                    'tenant_id' => null,
                    'tenant_plan' => $tenantPlan,
                    'strategy' => $strategy,
                    'failure_reason' => 'TENANT_NOT_YET_MATERIALIZED',
                    'subscription_fetch_failed' => $subscriptionFetchFailed,
                ];
            }
        }

        $effectivePlan = $tenantPlan;
        if (!is_string($effectivePlan) || trim($effectivePlan) === '') {
            $tenantPlanFromLocal = null;
            if ($this->tenantRepository !== null) {
                $tenant = $this->tenantRepository->getTenantById($tenantId);
                if ($tenant !== null) {
                    $tenantPlanFromLocal = $tenant['plano'] ?? null;
                }
            }

            $effectivePlan = is_string($tenantPlanFromLocal) ? trim($tenantPlanFromLocal) : null;
        }

        if (!is_string($effectivePlan) || trim($effectivePlan) === '') {
            $this->logTenantResolutionFailure($eventId, $invoiceId, $customerId, $subscriptionId, $strategy, 'TENANT_PLAN_MISSING');
            return [
                'tenant_id' => null,
                'tenant_plan' => null,
                'strategy' => $strategy,
                'failure_reason' => 'TENANT_PLAN_MISSING',
                'subscription_fetch_failed' => $subscriptionFetchFailed,
            ];
        }

        return [
            'tenant_id' => $tenantId,
            'tenant_plan' => trim($effectivePlan),
            'strategy' => $strategy,
            'failure_reason' => null,
            'subscription_fetch_failed' => $subscriptionFetchFailed,
        ];
    }

    private function resolveTenantFromInvoiceLines(array $invoice, string $eventId, ?string $invoiceId, ?string $customerId, ?string $subscriptionId): ?array
    {
        $lineItems = $invoice['lines']['data'] ?? [];
        if (!is_array($lineItems) || $lineItems === []) {
            return null;
        }

        $tenantIds = [];
        $tenantPlan = null;

        foreach ($lineItems as $lineItem) {
            if (!is_array($lineItem)) {
                continue;
            }

            $lineMetadata = is_array($lineItem['metadata'] ?? null) ? $lineItem['metadata'] : [];
            $lineTenantId = $this->coerceTenantId($lineMetadata['tenant_id'] ?? null);
            if ($lineTenantId === null) {
                continue;
            }

            $tenantIds[] = $lineTenantId;
            if ($tenantPlan === null && !empty($lineMetadata['tenant_plan'])) {
                $tenantPlan = (string)$lineMetadata['tenant_plan'];
            }
        }

        $uniqueTenantIds = array_values(array_unique($tenantIds));
        if ($uniqueTenantIds === []) {
            return null;
        }

        if (count($uniqueTenantIds) > 1) {
            $this->logTenantResolutionFailure($eventId, $invoiceId, $customerId, $subscriptionId, 'line_items', 'LINE_ITEMS_AMBIGUOUS');
            return ['tenant_id' => null, 'tenant_plan' => $tenantPlan, 'strategy' => 'line_items', 'failure_reason' => 'LINE_ITEMS_AMBIGUOUS', 'retryable' => true];
        }

        $tenantId = $uniqueTenantIds[0];
        if ($this->tenantRepository !== null) {
            $tenant = $this->tenantRepository->getTenantById($tenantId);
            if (!$tenant) {
                $this->logTenantResolutionFailure($eventId, $invoiceId, $customerId, $subscriptionId, 'line_items', 'TENANT_NOT_YET_MATERIALIZED');
                return ['tenant_id' => null, 'tenant_plan' => $tenantPlan, 'strategy' => 'line_items', 'failure_reason' => 'TENANT_NOT_YET_MATERIALIZED'];
            }
        }

        return ['tenant_id' => $tenantId, 'tenant_plan' => $tenantPlan, 'strategy' => 'line_items', 'failure_reason' => null];
    }

    private function coerceTenantId($value): ?int
    {
        if (!is_scalar($value)) {
            return null;
        }

        $normalized = trim((string)$value);
        if ($normalized === '' || !is_numeric($normalized)) {
            return null;
        }

        $tenantId = (int)$normalized;
        if ($tenantId <= 0) {
            return null;
        }

        return $tenantId;
    }

    private function classifyStripeFailure(\Throwable $e): string
    {
        $message = strtolower($e->getMessage() ?? '');

        if (str_contains($message, 'timeout')) {
            return 'STRIPE_API_TIMEOUT';
        }

        if (str_contains($message, 'api error') || str_contains($message, 'http request failed') || str_contains($message, 'invalid json')) {
            return 'STRIPE_API_ERROR';
        }

        return 'TENANT_RESOLUTION_FAILED';
    }

    private function logTenantResolutionFailure(string $eventId, ?string $invoiceId, ?string $customerId, ?string $subscriptionId, string $strategy, string $failureReason): void
    {
        error_log(sprintf(
            'StripeWebhookService tenant_resolution event_id=%s invoice_id=%s customer_id=%s subscription_id=%s strategy=%s failure_reason=%s',
            $eventId,
            $invoiceId ?? 'unknown',
            $customerId ?? 'unknown',
            $subscriptionId ?? 'unknown',
            $strategy,
            $failureReason
        ));
    }

    private function registerPayment(int $tenantId, string $tenantPlan, float $amount): array
    {
        return $this->billingService->registerPayment($tenantId, [
            'valor' => $amount,
            'plano' => $tenantPlan,
            'status' => 'pago',
            'data_pagamento' => date('Y-m-d H:i:s'),
        ], []);
    }

    private function getCanonicalEventId(array $event): ?string
    {
        return $event['id'] ?? null;
    }

    private function extractTenantId(array $event): ?int
    {
        $object = $event['data']['object'] ?? [];

        if (!empty($object['metadata']['tenant_id'])) {
            return (int)$object['metadata']['tenant_id'];
        }

        if (!empty($object['subscription'])) {
            $metadata = $this->resolveMetadata($object, $object['subscription']);
            if (!empty($metadata['tenant_id'])) {
                return (int)$metadata['tenant_id'];
            }
        }

        return null;
    }

    private function logEventLookup(array $existing, string $eventId): void
    {
        $usedField = 'unknown';
        if (!empty($existing['stripe_event_id']) && $existing['stripe_event_id'] === $eventId) {
            $usedField = 'stripe_event_id';
        } elseif (!empty($existing['event_id']) && $existing['event_id'] === $eventId) {
            $usedField = 'event_id';
        } elseif (!empty($existing['stripe_event_id'])) {
            $usedField = 'stripe_event_id';
        } elseif (!empty($existing['event_id'])) {
            $usedField = 'event_id';
        }

        error_log(sprintf(
            'StripeWebhookService: existing event lookup using %s for event_id=%s status=%s',
            $usedField,
            $eventId,
            $existing['status'] ?? 'unknown'
        ));
    }
}
