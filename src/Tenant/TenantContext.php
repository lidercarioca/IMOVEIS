<?php

namespace App\Tenant;

/**
 * TenantContext - Contexto puro do tenant.
 *
 * Esta classe não resolve tenant nem acessa request.
 * Ela apenas guarda o resultado canônico de resolução.
 */
class TenantContext
{
    private ?int $tenantId;
    private ?int $userId;
    private ?string $role;
    private bool $isSuperAdmin;
    private ?int $selectedTenant;
    private string $state;
    private TenantResolution $resolution;

    public function __construct(TenantResolution $resolution, ?int $userId = null, ?string $role = null, bool $isSuperAdmin = false, ?int $selectedTenant = null, string $state = 'resolved')
    {
        $this->resolution = $resolution;
        $this->tenantId = $resolution->getTenantId();
        $this->userId = $userId;
        $this->role = $role;
        $this->isSuperAdmin = $isSuperAdmin;
        $this->selectedTenant = $selectedTenant;
        $this->state = $state;
    }

    public static function fromResolution(
        TenantResolution $resolution,
        ?int $userId = null,
        ?string $role = null,
        bool $isSuperAdmin = false,
        ?int $selectedTenant = null,
        string $state = 'resolved'
    ): self {
        return new self($resolution, $userId, $role, $isSuperAdmin, $selectedTenant, $state);
    }

    public function getTenantId(): ?int
    {
        return $this->tenantId;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function isSuperAdmin(): bool
    {
        return $this->isSuperAdmin;
    }

    public function getSelectedTenant(): ?int
    {
        return $this->selectedTenant;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function hasTenantSelected(): bool
    {
        return $this->tenantId !== null;
    }

    public function getResolution(): TenantResolution
    {
        return $this->resolution;
    }

    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'user_id' => $this->userId,
            'role' => $this->role,
            'is_superadmin' => $this->isSuperAdmin,
            'selected_tenant' => $this->selectedTenant,
            'state' => $this->state,
            'resolution' => $this->resolution->toArray(),
        ];
    }
}
