<?php
header('Content-Type: application/json');
require_once '../config/database.php';

try {
    // Validação do ID
    $id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : false;
    if ($id === false) {
        throw new Exception('ID inválido');
    }

    // Busca a propriedade
    $stmt = $pdo->prepare("SELECT * FROM properties WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new Exception('Imóvel não encontrado');
    }

    // Buscar imagens
    $imgStmt = $pdo->prepare("SELECT image_url FROM property_images WHERE property_id = :property_id");
    $imgStmt->execute([':property_id' => $id]);
    $row['images'] = $imgStmt->fetchAll(PDO::FETCH_COLUMN);

    // Garantir que os dados numéricos sejam retornados como números
    if (isset($row['price'])) {
        $row['price'] = floatval($row['price']);
    }
    if (isset($row['area'])) {
        $row['area'] = floatval($row['area']);
    }
    if (isset($row['condominium'])) {
        $row['condominium'] = is_null($row['condominium']) ? null : floatval($row['condominium']);
    } else {
        $row['condominium'] = null;
    }
    if (isset($row['iptu'])) {
        $row['iptu'] = is_null($row['iptu']) ? null : floatval($row['iptu']);
    } else {
        $row['iptu'] = null;
    }
    if (isset($row['suites'])) {
        $row['suites'] = is_null($row['suites']) ? null : intval($row['suites']);
    } else {
        $row['suites'] = null;
    }

    // Decodificar features se estiver em JSON
    if (!empty($row['features'])) {
        $decoded = json_decode($row['features'], true);
        $row['features'] = is_array($decoded) ? $decoded : [$row['features']];
    } else {
        $row['features'] = [];
    }

    // Garantir campos padrão
    $defaultFields = ['neighborhood', 'city', 'state', 'zip'];
    foreach ($defaultFields as $field) {
        if (!isset($row[$field])) $row[$field] = '';
    }

    echo json_encode([
        'success' => true,
        'data' => $row
    ]);

} catch (PDOException $e) {
    error_log('Erro de banco ao buscar imóvel: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar detalhes do imóvel'
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
