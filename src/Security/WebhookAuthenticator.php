<?php

namespace App\Security;

/**
 * WebhookAuthenticator
 * 
 * Valida webhooks recebidos usando HMAC-SHA256.
 * Protege contra:
 * - Assinaturas inválidas
 * - Timestamps expirados
 * - Timing attacks
 */
class WebhookAuthenticator
{
    // Máximo de 300 segundos (5 minutos) de desvio de timestamp
    private const MAX_TIMESTAMP_DEVIATION = 300;

    /**
     * Validar webhook com HMAC-SHA256
     * 
     * @param string $payload Raw payload (não decodificado)
     * @param string $signature Header X-Webhook-Signature
     * @param string $timestamp Header X-Webhook-Timestamp
     * @param string $secret Webhook secret armazenado
     * @return bool True se válido, False caso contrário
     */
    public static function validate(
        string $payload,
        string $signature,
        string $timestamp,
        string $secret
    ): bool {
        // 1. Validar timestamp (previne replay attacks)
        if (!self::isValidTimestamp($timestamp)) {
            return false;
        }

        // 2. Validar assinatura (previne falsificação)
        $expectedSignature = self::generateSignature($payload, $timestamp, $secret);

        // hash_equals() previne timing attacks comparando em tempo constante
        if (!hash_equals($expectedSignature, $signature)) {
            return false;
        }

        return true;
    }

    /**
     * Gerar assinatura HMAC-SHA256
     * 
     * Formato: hash_hmac('sha256', timestamp.payload, secret)
     * 
     * @param string $payload Raw payload
     * @param string $timestamp Unix timestamp
     * @param string $secret Webhook secret
     * @return string Assinatura hexadecimal
     */
    public static function generateSignature(
        string $payload,
        string $timestamp,
        string $secret
    ): string {
        $message = $timestamp . '.' . $payload;
        return hash_hmac('sha256', $message, $secret);
    }

    /**
     * Validar se timestamp está dentro de tolerância
     * 
     * Rejeita:
     * - Timestamps não numéricos
     * - Timestamps muito antigos (replay)
     * - Timestamps no futuro
     * 
     * @param string $timestamp Unix timestamp em string
     * @return bool
     */
    private static function isValidTimestamp(string $timestamp): bool
    {
        // Timestamp deve ser numérico (Unix seconds)
        if (!ctype_digit($timestamp)) {
            return false;
        }

        $requestTime = (int)$timestamp;
        $currentTime = time();
        $deviation = abs($currentTime - $requestTime);

        // Rejeita se desvio maior que MAX_TIMESTAMP_DEVIATION
        return $deviation <= self::MAX_TIMESTAMP_DEVIATION;
    }
}
