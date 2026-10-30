<?php

namespace App\Tenant;

/**
 * TenantResolution - Resultado imutável da resolução de tenant.
 */
class TenantResolution
{
    private ?int $tenantId;
    private string $origin;
    private float $confidence;
    private bool $isPublic;
    private bool $isSuperAdmin;
    private bool $isFallback;
    private string $resolvedBy;
    private int $timestamp;
    private array $metadata;

    public function __construct(
        ?int $tenantId,
        string $origin,
        float $confidence,
        bool $isPublic,
        bool $isSuperAdmin,
        bool $isFallback,
        string $resolvedBy,
        array $metadata = []
    ) {
        $this->tenantId = $tenantId;
        $this->origin = $origin;
        $this->confidence = $confidence;
        $this->isPublic = $isPublic;
        $this->isSuperAdmin = $isSuperAdmin;
        $this->isFallback = $isFallback;
        $this->resolvedBy = $resolvedBy;
        $this->timestamp = time();
        $this->metadata = $metadata;
    }

    public function getTenantId(): ?int
    {
        return $this->tenantId;
    }

    public function getOrigin(): string
    {
        return $this->origin;
    }

    public function getConfidence(): float
    {
        return $this->confidence;
    }

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    public function isSuperAdmin(): bool
    {
        return $this->isSuperAdmin;
    }

    public function isFallback(): bool
    {
        return $this->isFallback;
    }

    public function getResolvedBy(): string
    {
        return $this->resolvedBy;
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'origin' => $this->origin,
            'confidence' => $this->confidence,
            'is_public' => $this->isPublic,
            'is_superadmin' => $this->isSuperAdmin,
            'is_fallback' => $this->isFallback,
            'resolved_by' => $this->resolvedBy,
            'timestamp' => $this->timestamp,
            'metadata' => $this->metadata,
        ];
    }
}
