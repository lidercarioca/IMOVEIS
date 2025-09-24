<?php
require_once 'check_auth.php';
checkApiAuth();

// Tratamento do campo 'area' para formato DECIMAL(10,2)
if (isset($_POST['area'])) {
    $_POST['area'] = number_format((float)str_replace(',', '.', $_POST['area']), 2, '.', '');
}

require_once '../config/database.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método inválido");
    }

    // Sanitiza e obtém dados
    $title = $_POST['title'] ?? '';
    $price = isset($_POST['price']) ? floatval($_POST['price']) : 0;
    $location = $_POST['location'] ?? '';
    $type = $_POST['type'] ?? '';
    $transactionType = $_POST['transactionType'] ?? '';
    $area = isset($_POST['area']) ? floatval(str_replace(',', '.', $_POST['area'])) : 0;
    $yearBuilt = $_POST['yearBuilt'] ?? date('Y');
    $description = $_POST['description'] ?? '';
    $bedrooms = isset($_POST['bedrooms']) ? intval($_POST['bedrooms']) : 0;
    $bathrooms = isset($_POST['bathrooms']) ? intval($_POST['bathrooms']) : 0;
    $garage = isset($_POST['garage']) ? $_POST['garage'] : null;
    $neighborhood = $_POST['neighborhood'] ?? '';
    $city = $_POST['city'] ?? '';
    $state = $_POST['state'] ?? '';
    $zip = $_POST['zip'] ?? '';
    $status = $_POST['status'] ?? '';
    
    $features = $_POST['features'] ?? '';
    // Sempre salvar como JSON no banco
    if (is_array($features)) {
        $features = json_encode($features, JSON_UNESCAPED_UNICODE);
    } else {
        $decoded = json_decode($features, true);
        if (is_array($decoded)) {
            $features = json_encode($decoded, JSON_UNESCAPED_UNICODE);
        } else if (is_string($features) && strlen(trim($features)) > 0) {
            // Se for string separada por vírgula, transformar em array e salvar como JSON
            $arr = array_map('trim', explode(',', $features));
            $features = json_encode($arr, JSON_UNESCAPED_UNICODE);
        } else {
            $features = json_encode([], JSON_UNESCAPED_UNICODE);
        }
    }

    // LOG dos dados recebidos para debug
    file_put_contents(__DIR__ . '/../property_debug.log',
        date('Y-m-d H:i:s') . "\n" .
        print_r([
            'title' => $title,
            'price' => $price,
            'location' => $location,
            'type' => $type,
            'transactionType' => $transactionType,
            'area' => $area,
            'yearBuilt' => $yearBuilt,
            'description' => $description,
            'bedrooms' => $bedrooms,
            'bathrooms' => $bathrooms,
            'garage' => $garage,
            'neighborhood' => $neighborhood,
            'city' => $city,
            'state' => $state,
            'zip' => $zip,
            'status' => $status,
            'features' => $features
        ], true) . "\n\n",
        FILE_APPEND
    );

    // Validação simples
    if (empty($title) || empty($price) || empty($location)) {
        throw new Exception("Campos obrigatórios faltando: " . json_encode([
            'title' => $title,
            'price' => $price,
            'location' => $location
        ]));
    }

   
    // Insere imóvel
    $stmt = $pdo->prepare("INSERT INTO properties (title, price, location, type, transactionType, area, yearBuilt, description, bedrooms, bathrooms, garage, neighborhood, city, state, zip, status, features) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt->execute([$title, $price, $location, $type, $transactionType, $area, $yearBuilt, $description, $bedrooms, $bathrooms, $garage, $neighborhood, $city, $state, $zip, $status, $features])) {
        $errorInfo = $stmt->errorInfo();
        throw new Exception("Erro ao inserir no banco: " . $errorInfo[2]);
    }

    $propertyId = $pdo->lastInsertId();

    // Processa novas imagens
    if (isset($_FILES['imagens'])) {
        $targetDir = "../assets/imagens/$propertyId/";
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        foreach ($_FILES['imagens']['tmp_name'] as $index => $tmpName) {
            if ($_FILES['imagens']['error'][$index] !== UPLOAD_ERR_OK) continue;
            
            $ext = pathinfo($_FILES['imagens']['name'][$index], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $ext;
            $dest = $targetDir . $filename;

            if (move_uploaded_file($tmpName, $dest)) {
                // Salva imagem no banco
                $stmtImg = $pdo->prepare("INSERT INTO property_images (property_id, image_url) VALUES (?, ?)");
                $stmtImg->execute([$propertyId, $filename]);
            }
        }
    }

    echo json_encode(["success" => true, "message" => "Imóvel adicionado com sucesso.", "id" => $propertyId]);
} catch (Exception $e) {
    http_response_code(400);
    // LOG do erro para debug (mensagem completa + dados recebidos)
    $logMsg = date('Y-m-d H:i:s') . "\nERRO: " . $e->getMessage() . "\n";
    $logMsg .= "POST: " . print_r($_POST, true) . "\n";
    if (!empty($_FILES)) {
        $logMsg .= "FILES: " . print_r($_FILES, true) . "\n";
    }
    file_put_contents(__DIR__ . '/../property_debug.log', $logMsg . "\n", FILE_APPEND);
    $errorMsg = $e->getMessage();
    echo json_encode(["success" => false, "error" => $errorMsg]);
}
