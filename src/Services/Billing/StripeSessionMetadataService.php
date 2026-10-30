<?php

namespace App\Services\Billing;

use App\Infrastructure\Billing\TenantRepository;
use App\Infrastructure\Billing\StripeApiClient;
use RuntimeException;

/**
 * Resolve metadata de sessão do Checkout e recupera subscription quando necessário.
 */
class StripeSessionMetadataService
{
    private StripeApiClient $apiClient;

    public function __construct(StripeApiClient $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    /**
     * Extrai metadata de uma sessão Stripe (checkout.session)
     * @param array $session
     * @return array metadata
     */
    public function resolveMetadataFromSession(array $session): array
    {
        if (!empty($session['metadata']) && is_array($session['metadata'])) {
            return $session['metadata'];
        }

        // Se metadata ausente, tentar recuperar subscription/invoice e extrair
        if (!empty($session['subscription'])) {
            $sub = $this->apiClient->fetchSubscription($session['subscription']);
            return $sub['metadata'] ?? [];
        }

        // fallback: tentar buscar sessão completa pelo ID se tiver id
        if (!empty($session['id'])) {
            $full = $this->apiClient->fetchCheckoutSession($session['id']);
            return $full['metadata'] ?? [];
        }

        return [];
    }

    /**
     * Restaurar sessão (usado pelo return flow) — busca session e retorna array
     * @param string $sessionId
     * @return array
     */
    public function restoreSession(string $sessionId): array
    {
        return $this->apiClient->fetchCheckoutSession($sessionId);
    }
}
