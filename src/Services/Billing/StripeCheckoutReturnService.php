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

    public function handleReturn(string $sessionId): array
    {
        $session = $this->apiClient->fetchCheckoutSession($sessionId);
        if (empty($session) || !is_array($session)) {
            throw new RuntimeException('Checkout session not found');
        }

        $metadata = $this->metadataService->resolveMetadataFromSession($session);
        $tenantId = $this->extractTenantId($metadata, $session);
        $tenantPlan = $this->extractTenantPlan($metadata);

        return [
            'session' => $session,
            'metadata' => $metadata,
            'tenant_id' => $tenantId,
            'tenant_plan' => $tenantPlan,
            'customer_id' => $session['customer'] ?? $metadata['customer_id'] ?? null,
            'subscription_id' => $session['subscription'] ?? $metadata['subscription_id'] ?? null,
            'payment_status' => $session['payment_status'] ?? null,
            'status' => $session['status'] ?? null,
            'activates_tenant' => false,
        ];
    }

    public function extractTenantId(array $metadata, ?array $session = null): ?int
    {
        foreach (['tenant_id', 'tenantId', 'tenant', 'tenantid'] as $key) {
            if (isset($metadata[$key]) && is_numeric((string)$metadata[$key])) {
                return (int)$metadata[$key];
            }
        }

        if ($session !== null && isset($session['client_reference_id']) && is_numeric((string)$session['client_reference_id'])) {
            return (int)$session['client_reference_id'];
        }

        return null;
    }

    public function extractTenantPlan(array $metadata): ?string
    {
        foreach (['tenant_plan', 'plan', 'tenantPlan'] as $key) {
            if (isset($metadata[$key]) && is_scalar($metadata[$key])) {
                return (string)$metadata[$key];
            }
        }

        return null;
    }
}
