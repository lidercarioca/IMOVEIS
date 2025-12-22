<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../auth.php';

try {
    // IMPORTANTE: Sempre validar autenticação ANTES de qualquer operação
    checkAuth();
    
    // Constrói a query com filtro baseado no papel do usuário
    $query = "SELECT * FROM properties";
    
    // Aplica filtro por `assigned_user_id` se não for admin
    if (!isAdmin()) {
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            throw new Exception("Usuário não identificado");
        }
        $query .= " WHERE assigned_user_id = " . intval($userId);
    }
    
    $query .= " ORDER BY id DESC";

    // Consulta principal - ordena do mais novo para o mais antigo (ID descendente)
    $stmt = $pdo->query($query);

    $properties = [];

    while ($row = $stmt->fetch()) {

        // Busca imagens da tabela property_images
        $imovelId = $row['id'];
        $imgStmt = $pdo->prepare("SELECT image_url FROM property_images WHERE property_id = ?");
        $imgStmt->execute([$imovelId]);
        $images = [];
        while ($imgRow = $imgStmt->fetch()) {
            $images[] = "assets/imagens/{$imovelId}/" . $imgRow['image_url'];
        }

        // Verifica e formata o campo 'features'
        $features = json_decode($row['features'], true);
        if (!is_array($features)) {
            $features = [];
        }

        // Prepara os dados do imóvel
        $properties[] = [
            'id'              => $row['id'],
            'title'           => $row['title'],
            'description'     => $row['description'],
            'location'        => $row['location'],
            'neighborhood'    => isset($row['neighborhood']) ? $row['neighborhood'] : '',
            'city'            => isset($row['city']) ? $row['city'] : '',
            'state'           => isset($row['state']) ? $row['state'] : '',
            'zip'             => isset($row['zip']) ? $row['zip'] : '',
            'price'           => $row['price'],
            'type'            => $row['type'],
            'transactionType' => $row['transactionType'],
            'images'          => $images,
            'features'        => $features,
            'bedrooms'        => $row['bedrooms'],
            'bathrooms'       => $row['bathrooms'],
            'garage'          => isset($row['garage']) ? $row['garage'] : '',
            'area'            => $row['area'],
            'yearBuilt'       => $row['yearBuilt'],
            'status'          => isset($row['status']) ? (($row['status'] === 'active' || $row['status'] === 'activ') ? 'Ativo' : $row['status']) : '',
            'condominium'     => isset($row['condominium']) ? (float)$row['condominium'] : null,
            'iptu'            => isset($row['iptu']) ? (float)$row['iptu'] : null,
            'suites'          => isset($row['suites']) ? intval($row['suites']) : null,
            'assigned_user_id' => isset($row['assigned_user_id']) ? $row['assigned_user_id'] : null,
        ];
    }

    echo json_encode($properties, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    echo json_encode(['error' => 'Erro de conexão: ' . $e->getMessage()]);
}
?>
