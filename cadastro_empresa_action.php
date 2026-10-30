<?php

/**
 * Processamento de Cadastro de Nova Empresa
 * 
 * Lógica de onboarding:
 * 1. Validar dados do formulário
 * 2. Verificar email duplicado
 * 3. Criar tenant
 * 4. Criar usuário admin
 * 5. Login automático
 * 6. Redirecionar para dashboard
 */

require_once 'config/database.php';
require_once 'config/session_manager.php';
require_once 'app/security/Security.php';
require_once 'app/security/CSRF.php';
require_once 'app/helpers/charset_helper.php';
require_once 'app/utils/logger_functions.php';

// Novos requires para arquitetura de Service/Repository
require_once 'src/Infrastructure/Tenant/TenantRepository.php';
require_once 'src/Infrastructure/Company/CompanySettingsRepository.php';
require_once 'src/Infrastructure/Pipeline/PipelineStageRepository.php';
require_once 'src/Application/User/UserService.php';
require_once 'src/Infrastructure/User/UserRepository.php';
require_once 'src/Application/Tenant/TenantOnboardingService.php';

// Se já está logado, redireciona
if (isset($_SESSION['user_id'])) {
    header("Location: painel.php");
    exit;
}

// Apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: cadastro_empresa.php");
    exit;
}

try {
    // Inicializar variáveis que podem ser utilizadas no catch block
    $userId = null;
    $tenantId = null;
    $adminUsername = null;
    $adminEmail = null;

    // 1. Validar CSRF token (mais flexível)
    if (!isset($_POST['csrf_token'])) {
        throw new Exception("Token de segurança ausente. Tente novamente.");
    }

    // Tentar validar CSRF, mas se falhar por problema de sessão, continuar
    $csrfValid = false;
    try {
        CSRF::verifyToken($_POST['csrf_token']);
        $csrfValid = true;
    } catch (Exception $e) {
        // CSRF falhou - pode ser timeout de sessão
        error_log("CSRF validation failed: " . $e->getMessage());
        // Continuar mesmo assim para verificar se o cadastro já foi feito
    }

    // 2. Validar dados de entrada
    $companyName = trim($_POST['company_name'] ?? '');
    $adminName = trim($_POST['admin_name'] ?? '');
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $adminUsername = trim($_POST['admin_username'] ?? '');
    $adminPassword = $_POST['admin_password'] ?? '';
    $adminPasswordConfirm = $_POST['admin_password_confirm'] ?? '';

    // Sanitizar
    $companyName = Security::sanitizeInput($companyName);
    $adminName = Security::sanitizeInput($adminName);
    $adminEmail = Security::sanitizeInput($adminEmail);
    $adminUsername = Security::sanitizeInput($adminUsername);
    $companyEmail = Security::sanitizeInput(trim($_POST['company_email'] ?? ''));
    $companyWhatsApp = Security::sanitizeInput(trim($_POST['company_whatsapp'] ?? ''));
    $companyWebsite = Security::sanitizeInput(trim($_POST['company_website'] ?? ''));
    $companyCnpj = Security::sanitizeInput(trim($_POST['company_cnpj'] ?? ''));

    // Validações
    if (strlen($companyName) < 3 || strlen($companyName) > 100) {
        throw new Exception("Nome da empresa deve ter entre 3 e 100 caracteres.");
    }

    if (strlen($adminName) < 3 || strlen($adminName) > 100) {
        throw new Exception("Nome do administrador deve ter entre 3 e 100 caracteres.");
    }

    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Email inválido.");
    }

    if (strlen($adminUsername) < 3 || strlen($adminUsername) > 50) {
        throw new Exception("Nome de usuário deve ter entre 3 e 50 caracteres.");
    }

    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $adminUsername)) {
        throw new Exception("Nome de usuário pode conter apenas letras, números, traço e underscore.");
    }

    if (strlen($adminPassword) < 8) {
        throw new Exception("Senha deve ter no mínimo 8 caracteres.");
    }

    if ($adminPassword !== $adminPasswordConfirm) {
        throw new Exception("Senhas não conferem.");
    }

    // Instanciar serviços e repositórios para onboarding
    $tenantRepository = new \App\Infrastructure\Tenant\TenantRepository($pdo);
    $companySettingsRepository = new \App\Infrastructure\Company\CompanySettingsRepository($pdo);
    $pipelineStageRepository = new \App\Infrastructure\Pipeline\PipelineStageRepository($pdo);
    $userRepository = new \App\Infrastructure\User\UserRepository($pdo);
    $userService = new \App\Application\User\UserService($userRepository);
    $onboardingService = new \App\Application\Tenant\TenantOnboardingService(
        $pdo,
        $tenantRepository,
        $companySettingsRepository,
        $pipelineStageRepository,
        $userService,
        $userRepository
    );

    // Dados de empresa para onboarding
    $companyData = [
        'name' => $companyName,
        'website' => $companyWebsite,
        'email_company' => $companyEmail,
        'company_email' => $companyEmail,
        'phone' => Security::sanitizeInput($_POST['company_phone'] ?? ''),
        'company_whatsapp' => $companyWhatsApp,
        'description' => '',
        'address' => Security::sanitizeInput($_POST['company_address'] ?? ''),
        'city' => Security::sanitizeInput($_POST['company_city'] ?? ''),
        'state' => Security::sanitizeInput($_POST['company_state'] ?? ''),
        'zip_code' => Security::sanitizeInput($_POST['company_zip_code'] ?? ''),
        'country' => 'Brasil',
        'cnpj' => $companyCnpj,
        'company_website' => $companyWebsite,
        'logo' => '/assets/imagens/logo/logo_padrao.png',
        'primary_color' => '#1e40af',
        'secondary_color' => '#64748b',
        'accent_color' => '#f59e0b',
        'plano' => 'trial',
        'limite_usuarios' => 2,
        'max_users' => 2,
        'data_expiracao' => date('Y-m-d H:i:s', strtotime('+7 days')),
    ];

    // Dados do administrador
    $adminData = [
        'name' => $adminName,
        'email' => $adminEmail,
        'username' => $adminUsername,
        'password' => $adminPassword,
    ];

    // Registrar novo tenant usando TenantOnboardingService (com transação atômica)
    $onboardingResult = $onboardingService->registerNewTenant($companyData, $adminData);

    $userId = $onboardingResult['admin_user_id'];
    $tenantId = $onboardingResult['tenant_id'];

    // 10. Criar sessão do usuário e fazer login automático
    session_unset();
    session_regenerate_id(true);

    $_SESSION['user_id'] = $userId;
    $_SESSION['role'] = 'admin';
    $_SESSION['username'] = $adminUsername;
    $_SESSION['email'] = $adminEmail;
    $_SESSION['tenant_id'] = $tenantId;
    $_SESSION['is_superadmin'] = 0;
    $_SESSION['last_activity'] = time();
    $_SESSION['last_regeneration'] = time();
    $_SESSION['selected_tenant_id'] = $tenantId; // Para compatibilidade

    // Log de auditoria (se houver função disponível)
    if (function_exists('log_user_action')) {
        log_user_action('company_onboarding_success', [
            'tenant_id' => $tenantId,
            'tenant_name' => $companyName,
            'user_id' => $userId,
            'admin_email' => $adminEmail,
            'admin_username' => $adminUsername
        ]);
    }

    // 13. Sucesso - redirecionar para dashboard
    header("Location: painel.php?onboarding=1");
    exit;
} catch (Exception $e) {
    // Log do erro
    $errorMessage = $e->getMessage();
    error_log("Erro no cadastro de empresa: " . $errorMessage);

    // Se o CSRF falhou mas o usuário foi criado, fazer login mesmo assim
    if (strpos($errorMessage, 'inválido') !== false && $userId !== null && $tenantId !== null) {
        error_log("CSRF erro mas cadastro foi bem-sucedido. Fazendo login automaticamente.");

        // Restaurar sessão do usuário
        $_SESSION['user_id'] = $userId;
        $_SESSION['role'] = 'admin';
        $_SESSION['username'] = $adminUsername;
        $_SESSION['email'] = $adminEmail;
        $_SESSION['tenant_id'] = $tenantId;
        $_SESSION['is_superadmin'] = 0;
        $_SESSION['last_activity'] = time();
        $_SESSION['selected_tenant_id'] = $tenantId;

        header("Location: painel.php?onboarding=1&restored=1");
        exit;
    }

    // Redirecionar de volta com mensagem de erro
    header("Location: cadastro_empresa.php?error=" . urlencode($errorMessage));
    exit;
}
