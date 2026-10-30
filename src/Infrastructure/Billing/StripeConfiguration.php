<?php

namespace App\Infrastructure\Billing;

/**
 * StripeConfiguration
 *
 * Centraliza leitura das configurações do Stripe a partir de config/payments.php
 * Não acessa banco, não contém lógica de negócio.
 */
class StripeConfiguration
{
    private array $payments = [];

    public function __construct(?array $payments = null)
    {
        if ($payments !== null) {
            $this->payments = $payments;
            return;
        }

        $configFile = __DIR__ . '/../../../config/payments.php';
        if (is_file($configFile)) {
            $data = require $configFile;
            if (is_array($data)) {
                $this->payments = $data;
            }
        }
    }

    private function stripeConfig(): array
    {
        return $this->payments['stripe'] ?? [];
    }

    public function getSecretKey(): string
    {
        return (string)($this->stripeConfig()['secret_key'] ?? '');
    }

    public function getPublishableKey(): string
    {
        return (string)($this->stripeConfig()['publishable_key'] ?? '');
    }

    public function getWebhookSecret(): string
    {
        return (string)($this->stripeConfig()['webhook_secret'] ?? '');
    }

    public function getCurrency(): string
    {
        return (string)($this->stripeConfig()['currency'] ?? 'brl');
    }

    public function getSiteUrl(): string
    {
        return (string)($this->stripeConfig()['site_url'] ?? '');
    }
}
