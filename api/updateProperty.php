<?php
require_once 'check_auth.php';
checkApiAuth();

// Tratamento do campo 'area' para formato DECIMAL(10,2)
if (isset($_POST['area'])) {
    $_POST['area'] = number_format((float)str_replace(',', '.', $_POST['area']), 2, '.', '');
}

require_once '../auth.php';
require_once '../config/database.php';
require_once '../app/utils/NotificationManager.php';
checkAuth();

// Helper de logging de ações do usuário
require_once __DIR__ . '/../app/utils/logger_functions.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método inválido");
    }

    if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
        throw new Exception("ID do imóvel inválido");
    }
    $id = intval($_POST['id']);

    // Sanitiza e obtém dados
    $title = $_POST['title'] ?? '';
    $price = $_POST['price'] ?? '';
    $location = $_POST['location'] ?? '';
    $type = $_POST['type'] ?? '';
    $transactionType = $_POST['transactionType'] ?? '';
    $area = $_POST['area'] ?? '';
    $yearBuilt = $_POST['yearBuilt'] ?? null;
    $description = $_POST['description'] ?? '';
    $bedrooms = $_POST['bedrooms'] ?? '';
    $bathrooms = $_POST['bathrooms'] ?? '';
    $garage = $_POST['garage'] ?? '';
    $neighborhood = $_POST['neighborhood'] ?? '';
    $city = $_POST['city'] ?? '';
    $state = $_POST['state'] ?? '';
    $zip = $_POST['zip'] ?? '';
    $condominium = $_POST['condominium'] ?? null;
    $iptu = $_POST['iptu'] ?? null;
    $suites = isset($_POST['suites']) && $_POST['suites'] !== '' ? intval($_POST['suites']) : null;
    $assigned_user_id = isset($_POST['assigned_user_id']) && is_numeric($_POST['assigned_user_id']) ? intval($_POST['assigned_user_id']) : null;
    $status = $_POST['status'] ?? '';
    $features = isset($_POST['features']) ? $_POST['features'] : '';
    // Sempre salvar como JSON
    if (is_array($features)) {
        $features = json_encode($features, JSON_UNESCAPED_UNICODE);
    } else {
        $decoded = json_decode($features, true);
        if (is_array($decoded)) {
            $features = json_encode($decoded, JSON_UNESCAPED_UNICODE);
        } else if (is_string($features) && strlen($features) > 0) {
            // Se vier string separada por vírgula, transforma em array e salva como JSON
            $arr = array_map('trim', explode(',', $features));
            $features = json_encode($arr, JSON_UNESCAPED_UNICODE);
        } else {
            $features = json_encode([], JSON_UNESCAPED_UNICODE);
        }
    }


    // Busca status atual do imóvel
    $stmtStatus = $pdo->prepare("SELECT status, title FROM properties WHERE id = :id");
    $stmtStatus->execute([':id' => $id]);
    $currentProperty = $stmtStatus->fetch(PDO::FETCH_ASSOC);
    $oldStatus = $currentProperty['status'];

    // Atualiza imóvel
    // Sanitizar valores monetários (condomínio / iptu)
    function parseCurrencyLocal($val) {
        if ($val === null || $val === '') return null;
        $v = preg_replace('/[^0-9,\.\-]/', '', $val);
        $v = str_replace(',', '.', $v);
        return number_format((float)$v, 2, '.', '');
    }

    $condominiumVal = parseCurrencyLocal($condominium);
    $iptuVal = parseCurrencyLocal($iptu);

    $stmt = $pdo->prepare("UPDATE properties SET 
        title = :title,
        price = :price,
        location = :location,
        type = :type,
        transactionType = :transactionType,
        area = :area,
        yearBuilt = :yearBuilt,
        description = :description,
        bedrooms = :bedrooms,
        bathrooms = :bathrooms,
        garage = :garage,
        condominium = :condominium,
        iptu = :iptu,
        suites = :suites,
        neighborhood = :neighborhood,
        city = :city,
        state = :state,
        zip = :zip,
        assigned_user_id = :assigned_user_id,
        status = :status,
        features = :features 
        WHERE id = :id");
        
    if (!$stmt->execute([
        ':title' => $title,
        ':price' => $price,
        ':location' => $location,
        ':type' => $type,
        ':transactionType' => $transactionType,
        ':area' => $area,
        ':yearBuilt' => $yearBuilt,
        ':description' => $description,
        ':bedrooms' => $bedrooms,
        ':bathrooms' => $bathrooms,
        ':garage' => $garage,
        ':condominium' => $condominiumVal,
        ':iptu' => $iptuVal,
        ':suites' => $suites,
        ':neighborhood' => $neighborhood,
        ':city' => $city,
        ':state' => $state,
        ':zip' => $zip,
        ':status' => $status,
        ':features' => $features,
        ':assigned_user_id' => $assigned_user_id,
        ':id' => $id
    ])) {
        $errorInfo = $stmt->errorInfo();
        throw new Exception("Erro ao atualizar no banco: " . $errorInfo[2]);
    }

    // Atualiza automaticamente os leads associados a este imóvel com o mesmo usuário atribuído
    if ($assigned_user_id !== null) {
        // Se um usuário foi atribuído ao imóvel, atribui também todos os leads deste imóvel ao mesmo usuário
        $stmtLeads = $pdo->prepare("UPDATE leads SET assigned_user_id = :assigned_user_id WHERE property_id = :property_id");
        $stmtLeads->execute([
            ':assigned_user_id' => $assigned_user_id,
            ':property_id' => $id
        ]);
    } else {
        // Se o imóvel foi desatribuído (assigned_user_id = NULL), desatribui também os leads
        $stmtLeads = $pdo->prepare("UPDATE leads SET assigned_user_id = NULL WHERE property_id = :property_id");
        $stmtLeads->execute([':property_id' => $id]);
    }

    // Se o status mudou para vendido, registra a venda e notifica admin
    if ($status == 'vendido' && $oldStatus != $status) {
        // Calcula comissão de 6%
        $propertyPrice = floatval($price);
        $commission = $propertyPrice * 0.06;
        $userName = $_SESSION['username'] ?? 'Usuário desconhecido';
        $userId = $_SESSION['user_id'] ?? null;

        // Registra a venda na tabela
        $stmtSale = $pdo->prepare("
            INSERT INTO property_sales 
            (property_id, user_id, username, property_title, property_price, commission_6percent, status)
            VALUES (:property_id, :user_id, :username, :property_title, :property_price, :commission, 'pending')
        ");
        $stmtSale->execute([
            ':property_id' => $id,
            ':user_id' => $userId,
            ':username' => $userName,
            ':property_title' => $title,
            ':property_price' => $propertyPrice,
            ':commission' => $commission
        ]);
        
        $saleId = $pdo->lastInsertId();

        // Cria notificação detalhada para o admin
        $notificationManager = new NotificationManager($pdo);
        $notificationMessage = sprintf(
            "Venda registrada:\n\n" .
            "Usuário: %s\n" .
            "Imóvel: %s\n" .
            "Valor: R$ %s\n" .
            "Comissão (6%%): R$ %s\n\n" .
            "⚠️ Por favor, gere uma transação financeira para registrar essa comissão.",
            $userName,
            $title,
            number_format($propertyPrice, 2, ',', '.'),
            number_format($commission, 2, ',', '.')
        );
        
        $notificationManager->createNotification(
            'property_sold',
            'Nova venda: ' . $title,
            $notificationMessage,
            '/painel.php?tab=financeiro#sale_' . $saleId
        );
    } elseif ($status == 'alugado' && $oldStatus != $status) {
        // Para aluguel, apenas cria notificação simples
        $notificationManager = new NotificationManager($pdo);
        $notificationManager->notifyPropertyStatus($id, $title, $status);
    }

    // Atualizar imagens, se enviadas
    $targetDir = "../assets/imagens/$id/";
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    // Se o front mandar imagensAntigas, mantém só essas, remove o resto
    if (isset($_POST['imagensAntigas'])) {
        $imagensAntigas = json_decode($_POST['imagensAntigas'], true);
        if (is_array($imagensAntigas)) {
            // Remove do banco e do disco as imagens que não estão em imagensAntigas
            $stmtImgs = $pdo->prepare("SELECT image_url FROM property_images WHERE property_id = :property_id");
            $stmtImgs->execute([':property_id' => $id]);
            $imagensAtuais = $stmtImgs->fetchAll(PDO::FETCH_COLUMN);
            foreach ($imagensAtuais as $img) {
                if (!in_array($img, $imagensAntigas)) {
                    $imgPath = $targetDir . $img;
                    if (file_exists($imgPath)) unlink($imgPath);
                    $pdo->prepare("DELETE FROM property_images WHERE property_id = :property_id AND image_url = :image_url")
                        ->execute([':property_id' => $id, ':image_url' => $img]);
                }
            }
        }
    }

    // Processa novas imagens
    if (isset($_FILES['imagens'])) {
        // Carrega hashes das imagens já existentes para evitar duplicidade
        $hashesExistentes = [];
        $stmtImgs = $pdo->prepare("SELECT image_url FROM property_images WHERE property_id = ?");
        $stmtImgs->execute([$id]);
        $imagensAtuais = $stmtImgs->fetchAll(PDO::FETCH_COLUMN);
        foreach ($imagensAtuais as $img) {
            $imgPath = $targetDir . $img;
            if (file_exists($imgPath)) {
                $hashesExistentes[] = md5_file($imgPath);
            }
        }
        require_once __DIR__ . '/../app/utils/image.php';
        foreach ($_FILES['imagens']['tmp_name'] as $index => $tmpName) {
            if ($_FILES['imagens']['error'][$index] !== UPLOAD_ERR_OK) continue;
            $info = @getimagesize($tmpName);
            if ($info === false) continue;
            $mime = $info['mime'] ?? '';
            $mimeMap = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp'
            ];
            if (!isset($mimeMap[$mime])) continue;
            $hashNova = md5_file($tmpName);
            if (in_array($hashNova, $hashesExistentes)) {
                continue;
            }
            $ext = $mimeMap[$mime];
            $filename = uniqid() . '.' . $ext;
            $dest = $targetDir . $filename;
            $reencoded = reencode_image($tmpName, $dest, $mime);
            if ($reencoded) {
                $stmtImg = $pdo->prepare("INSERT INTO property_images (property_id, image_url) VALUES (?, ?)");
                $stmtImg->execute([$id, $filename]);
            }
        }
    }

    echo json_encode(["success" => true, "message" => "Imóvel atualizado com sucesso."]);
    // Log de atualização de imóvel
    log_user_action('property_update', [
        'property_id' => $id,
        'title' => $title,
        'status' => $status
    ]);
    exit;

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
    exit;
}
