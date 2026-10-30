<?php

namespace App\Infrastructure\Company;

use PDO;

/**
 * CompanySettingsRepository - Camada de Acesso a Dados para configurações de empresa
 *
 * Responsável exclusivamente por:
 * - construir e executar consultas à tabela company_settings
 * - gerenciar a conexão PDO
 * - retornar dados brutos para a camada de serviço
 */
class CompanySettingsRepository
{
    private PDO $pdo;

    /**
     * Construtor
     *
     * @param PDO $pdo Conexão com o banco de dados
     */
    private array $tableColumns = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->tableColumns = [];
    }

    /**
     * Obter colunas reais da tabela company_settings
     *
     * @return string[]
     */
    private function getTableColumns(): array
    {
        if ($this->tableColumns !== []) {
            return $this->tableColumns;
        }

        $stmt = $this->pdo->query("SHOW COLUMNS FROM company_settings");
        $columns = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
            $columns[] = $column['Field'];
        }

        return $this->tableColumns = $columns;
    }

    /**
     * Colunas que podem ser persistidas no INSERT
     *
     * @return string[]
     */
    private function getPersistableColumns(): array
    {
        $desired = [
            'tenant_id',
            'company_name',
            'company_email',
            'company_email2',
            'company_phone',
            'company_whatsapp',
            'company_address',
            'company_weekday_hours',
            'company_saturday_hours',
            'company_description',
            'company_facebook',
            'company_instagram',
            'company_linkedin',
            'company_youtube',
            'company_logo',
            'company_color1',
            'company_color2',
            'company_color3',
            'company_font',
            'email_notifications',
            'email_leads',
            'notify_new_lead',
            'notify_new_property',
            'notify_property_status',
            'notify_contact_form',
            'notify_agendamento',
            'company_website',
            'company_cnpj',
        ];

        // TODO: company_website and company_cnpj are optional and will be persisted only if the company_settings table contains these columns.
        //       If those columns are added to the schema, this repository will automatically include them in INSERT/UPDATE.
        return array_values(array_filter($desired, function ($column) {
            return in_array($column, $this->getTableColumns(), true);
        }));
    }

    /**
     * Colunas que podem ser retornadas em um SELECT
     *
     * @return string[]
     */
    private function getQueryableColumns(): array
    {
        $desired = [
            'id',
            'tenant_id',
            'company_name',
            'company_email',
            'company_email2',
            'company_phone',
            'company_whatsapp',
            'company_address',
            'company_weekday_hours',
            'company_saturday_hours',
            'company_description',
            'company_facebook',
            'company_instagram',
            'company_linkedin',
            'company_youtube',
            'company_logo',
            'company_color1',
            'company_color2',
            'company_color3',
            'company_font',
            'updated_at',
            'map_coordinates',
            'business_hours',
            'creci',
            'email_notifications',
            'email_leads',
            'notify_new_lead',
            'notify_new_property',
            'notify_property_status',
            'notify_contact_form',
            'notify_agendamento',
            'company_website',
            'company_cnpj',
        ];

        return array_values(array_filter($desired, function ($column) {
            return in_array($column, $this->getTableColumns(), true);
        }));
    }

    /**
     * Criar configurações de empresa para um tenant
     *
     * @param int $tenantId ID do tenant
     * @param array $data Dados de configuração (company_name, website, logo, phone, email_company, address, city, state, zip_code, country, etc)
     * @return int ID do registro criado
     */
    public function create(int $tenantId, array $data): int
    {
        $columns = $this->getPersistableColumns();

        if (empty($columns)) {
            throw new \Exception('CompanySettingsRepository::create no persistable columns found for company_settings');
        }

        $values = [];
        foreach ($columns as $column) {
            switch ($column) {
                case 'tenant_id':
                    $values[] = $tenantId;
                    break;
                case 'company_name':
                    $values[] = $data['company_name'] ?? ($data['name'] ?? '');
                    break;
                case 'company_email':
                    $values[] = $data['email_company'] ?? ($data['company_email'] ?? '');
                    break;
                case 'company_email2':
                    $values[] = $data['company_email2'] ?? null;
                    break;
                case 'company_phone':
                    $values[] = $data['phone'] ?? ($data['company_phone'] ?? null);
                    break;
                case 'company_whatsapp':
                    $values[] = $data['company_whatsapp'] ?? null;
                    break;
                case 'company_address':
                    $values[] = $data['address'] ?? null;
                    break;
                case 'company_weekday_hours':
                    $values[] = $data['company_weekday_hours'] ?? null;
                    break;
                case 'company_saturday_hours':
                    $values[] = $data['company_saturday_hours'] ?? null;
                    break;
                case 'company_description':
                    $values[] = $data['description'] ?? ($data['company_description'] ?? null);
                    break;
                case 'company_facebook':
                    $values[] = $data['company_facebook'] ?? null;
                    break;
                case 'company_instagram':
                    $values[] = $data['company_instagram'] ?? null;
                    break;
                case 'company_linkedin':
                    $values[] = $data['company_linkedin'] ?? null;
                    break;
                case 'company_youtube':
                    $values[] = $data['company_youtube'] ?? null;
                    break;
                case 'company_logo':
                    $values[] = $data['logo'] ?? ($data['company_logo'] ?? null);
                    break;
                case 'company_color1':
                    $values[] = $data['primary_color'] ?? '#007bff';
                    break;
                case 'company_color2':
                    $values[] = $data['secondary_color'] ?? '#6c757d';
                    break;
                case 'company_color3':
                    $values[] = $data['accent_color'] ?? '#f59e0b';
                    break;
                case 'company_font':
                    $values[] = $data['company_font'] ?? 'Poppins';
                    break;
                case 'email_notifications':
                    $values[] = $data['email_notifications'] ?? ($data['email_company'] ?? ($data['company_email'] ?? null));
                    break;
                case 'email_leads':
                    $values[] = $data['email_leads'] ?? ($data['email_company'] ?? ($data['company_email'] ?? null));
                    break;
                case 'notify_new_lead':
                    $values[] = $data['notify_new_lead'] ?? 1;
                    break;
                case 'notify_new_property':
                    $values[] = $data['notify_new_property'] ?? 1;
                    break;
                case 'notify_property_status':
                    $values[] = $data['notify_property_status'] ?? 1;
                    break;
                case 'notify_contact_form':
                    $values[] = $data['notify_contact_form'] ?? 1;
                    break;
                case 'notify_agendamento':
                    $values[] = $data['notify_agendamento'] ?? 1;
                    break;
                case 'company_website':
                    $values[] = $data['company_website'] ?? null;
                    break;
                case 'company_cnpj':
                    $values[] = $data['company_cnpj'] ?? null;
                    break;
                default:
                    $values[] = $data[$column] ?? null;
                    break;
            }
        }

        $sql = 'INSERT INTO company_settings (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $stmt = $this->pdo->prepare($sql);

        try {
            $stmt->execute($values);
        } catch (\PDOException $e) {
            error_log('CompanySettingsRepository::create failed SQL=' . $sql . ' error=' . $e->getMessage());
            throw $e;
        }

        if (!in_array('company_website', $columns, true)) {
            error_log('CompanySettingsRepository::create TODO company_website column missing in company_settings');
        }
        if (!in_array('company_cnpj', $columns, true)) {
            error_log('CompanySettingsRepository::create TODO company_cnpj column missing in company_settings');
        }

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Buscar configurações por ID.
     *
     * @param int $id ID da configuração
     * @return array|null Dados de configuração ou null se não encontrado
     */
    public function findById(int $id): ?array
    {
        $columns = $this->getQueryableColumns();
        $sql = 'SELECT ' . implode(', ', $columns) . ' FROM company_settings WHERE id = ? LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ?: null;
    }

    /**
     * Buscar configurações por tenant
     *
     * @param int $tenantId ID do tenant
     * @return array|null Dados de configuração ou null se não encontrado
     */
    public function findByTenant(int $tenantId): ?array
    {
        $columns = $this->getQueryableColumns();
        $sql = 'SELECT ' . implode(', ', $columns) . ' FROM company_settings WHERE tenant_id = ? LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$tenantId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ?: null;
    }

    /**
     * Atualizar configurações de empresa
     *
     * @param int $tenantId ID do tenant
     * @param array $data Campos a atualizar
     * @return bool true se sucesso
     */
    public function update(int $tenantId, array $data): bool
    {
        $updates = [];
        $values = [];

        foreach ($data as $key => $value) {
            // Validar se coluna pode ser atualizada
            if ($this->isUpdatableColumn($key)) {
                $updates[] = "$key = ?";
                $values[] = $value;
            }
        }

        if (empty($updates)) {
            return false;
        }

        $values[] = $tenantId;
        $updates[] = 'updated_at = NOW()';

        $sql = 'UPDATE company_settings SET ' . implode(', ', $updates) . ' WHERE tenant_id = ?';
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute($values);
    }

    /**
     * Definir configurações padrão de empresa
     *
     * Cria ou atualiza configurações padrão quando não fornecidas explicitamente
     *
     * @param int $tenantId ID do tenant
     * @param array $defaults Valores padrão
     * @return int ID do registro (criado ou existente)
     */
    public function setDefaults(int $tenantId, array $defaults): int
    {
        $existing = $this->findByTenant($tenantId);

        if ($existing) {
            // Atualizar apenas campos nulos com defaults
            $toUpdate = [];
            foreach ($defaults as $key => $value) {
                if ($this->isUpdatableColumn($key) && (empty($existing[$key]) || $existing[$key] === null)) {
                    $toUpdate[$key] = $value;
                }
            }

            if (!empty($toUpdate)) {
                $this->update($tenantId, $toUpdate);
            }

            return $existing['id'];
        }

        // Criar novo registro com defaults
        return $this->create($tenantId, $defaults);
    }

    /**
     * Verificar se coluna pode ser atualizada
     *
     * @param string $column Nome da coluna
     * @return bool
     */
    private function isUpdatableColumn(string $column): bool
    {
        $updatable = [
            'company_name',
            'company_email',
            'company_email2',
            'company_phone',
            'company_whatsapp',
            'company_address',
            'company_weekday_hours',
            'company_saturday_hours',
            'company_description',
            'company_facebook',
            'company_instagram',
            'company_linkedin',
            'company_youtube',
            'company_logo',
            'company_color1',
            'company_color2',
            'company_color3',
            'company_font',
            'email_notifications',
            'email_leads',
            'notify_new_lead',
            'notify_new_property',
            'notify_property_status',
            'notify_contact_form',
            'notify_agendamento',
            'company_website',
            'company_cnpj',
        ];

        return in_array($column, $updatable, true) && in_array($column, $this->getTableColumns(), true);
    }
}
