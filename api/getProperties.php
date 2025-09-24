<?php
header('Content-Type: application/json');
require_once '../config/database.php';

try {

    // Consulta principal
    $stmt = $pdo->query("SELECT * FROM properties");

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
        ];
    }

    echo json_encode($properties, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    echo json_encode(['error' => 'Erro de conexão: ' . $e->getMessage()]);
}
?>
