<?php

namespace Tests\Unit;

use App\Infrastructure\Billing\PlanRepository;
use App\Services\Billing\StripePriceSyncService;
use PHPUnit\Framework\TestCase;

class StripePriceSyncServiceContractTest extends TestCase
{
    public function testBillingSyncContractExists(): void
    {
        $this->assertTrue(class_exists(PlanRepository::class));
        $this->assertTrue(class_exists(StripePriceSyncService::class));
        $this->assertTrue(method_exists(StripePriceSyncService::class, 'syncStripePriceIds'));
    }

    public function testBillingEndpointUsesServiceAndNoInlineSql(): void
    {
        $endpoint = file_get_contents(__DIR__ . '/../../api/v1/billing/syncStripePrices.php');

        $this->assertStringContainsString('new \\App\\Services\\Billing\\StripePriceSyncService', $endpoint);
        $this->assertStringNotContainsString("SELECT code, name, stripe_price_id FROM plans", $endpoint);
        $this->assertStringNotContainsString("UPDATE plans SET stripe_price_id = ? WHERE code = ?", $endpoint);
    }
}
