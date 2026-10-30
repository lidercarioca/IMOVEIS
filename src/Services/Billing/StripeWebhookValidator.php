<?php

namespace App\Services\Billing;

use InvalidArgumentException;

/**
 * Valida assinatura e payload de webhooks Stripe.
 * Não contém lógica de negócio nem SQL.
 */
class StripeWebhookValidator
{
    /**
     * Valida a assinatura Stripe conforme docs: header 'Stripe-Signature'
     * Implementa verificação HMAC SHA256 do timestamp.payload
     * @param string $payload
     * @param string $sigHeader
     * @param string $secret
     * @param int $tolerance seconds tolerance (default 300)
     * @return bool
     */
    public function validateSignature(string $payload, string $sigHeader, string $secret, int $tolerance = 300): bool
    {
        if ($secret === '') {
            throw new InvalidArgumentException('Webhook secret is empty');
        }

        // Parse header like: t=timestamp,v1=signature,v0=...
        $parts = array_map(fn($p) => explode('=', $p, 2), explode(',', $sigHeader));
        $map = [];
        foreach ($parts as $p) {
            if (count($p) === 2) {
                $map[$p[0]] = $p[1];
            }
        }

        if (!isset($map['t']) || !isset($map['v1'])) {
            return false;
        }

        $timestamp = (int)$map['t'];
        $signedPayload = $timestamp . '.' . $payload;
        $expectedSig = hash_hmac('sha256', $signedPayload, $secret);

        // Compare signatures in constant time
        $verified = hash_equals($expectedSig, $map['v1']);

        if (!$verified) {
            return false;
        }

        // Check timestamp tolerance
        if (abs(time() - $timestamp) > $tolerance) {
            return false;
        }

        return true;
    }

    /**
     * Decode JSON payload to array
     * @param string $payload
     * @return array
     */
    public function parsePayload(string $payload): array
    {
        $data = json_decode($payload, true);
        if (!is_array($data)) {
            throw new InvalidArgumentException('Invalid JSON payload');
        }
        return $data;
    }
}
