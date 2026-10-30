<?php

namespace Tests\Unit;

use App\Models\BillingHistoryModel;
use App\Infrastructure\Billing\PaymentRepository;
use App\Infrastructure\Billing\StripeApiClient;
use App\Infrastructure\Billing\StripeEventRepository;
use App\Infrastructure\Billing\TenantRepository;
use App\Services\Billing\BillingNotificationService;
use App\Services\Billing\StripeWebhookService;
use App\Services\Billing\StripeWebhookValidator;
use App\Services\Billing\TenantBillingService;
use PDO;
use PHPUnit\Framework\TestCase;

class StripeWebhookServiceTest extends TestCase
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
    }

    private function buildSignedHeader(string $payload, string $secret): string
    {
        $t = time();
        $sig = hash_hmac('sha256', $t . '.' . $payload, $secret);
        return 't=' . $t . ',v1=' . $sig;
    }

    private function createStripeWebhookService(array $tenantRepoMethods = []): array
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
        $tenantRepo->method('getTenantById')->willReturn(['id' => 1, 'plano' => 'basico']);
        $tenantRepo->method('updateStripeIdentifiers')->willReturn(true);
        $tenantRepo->method('updateBillingFields')->willReturn(true);
        $tenantRepo->method('updateUserLimitFromPlan')->willReturn(true);
        $tenantRepo->method('updateUserLimitFromPlan')->willReturn(true);

        foreach ($tenantRepoMethods as $method => $config) {
            $tenantRepo->method($method)->willReturn($config['return'] ?? true);
        }

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

    private function makeCheckoutSessionEvent(string $eventId, string $sessionId): array
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
                'metadata' => ['tenant_id' => '1', 'tenant_plan' => 'basico'],
            ]],
        ];
    }

    private function makeInvoiceEvent(string $type, string $eventId, string $invoiceId): array
    {
        return [
            'id' => $eventId,
            'type' => $type,
            'data' => ['object' => [
                'id' => $invoiceId,
                'amount_paid' => 4990,
                'customer' => 'cus_test_1',
                'subscription' => 'sub_test_1',
                'metadata' => ['tenant_id' => '1', 'tenant_plan' => 'basico'],
                'lines' => ['data' => [['period' => ['end' => time() + 30 * 86400]]]],
            ]],
        ];
    }

    public function test_checkout_session_completed_idempotent_for_same_event(): void
    {
        [$service, $eventRepo] = $this->createStripeWebhookService();
        $event = $this->makeCheckoutSessionEvent('evt_chk_same', 'cs_same_1');
        $payload = json_encode($event);
        $header = $this->buildSignedHeader($payload, 'whsec_test');

        $first = $service->handle($payload, $header, 'whsec_test');
        $second = $service->handle($payload, $header, 'whsec_test');

        $this->assertTrue($first['success']);
        $this->assertTrue($second['success']);
        $this->assertSame(0, $this->countPayments());
    }

    public function test_checkout_session_completed_same_session_different_event_ids_only_records_one_payment(): void
    {
        [$service, $eventRepo] = $this->createStripeWebhookService();

        $eventA = $this->makeCheckoutSessionEvent('evt_chk_a', 'cs_duplicate_1');
        $eventB = $this->makeCheckoutSessionEvent('evt_chk_b', 'cs_duplicate_1');

        $first = $service->handle(json_encode($eventA), $this->buildSignedHeader(json_encode($eventA), 'whsec_test'), 'whsec_test');
        $second = $service->handle(json_encode($eventB), $this->buildSignedHeader(json_encode($eventB), 'whsec_test'), 'whsec_test');

        $this->assertTrue($first['success']);
        $this->assertTrue($second['success']);
        $this->assertSame(0, $this->countPayments());
    }

    public function test_invoice_paid_same_event_id_only_records_one_payment(): void
    {
        [$service, $eventRepo] = $this->createStripeWebhookService();

        $event = $this->makeInvoiceEvent('invoice.paid', 'evt_inv_same', 'in_same_1');
        $payload = json_encode($event);
        $header = $this->buildSignedHeader($payload, 'whsec_test');

        $service->handle($payload, $header, 'whsec_test');
        $service->handle($payload, $header, 'whsec_test');

        $this->assertSame(1, $this->countPayments());
    }

    public function test_invoice_paid_and_invoice_payment_succeeded_same_invoice_only_records_one_payment(): void
    {
        [$service, $eventRepo] = $this->createStripeWebhookService();

        $eventA = $this->makeInvoiceEvent('invoice.paid', 'evt_inv_a', 'in_shared_1');
        $eventB = $this->makeInvoiceEvent('invoice.payment_succeeded', 'evt_inv_b', 'in_shared_1');

        $service->handle(json_encode($eventA), $this->buildSignedHeader(json_encode($eventA), 'whsec_test'), 'whsec_test');
        $service->handle(json_encode($eventB), $this->buildSignedHeader(json_encode($eventB), 'whsec_test'), 'whsec_test');

        $this->assertSame(1, $this->countPayments());
    }

    public function test_payment_intent_succeeded_does_not_register_payment(): void
    {
        $validator = new StripeWebhookValidator();
        $apiClient = $this->createMock(StripeApiClient::class);
        $apiClient->method('fetchSubscription')->willReturn(['id' => 'sub_test', 'metadata' => ['tenant_id' => '1', 'tenant_plan' => 'basico']]);

        $eventRepo = $this->createMock(StripeEventRepository::class);
        $eventRepo->method('findByEventId')->willReturn(null);
        $eventRepo->method('createPendingEvent')->willReturn(1);
        $eventRepo->method('markProcessed')->willReturn(true);
        $eventRepo->method('markFailed')->willReturn(true);
        $eventRepo->method('incrementRetryCount')->willReturn(true);
        $eventRepo->method('findByStripeEntity')->willReturn([]);

        $tenantService = $this->createMock(TenantBillingService::class);
        $tenantService->expects($this->never())->method('registerPayment');

        $notificationService = $this->createMock(BillingNotificationService::class);
        $service = new StripeWebhookService($validator, $apiClient, $eventRepo, $tenantService, $notificationService);

        $event = [
            'id' => 'evt_pi_succeed',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => [
                'id' => 'pi_test_123',
                'payment_intent' => 'pi_test_123',
            ]],
        ];

        $payload = json_encode($event);
        $result = $service->handle($payload, $this->buildSignedHeader($payload, 'whsec_test'), 'whsec_test');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('stripe_reference', $result);
        $this->assertSame('pi_test_123', $result['stripe_reference']);
        $this->assertSame('payment_intent', $result['stripe_reference_type']);
    }

    public function test_event_without_id_is_rejected(): void
    {
        [$service, $eventRepo] = $this->createStripeWebhookService();

        $event = [
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => 'cs_test_1', 'payment_status' => 'paid', 'amount_total' => 4990, 'customer' => 'cus_test_1', 'subscription' => 'sub_test_1', 'metadata' => ['tenant_id' => '1', 'tenant_plan' => 'basico']]],
        ];

        $payload = json_encode($event);
        $result = $service->handle($payload, $this->buildSignedHeader($payload, 'whsec_test'), 'whsec_test');

        $this->assertFalse($result['success']);
        $this->assertTrue(!empty($result['client_error']));
    }

    public function test_resolve_expiration_uses_items_data_when_root_absent(): void
    {
        // Setup in-memory DB and dependencies
        $pdo = $this->pdo;

        $validator = new StripeWebhookValidator();

        $apiClient = $this->createMock(StripeApiClient::class);
        $apiClient->method('fetchSubscription')->willReturn([
            'id' => 'sub_test_items',
            'metadata' => ['tenant_id' => '1', 'tenant_plan' => 'basico'],
            // root current_period_end intentionally absent
            'items' => ['data' => [['id' => 'si_1', 'current_period_end' => time() + 30 * 86400]]],
            'status' => 'active',
        ]);

        $eventRepo = $this->createMock(StripeEventRepository::class);
        $eventRepo->method('findByEventId')->willReturn(null);
        $eventRepo->method('createPendingEvent')->willReturn(1);
        $eventRepo->method('markProcessed')->willReturn(true);
        $eventRepo->method('markFailed')->willReturn(true);
        $eventRepo->method('incrementRetryCount')->willReturn(true);
        $eventRepo->method('findByStripeEntity')->willReturn([]);

        // Tenant repo must receive updateBillingFields with data_expiracao present
        $tenantRepo = $this->createMock(TenantRepository::class);
        $tenantRepo->method('updateStripeIdentifiers')->willReturn(true);
        $tenantRepo->method('updateUserLimitFromPlan')->willReturn(true);
        // Expect updateBillingFields called once with tenantId and fields containing data_expiracao
        $tenantRepo->expects($this->once())->method('updateBillingFields')->with(
            $this->equalTo(1),
            $this->callback(function ($arg) {
                return is_array($arg) && array_key_exists('data_expiracao', $arg);
            })
        )->willReturn(true);

        $billingService = new TenantBillingService(
            new PaymentRepository(new BillingHistoryModel($pdo)),
            $tenantRepo,
            null,
            $pdo
        );

        $notificationService = $this->createMock(BillingNotificationService::class);

        $service = new StripeWebhookService($validator, $apiClient, $eventRepo, $billingService, $notificationService);

        $event = [
            'id' => 'evt_items_exp',
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_items_1',
                'payment_status' => 'paid',
                'amount_total' => 4990,
                'customer' => 'cus_test_1',
                'subscription' => 'sub_test_items',
                'metadata' => ['tenant_id' => '1', 'tenant_plan' => 'basico'],
            ]],
        ];

        $payload = json_encode($event);
        $header = $this->buildSignedHeader($payload, 'whsec_test');

        $result = $service->handle($payload, $header, 'whsec_test');

        $this->assertTrue($result['success']);
        $this->assertSame(0, $this->countPayments());
    }

    public function test_checkout_session_without_session_id_is_rejected(): void
    {
        [$service, $eventRepo] = $this->createStripeWebhookService();

        $event = [
            'id' => 'evt_chk_missing',
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['payment_status' => 'paid', 'amount_total' => 4990, 'customer' => 'cus_test_1', 'subscription' => 'sub_test_1', 'metadata' => ['tenant_id' => '1', 'tenant_plan' => 'basico']]],
        ];

        $payload = json_encode($event);
        $result = $service->handle($payload, $this->buildSignedHeader($payload, 'whsec_test'), 'whsec_test');

        $this->assertFalse($result['success']);
        $this->assertTrue(!empty($result['client_error']));
    }

    public function test_invoice_without_invoice_id_is_rejected(): void
    {
        [$service, $eventRepo] = $this->createStripeWebhookService();

        $event = [
            'id' => 'evt_inv_missing_id',
            'type' => 'invoice.paid',
            'data' => ['object' => ['amount_paid' => 4990, 'customer' => 'cus_test_1', 'subscription' => 'sub_test_1', 'metadata' => ['tenant_id' => '1', 'tenant_plan' => 'basico']]],
        ];

        $payload = json_encode($event);
        $result = $service->handle($payload, $this->buildSignedHeader($payload, 'whsec_test'), 'whsec_test');

        $this->assertFalse($result['success']);
        $this->assertTrue(!empty($result['client_error']));
    }

    public function test_invoice_metadata_direct_valid_does_not_use_customer_fallback(): void
    {
        $validator = new StripeWebhookValidator();
        $apiClient = $this->createMock(StripeApiClient::class);
        $apiClient->expects($this->once())->method('fetchSubscription')->with('sub_direct')->willReturn([
            'id' => 'sub_direct',
            'metadata' => ['tenant_id' => '42', 'tenant_plan' => 'premium'],
            'current_period_end' => time() + 30 * 86400,
        ]);

        $eventRepo = $this->createMock(StripeEventRepository::class);
        $eventRepo->method('findByEventId')->willReturn(null);
        $eventRepo->method('createPendingEvent')->willReturn(1);
        $eventRepo->method('markProcessed')->willReturn(true);
        $eventRepo->method('markFailed')->willReturn(true);
        $eventRepo->method('incrementRetryCount')->willReturn(true);
        $eventRepo->method('findByStripeEntity')->willReturn([]);

        $tenantRepo = $this->createMock(TenantRepository::class);
        $tenantRepo->method('getTenantById')->willReturn(['id' => 42, 'plano' => 'premium']);
        $tenantRepo->expects($this->never())->method('findByStripeCustomerId');
        $tenantRepo->method('updateStripeIdentifiers')->willReturn(true);
        $tenantRepo->method('updateBillingFields')->willReturn(true);
        $tenantRepo->method('updateUserLimitFromPlan')->willReturn(true);

        $billingService = new TenantBillingService(
            new PaymentRepository(new BillingHistoryModel($this->pdo)),
            $tenantRepo,
            null,
            $this->pdo
        );

        $notificationService = $this->createMock(BillingNotificationService::class);
        $service = new StripeWebhookService($validator, $apiClient, $eventRepo, $billingService, $notificationService);

        $event = [
            'id' => 'evt_inv_direct_meta',
            'type' => 'invoice.paid',
            'data' => ['object' => [
                'id' => 'in_direct_meta',
                'amount_paid' => 4990,
                'customer' => 'cus_direct',
                'subscription' => 'sub_direct',
                'metadata' => ['tenant_id' => '42', 'tenant_plan' => 'premium'],
                'lines' => ['data' => [['period' => ['end' => time() + 30 * 86400]]]],
            ]],
        ];

        $payload = json_encode($event);
        $result = $service->handle($payload, $this->buildSignedHeader($payload, 'whsec_test'), 'whsec_test');

        $this->assertTrue($result['success']);
        $this->assertSame('subscription_metadata', $result['strategy'] ?? null);
        $this->assertSame(1, $this->countPayments());
    }

    public function test_invoice_parent_subscription_details_metadata_resolves_tenant(): void
    {
        $validator = new StripeWebhookValidator();
        $apiClient = $this->createMock(StripeApiClient::class);
        $apiClient->method('fetchSubscription')->willReturn([
            'id' => 'sub_parent_meta',
            'metadata' => ['tenant_id' => '21', 'tenant_plan' => 'pro'],
            'current_period_end' => time() + 30 * 86400,
        ]);

        $eventRepo = $this->createMock(StripeEventRepository::class);
        $eventRepo->method('findByEventId')->willReturn(null);
        $eventRepo->method('createPendingEvent')->willReturn(1);
        $eventRepo->method('markProcessed')->willReturn(true);
        $eventRepo->method('markFailed')->willReturn(true);
        $eventRepo->method('incrementRetryCount')->willReturn(true);
        $eventRepo->method('findByStripeEntity')->willReturn([]);

        $tenantRepo = $this->createMock(TenantRepository::class);
        $tenantRepo->method('getTenantById')->willReturn(['id' => 21, 'plano' => 'pro']);
        $tenantRepo->expects($this->never())->method('findByStripeCustomerId');
        $tenantRepo->method('updateStripeIdentifiers')->willReturn(true);
        $tenantRepo->method('updateBillingFields')->willReturn(true);
        $tenantRepo->method('updateUserLimitFromPlan')->willReturn(true);

        $billingService = new TenantBillingService(
            new PaymentRepository(new BillingHistoryModel($this->pdo)),
            $tenantRepo,
            null,
            $this->pdo
        );

        $notificationService = $this->createMock(BillingNotificationService::class);
        $service = new StripeWebhookService($validator, $apiClient, $eventRepo, $billingService, $notificationService);

        $event = [
            'id' => 'evt_parent_subscription_details',
            'type' => 'invoice.paid',
            'data' => ['object' => [
                'id' => 'in_parent_meta',
                'amount_paid' => 4990,
                'customer' => 'cus_parent_meta',
                'subscription' => 'sub_parent_meta',
                'metadata' => [],
                'parent' => ['subscription_details' => ['metadata' => ['tenant_id' => '21', 'tenant_plan' => 'pro']]],
                'lines' => ['data' => [['period' => ['end' => time() + 30 * 86400]]]],
            ]],
        ];

        $payload = json_encode($event);
        $result = $service->handle($payload, $this->buildSignedHeader($payload, 'whsec_test'), 'whsec_test');

        $this->assertTrue($result['success']);
        $this->assertSame('subscription_metadata', $result['strategy'] ?? null);
        $this->assertSame(1, $this->countPayments());
    }

    public function test_invoice_subscription_metadata_takes_precedence_over_stale_invoice_metadata(): void
    {
        $validator = new StripeWebhookValidator();
        $apiClient = $this->createMock(StripeApiClient::class);
        $apiClient->method('fetchSubscription')->willReturn([
            'id' => 'sub_valid_priority',
            'metadata' => ['tenant_id' => '7', 'tenant_plan' => 'basico'],
            'current_period_end' => time() + 30 * 86400,
        ]);

        $eventRepo = $this->createMock(StripeEventRepository::class);
        $eventRepo->method('findByEventId')->willReturn(null);
        $eventRepo->method('createPendingEvent')->willReturn(1);
        $eventRepo->method('markProcessed')->willReturn(true);
        $eventRepo->method('markFailed')->willReturn(true);
        $eventRepo->method('incrementRetryCount')->willReturn(true);
        $eventRepo->method('findByStripeEntity')->willReturn([]);

        $tenantRepo = $this->createMock(TenantRepository::class);
        $tenantRepo->method('getTenantById')->willReturnMap([
            [7, ['id' => 7, 'plano' => 'basico']],
        ]);
        $tenantRepo->expects($this->never())->method('findByStripeCustomerId');
        $tenantRepo->method('updateStripeIdentifiers')->willReturn(true);
        $tenantRepo->method('updateBillingFields')->willReturn(true);
        $tenantRepo->method('updateUserLimitFromPlan')->willReturn(true);

        $billingService = new TenantBillingService(
            new PaymentRepository(new BillingHistoryModel($this->pdo)),
            $tenantRepo,
            null,
            $this->pdo
        );

        $notificationService = $this->createMock(BillingNotificationService::class);
        $service = new StripeWebhookService($validator, $apiClient, $eventRepo, $billingService, $notificationService);

        $event = [
            'id' => 'evt_subscription_priority',
            'type' => 'invoice.paid',
            'data' => ['object' => [
                'id' => 'in_subscription_priority',
                'amount_paid' => 17990,
                'customer' => 'cus_prioritized',
                'subscription' => 'sub_valid_priority',
                'metadata' => ['tenant_id' => '999', 'tenant_plan' => 'wrong_plan'],
                'lines' => ['data' => [['period' => ['end' => time() + 30 * 86400]]]],
            ]],
        ];

        $payload = json_encode($event);
        $result = $service->handle($payload, $this->buildSignedHeader($payload, 'whsec_test'), 'whsec_test');

        $this->assertTrue($result['success']);
        $this->assertSame(7, (int)($result['tenant_id'] ?? 0));
        $this->assertSame('basico', $result['tenant_plan'] ?? null);
        $this->assertSame(1, $this->countPayments());
    }

    public function test_invoice_subscription_metadata_without_plan_uses_local_tenant_plan_when_safe(): void
    {
        $validator = new StripeWebhookValidator();
        $apiClient = $this->createMock(StripeApiClient::class);
        $apiClient->method('fetchSubscription')->willReturn([
            'id' => 'sub_missing_plan',
            'metadata' => ['tenant_id' => '11'],
            'current_period_end' => time() + 30 * 86400,
        ]);

        $eventRepo = $this->createMock(StripeEventRepository::class);
        $eventRepo->method('findByEventId')->willReturn(null);
        $eventRepo->method('createPendingEvent')->willReturn(1);
        $eventRepo->method('markProcessed')->willReturn(true);
        $eventRepo->method('markFailed')->willReturn(true);
        $eventRepo->method('incrementRetryCount')->willReturn(true);
        $eventRepo->method('findByStripeEntity')->willReturn([]);

        $tenantRepo = $this->createMock(TenantRepository::class);
        $tenantRepo->method('getTenantById')->with(11)->willReturn(['id' => 11, 'plano' => 'basico']);
        $tenantRepo->expects($this->never())->method('findByStripeCustomerId');
        $tenantRepo->method('updateStripeIdentifiers')->willReturn(true);
        $tenantRepo->method('updateBillingFields')->willReturn(true);
        $tenantRepo->method('updateUserLimitFromPlan')->willReturn(true);

        $billingService = new TenantBillingService(
            new PaymentRepository(new BillingHistoryModel($this->pdo)),
            $tenantRepo,
            null,
            $this->pdo
        );

        $notificationService = $this->createMock(BillingNotificationService::class);
        $service = new StripeWebhookService($validator, $apiClient, $eventRepo, $billingService, $notificationService);

        $event = [
            'id' => 'evt_subscription_missing_plan',
            'type' => 'invoice.paid',
            'data' => ['object' => [
                'id' => 'in_subscription_missing_plan',
                'amount_paid' => 17990,
                'customer' => 'cus_missing_plan',
                'subscription' => 'sub_missing_plan',
                'metadata' => [],
                'lines' => ['data' => [['period' => ['end' => time() + 30 * 86400]]]],
            ]],
        ];

        $payload = json_encode($event);
        $result = $service->handle($payload, $this->buildSignedHeader($payload, 'whsec_test'), 'whsec_test');

        $this->assertTrue($result['success']);
        $this->assertSame(11, (int)($result['tenant_id'] ?? 0));
        $this->assertSame('basico', $result['tenant_plan'] ?? null);
        $this->assertSame(1, $this->countPayments());
    }

    public function test_invoice_line_items_with_single_unique_tenant_id_resolves_correctly(): void
    {
        $validator = new StripeWebhookValidator();
        $apiClient = $this->createMock(StripeApiClient::class);
        $apiClient->method('fetchSubscription')->willThrowException(new \RuntimeException('Stripe API timeout'));

        $eventRepo = $this->createMock(StripeEventRepository::class);
        $eventRepo->method('findByEventId')->willReturn(null);
        $eventRepo->method('createPendingEvent')->willReturn(1);
        $eventRepo->method('markProcessed')->willReturn(true);
        $eventRepo->method('markFailed')->willReturn(true);
        $eventRepo->method('incrementRetryCount')->willReturn(true);
        $eventRepo->method('findByStripeEntity')->willReturn([]);

        $tenantRepo = $this->createMock(TenantRepository::class);
        $tenantRepo->method('getTenantById')->willReturn(['id' => 33, 'plano' => 'premium']);
        $tenantRepo->expects($this->never())->method('findByStripeCustomerId');
        $tenantRepo->method('updateStripeIdentifiers')->willReturn(true);
        $tenantRepo->method('updateBillingFields')->willReturn(true);
        $tenantRepo->method('updateUserLimitFromPlan')->willReturn(true);

        $billingService = new TenantBillingService(
            new PaymentRepository(new BillingHistoryModel($this->pdo)),
            $tenantRepo,
            null,
            $this->pdo
        );

        $notificationService = $this->createMock(BillingNotificationService::class);
        $service = new StripeWebhookService($validator, $apiClient, $eventRepo, $billingService, $notificationService);

        $event = [
            'id' => 'evt_inv_line_single',
            'type' => 'invoice.paid',
            'data' => ['object' => [
                'id' => 'in_line_single',
                'amount_paid' => 4990,
                'customer' => 'cus_line_single',
                'subscription' => 'sub_line_single',
                'metadata' => [],
                'lines' => ['data' => [
                    ['metadata' => ['tenant_id' => '33', 'tenant_plan' => 'premium'], 'period' => ['end' => time() + 30 * 86400]],
                ]],
            ]],
        ];

        $payload = json_encode($event);
        $result = $service->handle($payload, $this->buildSignedHeader($payload, 'whsec_test'), 'whsec_test');

        $this->assertTrue($result['success']);
        $this->assertSame(1, $this->countPayments());
    }

    public function test_invoice_line_items_with_same_tenant_id_across_multiple_lines_resolves_correctly(): void
    {
        $validator = new StripeWebhookValidator();
        $apiClient = $this->createMock(StripeApiClient::class);
        $apiClient->method('fetchSubscription')->willThrowException(new \RuntimeException('Stripe API timeout'));

        $eventRepo = $this->createMock(StripeEventRepository::class);
        $eventRepo->method('findByEventId')->willReturn(null);
        $eventRepo->method('createPendingEvent')->willReturn(1);
        $eventRepo->method('markProcessed')->willReturn(true);
        $eventRepo->method('markFailed')->willReturn(true);
        $eventRepo->method('incrementRetryCount')->willReturn(true);
        $eventRepo->method('findByStripeEntity')->willReturn([]);

        $tenantRepo = $this->createMock(TenantRepository::class);
        $tenantRepo->method('getTenantById')->willReturn(['id' => 44, 'plano' => 'plus']);
        $tenantRepo->expects($this->never())->method('findByStripeCustomerId');
        $tenantRepo->method('updateStripeIdentifiers')->willReturn(true);
        $tenantRepo->method('updateBillingFields')->willReturn(true);
        $tenantRepo->method('updateUserLimitFromPlan')->willReturn(true);

        $billingService = new TenantBillingService(
            new PaymentRepository(new BillingHistoryModel($this->pdo)),
            $tenantRepo,
            null,
            $this->pdo
        );

        $notificationService = $this->createMock(BillingNotificationService::class);
        $service = new StripeWebhookService($validator, $apiClient, $eventRepo, $billingService, $notificationService);

        $event = [
            'id' => 'evt_inv_line_same_tenant',
            'type' => 'invoice.paid',
            'data' => ['object' => [
                'id' => 'in_line_same_tenant',
                'amount_paid' => 4990,
                'customer' => 'cus_line_same_tenant',
                'subscription' => 'sub_line_same_tenant',
                'metadata' => [],
                'lines' => ['data' => [
                    ['metadata' => ['tenant_id' => '44', 'tenant_plan' => 'plus'], 'period' => ['end' => time() + 30 * 86400]],
                    ['metadata' => ['tenant_id' => '44', 'tenant_plan' => 'plus'], 'period' => ['end' => time() + 30 * 86400]],
                ]],
            ]],
        ];

        $payload = json_encode($event);
        $result = $service->handle($payload, $this->buildSignedHeader($payload, 'whsec_test'), 'whsec_test');

        $this->assertTrue($result['success']);
        $this->assertSame(1, $this->countPayments());
    }

    public function test_invoice_line_items_with_different_tenant_ids_fail_ambiguous(): void
    {
        $validator = new StripeWebhookValidator();
        $apiClient = $this->createMock(StripeApiClient::class);
        $apiClient->method('fetchSubscription')->willThrowException(new \RuntimeException('Stripe API timeout'));

        $eventRepo = $this->createMock(StripeEventRepository::class);
        $eventRepo->method('findByEventId')->willReturn(null);
        $eventRepo->method('createPendingEvent')->willReturn(1);
        $eventRepo->method('markProcessed')->willReturn(true);
        $eventRepo->method('markFailed')->willReturn(true);
        $eventRepo->method('incrementRetryCount')->willReturn(true);
        $eventRepo->method('findByStripeEntity')->willReturn([]);

        $tenantRepo = $this->createMock(TenantRepository::class);
        $tenantRepo->method('getTenantById')->willReturn(['id' => 55, 'plano' => 'enterprise']);
        $tenantRepo->method('findByStripeCustomerId')->willReturn([]);
        $tenantRepo->method('updateStripeIdentifiers')->willReturn(true);
        $tenantRepo->method('updateBillingFields')->willReturn(true);
        $tenantRepo->method('updateUserLimitFromPlan')->willReturn(true);

        $billingService = new TenantBillingService(
            new PaymentRepository(new BillingHistoryModel($this->pdo)),
            $tenantRepo,
            null,
            $this->pdo
        );

        $notificationService = $this->createMock(BillingNotificationService::class);
        $service = new StripeWebhookService($validator, $apiClient, $eventRepo, $billingService, $notificationService);

        $event = [
            'id' => 'evt_inv_line_ambiguous',
            'type' => 'invoice.paid',
            'data' => ['object' => [
                'id' => 'in_line_ambiguous',
                'amount_paid' => 4990,
                'customer' => 'cus_line_ambiguous',
                'subscription' => 'sub_line_ambiguous',
                'metadata' => [],
                'lines' => ['data' => [
                    ['metadata' => ['tenant_id' => '55', 'tenant_plan' => 'enterprise'], 'period' => ['end' => time() + 30 * 86400]],
                    ['metadata' => ['tenant_id' => '66', 'tenant_plan' => 'basico'], 'period' => ['end' => time() + 30 * 86400]],
                ]],
            ]],
        ];

        $payload = json_encode($event);
        $result = $service->handle($payload, $this->buildSignedHeader($payload, 'whsec_test'), 'whsec_test');

        $this->assertFalse($result['success']);
        $this->assertSame('LINE_ITEMS_AMBIGUOUS', $result['failure_reason'] ?? null);
        $this->assertSame(0, $this->countPayments());
    }

    public function test_invoice_metadata_with_nonexistent_tenant_is_treated_as_not_yet_materialized(): void
    {
        $validator = new StripeWebhookValidator();
        $apiClient = $this->createMock(StripeApiClient::class);
        $apiClient->expects($this->once())->method('fetchSubscription')->with('sub_missing_tenant')->willThrowException(new \RuntimeException('Stripe API timeout'));

        $eventRepo = $this->createMock(StripeEventRepository::class);
        $eventRepo->method('findByEventId')->willReturn(null);
        $eventRepo->method('createPendingEvent')->willReturn(1);
        $eventRepo->method('markProcessed')->willReturn(true);
        $eventRepo->method('markFailed')->willReturn(true);
        $eventRepo->method('incrementRetryCount')->willReturn(true);
        $eventRepo->method('findByStripeEntity')->willReturn([]);

        $tenantRepo = $this->createMock(TenantRepository::class);
        $tenantRepo->method('getTenantById')->with(999)->willReturn(null);
        $tenantRepo->expects($this->never())->method('findByStripeCustomerId');
        $tenantRepo->method('updateStripeIdentifiers')->willReturn(true);
        $tenantRepo->method('updateBillingFields')->willReturn(true);
        $tenantRepo->method('updateUserLimitFromPlan')->willReturn(true);

        $billingService = new TenantBillingService(
            new PaymentRepository(new BillingHistoryModel($this->pdo)),
            $tenantRepo,
            null,
            $this->pdo
        );

        $notificationService = $this->createMock(BillingNotificationService::class);
        $service = new StripeWebhookService($validator, $apiClient, $eventRepo, $billingService, $notificationService);

        $event = [
            'id' => 'evt_inv_missing_tenant',
            'type' => 'invoice.paid',
            'data' => ['object' => [
                'id' => 'in_missing_tenant',
                'amount_paid' => 4990,
                'customer' => 'cus_missing_tenant',
                'subscription' => 'sub_missing_tenant',
                'metadata' => ['tenant_id' => '999', 'tenant_plan' => 'basico'],
                'lines' => ['data' => [['period' => ['end' => time() + 30 * 86400]]]],
            ]],
        ];

        $payload = json_encode($event);
        $result = $service->handle($payload, $this->buildSignedHeader($payload, 'whsec_test'), 'whsec_test');

        $this->assertFalse($result['success']);
        $this->assertSame('TENANT_NOT_YET_MATERIALIZED', $result['failure_reason'] ?? null);
        $this->assertTrue(!empty($result['retryable']));
        $this->assertSame(0, $this->countPayments());
    }

    public function test_invoice_without_metadata_uses_customer_fallback_when_one_tenant_matches(): void
    {
        $validator = new StripeWebhookValidator();
        $apiClient = $this->createMock(StripeApiClient::class);
        $apiClient->method('fetchSubscription')->willThrowException(new \RuntimeException('Stripe API timeout'));

        $eventRepo = $this->createMock(StripeEventRepository::class);
        $eventRepo->method('findByEventId')->willReturn(null);
        $eventRepo->method('createPendingEvent')->willReturn(1);
        $eventRepo->method('markProcessed')->willReturn(true);
        $eventRepo->method('markFailed')->willReturn(true);
        $eventRepo->method('incrementRetryCount')->willReturn(true);
        $eventRepo->method('findByStripeEntity')->willReturn([]);

        $tenantRepo = $this->createMock(TenantRepository::class);
        $tenantRepo->method('getTenantById')->willReturn(['id' => 7, 'plano' => 'basico']);
        $tenantRepo->method('findByStripeCustomerId')->willReturn([
            ['id' => 7, 'plano' => 'basico'],
        ]);
        $tenantRepo->method('updateStripeIdentifiers')->willReturn(true);
        $tenantRepo->method('updateBillingFields')->willReturn(true);
        $tenantRepo->method('updateUserLimitFromPlan')->willReturn(true);

        $billingService = new TenantBillingService(
            new PaymentRepository(new BillingHistoryModel($this->pdo)),
            $tenantRepo,
            null,
            $this->pdo
        );

        $notificationService = $this->createMock(BillingNotificationService::class);
        $service = new StripeWebhookService($validator, $apiClient, $eventRepo, $billingService, $notificationService);

        $event = [
            'id' => 'evt_inv_customer_fallback',
            'type' => 'invoice.paid',
            'data' => ['object' => [
                'id' => 'in_customer_fallback',
                'amount_paid' => 4990,
                'customer' => 'cus_customer_match',
                'subscription' => 'sub_missing_meta',
                'metadata' => [],
                'lines' => ['data' => [['period' => ['end' => time() + 30 * 86400]]]],
            ]],
        ];

        $payload = json_encode($event);
        $result = $service->handle($payload, $this->buildSignedHeader($payload, 'whsec_test'), 'whsec_test');

        $this->assertTrue($result['success']);
        $this->assertSame(1, $this->countPayments());
    }

    public function test_invoice_without_metadata_and_unknown_customer_fails_explicitly(): void
    {
        $validator = new StripeWebhookValidator();
        $apiClient = $this->createMock(StripeApiClient::class);
        $apiClient->method('fetchSubscription')->willThrowException(new \RuntimeException('Stripe API timeout'));

        $eventRepo = $this->createMock(StripeEventRepository::class);
        $eventRepo->method('findByEventId')->willReturn(null);
        $eventRepo->method('createPendingEvent')->willReturn(1);
        $eventRepo->method('markProcessed')->willReturn(true);
        $eventRepo->method('markFailed')->willReturn(true);
        $eventRepo->method('incrementRetryCount')->willReturn(true);
        $eventRepo->method('findByStripeEntity')->willReturn([]);

        $tenantRepo = $this->createMock(TenantRepository::class);
        $tenantRepo->method('getTenantById')->willReturn(null);
        $tenantRepo->method('findByStripeCustomerId')->willReturn([]);
        $tenantRepo->method('updateBillingFields')->willReturn(true);
        $tenantRepo->method('updateUserLimitFromPlan')->willReturn(true);

        $billingService = new TenantBillingService(
            new PaymentRepository(new BillingHistoryModel($this->pdo)),
            $tenantRepo,
            null,
            $this->pdo
        );

        $notificationService = $this->createMock(BillingNotificationService::class);
        $service = new StripeWebhookService($validator, $apiClient, $eventRepo, $billingService, $notificationService);

        $event = [
            'id' => 'evt_inv_customer_missing',
            'type' => 'invoice.paid',
            'data' => ['object' => [
                'id' => 'in_customer_missing',
                'amount_paid' => 4990,
                'customer' => 'cus_unknown',
                'subscription' => 'sub_missing_meta',
                'metadata' => [],
                'lines' => ['data' => [['period' => ['end' => time() + 30 * 86400]]]],
            ]],
        ];

        $payload = json_encode($event);
        $result = $service->handle($payload, $this->buildSignedHeader($payload, 'whsec_test'), 'whsec_test');

        // Customer unknown — with our new policy treat as transient (customer not yet mapped)
        $this->assertFalse($result['success']);
        $this->assertTrue(!empty($result['retryable']));
        $this->assertSame(0, $this->countPayments());
    }

    public function test_invoice_with_ambiguous_customer_fails_without_activating_tenant(): void
    {
        $validator = new StripeWebhookValidator();
        $apiClient = $this->createMock(StripeApiClient::class);
        $apiClient->method('fetchSubscription')->willThrowException(new \RuntimeException('Stripe API timeout'));

        $eventRepo = $this->createMock(StripeEventRepository::class);
        $eventRepo->method('findByEventId')->willReturn(null);
        $eventRepo->method('createPendingEvent')->willReturn(1);
        $eventRepo->method('markProcessed')->willReturn(true);
        $eventRepo->method('markFailed')->willReturn(true);
        $eventRepo->method('incrementRetryCount')->willReturn(true);
        $eventRepo->method('findByStripeEntity')->willReturn([]);

        $tenantRepo = $this->createMock(TenantRepository::class);
        $tenantRepo->method('getTenantById')->willReturn(null);
        $tenantRepo->method('findByStripeCustomerId')->willReturn([
            ['id' => 8, 'plano' => 'basico'],
            ['id' => 9, 'plano' => 'premium'],
        ]);
        $tenantRepo->method('updateBillingFields')->willReturn(true);
        $tenantRepo->method('updateUserLimitFromPlan')->willReturn(true);

        $billingService = new TenantBillingService(
            new PaymentRepository(new BillingHistoryModel($this->pdo)),
            $tenantRepo,
            null,
            $this->pdo
        );

        $notificationService = $this->createMock(BillingNotificationService::class);
        $service = new StripeWebhookService($validator, $apiClient, $eventRepo, $billingService, $notificationService);

        $event = [
            'id' => 'evt_inv_customer_ambiguous',
            'type' => 'invoice.paid',
            'data' => ['object' => [
                'id' => 'in_customer_ambiguous',
                'amount_paid' => 4990,
                'customer' => 'cus_ambiguous',
                'subscription' => 'sub_missing_meta',
                'metadata' => [],
                'lines' => ['data' => [['period' => ['end' => time() + 30 * 86400]]]],
            ]],
        ];

        $payload = json_encode($event);
        $result = $service->handle($payload, $this->buildSignedHeader($payload, 'whsec_test'), 'whsec_test');

        // When Stripe subscription fetch times out, treat as transient (retryable) even if customers could be ambiguous
        $this->assertFalse($result['success']);
        $this->assertTrue(!empty($result['retryable']));
        $this->assertSame(0, $this->countPayments());
    }

    public function test_checkout_session_completed_syncs_user_limit_and_handles_failure(): void
    {
        // Build a custom tenantRepo mock that expects updateUserLimitFromPlan
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
            if (!isset($events[$eventId])) return false;
            $events[$eventId]['status'] = 'processed';
            return true;
        });
        $eventRepo->method('markFailed')->willReturnCallback(function ($eventId, $lastError = null) use (&$events) {
            if (!isset($events[$eventId])) return false;
            $events[$eventId]['status'] = 'failed';
            $events[$eventId]['error_message'] = $lastError;
            return true;
        });
        $eventRepo->method('incrementRetryCount')->willReturn(true);
        $eventRepo->method('findByStripeEntity')->willReturn([]);

        $tenantRepo = $this->createMock(TenantRepository::class);
        $tenantRepo->method('getTenantById')->willReturn(['id' => 1, 'plano' => 'basico']);
        $tenantRepo->method('updateStripeIdentifiers')->willReturn(true);
        $tenantRepo->method('updateBillingFields')->willReturn(true);
        $tenantRepo->method('updateUserLimitFromPlan')->willReturn(true);
        $tenantRepo->expects($this->once())->method('updateUserLimitFromPlan')->with(1)->willReturn(true);

        $billingService = new TenantBillingService(new PaymentRepository(new BillingHistoryModel($this->pdo)), $tenantRepo, null, $this->pdo);
        $notificationService = $this->createMock(BillingNotificationService::class);
        $notificationService->method('sendFailedPaymentNotification')->willReturn(['success' => true]);

        $service = new StripeWebhookService($validator, $apiClient, $eventRepo, $billingService, $notificationService);

        $event = $this->makeCheckoutSessionEvent('evt_chk_limit', 'cs_limit_1');
        $payload = json_encode($event);
        $header = $this->buildSignedHeader($payload, 'whsec_test');

        $res = $service->handle($payload, $header, 'whsec_test');
        $this->assertTrue($res['success']);

        // Now simulate failure of updateUserLimitFromPlan -> should be retryable
        $tenantRepoFail = $this->createMock(TenantRepository::class);
        $tenantRepoFail->method('getTenantById')->willReturn(['id' => 1, 'plano' => 'basico']);
        $tenantRepoFail->method('updateStripeIdentifiers')->willReturn(true);
        $tenantRepoFail->method('updateBillingFields')->willReturn(true);
        $tenantRepoFail->method('updateUserLimitFromPlan')->willReturn(false);
        $tenantRepoFail->expects($this->once())->method('updateUserLimitFromPlan')->with(1)->willReturn(false);

        $billingServiceFail = new TenantBillingService(new PaymentRepository(new BillingHistoryModel($this->pdo)), $tenantRepoFail, null, $this->pdo);

        // Use a fresh eventRepo for the failing scenario so the event is not already marked processed
        $eventRepoFail = $this->createMock(StripeEventRepository::class);
        $eventRepoFail->method('findByEventId')->willReturn(null);
        $eventRepoFail->method('createPendingEvent')->willReturn(1);
        $eventRepoFail->method('markProcessed')->willReturn(true);
        $eventRepoFail->method('markFailed')->willReturn(true);
        $eventRepoFail->method('incrementRetryCount')->willReturn(true);
        $eventRepoFail->method('findByStripeEntity')->willReturn([]);

        $serviceFail = new StripeWebhookService($validator, $apiClient, $eventRepoFail, $billingServiceFail, $notificationService);

        $resFail = $serviceFail->handle($payload, $header, 'whsec_test');
        $this->assertFalse($resFail['success']);
        $this->assertTrue(!empty($resFail['retryable']));
    }
}
