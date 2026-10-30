<?php

namespace App\Services;

use App\Infrastructure\Alert\AlertConfigRepository;
use PDO;

class AlertConfigService
{
    private AlertConfigRepository $repository;

    public function __construct(PDO $pdo)
    {
        $this->repository = new AlertConfigRepository($pdo);
    }

    public function getUserConfigs(int $tenantId, int $userId): array
    {
        $configs = $this->repository->findByTenantAndUser($tenantId, $userId);
        $defaultThresholds = self::getDefaultThresholds();
        $alertTypes = self::getAlertTypes();

        return array_map(function (array $config) use ($defaultThresholds, $alertTypes) {
            $alertType = $config['alert_type'] ?? '';
            return [
                'id' => $config['id'] ?? null,
                'user_id' => $config['user_id'] ?? null,
                'alert_type' => $alertType,
                'threshold' => isset($config['threshold']) ? (int)$config['threshold'] : ($defaultThresholds[$alertType] ?? 0),
                'enabled' => isset($config['enabled']) ? (bool)$config['enabled'] : true,
                'created_at' => $config['created_at'] ?? null,
                'updated_at' => $config['updated_at'] ?? null,
                'metadata' => $alertTypes[$alertType] ?? null,
            ];
        }, $configs);
    }

    public function saveConfig(int $tenantId, int $userId, string $alertType, int $threshold, bool $enabled): bool
    {
        if (!array_key_exists($alertType, self::getAlertTypes())) {
            return false;
        }

        return $this->repository->saveConfig($tenantId, $userId, $alertType, $threshold, $enabled);
    }

    public static function getAlertTypes(): array
    {
        return AlertConfigRepository::getAlertTypes();
    }

    public static function getDefaultThresholds(): array
    {
        return AlertConfigRepository::getDefaultThresholds();
    }
}
