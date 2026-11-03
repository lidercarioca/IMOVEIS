<?php

namespace App\Application\Tenant;

use App\Infrastructure\Billing\TenantRepository as BillingTenantRepository;
use App\Infrastructure\Tenant\TenantRepository;
use App\Services\TenantSubdomainManager;
use InvalidArgumentException;
use PDO;
use RuntimeException;

class TenantRegistrationService
{
    private TenantRepository $tenantRepository;
    private BillingTenantRepository $billingTenantRepository;
    private TenantSubdomainManager $subdomainManager;

    public function __construct(PDO $pdo, ?TenantRepository $tenantRepository = null, ?TenantSubdomainManager $subdomainManager = null)
    {
        $this->tenantRepository = $tenantRepository ?? new TenantRepository($pdo);
        $this->billingTenantRepository = new BillingTenantRepository($pdo);
        $this->subdomainManager = $subdomainManager ?? new TenantSubdomainManager($pdo);
    }

    public function createTenant(array $input): array
    {
        $companyName = trim((string)($input['name'] ?? ''));

        if ($companyName === '') {
            throw new InvalidArgumentException('Nome da empresa é obrigatório');
        }

        if (mb_strlen($companyName, 'UTF-8') > 100) {
            throw new InvalidArgumentException('Nome não pode exceder 100 caracteres');
        }

        $description = trim((string)($input['description'] ?? ''));
        $maxUsers = isset($input['max_users']) && $input['max_users'] !== ''
            ? (int)$input['max_users']
            : 5;

        if ($maxUsers < 1) {
            $maxUsers = 1;
        }

        $slug = $this->tenantRepository->generateUniqueSlug($companyName);
        $tenantData = [
            'name' => $companyName,
            'slug' => $slug,
            'company_subdomain' => $slug,
            'description' => $description,
            'max_users' => $maxUsers,
            'status' => 'active',
            'plano' => 'trial',
            'limite_usuarios' => 2,
            'data_expiracao' => date('Y-m-d H:i:s', strtotime('+7 days')),
        ];

        $tenantId = $this->tenantRepository->create($tenantData);
        if ($tenantId <= 0) {
            throw new RuntimeException('Falha ao criar tenant');
        }

        $planCode = (string)($tenantData['plano'] ?? 'trial');
        if ($planCode === 'trial') {
            $startedAt = date('Y-m-d H:i:s');
            $expiresAt = (string)($tenantData['data_expiracao'] ?? date('Y-m-d H:i:s', strtotime('+7 days')));
            if (!$this->billingTenantRepository->createTrialUsageRecord($tenantId, $planCode, $startedAt, $expiresAt)) {
                throw new RuntimeException('Falha ao registrar o uso do trial do tenant');
            }
        }

        $accessUrl = $this->subdomainManager->generateSubdomainUrl($tenantId, '/');

        return [
            'success' => true,
            'tenant_id' => (int)$tenantId,
            'name' => $companyName,
            'subdomain' => $slug,
            'description' => $description,
            'max_users' => $maxUsers,
            'access_url' => $accessUrl,
            'created_at' => date('Y-m-d\TH:i:s'),
        ];
    }
}
