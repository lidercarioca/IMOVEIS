<?php

namespace App\Services\Billing;

use App\Infrastructure\Billing\StripeApiClient;

class StripeBillingPortalService
{
    private StripeApiClient $apiClient;

    public function __construct(StripeApiClient $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    public function createPortalSession(string $customerId, string $returnUrl): array
    {
        return $this->apiClient->createBillingPortalSession($customerId, $returnUrl);
    }
}
