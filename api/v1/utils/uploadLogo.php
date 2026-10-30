<?php

header('Content-Type: application/json');
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/session_manager.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';

checkAuth();
initializeTenantMiddleware($pdo);

// Define o diretório de destino para a logo (api/v1/utils -> ../../../assets/)
$uploadDir = '../../../assets/imagens/logo/';

// Cria o diretório se ele não existir
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true)) {
        echo json_encode(['success' => false, 'message' => 'Falha ao criar o diretório de uploads.']);
        exit;
    }
}

// Obter tenant_id (usar exclusivamente o tenant autenticado)
// Não confiar em tenant_id enviado pelo cliente via POST para evitar gravação em tenant incorreto
$tenantId = getCurrentTenantId();

// Verifica se um arquivo foi enviado
if (isset($_FILES['logo'])) {
    $file = $_FILES['logo'];

    // Valida erros no upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Erro no upload do arquivo. Código: ' . $file['error']]);
        exit;
    }

    // Valida o tipo de arquivo (MIME type real)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedTypes = ['image/png'];
    if (!in_array($mimeType, $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'Tipo de arquivo não permitido. Apenas arquivos PNG são aceitos.']);
        exit;
    }

    // Verificar se é realmente uma imagem válida
    if (!getimagesize($file['tmp_name'])) {
        echo json_encode(['success' => false, 'message' => 'Arquivo não é uma imagem válida.']);
        exit;
    }

    // Valida o tamanho do arquivo (ex: máximo de 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'O arquivo é muito grande (máximo 5MB).']);
        exit;
    }

    // Buscar o caminho da logo anterior via service (isolamento de tenant preservado)
    $service = new \App\Service\CompanySettingsService($pdo);
    $oldLogoPath = $service->getLogoPathByTenant($tenantId);

    // Deleta a logo anterior se existir e for diferente da nova (path relativo de api/v1/utils/ precisa de ../../../)
    if ($oldLogoPath && file_exists('../../../' . $oldLogoPath)) {
        @unlink('../../../' . $oldLogoPath);
    }

    // Gera um nome de arquivo único para evitar sobreposições
    // Inclui tenant_id no nome para melhor rastreamento
    $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newFileName = 'logo_tenant_' . $tenantId . '.' . $fileExtension;
    $uploadPath = $uploadDir . $newFileName;

    // Move o arquivo para o diretório de destino
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        // Atualiza ou cria o registro no banco de dados via service com isolamento de tenant
        $relativeFilePath = 'assets/imagens/logo/' . $newFileName;
        try {
            $service->upsertLogoPath($tenantId, $relativeFilePath);
        } catch (Exception $e) {
            // Se falhar, ainda retorna sucesso do upload, mas avisa
            echo json_encode([
                'success' => true,
                'filePath' => $relativeFilePath,
                'warning' => 'Logo salva, mas não foi possível atualizar o banco.'
            ]);
            exit;
        }

        echo json_encode(['success' => true, 'filePath' => $relativeFilePath]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Falha ao mover o arquivo para o destino. Verifique as permissões da pasta.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Nenhum arquivo foi enviado.']);
}
