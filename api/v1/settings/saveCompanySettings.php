<?php
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/api/check_auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';
require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';

use App\Service\CompanySettingsService;

checkApiAuth();

$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true);

if (!is_array($data)) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$required = [
    'company_name',
    'company_email',
    'company_phone',
    'company_address',
    'company_description'
];
$missing = [];
foreach ($required as $field) {
    if (empty($data[$field]) || trim($data[$field]) === '') {
        $missing[] = $field;
    }
}
if (!empty($missing)) {
    $labels = [
        'company_name' => 'Nome da Empresa',
        'company_email' => 'E-mail Principal',
        'company_phone' => 'Telefone',
        'company_address' => 'Endereço',
        'company_description' => 'Descrição da Empresa'
    ];
    $msg = "Preencha os campos obrigatórios:\n" . implode("\n", array_map(function ($f) use ($labels) {
        return '- ' . ($labels[$f] ?? $f);
    }, $missing));
    echo json_encode(['success' => false, 'message' => $msg, 'missing' => $missing], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$fields = [
    'company_name',
    'company_email',
    'company_email2',
    'company_phone',
    'company_whatsapp',
    'company_address',
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
    'company_weekday_hours',
    'company_saturday_hours',
    'email_notifications',
    'email_leads',
    'notify_new_lead',
    'notify_new_property',
    'notify_property_status',
    'notify_contact_form',
    'notify_agendamento'
];

$tenantId = getRequestTenantId();
$values = [];
foreach ($fields as $f) {
    $values[$f] = isset($data[$f]) && $data[$f] !== '' ? $data[$f] : null;
}

if (!empty($values['company_font'])) {
    $values['company_font'] = trim($values['company_font']);
    if (!preg_match('/^[a-zA-Z0-9 \-_]+$/', $values['company_font'])) {
        $values['company_font'] = null;
    }
}

foreach (['company_color1', 'company_color2', 'company_color3'] as $colorField) {
    if ($values[$colorField]) {
        $color = trim($values[$colorField]);
        $hex = str_replace('#', '', $color);
        if (preg_match('/^[0-9A-Fa-f]{6}$/', $hex)) {
            $values[$colorField] = '#' . $hex;
        } else {
            $values[$colorField] = null;
        }
    }
}

$companySettingsService = new CompanySettingsService($pdo);
$success = $companySettingsService->saveForTenant($tenantId, $values);

if ($success) {
    $retorno = [];
    foreach ($fields as $f) {
        $retorno[$f] = isset($data[$f]) ? $data[$f] : '';
    }
    echo json_encode(['success' => true, 'data' => $retorno], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} else {
    error_log('Erro ao salvar configurações da empresa via CompanySettingsService');
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao salvar. Tente novamente.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
