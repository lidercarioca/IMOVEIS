<?php
// Endpoint para executar export de propriedades e (opcional) push ao portal
// Recebe JSON { portal, format, limit, endpoint, api_key, push }

session_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/integrations.php';
require_once __DIR__ . '/../app/integrations/PortalManager.php';
require_once __DIR__ . '/../app/utils/logger_functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acesso negado']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) $data = $_POST;

$portal = $data['portal'] ?? 'zap';
$format = strtolower($data['format'] ?? 'xml');
$limit = isset($data['limit']) ? intval($data['limit']) : 500;
$endpoint = $data['endpoint'] ?? null;
$apiKey = $data['api_key'] ?? null;
$push = !empty($data['push']) ? true : false;

try {
    $cfg = require __DIR__ . '/../config/integrations.php';
    $manager = new PortalManager($pdo, $cfg);
    $exporter = $manager->getExporter($portal);
    $props = $manager->fetchProperties($limit, $portal);

    $payload = $exporter->export($props, $format);

    // Log de execução
    log_user_action('export_run_manual', [
        'portal' => $portal,
        'format' => $format,
        'limit' => $limit,
        'by_user' => $_SESSION['user']['id'] ?? null
    ]);

    $response = ['success' => true, 'message' => 'Export gerado com sucesso', 'details' => ['count' => count($props)]];

    // Persist export info (light) em property_portal_sync - similar ao exportProperties
    try {
        $exportedIds = [];
        foreach ($props as $p) {
            if (is_array($p) && isset($p['id'])) $exportedIds[] = intval($p['id']);
        }
        if (!empty($exportedIds)) {
            $now = (new DateTime())->format('Y-m-d H:i:s');
            $pdo->beginTransaction();
            $selectStmt = $pdo->prepare('SELECT id FROM property_portal_sync WHERE property_id = :property_id AND portal = :portal LIMIT 1');
            $updateStmt = $pdo->prepare('UPDATE property_portal_sync SET last_exported_at = :last_exported_at, status = :status WHERE id = :id');
            $insertStmt = $pdo->prepare('INSERT INTO property_portal_sync (property_id, portal, last_exported_at, status) VALUES (:property_id, :portal, :last_exported_at, :status)');
            foreach ($exportedIds as $pid) {
                $selectStmt->execute([':property_id' => $pid, ':portal' => $portal]);
                $row = $selectStmt->fetch(PDO::FETCH_ASSOC);
                if ($row && !empty($row['id'])) {
                    $updateStmt->execute([':last_exported_at' => $now, ':status' => 'exported', ':id' => $row['id']]);
                } else {
                    $insertStmt->execute([':property_id' => $pid, ':portal' => $portal, ':last_exported_at' => $now, ':status' => 'exported']);
                }
            }
            $pdo->commit();
            log_user_action('export_persist', ['portal' => $portal, 'count' => count($exportedIds)]);
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        log_user_action('export_persist_failed', ['portal' => $portal, 'error' => $e->getMessage()]);
    }

    // Se pedir push, tenta enviar ao endpoint
    if ($push && !empty($endpoint)) {
        // Faz request com cURL
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        $ctype = ($format === 'json') ? 'application/json' : 'application/xml';
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: {$ctype}"]);
        if (!empty($apiKey)) {
            // Envia como Authorization Bearer ou X-API-Key - o usuário escolherá
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: {$ctype}", "Authorization: Bearer {$apiKey}"]);
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        log_user_action('export_push', ['portal' => $portal, 'endpoint' => $endpoint, 'http_code' => $httpCode]);

        $response['push'] = ['http_code' => $httpCode, 'response' => $resp, 'curl_error' => $curlErr];
        if ($httpCode >= 200 && $httpCode < 300) {
            $response['message'] .= ' e enviado com sucesso.';
        } else {
            $response['success'] = false;
            $response['error'] = 'Push retornou HTTP ' . $httpCode;
        }
    }

    echo json_encode($response);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    log_user_action('export_run_failed', ['portal' => $portal, 'error' => $e->getMessage()]);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
