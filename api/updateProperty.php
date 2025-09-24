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
        neighborhood = :neighborhood,
        city = :city,
        state = :state,
        zip = :zip,
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
        ':neighborhood' => $neighborhood,
        ':city' => $city,
        ':state' => $state,
        ':zip' => $zip,
        ':status' => $status,
        ':features' => $features,
        ':id' => $id
    ])) {
        $errorInfo = $stmt->errorInfo();
        throw new Exception("Erro ao atualizar no banco: " . $errorInfo[2]);
    }

    // Se o status mudou para vendido ou alugado, cria notificação
    if (($status == 'vendido' || $status == 'alugado') && $oldStatus != $status) {
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
        foreach ($_FILES['imagens']['tmp_name'] as $index => $tmpName) {
            if ($_FILES['imagens']['error'][$index] !== UPLOAD_ERR_OK) continue;
            $hashNova = md5_file($tmpName);
            if (in_array($hashNova, $hashesExistentes)) {
                // Já existe uma imagem igual, não salva novamente
                continue;
            }
            $ext = pathinfo($_FILES['imagens']['name'][$index], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $ext;
            $dest = $targetDir . $filename;
            if (move_uploaded_file($tmpName, $dest)) {
                $stmtImg = $pdo->prepare("INSERT INTO property_images (property_id, image_url) VALUES (?, ?)");
                $stmtImg->execute([$id, $filename]);
            }
        }
    }

    echo json_encode(["success" => true, "message" => "Imóvel atualizado com sucesso."]);
    exit;

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
    exit;
}
