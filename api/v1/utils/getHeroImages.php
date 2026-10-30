<?php
require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/security/TenantContext.php';

use App\Http\ApiResponse;
use App\Service\PropertyService;

// Detectar se quer resposta padronizada
$useStandardResponse = isset($_GET['standard_response']) && $_GET['standard_response'] === '1';

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

    \TenantContext::initialize();
    $tenantId = \TenantContext::getTenantId();
    if ($tenantId === null || $tenantId <= 0) {
        throw new Exception('Tenant não resolvido para hero images');
    }

    $service = new PropertyService($pdo);

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
                    $property = $service->getPropertyByIdForApi((int)$folder, ['tenantId' => $tenantId, 'isAdmin' => false]);
                    if ($property === null) {
                        continue;
                    }

                    $heroImages[] = [
                        'path' => "assets/imagens/$folder/" . $images[0],
                        'id' => (int)$folder,
                        'title' => $property['title'] ?? 'Sem título'
                    ];
                } catch (Exception $e) {
                    error_log("Erro na consulta do imóvel ID $folder: " . $e->getMessage());
                    continue;
                }
            }
        }
    }

    if (empty($heroImages)) {
        error_log("Nenhuma imagem hero encontrada");
    }

    // Usar resposta padronizada se solicitado
    if ($useStandardResponse) {
        ApiResponse::success(
            $heroImages,
            'Imagens de destaque carregadas com sucesso',
            ['count' => count($heroImages)]
        );
    }

    // Resposta legada (padrão)
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'images' => $heroImages
    ]);
} catch (Exception $e) {
    error_log("Erro em getHeroImages.php: " . $e->getMessage());

    // Usar resposta padronizada se solicitado
    if ($useStandardResponse) {
        ApiResponse::error(
            'Erro ao buscar imagens dos imóveis',
            500,
            [],
            null
        );
    }

    // Resposta legada (padrão)
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar imagens dos imóveis',
        'message' => $e->getMessage()
    ]);
}
