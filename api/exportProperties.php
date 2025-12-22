<?php
/**
 * API para gerar feeds para portais. Uso:
 *  - GET /api/exportProperties.php?portal=zap&format=xml
 *  - GET /api/exportProperties.php?portal=zap&format=json
 * Opcional: &limit=100
 */
// Determina tipo default de resposta
$acceptFormat = strtolower($_GET['format'] ?? 'xml');

require_once '../config/integrations.php';
require_once '../config/database.php';
require_once '../app/integrations/PortalManager.php';
require_once __DIR__ . '/../app/utils/logger_functions.php';

$portal = $_GET['portal'] ?? 'zap';
$format = $acceptFormat;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 500;

try {
    $cfg = require __DIR__ . '/../config/integrations.php';

    // Determina chave esperada: prioridade para variável de ambiente FEED_API_KEY,
    // caso contrário usa credentials.api_key do portal (se configurado).
    $expectedKey = getenv('FEED_API_KEY') ?: ($cfg[$portal]['credentials']['api_key'] ?? null);

    // Captura key enviada pelo cliente (query ?key= or header X-Feed-Key)
    $providedKey = $_GET['key'] ?? null;
    if (empty($providedKey)) {
        // tenta header
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        if (!empty($headers['X-Feed-Key'])) $providedKey = $headers['X-Feed-Key'];
        elseif (!empty($headers['x-feed-key'])) $providedKey = $headers['x-feed-key'];
    }

    // Se houver uma chave esperada, exige autenticação
    if (!empty($expectedKey)) {
        if (empty($providedKey) || !hash_equals($expectedKey, $providedKey)) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            // Log tentativa negada
            log_user_action('export_run_denied', [
                'portal' => $portal,
                'format' => $format,
                'limit' => $limit,
                'reason' => 'invalid_or_missing_key',
                'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? null
            ]);
            echo json_encode(['success' => false, 'error' => 'Acesso negado: chave inválida ou ausente.']);
            exit;
        }
    }

    $manager = new PortalManager($pdo, $cfg);
    $exporter = $manager->getExporter($portal);
    $props = $manager->fetchProperties($limit, $portal);

    $payload = $exporter->export($props, $format);

    // Log do export bem sucedido (não grava a chave em claro)
    log_user_action('export_run', [
        'portal' => $portal,
        'format' => $format,
        'limit' => $limit,
        'authenticated' => !empty($expectedKey) ? true : false,
        'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? null
    ]);

    // Persistir marcação de sincronização incremental em `property_portal_sync`
    try {
        // Coleta ids exportados (assume que cada item mapeado contém a chave 'id')
        $exportedIds = [];
        foreach ($props as $p) {
            if (is_array($p) && isset($p['id'])) {
                $exportedIds[] = intval($p['id']);
            }
        }

        if (!empty($exportedIds)) {
            $now = (new DateTime())->format('Y-m-d H:i:s');
            // Usamos transação para consistência
            $pdo->beginTransaction();

            // Prepared statements para insert e update
            $selectStmt = $pdo->prepare('SELECT id FROM property_portal_sync WHERE property_id = :property_id AND portal = :portal LIMIT 1');
            $updateStmt = $pdo->prepare('UPDATE property_portal_sync SET last_exported_at = :last_exported_at, status = :status, response = :response WHERE id = :id');
            $insertStmt = $pdo->prepare('INSERT INTO property_portal_sync (property_id, portal, last_exported_at, status, response) VALUES (:property_id, :portal, :last_exported_at, :status, :response)');

            $metaResponse = json_encode(['format' => $format, 'count' => count($exportedIds)]);

            foreach ($exportedIds as $pid) {
                $selectStmt->execute([':property_id' => $pid, ':portal' => $portal]);
                $row = $selectStmt->fetch(PDO::FETCH_ASSOC);
                if ($row && !empty($row['id'])) {
                    $updateStmt->execute([
                        ':last_exported_at' => $now,
                        ':status' => 'exported',
                        ':response' => $metaResponse,
                        ':id' => $row['id']
                    ]);
                } else {
                    $insertStmt->execute([
                        ':property_id' => $pid,
                        ':portal' => $portal,
                        ':last_exported_at' => $now,
                        ':status' => 'exported',
                        ':response' => $metaResponse
                    ]);
                }
            }

            $pdo->commit();

            log_user_action('export_persist', [
                'portal' => $portal,
                'exported_count' => count($exportedIds),
                'sample_ids' => array_slice($exportedIds, 0, 10)
            ]);
        }
    } catch (Exception $e) {
        // Em caso de falha ao persistir, desfazer e logar
        if ($pdo->inTransaction()) $pdo->rollBack();
        log_user_action('export_persist_failed', [
            'portal' => $portal,
            'error' => $e->getMessage()
        ]);
    }

    if (strtolower($format) === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        echo $payload;
        exit;
    }

    header('Content-Type: application/xml; charset=utf-8');
    echo $payload;
    exit;

} catch (Exception $e) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
