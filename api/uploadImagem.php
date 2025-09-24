<?php
header('Content-Type: application/json');
require_once '../config/database.php';

try {
    $id = isset($_POST['id']) ? preg_replace('/[^0-9]/', '', $_POST['id']) : '';
    if (!$id) {
        throw new Exception('ID do imóvel não informado.');
    }
$targetDir = __DIR__ . '/../assets/imagens/' . $id . '/';
if (!file_exists($targetDir)) {
  mkdir($targetDir, 0777, true);
}
$uploaded = [];
if (!empty($_FILES['imagens'])) {
  foreach ($_FILES['imagens']['tmp_name'] as $idx => $tmpName) {
    if ($_FILES['imagens']['error'][$idx] !== UPLOAD_ERR_OK) continue;
    $ext = pathinfo($_FILES['imagens']['name'][$idx], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $ext;
    $dest = $targetDir . $filename;
    if (move_uploaded_file($tmpName, $dest)) {
      try {
        // Salva referência no banco
        $stmt = $pdo->prepare('INSERT INTO property_images (property_id, image_url) VALUES (:property_id, :image_url)');
        $stmt->execute([
          ':property_id' => $id,
          ':image_url' => $filename
        ]);
        $uploaded[] = $filename;
      } catch (PDOException $e) {
        error_log("Erro ao salvar imagem no banco: " . $e->getMessage());
        // Remove o arquivo se falhar ao salvar no banco
        unlink($dest);
        continue;
      }
    }
  }
}

    if (count($uploaded) > 0) {
        echo json_encode(['success' => true, 'uploaded_images' => $uploaded]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Nenhuma imagem enviada ou erro no upload.']);
    }
} catch (PDOException $e) {
    error_log("Erro de banco de dados ao fazer upload: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao processar o upload no banco de dados.']);
} catch (Exception $e) {
    error_log("Erro ao fazer upload: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
