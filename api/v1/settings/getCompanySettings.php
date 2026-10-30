<?php
header('Content-Type: application/json; charset=UTF-8');
// Para modo público (sem autenticação), cache é menor
header('Cache-Control: public, max-age=300');
// Para modo autenticado, sem cache
header('Pragma: no-cache');
// Variar por tenant ID parameter e cookies (sessão)
header('Vary: Host, Accept-Encoding, Cookie');
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/session_manager.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';
require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';

use App\Application\Integration\PortalIntegrationService;
use App\Infrastructure\PortalIntegration\PortalIntegrationRepository;
use App\Service\CompanySettingsService;

// Desabilita exibição de erros para API
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// =============================================================================
// MODO: Público vs Autenticado
// =============================================================================
// Se há ?tenant_id=X ou acesso via subdomínio público, permite modo público (sem autenticação)
$isPublicMode = (isset($_REQUEST['tenant_id']) && intval($_REQUEST['tenant_id']) > 0);

if (!$isPublicMode && !isLoggedIn()) {
    error_log("⚠️ getCompanySettings: Usuário não autenticado e sem tenant_id público");
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

// =============================================================================
// DETERMINAR TENANT_ID
// =============================================================================
$tenantId = getRequestTenantId();
error_log("[getCompanySettings] Final tenant_id determinado: " . $tenantId);

// Função helper para aplicar defaults aos dados de configuração
function applySettingsDefaults(&$data, $tenantId)
{
    $defaults = [
        'company_weekday_hours' => '',
        'company_saturday_hours' => '',
        'company_logo' => '/assets/imagens/logo/logo_padrao.png',
        'company_color1' => '#1e40af',
        'company_color2' => '#64748b',
        'company_color3' => '#f59e0b',
        'email_notifications' => '',
        'email_leads' => '',
        'notify_new_lead' => 1,
        'notify_new_property' => 1,
        'notify_property_status' => 1,
        'notify_contact_form' => 1,
        'company_description' => "Há mais de 15 anos no mercado, a RR Imóveis se destaca pela excelência e compromisso com nossos clientes. Nossa missão é encontrar o imóvel perfeito para você e sua família, com atendimento personalizado e as melhores condições do mercado."
    ];

    foreach ($defaults as $key => $default) {
        if (!isset($data[$key]) || empty($data[$key])) {
            $data[$key] = $default;
        }
    }

    if (empty($data['company_logo'])) {
        $data['company_logo'] = '/assets/imagens/logo/logo_padrao.png';
    } elseif (!str_starts_with($data['company_logo'], '/')) {
        $data['company_logo'] = '/' . $data['company_logo'];
    }

    foreach (['company_color1', 'company_color2', 'company_color3'] as $colorField) {
        $data[$colorField] = !empty($data[$colorField]) ? $data[$colorField] : $defaults[$colorField];
    }

    foreach (['notify_new_lead', 'notify_new_property', 'notify_property_status', 'notify_contact_form'] as $notifField) {
        $data[$notifField] = isset($data[$notifField]) ? (int)$data[$notifField] : 1;
    }

    $data['tenant_id'] = $tenantId;
}

try {
    $companySettingsService = new CompanySettingsService($pdo);
    $row = $companySettingsService->getContactInfoForTenant($tenantId) ?? [];

    if ($row !== []) {
        error_log("[getCompanySettings] ✅ Dados encontrados para tenant_id=$tenantId");
        error_log("[getCompanySettings] Company Name: " . ($row['company_name'] ?? ''));
    } else {
        error_log("[getCompanySettings] ⚠️ Nenhum registro encontrado para tenant_id=$tenantId. Retornando valores padrão.");
    }

    applySettingsDefaults($row, $tenantId);

    $portalRepository = new PortalIntegrationRepository($pdo);
    $portalRepository->ensureTableExists();
    $portalService = new PortalIntegrationService($portalRepository);

    $whatsappIntegration = $portalService->getPortalIntegration($tenantId, 'whatsapp_chat');
    $row['whatsapp_chat_integration'] = [
        'enabled' => $whatsappIntegration && $whatsappIntegration['enabled'],
        'credentials' => $whatsappIntegration['credentials'] ?? []
    ];

    echo json_encode(['success' => true, 'data' => $row], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    error_log("[getCompanySettings] ERRO FATAL: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao carregar configurações da empresa',
        'error_code' => 'COMPANY_SETTINGS_ERROR'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
