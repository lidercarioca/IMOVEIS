<?php
// api/getContactInfo.php
header('Content-Type: application/json');
require_once '../config/database.php';

try {
    $stmt = $pdo->query('SELECT 
        company_phone,
        company_whatsapp,
        company_email,
        company_address,
        company_weekday_hours,
        company_saturday_hours
    FROM company_settings WHERE id = 1 LIMIT 1');
    
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode([
            'success' => true,
            'data' => $row
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Não foi possível encontrar as informações de contato'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar informações de contato'
    ]);
}
