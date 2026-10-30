<?php

namespace App\Services\Billing;

use App\Infrastructure\Billing\StripeApiClient;

class StripeCheckoutService
{
    private StripeApiClient $apiClient;

    public function __construct(StripeApiClient $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    /**
     * Cria um Checkout Session usando o StripeApiClient
     * @param array $payload
     * @return array
     */
    public function createCheckoutSession(array $payload): array
    {
        return $this->apiClient->createCheckoutSession($payload);
    }
}
