<?php

namespace App\Infrastructure\Tenant;

use PDO;
use RuntimeException;

/**
 * TenantRepository - Camada de Acesso a Dados para tenants
 *
 * Responsável exclusivamente por:
 * - construir e executar consultas à tabela tenants
 * - gerenciar a conexão PDO
 * - retornar dados brutos para a camada de serviço
 */
class TenantRepository
{
    private PDO $pdo;

    /**
     * Construtor
     *
     * @param PDO $pdo Conexão com o banco de dados
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Criar novo tenant
     *
     * @param array $data Dados do tenant (name, slug, company_subdomain, description, max_users, status, plano, limite_usuarios, data_expiracao)
     * @return int ID do tenant criado
     */
    public function create(array $data): int
    {
        $sql = 'INSERT INTO tenants (
            name,
            slug,
            company_subdomain,
            description,
            max_users,
            status,
            plano,
            limite_usuarios,
            data_expiracao,
            created_at,
            updated_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
        )';

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            $data['name'] ?? '',
            $data['slug'] ?? '',
            $data['company_subdomain'] ?? '',
            $data['description'] ?? '',
            $data['max_users'] ?? 2,
            $data['status'] ?? 'active',
            $data['plano'] ?? 'trial',
            $data['limite_usuarios'] ?? 2,
            $data['data_expiracao'] ?? date('Y-m-d H:i:s', strtotime('+7 days')),
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Buscar tenant por ID
     *
     * @param int $id ID do tenant
     * @return array|null Dados do tenant ou null se não encontrado
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, slug, company_subdomain, description, max_users, status, plano, limite_usuarios, data_expiracao, created_at, updated_at
            FROM tenants WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ?: null;
    }

    /**
     * Buscar tenant por slug
     *
     * @param string $slug Slug único do tenant
     * @return array|null Dados do tenant ou null se não encontrado
     */
    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, slug, company_subdomain, description, max_users, status, plano, limite_usuarios, data_expiracao, created_at, updated_at
            FROM tenants WHERE slug = ? LIMIT 1'
        );
        $stmt->execute([$slug]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ?: null;
    }

    /**
     * Listar tenants ativos
     *
     * @return array<int, array<string, mixed>>
     */
    public function listActiveTenants(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM tenants WHERE status = ? ORDER BY name ASC'
        );
        $stmt->execute(['active']);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obter estatísticas do tenant
     *
     * @param int $tenantId
     * @return array<string, mixed>
     */
    public function getTenantStats(int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                (SELECT COUNT(*) FROM users WHERE tenant_id = :tenant_id AND active = 1 AND is_superadmin != 1) as active_users,
                (SELECT COUNT(*) FROM users WHERE tenant_id = :tenant_id AND is_superadmin != 1) as total_users,
                (SELECT COUNT(*) FROM audit_logs WHERE tenant_id = :tenant_id) as audit_logs_count,
                (SELECT MAX(created_at) FROM users WHERE tenant_id = :tenant_id AND is_superadmin != 1) as last_user_created
            FROM tenants
            WHERE id = :tenant_id
            LIMIT 1'
        );
        $stmt->execute([':tenant_id' => $tenantId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: [];
    }

    /**
     * Listar usuários de um tenant
     *
     * @param int $tenantId
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function getTenantUsers(int $tenantId, array $filters = []): array
    {
        $query = 'SELECT * FROM users WHERE tenant_id = :tenant_id';
        $params = [':tenant_id' => $tenantId];

        if (!empty($filters['role'])) {
            $query .= ' AND role = :role';
            $params[':role'] = $filters['role'];
        }

        if (isset($filters['active'])) {
            $query .= ' AND active = :active';
            $params[':active'] = (int) $filters['active'];
        }

        $query .= ' ORDER BY created_at DESC';

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Verificar se um usuário pertence ao tenant
     *
     * @param int $userId
     * @param int $tenantId
     * @return bool
     */
    public function userBelongsToTenant(int $userId, int $tenantId): bool
    {
        if ($userId <= 0 || $tenantId <= 0) {
            return false;
        }

        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE id = :user_id AND tenant_id = :tenant_id LIMIT 1');
        $stmt->execute([':user_id' => $userId, ':tenant_id' => $tenantId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }

    /**
     * Criar tenant via compatibilidade legacy
     *
     * @param array<string, mixed> $data
     * @return int
     */
    public function createTenant(array $data): int
    {
        return $this->create($data);
    }

    /**
     * Atualizar tenant via compatibilidade legacy
     *
     * @param int $id
     * @param array<string, mixed> $data
     * @return bool
     */
    public function updateTenant(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];

        foreach (['name', 'slug', 'description', 'status', 'max_users'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = $field . ' = :' . $field;
                $params[':' . $field] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = 'UPDATE tenants SET ' . implode(', ', $fields) . ', updated_at = NOW() WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute($params);
    }

    /**
     * Verificar se slug já existe
     *
     * @param string $slug Slug para verificação
     * @return bool true se existe, false caso contrário
     */
    public function checkSlugExists(string $slug): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM tenants WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);

        return (bool)$stmt->fetchColumn();
    }

    /**
     * Gerar slug única baseada em string
     *
     * Função auxiliar para criar slug sem acentos e verificar unicidade
     *
     * @param string $base String base para gerar slug
     * @return string Slug única gerada
     */
    public function generateUniqueSlug(string $base): string
    {
        // Remover acentos
        $accents = [
            'À' => 'a',
            'á' => 'a',
            'à' => 'a',
            'ä' => 'a',
            'â' => 'a',
            'ã' => 'a',
            'Á' => 'a',
            'Â' => 'a',
            'Ã' => 'a',
            'Ä' => 'a',
            'È' => 'e',
            'é' => 'e',
            'è' => 'e',
            'ê' => 'e',
            'ë' => 'e',
            'É' => 'e',
            'Ê' => 'e',
            'Ë' => 'e',
            'Ì' => 'i',
            'í' => 'i',
            'ì' => 'i',
            'î' => 'i',
            'ï' => 'i',
            'Í' => 'i',
            'Î' => 'i',
            'Ï' => 'i',
            'Ò' => 'o',
            'ó' => 'o',
            'ò' => 'o',
            'ô' => 'o',
            'ö' => 'o',
            'õ' => 'o',
            'Ó' => 'o',
            'Ô' => 'o',
            'Ö' => 'o',
            'Õ' => 'o',
            'Ù' => 'u',
            'ú' => 'u',
            'ù' => 'u',
            'û' => 'u',
            'ü' => 'u',
            'Ú' => 'u',
            'Û' => 'u',
            'Ü' => 'u',
            'ç' => 'c',
            'Ç' => 'c',
            'ñ' => 'n',
            'Ñ' => 'n'
        ];

        $slug = strtolower($base);
        $slug = strtr($slug, $accents);
        $slug = preg_replace('/[^a-z0-9]+/', '', $slug);
        $slug = substr($slug, 0, 50);

        $originalSlug = $slug;
        $counter = 1;

        // Loop para encontrar slug disponível
        while ($this->checkSlugExists($slug)) {
            $slug = $originalSlug . $counter;
            $counter++;

            if ($counter > 999) {
                throw new RuntimeException('Não foi possível gerar um slug único para o tenant');
            }
        }

        return $slug;
    }

    /**
     * Atualizar status do tenant
     *
     * @param int $id ID do tenant
     * @param string $status Novo status
     * @return bool true se sucesso
     */
    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->pdo->prepare('UPDATE tenants SET status = ?, updated_at = NOW() WHERE id = ?');
        return $stmt->execute([$status, $id]);
    }

    /**
     * Verifica se um subdomínio já existe para um tenant.
     *
     * @param string $subdomain
     * @param int|null $excludeTenantId
     * @return bool
     */
    public function subdomainExists(string $subdomain, ?int $excludeTenantId = null): bool
    {
        $sql = 'SELECT 1 FROM tenants WHERE company_subdomain = ?';
        $params = [$subdomain];

        if ($excludeTenantId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeTenantId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (bool)$stmt->fetchColumn();
    }

    /**
     * Atualiza o subdomínio do tenant.
     *
     * @param int $tenantId
     * @param string $companySubdomain
     * @return bool
     */
    public function updateSubdomain(int $tenantId, string $companySubdomain): bool
    {
        $stmt = $this->pdo->prepare('UPDATE tenants SET company_subdomain = ?, updated_at = NOW() WHERE id = ?');

        return $stmt->execute([$companySubdomain, $tenantId]);
    }

    /**
     * Lista todos os subdomínios ativos.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listActiveSubdomains(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, slug, company_subdomain, status FROM tenants WHERE status = ? ORDER BY id ASC'
        );
        $stmt->execute(['active']);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar mapa de subdomínios ativos para resolução de tenant por host.
     *
     * @return array<string, int>
     */
    public function findActiveTenantSubdomainMap(): array
    {
        $rows = $this->listActiveSubdomains();

        $map = [];
        foreach ($rows as $row) {
            $tenantId = (int)($row['id'] ?? 0);
            $slug = strtolower((string)($row['slug'] ?? $row['company_subdomain'] ?? ''));
            $companySubdomain = strtolower((string)($row['company_subdomain'] ?? ''));

            if ($slug !== '') {
                $map[$slug] = $tenantId;
            }

            if ($companySubdomain !== '') {
                $map[$companySubdomain] = $tenantId;
            }

            if ($slug === 'default' || $companySubdomain === 'default') {
                $map['dj'] = $tenantId;
                $map['www'] = $tenantId;
            }

            if (str_starts_with($slug, 'imoveis-')) {
                $map[substr($slug, 8)] = $tenantId;
            }

            if (str_starts_with($companySubdomain, 'imoveis-')) {
                $map[substr($companySubdomain, 8)] = $tenantId;
            }
        }

        return $map;
    }
}
