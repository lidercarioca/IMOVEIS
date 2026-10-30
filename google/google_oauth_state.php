<?php

function google_calendar_build_oauth_state(int $tenantId, int $userId, int $ttlSeconds = 600): array
{
    $now = time();
    $state = bin2hex(random_bytes(32));

    $payload = [
        'state' => $state,
        'tenant_id' => $tenantId,
        'user_id' => $userId,
        'issued_at' => $now,
        'expires_at' => $now + max(60, $ttlSeconds),
        'used' => false,
    ];

    $_SESSION['google_oauth_state'] = $state;
    $_SESSION['google_oauth_state_payload'] = $payload;
    $_SESSION['google_oauth_tenant_id'] = $tenantId;
    $_SESSION['google_oauth_user_id'] = $userId;

    return $payload;
}

function google_calendar_is_valid_oauth_state(string $state, int $tenantId, int $userId, int $ttlSeconds = 600): bool
{
    if ($state === '' || $tenantId <= 0 || $userId <= 0) {
        return false;
    }

    $storedState = $_SESSION['google_oauth_state'] ?? null;
    $payload = $_SESSION['google_oauth_state_payload'] ?? [];

    if (!is_array($payload) || !hash_equals((string) $storedState, (string) $state)) {
        return false;
    }

    if ((int) ($payload['tenant_id'] ?? 0) !== $tenantId) {
        return false;
    }

    if ((int) ($payload['user_id'] ?? 0) !== $userId) {
        return false;
    }

    if (!empty($payload['used'])) {
        return false;
    }

    $issuedAt = (int) ($payload['issued_at'] ?? 0);
    $expiresAt = (int) ($payload['expires_at'] ?? ($issuedAt + max(60, $ttlSeconds)));
    $now = time();

    if ($issuedAt <= 0 || $expiresAt <= $now) {
        return false;
    }

    return true;
}

function google_calendar_consume_oauth_state(): void
{
    $payload = $_SESSION['google_oauth_state_payload'] ?? [];
    if (is_array($payload)) {
        $payload['used'] = true;
        $_SESSION['google_oauth_state_payload'] = $payload;
    }

    unset($_SESSION['google_oauth_state'], $_SESSION['google_oauth_tenant_id'], $_SESSION['google_oauth_user_id']);
}
