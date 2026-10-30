<?php

namespace App\Infrastructure\Billing;

use RuntimeException;

/**
 * StripeApiClient
 *
 * Encapsula chamadas HTTP para a API Stripe. Não contém lógica de tenant
 * nem persiste dados. Lança RuntimeException em caso de falha HTTP/parse.
 */
class StripeApiClient
{
    private StripeConfiguration $config;
    private int $timeout;

    public function __construct(StripeConfiguration $config, int $timeout = 10)
    {
        $this->config = $config;
        $this->timeout = $timeout;
    }

    private function request(string $method, string $url, array $payload = []): array
    {
        $secret = $this->config->getSecretKey();
        if (empty($secret)) {
            throw new RuntimeException('Stripe secret key not configured');
        }

        $ch = curl_init();
        if (strtoupper($method) === 'GET') {
            if (!empty($payload)) {
                $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($payload);
            }
        } else {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERPWD, $secret . ':');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('Stripe HTTP request failed: ' . $curlErr);
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new RuntimeException('Invalid JSON response from Stripe');
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $err = $data['error']['message'] ?? json_encode($data);
            throw new RuntimeException('Stripe API error: ' . $err);
        }

        return $data;
    }

    public function createCheckoutSession(array $payload): array
    {
        $url = 'https://api.stripe.com/v1/checkout/sessions';
        return $this->request('POST', $url, $payload);
    }

    public function createBillingPortalSession(string $customerId, string $returnUrl): array
    {
        $url = 'https://api.stripe.com/v1/billing_portal/sessions';
        $payload = ['customer' => $customerId, 'return_url' => $returnUrl];
        return $this->request('POST', $url, $payload);
    }

    public function fetchCheckoutSession(string $sessionId): array
    {
        $url = 'https://api.stripe.com/v1/checkout/sessions/' . urlencode($sessionId);
        return $this->request('GET', $url);
    }

    public function fetchSubscription(string $subscriptionId): array
    {
        $url = 'https://api.stripe.com/v1/subscriptions/' . urlencode($subscriptionId);
        return $this->request('GET', $url);
    }

    public function fetchPrices(): array
    {
        $endpoint = 'https://api.stripe.com/v1/prices';
        $params = [
            'limit' => 100,
            'active' => 'true',
            'expand[]' => 'data.product'
        ];

        $prices = [];
        $startingAfter = null;

        while (true) {
            $paramsPage = $params;
            if ($startingAfter) {
                $paramsPage['starting_after'] = $startingAfter;
            }

            $result = $this->request('GET', $endpoint, $paramsPage);
            if (empty($result['data']) || !is_array($result['data'])) {
                break;
            }

            foreach ($result['data'] as $price) {
                $prices[] = $price;
            }

            if (empty($result['has_more'])) {
                break;
            }

            $last = end($result['data']);
            $startingAfter = $last['id'] ?? null;
            if (!$startingAfter) {
                break;
            }
        }

        return $prices;
    }
}
