<?php
header("Content-Type: application/json");
require_once '../config/database.php';
require_once '../app/utils/NotificationManager.php';

try {
    // Receber os dados do formulário (JSON)
    $data = json_decode(file_get_contents("php://input"), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Dados JSON inválidos");
    }

    // Validação básica
    if (!isset($data['name'], $data['email'], $data['phone'], $data['message'])) {
        throw new Exception("Dados incompletos");
    }

// property_id pode ser null
if (!isset($data['property_id']) || $data['property_id'] === '' || is_null($data['property_id'])) {
    $data['property_id'] = null;
}

$status = "new";
$created_at = date('Y-m-d H:i:s');
$updated_at = $created_at;
$source = isset($data['source']) ? $data['source'] : 'site';


    $db = new Database();
    $pdo = $db->connect();

    // Inserir o lead no banco de dados usando PDO
    $stmt = $pdo->prepare("INSERT INTO leads (name, email, phone, property_id, message, status, created_at, updated_at, source) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$data['name'], $data['email'], $data['phone'], $data['property_id'], $data['message'], $status, $created_at, $updated_at, $source]);
    
    // Obtém o ID do lead inserido
    $leadId = $pdo->lastInsertId();

    // Se houver propriedade vinculada, busca o título e usuário atribuído
    $propertyTitle = null;
    $assignedUserId = null;
    if ($data['property_id']) {
        $stmtProp = $pdo->prepare("SELECT title, assigned_user_id FROM properties WHERE id = ?");
        $stmtProp->execute([$data['property_id']]);
        if ($row = $stmtProp->fetch(PDO::FETCH_ASSOC)) {
            $propertyTitle = $row['title'];
            $assignedUserId = $row['assigned_user_id'];
        }
    }

    // Se houver um usuário atribuído ao imóvel, atribui o lead ao mesmo usuário
    if ($assignedUserId) {
        $stmtUpdate = $pdo->prepare("UPDATE leads SET assigned_user_id = ? WHERE id = ?");
        $stmtUpdate->execute([$assignedUserId, $leadId]);
    }

    // Cria a notificação do lead
    $notificationManager = new NotificationManager($pdo);
    $notificationManager->notifyNewLead($data['name'], $data['email'], $propertyTitle);

    // Se houver um usuário atribuído ao imóvel, cria notificação para ele também
    if ($assignedUserId) {
        $preview = mb_substr($data['message'], 0, 100) . (mb_strlen($data['message']) > 100 ? '...' : '');
        $notificationManager->createNotification(
            'lead',
            "Novo Interesse: {$propertyTitle}",
            "Novo contato de {$data['name']} ({$data['email']}) no imóvel: {$propertyTitle}\n\nMensagem: " . $preview,
            'painel.php?tab=leads',
            $assignedUserId
        );
    }

    // Salva a mensagem do lead
    if (!empty($data['message'])) {
        $subject = $propertyTitle ? "Interesse no imóvel: " . $propertyTitle : "Novo contato do site";
        
        // Salva a mensagem sem associar a um usuário específico
        $messageId = $notificationManager->createMessage(
            $data['name'],
            $data['email'],
            $subject,
            $data['message'],
            $data['property_id'],
            null // Não associa a nenhum usuário específico
        );

        // Se salvou e retornou o ID, cria uma notificação vinculada à mensagem
        if ($messageId) {
            $preview = mb_substr($data['message'], 0, 100) . (mb_strlen($data['message']) > 100 ? '...' : '');
            $notificationManager->createNotification(
                'contact',
                "Nova Mensagem de Contato",
                "Mensagem recebida de {$data['name']} ({$data['email']}): " . $preview,
                'painel.php?tab=messages&id=' . $messageId
            );
        }
    }

    echo json_encode([
        "success" => true, 
        "message" => "Lead adicionado com sucesso"
    ]);

} catch (Exception $e) {
    error_log("Erro ao adicionar lead: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erro ao adicionar lead",
        "error" => $e->getMessage()
    ]);
}
?>
