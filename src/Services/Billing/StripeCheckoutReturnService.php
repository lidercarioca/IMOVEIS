<?php

namespace App\Services\Billing;

use App\Infrastructure\Billing\StripeApiClient;
use RuntimeException;

class StripeCheckoutReturnService
{
    private StripeApiClient $apiClient;
    private StripeSessionMetadataService $metadataService;

    public function __construct(StripeApiClient $apiClient, StripeSessionMetadataService $metadataService)
    {
        $this->apiClient = $apiClient;
        $this->metadataService = $metadataService;
    }

    /**
     * Recupera sessão e metadata; retorna array com dados sem saída HTML
     * @param string $sessionId
     * @return array
     */
    public function handleReturn(string $sessionId): array
    {
        $session = $this->apiClient->fetchCheckoutSession($sessionId);
        if (empty($session) || !is_array($session)) {
            throw new RuntimeException('Checkout session not found');
        }

        $metadata = $this->metadataService->resolveMetadataFromSession($session);

        return [
            'session' => $session,
            'metadata' => $metadata
        ];
    }
}
