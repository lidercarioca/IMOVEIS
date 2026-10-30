<?php

namespace App\Service;

use App\Repository\UtmRepository;
use InvalidArgumentException;
use PDO;

class UtmService
{
    private PDO $pdo;
    private UtmRepository $repository;

    public function __construct(PDO $pdo, ?UtmRepository $repository = null)
    {
        $this->pdo = $pdo;
        $this->repository = $repository ?? new UtmRepository($pdo);
    }

    public function listCampaigns(int $tenantId): array
    {
        if ($tenantId <= 0) {
            throw new InvalidArgumentException('Tenant inválido');
        }

        return $this->repository->listCampaigns($tenantId);
    }

    public function createCampaign(int $tenantId, int $createdBy, array $data): array
    {
        if ($tenantId <= 0) {
            throw new InvalidArgumentException('Tenant inválido');
        }

        $campaignName = trim((string) ($data['campaign_name'] ?? ''));
        $source = trim((string) ($data['source'] ?? ''));
        $medium = trim((string) ($data['medium'] ?? ''));
        $content = isset($data['content']) ? trim((string) $data['content']) : null;
        $description = isset($data['description']) ? trim((string) $data['description']) : null;

        if ($campaignName === '' || $source === '' || $medium === '') {
            throw new InvalidArgumentException('Campos obrigatórios: campaign_name, source, medium');
        }

        $createdBy = max(0, (int) $createdBy);

        return $this->repository->createCampaign($tenantId, $campaignName, $source, $medium, $content !== '' ? $content : null, $description !== '' ? $description : null, $createdBy);
    }

    public function updateCampaign(int $tenantId, array $data): void
    {
        if ($tenantId <= 0) {
            throw new InvalidArgumentException('Tenant inválido');
        }

        $campaignId = isset($data['id']) ? (int) $data['id'] : 0;
        if ($campaignId <= 0) {
            throw new InvalidArgumentException('ID da campanha obrigatório');
        }

        $fields = [];

        foreach (['campaign_name', 'source', 'medium', 'content', 'description'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $value = $data[$field];
                if (is_string($value)) {
                    $value = trim($value);
                }
                $fields[$field === 'content' || $field === 'description' ? $field : $field] = $value;
            }
        }

        if ($fields === []) {
            throw new InvalidArgumentException('Nenhum campo para atualizar');
        }

        $updated = $this->repository->updateCampaign($tenantId, $campaignId, $fields);
        if (!$updated) {
            throw new InvalidArgumentException('Campanha não encontrada');
        }
    }

    public function deleteCampaign(int $tenantId, int $campaignId): void
    {
        if ($tenantId <= 0) {
            throw new InvalidArgumentException('Tenant inválido');
        }

        if ($campaignId <= 0) {
            throw new InvalidArgumentException('ID da campanha obrigatório');
        }

        $deleted = $this->repository->deleteCampaign($tenantId, $campaignId);
        if (!$deleted) {
            throw new InvalidArgumentException('Campanha não encontrada');
        }
    }

    public function getAnalytics(int $tenantId, int $days, ?int $campaignId = null): array
    {
        if ($tenantId <= 0) {
            throw new InvalidArgumentException('Tenant inválido');
        }

        $days = max(1, (int) $days);
        if ($campaignId !== null) {
            $campaignId = (int) $campaignId;
        }

        return $this->repository->getAnalytics($tenantId, $days, $campaignId);
    }

    public function trackClick(int $tenantId, array $data, string $userIp, string $userAgent, string $referrer): array
    {
        if ($tenantId <= 0) {
            throw new InvalidArgumentException('Tenant inválido');
        }

        $utmSource = isset($data['utm_source']) ? trim((string) $data['utm_source']) : null;
        $utmMedium = isset($data['utm_medium']) ? trim((string) $data['utm_medium']) : null;
        $utmCampaign = isset($data['utm_campaign']) ? trim((string) $data['utm_campaign']) : null;
        $utmContent = isset($data['utm_content']) ? trim((string) $data['utm_content']) : null;
        $utmTerm = isset($data['utm_term']) ? trim((string) $data['utm_term']) : null;
        $destinationUrl = $data['destination_url'] ?? '';

        $campaignId = null;
        if ($utmCampaign !== null && $utmCampaign !== '') {
            $campaignId = $this->repository->findCampaignIdByTenantAndName($tenantId, $utmCampaign);
        }

        $trackingId = $this->repository->insertTracking($tenantId, $campaignId, [
            'utm_source' => $utmSource,
            'utm_medium' => $utmMedium,
            'utm_campaign' => $utmCampaign,
            'utm_content' => $utmContent,
            'utm_term' => $utmTerm,
            'destination_url' => $destinationUrl,
        ], $userIp, $userAgent, $referrer);

        return [
            'tracking_id' => $trackingId,
            'campaign' => $utmCampaign,
            'source' => $utmSource,
            'medium' => $utmMedium,
        ];
    }
}
