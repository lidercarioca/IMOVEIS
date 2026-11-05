<?php

namespace Tests\Unit;

use App\Infrastructure\Billing\StripeApiClient;
use App\Services\Billing\StripeCheckoutReturnService;
use App\Services\Billing\StripeSessionMetadataService;
use PHPUnit\Framework\TestCase;

class StripeCheckoutReturnServiceTest extends TestCase
{
    public function test_handles_paid_session_and_extracts_tenant_from_metadata_without_active_php_session(): void
    {
        $apiClient = $this->createMock(StripeApiClient::class);
        $sessionId = 'cs_test_session_323';

        $apiClient->method('fetchCheckoutSession')->with($sessionId)->willReturn([
            'id' => $sessionId,
            'status' => 'complete',
            'payment_status' => 'paid',
            'customer' => 'cus_test_323',
            'subscription' => 'sub_test_323',
            'metadata' => [
                'tenant_id' => '323',
                'tenant_plan' => 'basico',
                'user_id' => '163',
            ],
        ]);

        $service = new StripeCheckoutReturnService($apiClient, new StripeSessionMetadataService($apiClient));

        $result = $service->handleReturn($sessionId);

        $this->assertSame('paid', $result['payment_status']);
        $this->assertSame(323, $result['tenant_id']);
        $this->assertSame('basico', $result['tenant_plan']);
        $this->assertSame('cus_test_323', $result['customer_id']);
        $this->assertSame('sub_test_323', $result['subscription_id']);
        $this->assertFalse($result['activates_tenant']);
    }

    public function test_throws_when_session_is_invalid_or_missing(): void
    {
        $this->expectException(\RuntimeException::class);

        $apiClient = $this->createMock(StripeApiClient::class);
        $apiClient->method('fetchCheckoutSession')->with('cs_invalid')->willReturn([]);

        $service = new StripeCheckoutReturnService($apiClient, new StripeSessionMetadataService($apiClient));
        $service->handleReturn('cs_invalid');
    }
}
