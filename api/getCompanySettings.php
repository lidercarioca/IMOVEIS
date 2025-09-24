<?php
// api/getCompanySettings.php
header('Content-Type: application/json');
require_once '../config/database.php';

// Ativa exibição de erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

$stmt = $pdo->query('SELECT * FROM company_settings WHERE id = 1 LIMIT 1');
if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Loga o array vindo do banco para depuração
    file_put_contents(__DIR__ . '/../horas.log', date('c') . "\n" . print_r($row, true) . "\n", FILE_APPEND);
    // Garante que os campos de horário de atendimento existam no retorno
    $row['company_weekday_hours'] = isset($row['company_weekday_hours']) ? $row['company_weekday_hours'] : '';
    $row['company_saturday_hours'] = isset($row['company_saturday_hours']) ? $row['company_saturday_hours'] : '';
    // Ajusta o caminho do logo se existir
    if (!empty($row['company_logo'])) {
        $fullPath = __DIR__ . '/../' . $row['company_logo'];
        if (!file_exists($fullPath)) {
            $row['logo_error'] = "Arquivo não encontrado: " . $fullPath;
        }
    } else {
        // Se não houver logo definido, usa o padrão
        $row['company_logo'] = 'assets/imagens/logo/logo.png';
    }
    // Garante que os campos de e-mail e notificações existam no retorno
    $row['email_notifications'] = $row['email_notifications'] ?? '';
    $row['email_leads'] = $row['email_leads'] ?? '';
    $row['notify_new_lead'] = isset($row['notify_new_lead']) ? (int)$row['notify_new_lead'] : 1;
    $row['notify_new_property'] = isset($row['notify_new_property']) ? (int)$row['notify_new_property'] : 1;
    $row['notify_property_status'] = isset($row['notify_property_status']) ? (int)$row['notify_property_status'] : 1;
    $row['notify_contact_form'] = isset($row['notify_contact_form']) ? (int)$row['notify_contact_form'] : 1;
    // Garante que a descrição da empresa existe
    if (!isset($row['company_description']) || empty($row['company_description'])) {
        $row['company_description'] = "Há mais de 15 anos no mercado, a RR Imóveis se destaca pela excelência e compromisso com nossos clientes. Nossa missão é encontrar o imóvel perfeito para você e sua família, com atendimento personalizado e as melhores condições do mercado.";
    }

    echo json_encode(['success' => true, 'data' => $row, 'debug' => [
        'logo_path' => $row['company_logo'],
        'has_description' => isset($row['company_description']),
        'description_length' => strlen($row['company_description'] ?? ''),
        'full_path' => $fullPath ?? null,
        'file_exists' => file_exists($fullPath ?? '') ? 'sim' : 'não'
    ]]);
} else {
    echo json_encode(['success' => false, 'message' => 'Configurações não encontradas.']);
}
