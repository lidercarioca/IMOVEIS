<?php
require_once dirname(__DIR__) . '/config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    error_log("[PIPELINE INIT] Iniciando criação de estágios de pipeline");
    
    // Definir estágios padrão (o mesmo padrão usado em tenant_id=1)
    $defaultStages = [
        [
            'name' => 'Prospecto',
            'description' => 'Lead inicial, não qualificado',
            'position' => 0,
            'color' => '#EF4444'
        ],
        [
            'name' => 'Qualificado',
            'description' => 'Lead validado e com interesse',
            'position' => 1,
            'color' => '#F97316'
        ],
        [
            'name' => 'Apresentação',
            'description' => 'Imóvel apresentado ao cliente',
            'position' => 2,
            'color' => '#FBBF24'
        ],
        [
            'name' => 'Proposta',
            'description' => 'Proposta de venda enviada',
            'position' => 3,
            'color' => '#60A5FA'
        ],
        [
            'name' => 'Negociação',
            'description' => 'Em discussão de termos',
            'position' => 4,
            'color' => '#8B5CF6'
        ],
        [
            'name' => 'Fechado',
            'description' => 'Venda concluída com sucesso',
            'position' => 5,
            'color' => '#10B981'
        ],
        [
            'name' => 'Perdido',
            'description' => 'Lead perdido / sem interesse',
            'position' => 6,
            'color' => '#6B7280'
        ]
    ];

    // Buscar todos os tenants que existem
    $stmt = $pdo->prepare("SELECT DISTINCT id FROM tenants WHERE status = 'active' ORDER BY id");
    $stmt->execute();
    $tenants = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $results = [];
    foreach ($tenants as $tenantId) {
        // Verificar se este tenant já tem estágios
        $checkStmt = $pdo->prepare("SELECT COUNT(*) as total FROM pipeline_stages WHERE tenant_id = ?");
        $checkStmt->execute([$tenantId]);
        $count = $checkStmt->fetch(PDO::FETCH_ASSOC)['total'];

        if ($count === 0 || $count == '0') {
            // Inserir estágios padrão para este tenant
            $insertStmt = $pdo->prepare("
                INSERT INTO pipeline_stages 
                (tenant_id, name, description, position, color, is_active, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())
            ");

            $stagesInserted = 0;
            foreach ($defaultStages as $stage) {
                $insertStmt->execute([
                    $tenantId,
                    $stage['name'],
                    $stage['description'],
                    $stage['position'],
                    $stage['color']
                ]);
                $stagesInserted++;
            }

            $results[] = [
                'tenant_id' => $tenantId,
                'success' => true,
                'stages_inserted' => $stagesInserted,
                'message' => "Estágios criados com sucesso"
            ];
            error_log("[PIPELINE INIT] ✓ Tenant {$tenantId}: {$stagesInserted} estágios inseridos");
        } else {
            $results[] = [
                'tenant_id' => $tenantId,
                'success' => true,
                'stages_inserted' => 0,
                'message' => "Já possui {$count} estágios"
            ];
            error_log("[PIPELINE INIT] ℹ Tenant {$tenantId}: Já possui {$count} estágios");
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Inicialização de estágios de pipeline concluída',
        'results' => $results,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    error_log("[PIPELINE INIT] ✗ Erro: " . $e->getMessage());
}
?>
