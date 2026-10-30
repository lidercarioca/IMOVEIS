<?php

namespace App\Application\Tenant;

use App\Infrastructure\Tenant\TenantRepository;
use App\Infrastructure\Company\CompanySettingsRepository;
use App\Infrastructure\Pipeline\PipelineStageRepository;
use App\Application\User\UserService;
use App\Infrastructure\User\UserRepository;
use PDO;
use Exception;

/**
 * TenantOnboardingService - Serviço de Onboarding de Novo Tenant
 *
 * Orquestra o processo completo de cadastro de novo tenant:
 * - Criação do tenant
 * - Configurações padrão de empresa
 * - Estágios de pipeline padrão
 * - Usuário admin inicial
 * - Gerenciamento de transações
 */
class TenantOnboardingService
{
    private TenantRepository $tenantRepository;
    private CompanySettingsRepository $companySettingsRepository;
    private PipelineStageRepository $pipelineStageRepository;
    private UserService $userService;
    private UserRepository $userRepository;
    private PDO $pdo;

    /**
     * Construtor
     *
     * @param PDO $pdo Conexão com banco de dados
     * @param TenantRepository $tenantRepository
     * @param CompanySettingsRepository $companySettingsRepository
     * @param PipelineStageRepository $pipelineStageRepository
     * @param UserService $userService
     * @param UserRepository $userRepository
     */
    public function __construct(
        PDO $pdo,
        TenantRepository $tenantRepository,
        CompanySettingsRepository $companySettingsRepository,
        PipelineStageRepository $pipelineStageRepository,
        UserService $userService,
        UserRepository $userRepository
    ) {
        $this->pdo = $pdo;
        $this->tenantRepository = $tenantRepository;
        $this->companySettingsRepository = $companySettingsRepository;
        $this->pipelineStageRepository = $pipelineStageRepository;
        $this->userService = $userService;
        $this->userRepository = $userRepository;
    }

    /**
     * Registrar novo tenant com configurações iniciais
     *
     * Cria de forma atômica:
     * 1. Registro de tenant
     * 2. Configurações de empresa
     * 3. Estágios de pipeline padrão
     * 4. Usuário admin
     *
     * @param array $companyData Dados da empresa (name, website, logo, email_company, phone, etc)
     * @param array $adminData Dados do admin (name, email, username, password)
     * @return array Dados do tenant criado e admin user
     * @throws Exception Se falhar em qualquer etapa
     */
    public function registerNewTenant(array $companyData, array $adminData): array
    {
        $this->pdo->beginTransaction();
        error_log('TenantOnboardingService::registerNewTenant starting transaction');
        try {
            // 1. Gerar slug única para o tenant
            $slug = $this->tenantRepository->generateUniqueSlug($companyData['name'] ?? 'tenant');

            // 2. Criar tenant
            $tenantData = [
                'name' => $companyData['name'] ?? 'Sem Nome',
                'slug' => $slug,
                'company_subdomain' => $slug,
                'description' => $companyData['description'] ?? '',
                'max_users' => $companyData['max_users'] ?? 2,
                'status' => 'active',
                'plano' => $companyData['plano'] ?? 'trial',
                'limite_usuarios' => $companyData['limite_usuarios'] ?? 2,
                'data_expiracao' => $companyData['data_expiracao'] ?? date('Y-m-d H:i:s', strtotime('+7 days')),
            ];

            $tenantId = $this->tenantRepository->create($tenantData);

            if (!$tenantId) {
                throw new Exception('Falha ao criar tenant');
            }

            error_log(sprintf('TenantOnboardingService::registerNewTenant tenant created id=%d slug=%s', $tenantId, $slug));

            // 3. Criar configurações de empresa
            $settingsData = array_merge(
                $companyData,
                [
                    'company_name' => $companyData['name'] ?? '',
                    'email_notifications' => $companyData['email_company'] ?? ($companyData['company_email'] ?? ''),
                    'email_leads' => $companyData['email_company'] ?? ($companyData['company_email'] ?? ''),
                    'notify_new_lead' => 1,
                    'notify_new_property' => 1,
                    'notify_property_status' => 1,
                    'notify_contact_form' => 1,
                    'notify_agendamento' => 1,
                ]
            );

            $settingsId = $this->companySettingsRepository->create($tenantId, $settingsData);

            if (!$settingsId) {
                throw new Exception('Falha ao criar configurações de empresa');
            }

            error_log(sprintf('TenantOnboardingService::registerNewTenant company settings created id=%d tenant_id=%d', $settingsId, $tenantId));

            // 4. Criar estágios de pipeline padrão
            try {
                $pipelineIds = $this->pipelineStageRepository->createDefaults($tenantId);
                if (empty($pipelineIds)) {
                    throw new Exception('Falha ao criar estágios de pipeline padrão');
                }

                error_log(sprintf('TenantOnboardingService::registerNewTenant pipeline stages created ids=[%s]', implode(',', $pipelineIds)));
            } catch (Exception $e) {
                throw new Exception('Falha ao criar estágios de pipeline: ' . $e->getMessage());
            }

            // 5. Criar usuário admin via UserService para centralizar regras de domínio
            $userData = [
                'name' => $adminData['name'] ?? 'Administrador',
                'email' => $adminData['email'] ?? '',
                'username' => $adminData['username'] ?? '',
                'password' => $adminData['password'] ?? '',
                'role' => 'admin',
            ];

            $createdUser = $this->userService->createUser($tenantId, $userData, true);
            $userId = $createdUser['id'];

            if (!$userId) {
                throw new Exception('Falha ao criar usuário admin');
            }

            error_log(sprintf('TenantOnboardingService::registerNewTenant admin user created id=%d tenant_id=%d', $userId, $tenantId));

            // 7. Commit da transação
            error_log('TenantOnboardingService::registerNewTenant committing transaction');
            $this->pdo->commit();

            // 8. Retornar dados de sucesso
            return [
                'success' => true,
                'tenant_id' => $tenantId,
                'tenant_name' => $companyData['name'] ?? 'Sem Nome',
                'slug' => $slug,
                'admin_user_id' => $userId,
                'admin_email' => $adminData['email'] ?? '',
                'message' => 'Tenant e configurações criados com sucesso',
            ];
        } catch (Exception $e) {
            error_log(sprintf('TenantOnboardingService::registerNewTenant error: %s', $e->getMessage()));
            // Rollback em caso de erro
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw new Exception('Erro ao registrar novo tenant: ' . $e->getMessage());
        }
    }

    /**
     * Obter dados de onboarding iniciais padrão
     *
     * Retorna estrutura padrão esperada para validação no controller
     *
     * @return array Estrutura esperada de dados
     */
    public static function getExpectedStructure(): array
    {
        return [
            'companyData' => [
                'name' => '',
                'website' => '',
                'email_company' => '',
                'phone' => '',
                'description' => '',
                'address' => '',
                'city' => '',
                'state' => '',
                'zip_code' => '',
                'country' => 'Brasil',
                'cnpj' => '',
                'logo' => null,
                'primary_color' => '#007bff',
                'secondary_color' => '#6c757d',
            ],
            'adminData' => [
                'name' => '',
                'email' => '',
                'username' => '',
                'password' => '',
            ],
        ];
    }
}
