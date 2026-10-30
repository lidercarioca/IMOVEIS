<?php

namespace Tests\Unit;

use App\Models\BillingHistoryModel;
use App\Infrastructure\Billing\PaymentRepository;
use App\Infrastructure\Billing\StripeEventRepository;
use App\Infrastructure\Billing\StripeApiClient;
use App\Infrastructure\Billing\TenantRepository;
use App\Services\Billing\BillingNotificationService;
use App\Services\Billing\StripeWebhookService;
use App\Services\Billing\StripeWebhookValidator;
use App\Services\Billing\TenantBillingService;
use PDO;
use PHPUnit\Framework\TestCase;

class StripeWebhookIdempotencyTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE pagamentos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tenant_id INTEGER NOT NULL,
                valor REAL NOT NULL,
                status TEXT,
                data_pagamento TEXT,
                plano TEXT,
                stripe_reference TEXT,
                stripe_reference_type TEXT,
                stripe_event_id TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT NULL
            )'
        );
        $this->pdo->exec('CREATE UNIQUE INDEX uq_pagamentos_tenant_stripe_reference ON pagamentos(tenant_id, stripe_reference)');
        $this->pdo->exec('CREATE UNIQUE INDEX uq_pagamentos_tenant_stripe_event_id ON pagamentos(tenant_id, stripe_event_id)');
    }

    private function buildSignedHeader(string $payload, string $secret): string
    {
        $t = time();
        $sig = hash_hmac('sha256', $t . '.' . $payload, $secret);
        return 't=' . $t . ',v1=' . $sig;
    }

    private function createService(): array
    {
        $validator = new StripeWebhookValidator();

        $apiClient = $this->createMock(StripeApiClient::class);
        $apiClient->method('fetchSubscription')->willReturn([
            'id' => 'sub_test',
            'metadata' => ['tenant_id' => '1', 'tenant_plan' => 'basico'],
            'current_period_end' => time() + 30 * 86400,
        ]);

        $events = [];
        $eventRepo = $this->createMock(StripeEventRepository::class);
        $eventRepo->method('findByEventId')->willReturnCallback(function ($eventId) use (&$events) {
            return $events[$eventId] ?? null;
        });
        $eventRepo->method('createPendingEvent')->willReturnCallback(function ($eventId, $payload, $tenantId = null) use (&$events) {
            $events[$eventId] = [
                'stripe_event_id' => $eventId,
                'event_id' => $eventId,
                'payload' => $payload,
                'status' => 'pending',
                'tenant_id' => $tenantId,
                'retry_count' => 0,
            ];
            return 1;
        });
        $eventRepo->method('markProcessed')->willReturnCallback(function ($eventId) use (&$events) {
            if (!isset($events[$eventId])) {
                return false;
            }
            $events[$eventId]['status'] = 'processed';
            return true;
        });
        $eventRepo->method('markFailed')->willReturnCallback(function ($eventId, $lastError = null) use (&$events) {
            if (!isset($events[$eventId])) {
                return false;
            }
            $events[$eventId]['status'] = 'failed';
            $events[$eventId]['error_message'] = $lastError;
            return true;
        });
        $eventRepo->method('markPending')->willReturnCallback(function ($eventId) use (&$events) {
            if (!isset($events[$eventId])) {
                return false;
            }
            $events[$eventId]['status'] = 'pending';
            return true;
        });
        $eventRepo->method('incrementRetryCount')->willReturnCallback(function ($eventId) use (&$events) {
            if (!isset($events[$eventId])) {
                return false;
            }
            $events[$eventId]['retry_count'] = ($events[$eventId]['retry_count'] ?? 0) + 1;
            return true;
        });
        $eventRepo->method('findByStripeEntity')->willReturn([]);

        $tenantRepo = $this->createMock(TenantRepository::class);
        $tenantRepo->method('getTenantById')->willReturnCallback(static fn(int $id): array => ['id' => $id, 'plano' => 'basico']);
        $tenantRepo->method('findByStripeCustomerId')->willReturn([]);
        $tenantRepo->method('updateStripeIdentifiers')->willReturn(true);
        $tenantRepo->method('updateBillingFields')->willReturn(true);
        // Ensure updateUserLimitFromPlan returns true for provisioning flows in these unit tests
        $tenantRepo->method('updateUserLimitFromPlan')->willReturn(true);

        $billingService = new TenantBillingService(
            new PaymentRepository(new BillingHistoryModel($this->pdo)),
            $tenantRepo,
            null,
            $this->pdo
        );

        $notificationService = $this->createMock(BillingNotificationService::class);
        $notificationService->method('sendFailedPaymentNotification')->willReturn(['success' => true]);

        $service = new StripeWebhookService($validator, $apiClient, $eventRepo, $billingService, $notificationService);

        return [$service, $eventRepo];
    }

    private function countPayments(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM pagamentos');
        return (int)$stmt->fetchColumn();
    }

    private function fetchLastPayment(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM pagamentos ORDER BY id DESC LIMIT 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: [];
    }

    private function makeCheckoutSessionEvent(string $eventId, string $sessionId, ?int $tenantId = 1): array
    {
        return [
            'id' => $eventId,
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => $sessionId,
                'payment_status' => 'paid',
                'amount_total' => 4990,
                'customer' => 'cus_test_1',
                'subscription' => 'sub_test_1',
                'metadata' => ['tenant_id' => (string)$tenantId, 'tenant_plan' => 'basico'],
            ]],
        ];
    }

    private function makeInvoiceEvent(string $type, string $eventId, string $invoiceId, ?int $tenantId = 1): array
    {
        return [
            'id' => $eventId,
            'type' => $type,
            'data' => ['object' => [
                'id' => $invoiceId,
                'amount_paid' => 4990,
                'customer' => 'cus_test_1',
                'subscription' => 'sub_test_1',
                'metadata' => ['tenant_id' => (string)$tenantId, 'tenant_plan' => 'basico'],
                'lines' => ['data' => [['period' => ['end' => time() + 30 * 86400]]]],
            ]],
        ];
    }

    public function test_checkout_session_two_event_ids_records_zero_payments(): void
    {
        [$service, $eventRepo] = $this->createService();

        $eventA = $this->makeCheckoutSessionEvent('evt_chk_a', 'cs_same_1');
        $eventB = $this->makeCheckoutSessionEvent('evt_chk_b', 'cs_same_1');

        $payloadA = json_encode($eventA);
        $payloadB = json_encode($eventB);

        $resA = $service->handle($payloadA, $this->buildSignedHeader($payloadA, 'whsec_test'), 'whsec_test');
        $resB = $service->handle($payloadB, $this->buildSignedHeader($payloadB, 'whsec_test'), 'whsec_test');

        $this->assertTrue($resA['success']);
        $this->assertTrue($resB['success']);
        $this->assertSame(0, $this->countPayments(), 'Checkout session events are provisioning-only and must not create financial payments');
        $this->assertSame([], $this->fetchLastPayment(), 'No payment row should exist after checkout session processing');
    }

    public function test_invoice_paid_and_invoice_payment_succeeded_one_payment(): void
    {
        [$service, $eventRepo] = $this->createService();

        $eventA = $this->makeInvoiceEvent('invoice.paid', 'evt_inv_a', 'in_shared_1');
        $eventB = $this->makeInvoiceEvent('invoice.payment_succeeded', 'evt_inv_b', 'in_shared_1');

        $resA = $service->handle(json_encode($eventA), $this->buildSignedHeader(json_encode($eventA), 'whsec_test'), 'whsec_test');
        $resB = $service->handle(json_encode($eventB), $this->buildSignedHeader(json_encode($eventB), 'whsec_test'), 'whsec_test');

        $this->assertTrue($resA['success']);
        $this->assertTrue($resB['success']);
        $this->assertSame(1, $this->countPayments(), 'Equivalent invoice events should materialize only one financial payment');
        $last = $this->fetchLastPayment();
        $this->assertSame('in_shared_1', $last['stripe_reference'] ?? null);
        $this->assertSame('invoice', $last['stripe_reference_type'] ?? null);
    }

    public function test_same_event_processed_twice_is_idempotent(): void
    {
        [$service, $eventRepo] = $this->createService();
        $event = $this->makeCheckoutSessionEvent('evt_repeat', 'cs_repeat_1');
        $payload = json_encode($event);

        $first = $service->handle($payload, $this->buildSignedHeader($payload, 'whsec_test'), 'whsec_test');
        $second = $service->handle($payload, $this->buildSignedHeader($payload, 'whsec_test'), 'whsec_test');

        $this->assertTrue($first['success']);
        $this->assertTrue($second['success']);
        $this->assertSame(0, $this->countPayments(), 'Same checkout event retry must not create a financial payment row');
    }

    public function test_payment_intent_succeeded_does_not_create_payment(): void
    {
        [$service, $eventRepo] = $this->createService();

        $event = [
            'id' => 'evt_pi_ok',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_123', 'customer' => 'cus_test_1', 'metadata' => ['tenant_id' => '1', 'tenant_plan' => 'basico']]],
        ];

        $payload = json_encode($event);
        $result = $service->handle($payload, $this->buildSignedHeader($payload, 'whsec_test'), 'whsec_test');

        $this->assertTrue($result['success']);
        $this->assertSame('pi_123', $result['stripe_reference'] ?? null);
        $this->assertSame('payment_intent', $result['stripe_reference_type'] ?? null);
        $this->assertSame(0, $this->countPayments(), 'Payment intent success is correlational and must not materialize a payment');
    }

    public function test_subscription_created_persists_valid_subscription_id(): void
    {
        $validator = new StripeWebhookValidator();
        $apiClient = $this->createMock(StripeApiClient::class);
        $apiClient->method('fetchSubscription')->willReturn([
            'id' => 'sub_sync_1',
            'metadata' => ['tenant_id' => '7', 'tenant_plan' => 'pro'],
            'current_period_end' => time() + 30 * 86400,
        ]);

        $eventRepo = $this->createMock(StripeEventRepository::class);
        $eventRepo->method('findByEventId')->willReturn(null);
        $eventRepo->method('createPendingEvent')->willReturn(1);
        $eventRepo->method('markProcessed')->willReturn(true);
        $eventRepo->method('markFailed')->willReturn(true);
        $eventRepo->method('incrementRetryCount')->willReturn(true);
        $eventRepo->method('findByStripeEntity')->willReturn([]);

        $billingService = $this->createMock(TenantBillingService::class);
        $billingService->expects($this->once())
            ->method('syncStripeIdentifiers')
            ->with(7, 'cus_sync_1', 'sub_sync_1')
            ->willReturn(true);
        $billingService->expects($this->once())
            ->method('activateTenant')
            ->with(7, $this->arrayHasKey('plano'))
            ->willReturn(true);

        $notificationService = $this->createMock(BillingNotificationService::class);
        $service = new StripeWebhookService($validator, $apiClient, $eventRepo, $billingService, $notificationService);

        $subscription = [
            'id' => 'sub_sync_1',
            'customer' => 'cus_sync_1',
            'metadata' => ['tenant_id' => '7', 'tenant_plan' => 'pro'],
            'status' => 'active',
        ];

        $payload = json_encode(['id' => 'evt_sub_created', 'type' => 'customer.subscription.created', 'data' => ['object' => $subscription]]);
        $result = $service->handle($payload, $this->buildSignedHeader($payload, 'whsec_test'), 'whsec_test');

        $this->assertTrue($result['success']);
        $this->assertSame(0, $this->countPayments(), 'Subscription creation should not create a financial payment row');
    }

    public function test_same_invoice_reference_across_two_tenants_is_not_double_counted(): void
    {
        [$serviceA, $eventRepoA] = $this->createService();
        [$serviceB, $eventRepoB] = $this->createService();

        $eventA = $this->makeInvoiceEvent('invoice.paid', 'evt_t1', 'in_shared_global', 1);
        $eventB = $this->makeInvoiceEvent('invoice.payment_succeeded', 'evt_t2', 'in_shared_global', 2);

        $serviceA->handle(json_encode($eventA), $this->buildSignedHeader(json_encode($eventA), 'whsec_test'), 'whsec_test');
        $serviceB->handle(json_encode($eventB), $this->buildSignedHeader(json_encode($eventB), 'whsec_test'), 'whsec_test');

        $this->assertSame(1, $this->countPayments(), 'A single global invoice reference must not be materialized twice across tenants');
        $last = $this->fetchLastPayment();
        $this->assertSame('in_shared_global', $last['stripe_reference'] ?? null);
    }

    public function test_event_without_tenant_does_not_create_payment(): void
    {
        [$service, $eventRepo] = $this->createService();

        $event = [
            'id' => 'evt_no_tenant',
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_notenant',
                'payment_status' => 'paid',
                'amount_total' => 4990,
                'customer' => 'cus_test_1',
                // no subscription, no metadata -> tenant cannot be resolved
            ]],
        ];

        $payload = json_encode($event);
        $result = $service->handle($payload, $this->buildSignedHeader($payload, 'whsec_test'), 'whsec_test');

        $this->assertFalse($result['success'] ?? true, 'Event without tenant should be rejected');
        $this->assertSame(0, $this->countPayments(), 'No payment should be created when tenant cannot be resolved');
    }

    public function test_duplicate_key_handling_simulation(): void
    {
        // Simulate duplicate-key behavior using BillingHistoryModel directly
        $model = new BillingHistoryModel($this->pdo);

        $payload = [
            'valor' => 10.0,
            'plano' => 'basico',
            'stripe_reference' => 'dup_ref_1',
            'stripe_reference_type' => 'checkout.session',
            'stripe_event_id' => 'evt_dup_1',
        ];

        $first = $model->recordPayment(1, $payload);
        $second = $model->recordPayment(1, $payload);

        $this->assertTrue($first['success']);
        $this->assertTrue($second['success']);
        $this->assertTrue(!empty($second['existing']), 'Second insert should be treated as existing (idempotent)');
    }
}
