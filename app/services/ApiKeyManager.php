<?php

/**
 * API Key Manager
 *
 * Compatibilidade de legado: este adaptador delega para a camada moderna
 * de serviços e repositórios para não executar SQL diretamente no wrapper.
 */

use App\Repository\ApiKeyRepository;
use App\Service\ApiKeyService;

class ApiKeyManager
{
    private $pdo;
    private ApiKeyService $service;
    private ApiKeyRepository $repository;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->service = new ApiKeyService($pdo);
        $this->repository = new ApiKeyRepository($pdo);
    }

    public function generateKey($userId, $name = null, $expiresIn = null)
    {
        $tenantId = (int) ($_SESSION['tenant_id'] ?? 0);
        if ($tenantId <= 0) {
            return [
                'success' => false,
                'error' => 'Tenant inválido para geração da API Key.',
            ];
        }

        $expiresAt = null;
        if ($expiresIn) {
            $expiresAt = date('Y-m-d H:i:s', strtotime("+$expiresIn days"));
        }

        try {
            $record = $this->service->createKeyForTenant($tenantId, (int) $userId, $name ?? 'API Key ' . date('Y-m-d'), $expiresAt);

            return [
                'success' => true,
                'key' => $record['key'] ?? '',
                'name' => $record['name'] ?? null,
                'expires_at' => $record['expires_at'] ?? $expiresAt,
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function validateKey($key)
    {
        $record = $this->service->validateKey((string) $key);

        return $record ?: false;
    }

    public function listKeys($userId)
    {
        $tenantId = (int) ($_SESSION['tenant_id'] ?? 0);
        if ($tenantId <= 0) {
            return [];
        }

        return $this->service->listKeysForUser($tenantId, (int) $userId);
    }

    public function revokeKey($keyId, $userId)
    {
        $tenantId = (int) ($_SESSION['tenant_id'] ?? 0);
        if ($tenantId <= 0) {
            return false;
        }

        return $this->service->revokeKeyForUser($tenantId, (int) $userId, (int) $keyId);
    }

    public function getKeyStats($keyId)
    {
        return [
            'total_requests' => 0,
            'successful' => 0,
            'errors' => 0,
            'last_used' => null,
        ];
    }

    public function logRequest($keyId, $method, $endpoint, $statusCode, $responseTime = null)
    {
        return true;
    }
}
