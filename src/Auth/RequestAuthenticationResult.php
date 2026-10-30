<?php

namespace App\Auth;

final class RequestAuthenticationResult
{
    public const STATUS_NO_CREDENTIAL = 'NO_CREDENTIAL';
    public const STATUS_SESSION_AUTHENTICATED = 'SESSION_AUTHENTICATED';
    public const STATUS_JWT_AUTHENTICATED = 'JWT_AUTHENTICATED';
    public const STATUS_JWT_INVALID = 'JWT_INVALID';
    public const STATUS_JWT_EXPIRED = 'JWT_EXPIRED';
    public const STATUS_JWT_REVOKED = 'JWT_REVOKED';
    public const STATUS_MALFORMED_CREDENTIAL = 'MALFORMED_CREDENTIAL';
    public const STATUS_AUTHENTICATION_ERROR = 'AUTHENTICATION_ERROR';

    public const SOURCE_SESSION = 'session';
    public const SOURCE_JWT = 'jwt';
    public const SOURCE_NONE = 'none';

    private string $status;
    private string $source;
    private bool $authenticated;
    private ?int $userId;
    private ?string $role;
    private array $permissions;
    private bool $isSuperAdmin;
    private ?string $errorCode;
    private ?string $requestId;
    private ?string $conflictState;
    private ?AuthenticatedIdentity $identity;

    public function __construct(
        string $status,
        string $source = self::SOURCE_NONE,
        ?int $userId = null,
        ?string $role = null,
        array $permissions = [],
        bool $isSuperAdmin = false,
        ?string $errorCode = null,
        ?string $requestId = null,
        ?string $conflictState = null,
        ?AuthenticatedIdentity $identity = null
    ) {
        $this->status = $this->normalizeStatus($status);
        $this->source = $this->normalizeSource($source);
        $this->userId = $userId;
        $this->role = $role;
        $this->permissions = $this->normalizePermissions($permissions);
        $this->isSuperAdmin = $isSuperAdmin;
        $this->errorCode = $errorCode;
        $this->requestId = $requestId;
        $this->conflictState = $this->normalizeConflictState($conflictState);
        $this->identity = $identity;
        $this->authenticated = $this->resolveAuthenticated();

        $this->assertValidState();
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function isAuthenticated(): bool
    {
        return $this->authenticated;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function getPermissions(): array
    {
        return $this->permissions;
    }

    public function isSuperAdmin(): bool
    {
        return $this->isSuperAdmin;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    public function getConflictState(): ?string
    {
        return $this->conflictState;
    }

    public function hasConflict(): bool
    {
        return $this->conflictState !== null;
    }

    public function getIdentity(): ?AuthenticatedIdentity
    {
        return $this->identity;
    }

    private function normalizeStatus(string $status): string
    {
        $allowed = [
            self::STATUS_NO_CREDENTIAL,
            self::STATUS_SESSION_AUTHENTICATED,
            self::STATUS_JWT_AUTHENTICATED,
            self::STATUS_JWT_INVALID,
            self::STATUS_JWT_EXPIRED,
            self::STATUS_JWT_REVOKED,
            self::STATUS_MALFORMED_CREDENTIAL,
            self::STATUS_AUTHENTICATION_ERROR,
        ];

        if (!in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException('Status de autenticação inválido: ' . $status);
        }

        return $status;
    }

    private function normalizeSource(string $source): string
    {
        $allowed = [self::SOURCE_SESSION, self::SOURCE_JWT, self::SOURCE_NONE];

        if (!in_array($source, $allowed, true)) {
            throw new \InvalidArgumentException('Origem de autenticação inválida: ' . $source);
        }

        return $source;
    }

    private function normalizePermissions(array $permissions): array
    {
        $normalized = [];

        foreach ($permissions as $permission) {
            if (is_string($permission) && trim($permission) !== '') {
                $normalized[] = trim($permission);
            }
        }

        return $normalized;
    }

    private function normalizeConflictState(?string $conflictState): ?string
    {
        if ($conflictState === null) {
            return null;
        }

        $normalized = trim($conflictState);
        return $normalized === '' ? null : strtolower($normalized);
    }

    private function resolveAuthenticated(): bool
    {
        return in_array($this->status, [
            self::STATUS_SESSION_AUTHENTICATED,
            self::STATUS_JWT_AUTHENTICATED,
        ], true);
    }

    private function assertValidState(): void
    {
        if ($this->authenticated && $this->userId === null) {
            throw new \InvalidArgumentException('Uma autenticação válida exige user_id não nulo.');
        }

        if (!$this->authenticated && $this->userId !== null) {
            throw new \InvalidArgumentException('Uma instância não autenticada não pode conter user_id.');
        }

        if ($this->source === self::SOURCE_NONE && $this->authenticated) {
            throw new \InvalidArgumentException('Source none não pode representar identidade autenticada.');
        }

        if ($this->source === self::SOURCE_JWT && !$this->authenticated && !in_array($this->status, [
            self::STATUS_JWT_INVALID,
            self::STATUS_JWT_EXPIRED,
            self::STATUS_JWT_REVOKED,
            self::STATUS_MALFORMED_CREDENTIAL,
            self::STATUS_AUTHENTICATION_ERROR,
        ], true)) {
            throw new \InvalidArgumentException('Source jwt exige um status JWT válido ou inválido compatível.');
        }

        if ($this->source === self::SOURCE_SESSION && !$this->authenticated && $this->status !== self::STATUS_NO_CREDENTIAL) {
            throw new \InvalidArgumentException('Source session exige status de sessão ou ausência de credencial.');
        }

        if ($this->source === self::SOURCE_NONE && in_array($this->status, [
            self::STATUS_SESSION_AUTHENTICATED,
            self::STATUS_JWT_AUTHENTICATED,
        ], true)) {
            throw new \InvalidArgumentException('Source none não pode coexistir com autenticado.');
        }

        if ($this->conflictState !== null && $this->status !== self::STATUS_AUTHENTICATION_ERROR) {
            throw new \InvalidArgumentException('Conflito explícito exige status AUTHENTICATION_ERROR.');
        }

        if ($this->conflictState !== null && $this->userId !== null) {
            throw new \InvalidArgumentException('Conflito explícito não pode materializar user_id.');
        }
    }
}
