<?php

/**
 * TenantSubdomainManager
 * 
 * Gerencia a geração e validação de subdomínios para tenants
 * Integrada com SubdomainNormalizer para garantir normalização consistente
 */

namespace App\Services;

use App\Infrastructure\Tenant\TenantRepository;
use App\Utils\SubdomainNormalizer;
use PDO;

class TenantSubdomainManager
{

    private PDO $pdo;
    private TenantRepository $tenantRepository;

    public function __construct(PDO $pdo, ?TenantRepository $tenantRepository = null)
    {
        $this->pdo = $pdo;
        $this->tenantRepository = $tenantRepository ?? new TenantRepository($pdo);
    }

    /**
     * Gera um subdomínio único baseado no nome da empresa
     * 
     * @param string $companyName Nome da empresa
     * @param int|null $excludeTenantId Tenant ID a excluir da verificação (para updates)
     * @return string Subdomínio único e válido
     */
    public function generateUniqueSubdomain($companyName, $excludeTenantId = null)
    {
        $baseSubdomain = SubdomainNormalizer::normalize($companyName);

        // Verificar se já existe
        $subdomain = $baseSubdomain;
        $counter = 1;
        $maxAttempts = 100;

        while ($counter < $maxAttempts) {
            $exists = $this->subdomainExists($subdomain, $excludeTenantId);

            if (!$exists) {
                return $subdomain;
            }

            // Adicionar sufixo incremental
            $suffix = base_convert($counter, 10, 36); // Usar base36 para ficar compacto
            $subdomain = substr($baseSubdomain, 0, 55) . '-' . $suffix;
            $counter++;
        }

        throw new \Exception("Não foi possível gerar subdomínio único para '{$companyName}'");
    }

    /**
     * Verifica se um subdomínio já existe no banco
     */
    public function subdomainExists($subdomain, $excludeTenantId = null)
    {
        return $this->tenantRepository->subdomainExists(
            (string) $subdomain,
            $excludeTenantId !== null ? (int) $excludeTenantId : null
        );
    }

    /**
     * Valida um subdomínio
     */
    public function isValidSubdomain($subdomain)
    {
        return SubdomainNormalizer::isValid($subdomain);
    }

    /**
     * Atualizar o company_subdomain de um tenant
     * Útil para sincronizar quando o nome da empresa muda
     */
    public function updateSubdomainFromName($tenantId, $companyName)
    {
        try {
            $newSubdomain = $this->generateUniqueSubdomain($companyName, $tenantId);
            $updated = $this->tenantRepository->updateSubdomain((int) $tenantId, $newSubdomain);

            if (!$updated) {
                throw new \RuntimeException("Falha ao atualizar subdomínio do tenant {$tenantId}");
            }

            return [
                'success' => true,
                'subdomain' => $newSubdomain,
                'message' => "Subdomínio atualizado: {$newSubdomain}"
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obter info completa do tenant incluindo subdomínio
     */
    public function getTenantInfo($tenantId)
    {
        return $this->tenantRepository->findById((int) $tenantId);
    }

    /**
     * Listar todos os subdomínios ativos (para referência/debugging)
     */
    public function listActiveSubdomains()
    {
        return $this->tenantRepository->listActiveSubdomains();
    }

    /**
     * Gerar URL completa do tenant (com subdomínio)
     */
    public function generateSubdomainUrl($tenantId, $path = '/', $protocol = 'https')
    {
        $tenant = $this->getTenantInfo($tenantId);

        if (!$tenant) {
            return null;
        }

        $subdomain = $tenant['company_subdomain'] ?? $tenant['slug'] ?? 'default';
        $host = $subdomain . '.' . $_SERVER['HTTP_HOST'];

        // Se localhost, não forçar HTTPS
        if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
            $protocol = 'http';
        }

        return $protocol . '://' . $host . $path;
    }
}
