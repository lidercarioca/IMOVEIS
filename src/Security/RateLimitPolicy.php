<?php

namespace App\Security;

class RateLimitPolicy
{
    /**
     * Políticas de rate limit compatíveis com a API antiga do AdvancedRateLimiter.
     */
    private const POLICIES = [
        'password_reset' => ['limit' => 3, 'window' => 3600],
        'contact_form' => ['limit' => 10, 'window' => 3600],
        'api_call' => ['limit' => 100, 'window' => 60],
        'utm_tracking' => ['limit' => 100, 'window' => 60],
        'olx_pre_auth' => ['limit' => 20, 'window' => 60],
        'olx_tenant' => ['limit' => 100, 'window' => 300],
        'default' => ['limit' => 60, 'window' => 60],
    ];

    public static function getConfig(string $endpoint): array
    {
        $normalized = strtolower(trim($endpoint));

        return self::POLICIES[$normalized] ?? self::POLICIES['default'];
    }

    public static function all(): array
    {
        return self::POLICIES;
    }
}
