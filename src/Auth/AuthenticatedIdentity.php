<?php

namespace App\Auth;

final class AuthenticatedIdentity
{
    private int $userId;
    private string $source;
    private ?string $role;
    private array $permissions;
    private bool $isSuperAdmin;
    private string $authenticationMethod;
    private \DateTimeImmutable $authenticatedAt;

    private function __construct(
        int $userId,
        string $source,
        ?string $role = null,
        array $permissions = [],
        bool $isSuperAdmin = false,
        string $authenticationMethod = 'legacy',
        ?\DateTimeImmutable $authenticatedAt = null
    ) {
        $this->userId = $userId;
        $this->source = $source;
        $this->role = $role;
        $this->permissions = $permissions;
        $this->isSuperAdmin = $isSuperAdmin;
        $this->authenticationMethod = $authenticationMethod;
        $this->authenticatedAt = $authenticatedAt ?? new \DateTimeImmutable();
    }

    /**
     * Factory: cria identidade a partir de um RequestAuthenticationResult.
     * Retorna null se o resultado não representa uma autenticação válida.
     */
    public static function fromRequestAuthenticationResult(RequestAuthenticationResult $r): ?self
    {
        if (!$r->isAuthenticated()) {
            return null;
        }

        $userId = $r->getUserId();
        if ($userId === null) {
            return null;
        }

        $source = $r->getSource();
        $authenticationMethod = match ($source) {
            RequestAuthenticationResult::SOURCE_JWT => 'jwt',
            RequestAuthenticationResult::SOURCE_SESSION => 'session',
            default => 'legacy'
        };

        return new self(
            $userId,
            $source,
            $r->getRole(),
            $r->getPermissions(),
            $r->isSuperAdmin(),
            $authenticationMethod,
            new \DateTimeImmutable()
        );
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getSource(): string
    {
        return $this->source;
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

    public function getAuthenticationMethod(): string
    {
        return $this->authenticationMethod;
    }

    public function getAuthenticatedAt(): \DateTimeImmutable
    {
        return $this->authenticatedAt;
    }
}
