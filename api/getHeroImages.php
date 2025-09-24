<?php
require_once '../config/database.php';
header('Content-Type: application/json');

try {
    $baseDir = '../assets/imagens/';
    if (!is_dir($baseDir)) {
        throw new Exception('Diretório de imagens não encontrado');
    }

    $heroImages = [];
    $folders = scandir($baseDir);

    if ($folders === false) {
        throw new Exception('Erro ao ler diretório de imagens');
    }

    foreach ($folders as $folder) {
        if ($folder === '.' || $folder === '..') continue;

        $folderPath = $baseDir . $folder;
        if (is_dir($folderPath) && is_numeric($folder)) {
            // Lista arquivos do diretório
            $files = scandir($folderPath);
            if ($files === false) {
                error_log("Erro ao ler diretório: " . $folderPath);
                continue;
            }

            // Filtra apenas imagens
            $images = array_values(array_filter($files, function ($file) {
                return preg_match('/\.(jpg|jpeg|png|gif)$/i', $file);
            }));

            if (!empty($images)) {
                try {
                    // Consulta o título no banco de dados
                    $stmt = $pdo->prepare("SELECT title FROM properties WHERE id = ?");
                    if (!$stmt->execute([$folder])) {
                        throw new Exception("Erro ao executar consulta para ID " . $folder);
                    }
                    
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $heroImages[] = [
                        'path' => "assets/imagens/$folder/" . $images[0],
                        'id' => (int)$folder,
                        'title' => $row['title'] ?? 'Sem título'
                    ];
                } catch (PDOException $e) {
                    error_log("Erro na consulta do imóvel ID $folder: " . $e->getMessage());
                    continue;
                }
            }
        }
    }

    if (empty($heroImages)) {
        error_log("Nenhuma imagem hero encontrada");
    }

    echo json_encode([
        'success' => true,
        'images' => $heroImages
    ]);

} catch (Exception $e) {
    error_log("Erro em getHeroImages.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar imagens dos imóveis',
        'message' => $e->getMessage()
    ]);
}