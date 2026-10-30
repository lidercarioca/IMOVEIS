<?php
// api/getContactInfo.php
require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';

use App\Http\ApiResponse;

// Detectar se quer resposta padronizada
$useStandardResponse = isset($_GET['standard_response']) && $_GET['standard_response'] === '1';

// Headers para resposta legada (se não usar padronizada)
if (!$useStandardResponse) {
    header('Content-Type: application/json');
}

try {
    initializeTenantMiddleware($pdo);
    $tenantId = getCurrentTenantId();
    $service = new \App\Service\CompanySettingsService($pdo);
    $row = $service->getContactInfoForTenant($tenantId);

    if ($row) {
        // Usar resposta padronizada se solicitado
        if ($useStandardResponse) {
            ApiResponse::success(
                $row,
                'Informações de contato carregadas com sucesso',
                ['source' => 'company_settings']
            );
        }

        // Resposta legada (padrão)
        echo json_encode([
            'success' => true,
            'data' => $row
        ]);
    } else {
        // Usar resposta padronizada se solicitado
        if ($useStandardResponse) {
            ApiResponse::notFound('Informações de contato não encontradas');
        }

        // Resposta legada (padrão)
        echo json_encode([
            'success' => false,
            'message' => 'Não foi possível encontrar as informações de contato'
        ]);
    }
} catch (PDOException $e) {
    // Usar resposta padronizada se solicitado
    if ($useStandardResponse) {
        ApiResponse::error(
            'Erro ao buscar informações de contato',
            500,
            [],
            null
        );
    }

    // Resposta legada (padrão)
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar informações de contato'
    ]);
}
