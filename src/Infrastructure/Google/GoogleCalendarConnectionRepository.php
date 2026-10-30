<?php

namespace App\Infrastructure\Google;

use PDO;

class GoogleCalendarConnectionRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function ensureTableExists(): void
    {
        $driver = strtolower((string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME));

        if ($driver === 'sqlite') {
            $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS `google_calendar_connections` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `tenant_id` INTEGER NOT NULL,
  `user_id` INTEGER NOT NULL,
  `provider` TEXT NOT NULL DEFAULT 'google',
  `provider_account_id` TEXT DEFAULT NULL,
  `google_user_email` TEXT DEFAULT NULL,
  `access_token` TEXT DEFAULT NULL,
  `refresh_token` TEXT DEFAULT NULL,
  `expires_in` INTEGER DEFAULT NULL,
  `scope` TEXT DEFAULT NULL,
  `scopes` TEXT DEFAULT NULL,
  `token_type` TEXT DEFAULT NULL,
  `token_payload` TEXT DEFAULT NULL,
  `expires_at` TEXT DEFAULT NULL,
  `calendar_id` TEXT NOT NULL DEFAULT 'primary',
  `status` TEXT NOT NULL DEFAULT 'active',
  `revoked_at` TEXT DEFAULT NULL,
  `created_at` TEXT DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TEXT DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (`tenant_id`, `user_id`),
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);
SQL;
        } else {
            $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS `google_calendar_connections` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `provider` VARCHAR(32) NOT NULL DEFAULT 'google',
  `provider_account_id` VARCHAR(255) DEFAULT NULL,
  `google_user_email` VARCHAR(255) DEFAULT NULL,
  `access_token` TEXT DEFAULT NULL,
  `refresh_token` TEXT DEFAULT NULL,
  `expires_in` INT DEFAULT NULL,
  `scope` VARCHAR(512) DEFAULT NULL,
  `scopes` TEXT DEFAULT NULL,
  `token_type` VARCHAR(50) DEFAULT NULL,
  `token_payload` LONGTEXT DEFAULT NULL,
  `expires_at` DATETIME DEFAULT NULL,
  `calendar_id` VARCHAR(255) NOT NULL DEFAULT 'primary',
  `status` ENUM('active', 'revoked', 'disconnected') NOT NULL DEFAULT 'active',
  `revoked_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `tenant_user_google_connection` (`tenant_id`, `user_id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_provider_account_id` (`provider_account_id`),
  CONSTRAINT `fk_google_calendar_connections_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_google_calendar_connections_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        }

        $this->pdo->exec($sql);
    }

    public function findForUser(int $tenantId, int $userId): ?array
    {
        if ($tenantId <= 0 || $userId <= 0) {
            return null;
        }

        $this->ensureTableExists();

        $stmt = $this->pdo->prepare(
            'SELECT * FROM google_calendar_connections WHERE tenant_id = :tenant_id AND user_id = :user_id LIMIT 1'
        );
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':user_id' => $userId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return $this->normalizeRow($row);
    }

    public function upsertConnection(int $tenantId, int $userId, array $tokenData, ?string $googleUserEmail = null, ?string $providerAccountId = null, ?string $calendarId = null): bool
    {
        if ($tenantId <= 0 || $userId <= 0) {
            return false;
        }

        $this->ensureTableExists();

        $accessToken = $tokenData['access_token'] ?? null;
        $refreshToken = $tokenData['refresh_token'] ?? null;
        $scope = $this->normalizeScopes($tokenData['scope'] ?? ($tokenData['scopes'] ?? null));
        $tokenType = $tokenData['token_type'] ?? null;
        $expiresIn = isset($tokenData['expires_in']) ? (int) $tokenData['expires_in'] : null;
        $expiresAt = $this->normalizeExpiresAt($tokenData);
        $payload = json_encode($tokenData, JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            $payload = null;
        }

        $provider = $tokenData['provider'] ?? 'google';
        $status = $this->normalizeStatus($tokenData['status'] ?? 'active');
        $providerAccountId = $providerAccountId ?? ($tokenData['provider_account_id'] ?? null);
        $calendarId = $calendarId ?? ($tokenData['calendar_id'] ?? 'primary');
        $scopes = $this->normalizeScopes($tokenData['scopes'] ?? ($tokenData['scope'] ?? null));
        $revokedAt = $status === 'active' ? null : ($tokenData['revoked_at'] ?? date('Y-m-d H:i:s'));

        $existing = $this->findForUser($tenantId, $userId);

        if ($existing) {
            $stmt = $this->pdo->prepare(
                'UPDATE google_calendar_connections SET '
                    . 'provider = :provider, '
                    . 'provider_account_id = :provider_account_id, '
                    . 'google_user_email = :google_user_email, '
                    . 'access_token = :access_token, '
                    . 'refresh_token = :refresh_token, '
                    . 'expires_in = :expires_in, '
                    . 'scope = :scope, '
                    . 'scopes = :scopes, '
                    . 'token_type = :token_type, '
                    . 'token_payload = :token_payload, '
                    . 'expires_at = :expires_at, '
                    . 'calendar_id = :calendar_id, '
                    . 'status = :status, '
                    . 'revoked_at = :revoked_at, '
                    . 'updated_at = CURRENT_TIMESTAMP '
                    . 'WHERE tenant_id = :tenant_id AND user_id = :user_id'
            );

            return $stmt->execute([
                ':provider' => $provider,
                ':provider_account_id' => $providerAccountId ?? $existing['provider_account_id'] ?? null,
                ':google_user_email' => $googleUserEmail ?? $existing['google_user_email'] ?? ($tokenData['email'] ?? null),
                ':access_token' => $accessToken,
                ':refresh_token' => $refreshToken,
                ':expires_in' => $expiresIn,
                ':scope' => $scope,
                ':scopes' => $scopes,
                ':token_type' => $tokenType,
                ':token_payload' => $payload,
                ':expires_at' => $expiresAt,
                ':calendar_id' => $calendarId,
                ':status' => $status,
                ':revoked_at' => $revokedAt,
                ':tenant_id' => $tenantId,
                ':user_id' => $userId,
            ]);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO google_calendar_connections '
                . '(tenant_id, user_id, provider, provider_account_id, google_user_email, access_token, refresh_token, expires_in, scope, scopes, token_type, token_payload, expires_at, calendar_id, status, revoked_at) '
                . 'VALUES (:tenant_id, :user_id, :provider, :provider_account_id, :google_user_email, :access_token, :refresh_token, :expires_in, :scope, :scopes, :token_type, :token_payload, :expires_at, :calendar_id, :status, :revoked_at)'
        );

        return $stmt->execute([
            ':tenant_id' => $tenantId,
            ':user_id' => $userId,
            ':provider' => $provider,
            ':provider_account_id' => $providerAccountId,
            ':google_user_email' => $googleUserEmail,
            ':access_token' => $accessToken,
            ':refresh_token' => $refreshToken,
            ':expires_in' => $expiresIn,
            ':scope' => $scope,
            ':scopes' => $scopes,
            ':token_type' => $tokenType,
            ':token_payload' => $payload,
            ':expires_at' => $expiresAt,
            ':calendar_id' => $calendarId,
            ':status' => $status,
            ':revoked_at' => $revokedAt,
        ]);
    }

    public function deleteConnection(int $tenantId, int $userId): bool
    {
        if ($tenantId <= 0 || $userId <= 0) {
            return false;
        }

        $this->ensureTableExists();

        $stmt = $this->pdo->prepare('DELETE FROM google_calendar_connections WHERE tenant_id = :tenant_id AND user_id = :user_id');
        return $stmt->execute([
            ':tenant_id' => $tenantId,
            ':user_id' => $userId,
        ]);
    }

    public function disconnectConnectionStatus(int $tenantId, int $userId, string $status = 'disconnected'): array
    {
        if ($tenantId <= 0 || $userId <= 0) {
            return [
                'success' => false,
                'status' => 'invalid_context',
                'updated' => false,
                'found' => false,
                'already_disconnected' => false,
                'message' => 'Contexto inválido para desativar a conexão do Google Calendar.',
            ];
        }

        try {
            $this->ensureTableExists();

            $existing = $this->findForUser($tenantId, $userId);
            if (!$existing) {
                return [
                    'success' => false,
                    'status' => 'not_found',
                    'updated' => false,
                    'found' => false,
                    'already_disconnected' => false,
                    'message' => 'Nenhuma conexão Google Calendar foi encontrada para este tenant/usuário.',
                ];
            }

            $normalizedStatus = $this->normalizeStatus($status);
            $alreadyDisconnected = ($existing['status'] ?? 'active') === 'disconnected' || (empty($existing['access_token']) && empty($existing['refresh_token']));
            $effectiveStatus = $alreadyDisconnected ? 'disconnected' : $normalizedStatus;
            $revokedAt = $effectiveStatus === 'active' ? null : ($existing['revoked_at'] ?? date('Y-m-d H:i:s'));

            $stmt = $this->pdo->prepare(
                'UPDATE google_calendar_connections '
                    . 'SET access_token = NULL, '
                    . 'refresh_token = NULL, '
                    . 'expires_in = NULL, '
                    . 'expires_at = NULL, '
                    . 'scope = NULL, '
                    . 'scopes = NULL, '
                    . 'token_type = NULL, '
                    . 'token_payload = NULL, '
                    . 'status = :status, '
                    . 'revoked_at = :revoked_at, '
                    . 'updated_at = CURRENT_TIMESTAMP '
                    . 'WHERE tenant_id = :tenant_id AND user_id = :user_id'
            );

            $success = $stmt->execute([
                ':status' => $effectiveStatus,
                ':revoked_at' => $revokedAt,
                ':tenant_id' => $tenantId,
                ':user_id' => $userId,
            ]);

            if (!$success) {
                return [
                    'success' => false,
                    'status' => 'update_failed',
                    'updated' => false,
                    'found' => true,
                    'already_disconnected' => $alreadyDisconnected,
                    'message' => 'Não foi possível confirmar a invalidação local da conexão Google Calendar.',
                ];
            }

            return [
                'success' => true,
                'status' => $effectiveStatus,
                'updated' => true,
                'found' => true,
                'already_disconnected' => $alreadyDisconnected,
                'message' => $alreadyDisconnected
                    ? 'A conexão Google Calendar já estava desconectada; o estado local foi normalizado.'
                    : 'Conexão Google Calendar desativada com sucesso.',
            ];
        } catch (\PDOException $e) {
            return [
                'success' => false,
                'status' => 'pdo_exception',
                'updated' => false,
                'found' => false,
                'already_disconnected' => false,
                'message' => 'Falha de banco ao desativar a conexão Google Calendar.',
            ];
        }
    }

    public function disconnectConnection(int $tenantId, int $userId, string $status = 'disconnected'): bool
    {
        $result = $this->disconnectConnectionStatus($tenantId, $userId, $status);
        return (bool) ($result['success'] ?? false) && (bool) ($result['updated'] ?? false);
    }

    public function isConnectionActiveForUser(int $tenantId, int $userId): bool
    {
        if ($tenantId <= 0 || $userId <= 0) {
            return false;
        }

        $connection = $this->findForUser($tenantId, $userId);
        if (!$connection) {
            return false;
        }

        return $this->normalizeStatus($connection['status'] ?? 'active') === 'active'
            && !empty($connection['access_token']);
    }

    private function normalizeRow(array $row): array
    {
        $payload = [];
        if (!empty($row['token_payload'])) {
            $decoded = json_decode($row['token_payload'], true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'tenant_id' => (int) ($row['tenant_id'] ?? 0),
            'user_id' => (int) ($row['user_id'] ?? 0),
            'provider' => $row['provider'] ?? ($payload['provider'] ?? 'google'),
            'provider_account_id' => $row['provider_account_id'] ?? ($payload['provider_account_id'] ?? null),
            'google_user_email' => $row['google_user_email'] ?? ($payload['email'] ?? null),
            'access_token' => $row['access_token'] ?? ($payload['access_token'] ?? null),
            'refresh_token' => $row['refresh_token'] ?? ($payload['refresh_token'] ?? null),
            'expires_in' => isset($row['expires_in']) ? (int) $row['expires_in'] : ($payload['expires_in'] ?? null),
            'scope' => $row['scope'] ?? ($payload['scope'] ?? null),
            'scopes' => $row['scopes'] ?? ($payload['scopes'] ?? ($payload['scope'] ?? null)),
            'token_type' => $row['token_type'] ?? ($payload['token_type'] ?? null),
            'expires_at' => $row['expires_at'] ?? ($payload['expires_at'] ?? null),
            'calendar_id' => $row['calendar_id'] ?? ($payload['calendar_id'] ?? 'primary'),
            'status' => $this->normalizeStatus($row['status'] ?? ($payload['status'] ?? 'active')),
            'revoked_at' => $row['revoked_at'] ?? ($payload['revoked_at'] ?? null),
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
            'token_payload' => $payload,
        ];
    }

    private function normalizeStatus(?string $status): string
    {
        $status = strtolower(trim((string) ($status ?? 'active')));

        if (in_array($status, ['active', 'revoked', 'disconnected'], true)) {
            return $status;
        }

        return 'active';
    }

    private function normalizeScopes($scopes): ?string
    {
        if ($scopes === null || $scopes === '') {
            return null;
        }

        if (is_array($scopes)) {
            $scopes = array_values(array_filter(array_map('trim', $scopes), static fn($scope) => $scope !== ''));
            return $scopes ? implode(' ', $scopes) : null;
        }

        $value = trim((string) $scopes);
        return $value !== '' ? $value : null;
    }

    private function normalizeExpiresAt(array $tokenData): ?string
    {
        $expiresAt = $tokenData['expires_at'] ?? null;
        if ($expiresAt) {
            return $expiresAt;
        }

        $expiresIn = $tokenData['expires_in'] ?? null;
        if ($expiresIn === null || $expiresIn === '') {
            return null;
        }

        return gmdate('Y-m-d H:i:s', time() + (int) $expiresIn);
    }
}
