<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/session_manager.php';
require_once __DIR__ . '/../../../app/utils/InputValidator.php';
require_once __DIR__ . '/../../../src/Application/Integration/FacebookLeadService.php';
require_once __DIR__ . '/../../../src/Infrastructure/Lead/LeadRepository.php';

function sendJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function getRequestPayload(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return $_POST ?: [];
    }

    $raw = preg_replace('/^\x{FEFF}/u', '', $raw);
    $raw = preg_replace('/^\s+/u', '', $raw);

    $data = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
        return $data;
    }

    if (!empty($_POST)) {
        return $_POST;
    }

    parse_str($raw, $parsed);
    return is_array($parsed) ? $parsed : [];
}

try {
    $tenantId = isset($_GET['tenant_id']) ? intval($_GET['tenant_id']) : 0;
    if ($tenantId <= 0) {
        sendJson(['success' => false, 'error' => 'tenant_id é obrigatório'], 400);
    }

    $pdo = getConnection();

    // Instantiate service early for integration retrieval
    $repository = new \App\Infrastructure\Lead\LeadRepository($pdo);
    $facebookService = new \App\Application\Integration\FacebookLeadService($repository, $pdo);

    $method = $_SERVER['REQUEST_METHOD'];
    $integration = $facebookService->getFacebookIntegration($tenantId);
    if (!$integration) {
        sendJson(['success' => false, 'error' => 'Integração Facebook Leads não configurada para este tenant'], 404);
    }
    if (!$integration['enabled']) {
        sendJson(['success' => false, 'error' => 'Integração Facebook Leads está desativada'], 400);
    }

    $credentials = is_array($integration['credentials']) ? $integration['credentials'] : [];
    $verifyToken = trim((string)($credentials['webhook_verify_token'] ?? getenv('FACEBOOK_LEADS_VERIFY_TOKEN') ?: ''));

    if ($method === 'GET') {
        // Handle webhook verification (GET request)
        $hubMode = $_GET['hub.mode'] ?? $_GET['hub_mode'] ?? '';
        $hubChallenge = $_GET['hub.challenge'] ?? $_GET['hub_challenge'] ?? '';
        $hubVerifyToken = $_GET['hub.verify_token'] ?? $_GET['hub_verify_token'] ?? '';

        if ($hubMode !== 'subscribe' || $hubChallenge === '' || $hubVerifyToken === '') {
            sendJson(['success' => false, 'error' => 'Parâmetros de verificação inválidos'], 400);
        }

        $tenant = $facebookService->authorizeWebhookTenant($tenantId, false);
        if ($tenant['tenant_id'] !== $tenantId) {
            sendJson(['success' => false, 'error' => 'Tenant da integração não autenticado'], 403);
        }

        if ($verifyToken === '' || !hash_equals($verifyToken, $hubVerifyToken)) {
            sendJson(['success' => false, 'error' => 'verify_token inválido'], 403);
        }

        // Return challenge to Facebook
        header('Content-Type: text/plain; charset=utf-8');
        echo $hubChallenge;
        exit;
    }

    if ($method === 'POST') {
        // Handle webhook delivery (POST request)
        $rawPayload = file_get_contents('php://input');
        $signatureHeader = trim((string)($_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? ''));

        $payload = json_decode($rawPayload, true);
        if (!is_array($payload)) {
            $payload = [];
        }

        if (empty($payload) || !isset($payload['entry']) || !is_array($payload['entry'])) {
            sendJson(['success' => false, 'error' => 'Payload inválido ou ausente'], 400);
        }

        $results = [];
        foreach ($payload['entry'] as $entry) {
            if (!is_array($entry) || empty($entry['changes']) || !is_array($entry['changes'])) {
                continue;
            }
            foreach ($entry['changes'] as $change) {
                if (!is_array($change) || empty($change['value']) || !is_array($change['value'])) {
                    continue;
                }

                $leadgenId = trim((string)($change['value']['leadgen_id'] ?? ''));
                if ($leadgenId === '') {
                    $leadgenId = trim((string)($change['value']['lead_id'] ?? ''));
                }
                if ($leadgenId === '') {
                    continue;
                }

                try {
                    $processed = $facebookService->processAuthenticatedWebhookLead(
                        $tenantId,
                        $rawPayload ?: '',
                        $signatureHeader,
                        $leadgenId
                    );
                    $results[] = array_merge($processed, ['leadgen_id' => $leadgenId]);
                } catch (Exception $e) {
                    $results[] = ['leadgen_id' => $leadgenId, 'error' => $e->getMessage()];
                }
            }
        }

        if (empty($results)) {
            sendJson(['success' => false, 'error' => 'Nenhum evento de lead processado'], 400);
        }

        sendJson(['success' => true, 'message' => 'Webhook processado', 'data' => $results]);
    }

    sendJson(['success' => false, 'error' => 'Método não suportado'], 405);
} catch (Exception $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    sendJson(['success' => false, 'error' => 'Erro ao processar lead: ' . $e->getMessage()], 500);
}
