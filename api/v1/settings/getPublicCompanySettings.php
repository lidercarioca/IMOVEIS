<?php
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: public, max-age=300'); // Cache por 5 min para públicos
header('Vary: Host, X-Tenant-ID, Accept-Encoding');
header('Access-Control-Allow-Origin: *');

require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/session_manager.php';
require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';

use App\Application\Integration\PortalIntegrationService;
use App\Infrastructure\PortalIntegration\PortalIntegrationRepository;
use App\Service\CompanySettingsService;

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$tenantId = getRequestTenantId();

try {
    $companySettingsService = new CompanySettingsService($pdo);
    $row = $companySettingsService->getContactInfoForTenant($tenantId) ?? [];

    if (!$row) {
        error_log("⚠️ getPublicCompanySettings: Tenant $tenantId não encontrado. Usando padrões genéricos.");
    }

    $defaults = [
        'company_name' => 'Empresa padrão',
        'company_logo' => 'assets/imagens/logo/logo_padrao.png',
        'company_color1' => '#1e40af',
        'company_color2' => '#64748b',
        'company_color3' => '#f59e0b',
        'company_font' => 'Poppins',
        'company_description' => 'Há mais de 15 anos no mercado, oferecemos os melhores imóveis e atendimento personalizado. Encontre seu imóvel dos sonhos com a gente!',
        'company_phone' => '',
        'company_email' => '',
        'company_whatsapp' => '',
        'company_weekday_hours' => '',
        'company_saturday_hours' => '',
        'company_address' => '',
        'company_instagram' => '',
        'company_facebook' => '',
        'company_linkedin' => '',
        'company_youtube' => ''
    ];

    foreach ($defaults as $key => $default) {
        if (!isset($row[$key]) || empty($row[$key])) {
            $row[$key] = $default;
        }
    }

    if (empty($row['company_logo'])) {
        $row['company_logo'] = 'assets/imagens/logo/logo_padrao.png';
    }

    $row['tenant_id'] = $tenantId;

    try {
        $portalRepository = new PortalIntegrationRepository($pdo);
        $portalRepository->ensureTableExists();
        $portalService = new PortalIntegrationService($portalRepository);

        $whatsappIntegration = $portalService->getPortalIntegration($tenantId, 'whatsapp_chat');
        $googleAnalyticsIntegration = $portalService->getPortalIntegration($tenantId, 'google_analytics');
        $hotjarIntegration = $portalService->getPortalIntegration($tenantId, 'hotjar');

        $row['whatsapp_chat_integration'] = [
            'enabled' => $whatsappIntegration ? (bool)$whatsappIntegration['enabled'] : false,
            'credentials' => $whatsappIntegration ? ($whatsappIntegration['credentials'] ?? []) : []
        ];
        $row['google_analytics_integration'] = [
            'enabled' => $googleAnalyticsIntegration ? (bool)$googleAnalyticsIntegration['enabled'] : false,
            'credentials' => $googleAnalyticsIntegration ? ($googleAnalyticsIntegration['credentials'] ?? []) : []
        ];
        $row['hotjar_integration'] = [
            'enabled' => $hotjarIntegration ? (bool)$hotjarIntegration['enabled'] : false,
            'credentials' => $hotjarIntegration ? ($hotjarIntegration['credentials'] ?? []) : []
        ];
    } catch (Exception $integrationError) {
        error_log("Aviso ao buscar integrações: " . $integrationError->getMessage());
        $row['whatsapp_chat_integration'] = ['enabled' => false, 'credentials' => []];
        $row['google_analytics_integration'] = ['enabled' => false, 'credentials' => []];
        $row['hotjar_integration'] = ['enabled' => false, 'credentials' => []];
    }

    echo json_encode(['success' => true, 'data' => $row], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Exception $e) {
    error_log("Erro em getPublicCompanySettings.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar configurações.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
