<?php
require_once 'check_auth.php';
checkApiAuth();

// api/saveCompanySettings.php
header('Content-Type: application/json');
require_once '../config/database.php';

// Recebe os dados do frontend (JSON)
$data = json_decode(file_get_contents('php://input'), true);

// Debug - Loga os dados recebidos
$config = require_once __DIR__ . '/../config/logging.php';
$logFile = $config['path'] . $config['files']['settings'];
file_put_contents($logFile, date('Y-m-d H:i:s') . " Dados recebidos:\n" . print_r($data, true) . "\n\n", FILE_APPEND);

// Validação simples

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit;
}

// Validação de campos obrigatórios
$required = [
    'company_name', 'company_email', 'company_phone', 'company_address', 'company_description'
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
    $msg = "Preencha os campos obrigatórios:\n" . implode("\n", array_map(function($f) use ($labels) { return '- ' . ($labels[$f] ?? $f); }, $missing));
    echo json_encode(['success' => false, 'message' => $msg, 'missing' => $missing]);
    exit;
}

// Exemplo de campos (ajuste conforme seu formulário)

$fields = [
    'company_name', 'company_email', 'company_email2', 'company_phone', 'company_whatsapp', 'company_address',
    'company_description', 'company_facebook', 'company_instagram', 'company_linkedin', 'company_youtube',
    'company_logo', 'company_color1', 'company_color2', 'company_color3', 'company_font',
    'company_weekday_hours', 'company_saturday_hours',
    'email_notifications', 'email_leads',
    'notify_new_lead', 'notify_new_property', 'notify_property_status', 'notify_contact_form'
];

// Monta o SQL para update ou insert (1 linha só)
$sql = "REPLACE INTO company_settings (id, " . implode(", ", $fields) . ") VALUES (1, " . str_repeat('?,', count($fields)-1) . "?)";

$stmt = $pdo->prepare($sql);
$params = [];
foreach ($fields as $f) {
    $params[] = isset($data[$f]) && $data[$f] !== '' ? $data[$f] : null;
}

if ($stmt->execute($params)) {
    // Retorna os dados salvos para o frontend atualizar instantaneamente
    $retorno = [];
    foreach ($fields as $f) {
        $retorno[$f] = isset($data[$f]) ? $data[$f] : '';
    }
    echo json_encode(['success' => true, 'data' => $retorno]);
} else {
    $errorInfo = $stmt->errorInfo();
    file_put_contents(__DIR__ . '/../property_debug.log', date('c') . "\n" . print_r($errorInfo, true) . "\nPOST: " . print_r($data, true) . "\nPARAMS: " . print_r($params, true) . "\n", FILE_APPEND);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao salvar.',
        'errorInfo' => $errorInfo,
        'params' => $params
    ]);
}
