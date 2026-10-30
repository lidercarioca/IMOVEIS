<?php

/**
 * API: Google Calendar Integration
 * Endpoints para sincronizar agendamentos com Google Calendar
 * COM ISOLAMENTO DE TENANT_ID
 */

function emitGoogleCalendarJsonError(string $message, int $statusCode = 500, array $extra = []): void
{
    if (!headers_sent()) {
        http_response_code($statusCode);
    }

    $payload = [
        'success' => false,
        'status' => 'error',
        'message' => $message,
    ];

    foreach ($extra as $key => $value) {
        $payload[$key] = $value;
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
}

function logGoogleCalendarRequestError(Throwable $e, int $tenantId, int $userId, string $action, string $method): void
{
    $message = preg_replace('/(access_token|refresh_token|authorization_code|client_secret|token|secret|cookie|session|csrf|jwt)/i', '[REDACTED]', $e->getMessage());
    $safeMessage = $message !== null && $message !== '' ? $message : 'unknown-error';

    error_log(sprintf(
        '[GoogleCalendarAPI] action=%s method=%s tenant_id=%d user_id=%d file=%s line=%d message=%s',
        $action,
        $method,
        $tenantId,
        $userId,
        basename($e->getFile() ?: 'unknown'),
        (int) ($e->getLine() ?: 0),
        $safeMessage
    ));
}

function ensureGoogleCalendarPdoInitialized(): void
{
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        return;
    }

    $bootstrapFile = dirname(dirname(__DIR__)) . '/app/bootstrap.php';
    if (!is_file($bootstrapFile)) {
        throw new RuntimeException('Google Calendar endpoint requires a valid PDO connection.');
    }

    try {
        require_once $bootstrapFile;
    } catch (Throwable $bootstrapException) {
        $safeMessage = preg_replace('/(access_token|refresh_token|authorization_code|client_secret|token|secret|cookie|session|csrf|jwt)/i', '[REDACTED]', $bootstrapException->getMessage());
        error_log(sprintf(
            '[GoogleCalendarAPI] bootstrap_exception action=%s method=%s file=%s line=%d message=%s',
            $_GET['action'] ?? 'unknown',
            $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
            basename($bootstrapException->getFile() ?: 'unknown'),
            (int) ($bootstrapException->getLine() ?: 0),
            $safeMessage !== null && $safeMessage !== '' ? $safeMessage : 'unknown-error'
        ));
        throw $bootstrapException;
    }

    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        return;
    }

    throw new RuntimeException('Google Calendar endpoint requires a valid PDO connection.');
}

function resolveGoogleCalendarPdo(?PDO $pdo = null): PDO
{
    if ($pdo instanceof PDO) {
        $GLOBALS['pdo'] = $pdo;
        return $pdo;
    }

    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        return $GLOBALS['pdo'];
    }

    ensureGoogleCalendarPdoInitialized();

    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        return $GLOBALS['pdo'];
    }

    throw new RuntimeException('Google Calendar endpoint requires a valid PDO connection.');
}

function runGoogleCalendarHttpEndpoint(?callable $serviceFactory = null, ?PDO $pdo = null): void
{
    require_once dirname(dirname(__DIR__)) . '/api/check_auth.php';
    require_once dirname(dirname(__DIR__)) . '/app/helpers/tenant_isolation_helpers.php';

    $resolvedPdo = resolveGoogleCalendarPdo($pdo);
    $GLOBALS['pdo'] = $resolvedPdo;

    require_once dirname(dirname(__DIR__)) . '/google/GoogleCalendarService.php';
    require_once dirname(dirname(__DIR__)) . '/google/google_oauth_state.php';
    require_once dirname(dirname(__DIR__)) . '/app/security/CSRF.php';

    if ($serviceFactory === null) {
        $serviceFactory = static fn() => new GoogleCalendarService(
            (int) ($GLOBALS['tenantId'] ?? 0),
            (int) ($_SESSION['user_id'] ?? 0),
            $GLOBALS['pdo'] ?? null
        );
    }

    set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline): bool {
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    });

    try {
        checkApiAuth();

        if (!headers_sent()) {
            header('Content-Type: application/json');
        }

        initializeTenantMiddleware($resolvedPdo);
        $resolvedTenantId = getCurrentTenantId();
        $tenantId = is_numeric($resolvedTenantId) ? (int) $resolvedTenantId : 0;
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($tenantId <= 0 || $userId <= 0) {
            throw new ApiAuthException('Sessão sem tenant ou usuário válido.');
        }

        // Os handlers legados leem o contexto por $GLOBALS.
        $GLOBALS['tenantId'] = $tenantId;
        $GLOBALS['userId'] = $userId;

        $isSuperAdmin = isSuperAdmin();
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $action = $_GET['action'] ?? '';

        $calendarService = $serviceFactory();
        if (!is_object($calendarService) || !method_exists($calendarService, 'disconnectConnectionWithStatus')) {
            throw new TypeError('Google Calendar endpoint must receive an object with disconnectConnectionWithStatus().');
        }

        switch ($action) {
            case 'authenticate':
                handleAuthenticate($calendarService, $tenantId, $userId);
                break;

            case 'sync-all':
                handleSyncAll($calendarService);
                break;

            case 'sync-event':
                handleSyncEvent($calendarService);
                break;

            case 'unsync-event':
                handleUnsyncEvent($calendarService);
                break;

            case 'get-status':
                handleGetStatus($calendarService);
                break;

            case 'disconnect':
                handleDisconnect($calendarService, $tenantId, $userId);
                break;

            case 'list-events':
                handleListEvents($calendarService);
                break;

            default:
                emitGoogleCalendarJsonError('Ação não reconhecida', 400);
                break;
        }
    } catch (ApiAuthException $e) {
        $tenantId = $tenantId ?? 0;
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $action = $_GET['action'] ?? 'unknown';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';

        logGoogleCalendarRequestError($e, $tenantId, $userId, $action, $method);
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(401);
        }
        echo json_encode([
            'success' => false,
            'status' => 'unauthorized',
            'message' => 'Não autorizado. Faça login novamente.',
            'external_google_revoke' => false,
            'local_connection_inactive' => null,
            'partial_failure' => false,
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        $tenantId = $tenantId ?? 0;
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $action = $_GET['action'] ?? 'unknown';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';

        logGoogleCalendarRequestError($e, $tenantId, $userId, $action, $method);
        emitGoogleCalendarJsonError('Falha inesperada ao processar o Google Calendar.', 500, [
            'status' => 'error',
            'external_google_revoke' => false,
            'local_connection_inactive' => null,
            'partial_failure' => true,
        ]);
    } finally {
        restore_error_handler();
    }
}

function shouldRunGoogleCalendarHttpEndpointDirectly(): bool
{
    $scriptFilename = $_SERVER['SCRIPT_FILENAME'] ?? '';
    if ($scriptFilename === '' || realpath($scriptFilename) !== __FILE__) {
        return false;
    }

    $argv0 = $_SERVER['argv'][0] ?? '';
    if ($argv0 === '') {
        return true;
    }

    return basename($argv0) === basename(__FILE__);
}

if (shouldRunGoogleCalendarHttpEndpointDirectly()) {
    runGoogleCalendarHttpEndpoint();
}

/**
 * Iniciar processo de autenticação com Google
 */
function handleAuthenticate($calendarService, int $tenantId, int $userId)
{
    if ($tenantId <= 0 || $userId <= 0) {
        throw new ApiAuthException('Sessão sem tenant ou usuário válido.');
    }

    $client = $calendarService->getClient();
    $statePayload = google_calendar_build_oauth_state($tenantId, $userId);
    $client->setState($statePayload['state']);
    $authUrl = $client->createAuthUrl();

    echo json_encode([
        'success' => true,
        'auth_url' => $authUrl,
        'message' => 'Redirecione para esta URL para autorizar acesso ao Google Calendar'
    ]);
}

/**
 * Sincronizar todos os agendamentos para Google Calendar
 */
function handleSyncAll($calendarService)
{
    global $tenantId;

    $calendarSyncService = new \App\Service\GoogleCalendarSyncService($GLOBALS['pdo']);
    $requestedUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    $userId = $requestedUserId > 0 ? $requestedUserId : (int) $_SESSION['user_id'];

    // Admin pode sincronizar para outros usuários
    if ($requestedUserId > 0 && $requestedUserId !== (int) $_SESSION['user_id'] && !isAdmin()) {
        http_response_code(403);
        throw new Exception('Apenas administradores podem sincronizar para outros usuários');
    }

    if (!$calendarSyncService->validateUserBelongsToTenant($userId, $tenantId)) {
        http_response_code(403);
        throw new Exception('Usuário não pertence ao tenant atual');
    }

    $result = $calendarService->syncAllAgendamentos($userId, $tenantId);

    echo json_encode([
        'success' => true,
        'synced' => $result['synced'],
        'total' => $result['total'],
        'errors' => $result['errors']
    ]);
}

/**
 * Sincronizar um agendamento específico
 */
function handleSyncEvent($calendarService)
{
    global $tenantId;
    $data = json_decode(file_get_contents('php://input'), true);
    $agendamentoId = $data['agendamento_id'] ?? null;
    $currentUserId = (int) ($_SESSION['user_id'] ?? 0);
    $calendarSyncService = new \App\Service\GoogleCalendarSyncService($GLOBALS['pdo']);

    if (!$agendamentoId) {
        http_response_code(400);
        throw new Exception('agendamento_id é obrigatório');
    }

    $agendamento = $calendarSyncService->resolveOwnedAppointmentForUser($tenantId, $currentUserId, (int) $agendamentoId);
    if (!$agendamento) {
        http_response_code(404);
        throw new Exception('Agendamento não encontrado ou acesso negado');
    }

    $connection = $calendarService->getConnection();
    if (!$connection || ($connection['status'] ?? 'active') !== 'active' || empty($connection['access_token'])) {
        http_response_code(403);
        throw new Exception('Google Calendar está desconectado para este tenant/usuário.');
    }

    $propertyAddress = $calendarSyncService->getPropertyAddressForTenant($tenantId, (int) $agendamento['imovel_id']);

    $eventData = [
        'title' => 'Agendamento de Visita',
        'data_hora' => $agendamento['data_hora'],
        'description' => 'Imóvel: ' . ($propertyAddress ?? 'N/A'),
        'location' => $propertyAddress ?? '',
    ];

    $syncData = $calendarSyncService->findSyncRecordForAppointment($tenantId, (int) $agendamentoId);

    if ($syncData && !empty($syncData['google_event_id'])) {
        $event = $calendarService->updateEvent($syncData['google_event_id'], $eventData);
        $action = 'updated';
    } else {
        $event = $calendarService->createEvent($eventData);
        $calendarSyncService->saveSyncRecord($tenantId, (int) $agendamentoId, $event->getId());
        $action = 'created';
    }

    echo json_encode([
        'success' => true,
        'action' => $action,
        'event_id' => $event->getId(),
        'event_link' => $event->getHtmlLink()
    ]);
}

/**
 * Remover sincronização de um agendamento
 */
function handleUnsyncEvent($calendarService)
{
    global $tenantId;
    $data = json_decode(file_get_contents('php://input'), true);
    $agendamentoId = $data['agendamento_id'] ?? null;
    $currentUserId = (int) ($_SESSION['user_id'] ?? 0);
    $calendarSyncService = new \App\Service\GoogleCalendarSyncService($GLOBALS['pdo']);

    if (!$agendamentoId) {
        http_response_code(400);
        throw new Exception('agendamento_id é obrigatório');
    }

    $appointment = $calendarSyncService->resolveOwnedAppointmentForUser($tenantId, $currentUserId, (int) $agendamentoId);
    if (!$appointment) {
        http_response_code(404);
        throw new Exception('Agendamento não encontrado ou acesso negado');
    }

    $syncData = $calendarSyncService->findSyncRecordForAppointment($tenantId, (int) $agendamentoId);
    if (!$syncData) {
        http_response_code(404);
        throw new Exception('Sincronização não encontrada');
    }

    try {
        $calendarService->deleteEvent($syncData['google_event_id']);
    } catch (Exception $e) {
        // Ignorar se não encontrar no Google Calendar
    }

    $calendarSyncService->deleteSyncRecord($tenantId, (int) $agendamentoId);

    echo json_encode([
        'success' => true,
        'message' => 'Sincronização removida com sucesso'
    ]);
}

/**
 * Obter status de autenticação
 */
function handleGetStatus($calendarService)
{
    global $tenantId;
    $resolvedPdo = resolveGoogleCalendarPdo();
    $calendarSyncService = new \App\Service\GoogleCalendarSyncService($resolvedPdo);
    $isAuthenticated = $calendarService->isAuthenticated();

    echo json_encode([
        'authenticated' => $isAuthenticated,
        'synced_events' => $calendarSyncService->countSyncedEventsForTenant($tenantId),
        'connected' => $isAuthenticated,
        'external_google_revoke' => false,
        'status' => $isAuthenticated ? 'active' : 'disconnected'
    ]);
}

class GoogleCalendarDisconnectHttpHandler
{
    private $calendarService;
    private int $tenantId;
    private int $userId;
    private string $method;
    private array $payload;
    private $logger;
    private $csrfVerifier;
    private string $operationId;

    public function __construct(
        $calendarService,
        int $tenantId,
        int $userId,
        string $method,
        array $payload = [],
        ?callable $logger = null,
        ?callable $csrfVerifier = null,
        ?string $operationId = null
    ) {
        $this->calendarService = $calendarService;
        $this->tenantId = $tenantId;
        $this->userId = $userId;
        $this->method = $method;
        $this->payload = $payload;
        $this->logger = $logger;
        $this->csrfVerifier = $csrfVerifier ?? static fn(string $token): bool => CSRF::verifyToken($token);
        $this->operationId = $operationId ?? 'gcal-http-' . bin2hex(random_bytes(4));
    }

    public static function fromGlobals($calendarService, int $tenantId, int $userId): self
    {
        $rawBody = file_get_contents('php://input');
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            $payload = [];
        }

        if (isset($_POST['csrf_token']) && !isset($payload['csrf_token'])) {
            $payload['csrf_token'] = (string) $_POST['csrf_token'];
        }

        $operationId = 'gcal-http-' . bin2hex(random_bytes(4));

        $handler = new self(
            $calendarService,
            $tenantId,
            $userId,
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            $payload,
            static function (Throwable $e) use ($operationId, $tenantId, $userId): void {
                logGoogleCalendarRequestError(
                    $e,
                    $tenantId,
                    $userId,
                    'disconnect',
                    $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'
                );

                $safeMessage = preg_replace('/(access_token|refresh_token|authorization_code|client_secret|token|secret|cookie|session|csrf|jwt)/i', '[REDACTED]', $e->getMessage());
                $message = $safeMessage !== null && $safeMessage !== '' ? $safeMessage : 'unknown-error';

                try {
                    error_log(sprintf(
                        '[GoogleCalendarDisconnectHttpHandler] op_id=%s class=%s message=%s file=%s line=%d',
                        $operationId,
                        get_class($e),
                        $message,
                        basename($e->getFile() ?: 'unknown'),
                        (int) ($e->getLine() ?: 0)
                    ));
                } catch (Throwable $logError) {
                    // Logging failures must never interrupt the HTTP response or the fail-closed flow.
                }
            }
        );

        $handler->operationId = $operationId;

        return $handler;
    }

    public function handle(): array
    {
        if ($this->method !== 'POST') {
            return [
                405,
                ['success' => false, 'error' => 'Método inválido. Use POST para desconectar o Google Calendar.'],
            ];
        }

        $csrfToken = isset($this->payload['csrf_token']) ? (string) $this->payload['csrf_token'] : '';

        try {
            if (!($this->csrfVerifier)($csrfToken)) {
                return [403, ['success' => false, 'error' => 'Token CSRF inválido.']];
            }
        } catch (Throwable $e) {
            if (is_callable($this->logger)) {
                ($this->logger)($e);
            }
            return [403, ['success' => false, 'error' => 'Token CSRF inválido.']];
        }

        if ($this->userId <= 0 || $this->tenantId <= 0) {
            return [401, ['success' => false, 'error' => 'Sessão inválida para desconectar o Google Calendar.']];
        }

        try {
            $disconnectResult = $this->calendarService->disconnectConnectionWithStatus(null, $this->operationId);
            $localInactive = $disconnectResult['local_connection_inactive'] ?? null;

            if ($localInactive !== true) {
                $diagnosticMessage = $disconnectResult['message'] ?? 'Falha inesperada ao processar a desconexão do Google Calendar.';
                $safeMessage = preg_replace('/(access_token|refresh_token|authorization_code|client_secret|token|secret|cookie|session|csrf|jwt)/i', '[REDACTED]', $diagnosticMessage);
                $safeText = $safeMessage !== null && $safeMessage !== '' ? $safeMessage : 'unknown-error';

                try {
                    error_log(sprintf(
                        '[GoogleCalendarDisconnectHttpHandler] op_id=%s status=%s local_connection_inactive=%s external_google_revoke=%s message=%s',
                        $this->operationId,
                        (string) ($disconnectResult['status'] ?? 'unknown'),
                        var_export($localInactive, true),
                        var_export((bool) ($disconnectResult['external_google_revoke'] ?? false), true),
                        $safeText
                    ));
                } catch (Throwable $logError) {
                    // Logging failures must never interrupt the HTTP response or the fail-closed flow.
                }

                return [500, [
                    'success' => false,
                    'status' => $disconnectResult['status'] ?? 'unknown',
                    'message' => $diagnosticMessage,
                    'external_google_revoke' => (bool) ($disconnectResult['external_google_revoke'] ?? false),
                    'local_connection_inactive' => $localInactive,
                    'partial_failure' => (bool) ($disconnectResult['partial_failure'] ?? true),
                ]];
            }

            return [200, [
                'success' => (bool) $disconnectResult['success'],
                'status' => $disconnectResult['status'],
                'message' => $disconnectResult['message'],
                'external_google_revoke' => (bool) $disconnectResult['external_google_revoke'],
                'local_connection_inactive' => true,
                'partial_failure' => (bool) $disconnectResult['partial_failure'],
            ]];
        } catch (Throwable $e) {
            $safeMessage = preg_replace('/(access_token|refresh_token|authorization_code|client_secret|token|secret|cookie|session|csrf|jwt)/i', '[REDACTED]', $e->getMessage());
            $message = $safeMessage !== null && $safeMessage !== '' ? $safeMessage : 'unknown-error';

            try {
                error_log(sprintf(
                    '[GoogleCalendarDisconnectHttpHandler] op_id=%s class=%s message=%s file=%s line=%d',
                    $this->operationId,
                    get_class($e),
                    $message,
                    basename($e->getFile() ?: 'unknown'),
                    (int) ($e->getLine() ?: 0)
                ));
            } catch (Throwable $logError) {
                // Logging failures must never interrupt the HTTP response or the fail-closed flow.
            }

            if (is_callable($this->logger)) {
                try {
                    ($this->logger)($e);
                } catch (Throwable $logError) {
                    // Logging failures must never interrupt the HTTP response or the fail-closed flow.
                }
            }

            return [500, [
                'success' => false,
                'status' => 'unknown',
                'message' => 'Falha inesperada ao processar a desconexão do Google Calendar; o estado final da conexão é incerto.',
                'external_google_revoke' => false,
                'local_connection_inactive' => null,
                'partial_failure' => true,
            ]];
        }
    }

    public function emitResponse(): void
    {
        [$statusCode, $payload] = $this->handle();

        if (!headers_sent()) {
            http_response_code($statusCode);
        }

        if ($statusCode === 405 && !headers_sent()) {
            header('Allow: POST');
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }
}

function handleDisconnect($calendarService, int $tenantId, int $userId)
{
    $handler = GoogleCalendarDisconnectHttpHandler::fromGlobals($calendarService, $tenantId, $userId);
    $handler->emitResponse();
}

/**
 * Listar eventos do Google Calendar
 */
function handleListEvents($calendarService)
{
    global $tenantId;
    $currentUserId = (int) ($_SESSION['user_id'] ?? 0);
    $startDate = $_GET['start_date'] ?? date('Y-m-d');
    $endDate = $_GET['end_date'] ?? date('Y-m-d', strtotime('+7 days'));
    $maxResults = (int)($_GET['max_results'] ?? 100);
    $calendarSyncService = new \App\Service\GoogleCalendarSyncService($GLOBALS['pdo']);

    $allowedEventIds = array_flip($calendarSyncService->getAllowedGoogleEventIdsForUser($tenantId, $currentUserId));

    $events = array_values(array_filter(
        $calendarService->listEvents($startDate, $endDate, $maxResults),
        static fn($event): bool => isset($allowedEventIds[$event->getId()])
    ));

    echo json_encode([
        'success' => true,
        'events' => $events,
        'count' => count($events)
    ]);
}

function resolveOwnedAgendamentoForUser(int $tenantId, int $userId, int $agendamentoId): ?array
{
    $calendarSyncService = new \App\Service\GoogleCalendarSyncService($GLOBALS['pdo']);
    return $calendarSyncService->resolveOwnedAppointmentForUser($tenantId, $userId, $agendamentoId);
}
