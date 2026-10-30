<?php

/**
 * Google Calendar Service
 * Gerencia sincronização de agendamentos com Google Calendar
 */

use App\Infrastructure\Google\GoogleCalendarConnectionRepository;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;

class GoogleCalendarService
{
    private Client $client;
    private ?Calendar $service = null;
    private int $tenantId;
    private int $userId;
    private PDO $pdo;
    private GoogleCalendarConnectionRepository $repository;
    private string $calendarId = 'primary';

    public function __construct(?int $tenantId = null, ?int $userId = null, ?PDO $pdo = null)
    {
        $this->tenantId = $tenantId ?? (int) ($_SESSION['tenant_id'] ?? 0);
        $this->userId = $userId ?? (int) ($_SESSION['user_id'] ?? 0);

        if ($pdo instanceof PDO) {
            $this->pdo = $pdo;
        } else {
            if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
                $this->pdo = $GLOBALS['pdo'];
            } else {
                require_once __DIR__ . '/../config/database.php';
                if (!isset($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof PDO)) {
                    throw new RuntimeException('Google Calendar endpoint requires a valid PDO connection.');
                }
                $this->pdo = $GLOBALS['pdo'];
            }
        }

        $this->repository = new GoogleCalendarConnectionRepository($this->pdo);
        $this->client = $this->initializeClient();
        $this->service = $this->client->getAccessToken() ? new Calendar($this->client) : null;
    }

    private function initializeClient(): Client
    {
        $client = new Client();
        $client->setApplicationName('Sistema de Imóveis - Google Calendar Integration');
        $client->setScopes([
            'https://www.googleapis.com/auth/calendar',
            'https://www.googleapis.com/auth/calendar.events',
        ]);

        if (getenv('GOOGLE_CLIENT_ID')) {
            $client->setClientId(getenv('GOOGLE_CLIENT_ID'));
        }

        if (getenv('GOOGLE_CLIENT_SECRET')) {
            $client->setClientSecret(getenv('GOOGLE_CLIENT_SECRET'));
        }

        $redirectUri = getenv('GOOGLE_OAUTH_REDIRECT');
        if ($redirectUri === false || trim($redirectUri) === '') {
            $redirectUri = 'http://localhost/google/oauth_callback.php';
        }
        $client->setRedirectUri($redirectUri);

        $connection = $this->repository->findForUser($this->tenantId, $this->userId);
        if ($connection && ($connection['status'] ?? 'active') === 'active' && !empty($connection['access_token'])) {
            $token = [
                'access_token' => $connection['access_token'],
                'token_type' => $connection['token_type'] ?? 'Bearer',
                'expires_in' => $connection['expires_in'] ?? null,
                'scope' => $connection['scope'] ?? null,
            ];

            if (!empty($connection['refresh_token'])) {
                $token['refresh_token'] = $connection['refresh_token'];
            }

            $client->setAccessToken($token);
            $this->refreshTokenIfNeeded($client, $connection);
        }

        return $client;
    }

    private function refreshTokenIfNeeded(Client $client, array $connection): void
    {
        if ($this->tenantId <= 0 || $this->userId <= 0) {
            return;
        }

        if (!$client->isAccessTokenExpired()) {
            return;
        }

        if (empty($connection['refresh_token'])) {
            return;
        }

        $newToken = $client->fetchAccessTokenWithRefreshToken($connection['refresh_token']);
        if (!isset($newToken['access_token'])) {
            return;
        }

        $this->repository->upsertConnection($this->tenantId, $this->userId, $newToken, $connection['google_user_email'] ?? null);
        $client->setAccessToken($newToken);
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function getService(): ?Calendar
    {
        return $this->service;
    }

    public function getConnection(): ?array
    {
        if ($this->tenantId <= 0 || $this->userId <= 0) {
            return null;
        }

        return $this->repository->findForUser($this->tenantId, $this->userId);
    }

    public function revokeExternalGoogleToken(?array $connection = null): bool
    {
        if ($this->tenantId <= 0 || $this->userId <= 0) {
            return false;
        }

        $connection = $connection ?? $this->repository->findForUser($this->tenantId, $this->userId);
        if (!$connection) {
            return false;
        }

        $tokenToRevoke = $connection['refresh_token'] ?? $connection['access_token'] ?? null;
        if (empty($tokenToRevoke)) {
            return false;
        }

        try {
            $client = new Client();
            if (getenv('GOOGLE_CLIENT_ID')) {
                $client->setClientId(getenv('GOOGLE_CLIENT_ID'));
            }

            if (getenv('GOOGLE_CLIENT_SECRET')) {
                $client->setClientSecret(getenv('GOOGLE_CLIENT_SECRET'));
            }

            return (bool) $client->revokeToken($tokenToRevoke);
        } catch (Throwable $e) {
            return false;
        }
    }

    public function disconnectConnection(): bool
    {
        if ($this->tenantId <= 0 || $this->userId <= 0) {
            return false;
        }

        $connection = $this->repository->findForUser($this->tenantId, $this->userId);
        if (!$connection) {
            return false;
        }

        $this->revokeExternalGoogleToken($connection);

        return $this->repository->disconnectConnection($this->tenantId, $this->userId, 'disconnected');
    }

    public static function sanitizeGoogleErrorDetails(array $payload): string
    {
        $details = [];

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $details[] = $key . ': [array]';
                continue;
            }

            if (is_bool($value)) {
                $details[] = $key . ':' . ($value ? 'true' : 'false');
                continue;
            }

            if ($value === null) {
                continue;
            }

            $stringValue = (string) $value;
            $sanitized = preg_replace('/(access_token|refresh_token|authorization_code|code|token|secret)/i', '[REDACTED]', $stringValue);
            $details[] = $key . ':' . ($sanitized !== '' ? $sanitized : '[REDACTED]');
        }

        if (empty($details)) {
            return 'Google OAuth error';
        }

        return implode('; ', $details);
    }

    private static function logDisconnectFailure(Throwable $throwable, int $tenantId, int $userId, ?string $operationId = null): void
    {
        $message = $throwable->getMessage();
        $safeMessage = preg_replace('/(access_token|refresh_token|authorization_code|client_secret|token|secret|cookie|session)/i', '[REDACTED]', $message);
        $skip = $safeMessage !== null && $safeMessage !== '' ? $safeMessage : 'unknown-error';
        $opId = $operationId ?? sprintf('gcal-disconnect-%d-%d-%s', $tenantId, $userId, 'unknown-op');
        $className = get_class($throwable);

        try {
            error_log(sprintf(
                '[GoogleCalendarDisconnect] op_id=%s tenant_id=%d user_id=%d class=%s message=%s file=%s line=%d',
                $opId,
                $tenantId,
                $userId,
                $className,
                $skip,
                basename($throwable->getFile() ?: 'unknown'),
                (int) ($throwable->getLine() ?: 0)
            ));
        } catch (Throwable $logError) {
            // Logging failures must never interrupt the HTTP response or the fail-closed flow.
        }
    }

    private static function logDisconnectDiagnostic(string $message, int $tenantId, int $userId, ?string $operationId = null): void
    {
        $opId = $operationId ?? sprintf('gcal-disconnect-%d-%d-%s', $tenantId, $userId, 'unknown-op');
        $safeMessage = preg_replace('/(access_token|refresh_token|authorization_code|client_secret|token|secret|cookie|session|csrf|jwt)/i', '[REDACTED]', $message);
        $finalMessage = $safeMessage !== null && $safeMessage !== '' ? $safeMessage : 'unknown-error';

        try {
            error_log(sprintf(
                '[GoogleCalendarDisconnect] op_id=%s tenant_id=%d user_id=%d class=diagnostic message=%s',
                $opId,
                $tenantId,
                $userId,
                $finalMessage
            ));
        } catch (Throwable $logError) {
            // Logging failures must never interrupt the HTTP response or the fail-closed flow.
        }
    }

    public function disconnectConnectionWithStatus(?array $connection = null, ?string $operationId = null): array
    {
        $operationId = $operationId ?? sprintf('gcal-disconnect-%d-%d-%s', $this->tenantId, $this->userId, bin2hex(random_bytes(4)));

        if ($this->tenantId <= 0 || $this->userId <= 0) {
            self::logDisconnectDiagnostic('Sessão inválida para desconectar o Google Calendar; o estado da conexão permanece incerto.', $this->tenantId, $this->userId, $operationId);

            return [
                'success' => false,
                'status' => 'unknown',
                'external_google_revoke' => false,
                'local_connection_inactive' => null,
                'partial_failure' => true,
                'message' => 'Sessão inválida para desconectar o Google Calendar; o estado da conexão permanece incerto.',
            ];
        }

        try {
            $connection = $connection ?? $this->repository->findForUser($this->tenantId, $this->userId);
            if (!$connection) {
                return [
                    'success' => true,
                    'status' => 'disconnected',
                    'external_google_revoke' => false,
                    'local_connection_inactive' => true,
                    'partial_failure' => false,
                    'message' => 'Nenhuma conexão Google Calendar foi encontrada para este tenant/usuário.',
                ];
            }

            $externalRevokeResult = $this->revokeExternalGoogleToken($connection);
            $localResult = $this->repository->disconnectConnectionStatus($this->tenantId, $this->userId, 'disconnected');

            if (($localResult['status'] ?? null) === 'not_found') {
                return [
                    'success' => true,
                    'status' => 'disconnected',
                    'external_google_revoke' => false,
                    'local_connection_inactive' => true,
                    'partial_failure' => false,
                    'message' => 'Nenhuma conexão Google Calendar foi encontrada para este tenant/usuário.',
                ];
            }

            if (($localResult['already_disconnected'] ?? false) || (($localResult['status'] ?? null) === 'disconnected' && !($localResult['updated'] ?? true))) {
                return [
                    'success' => true,
                    'status' => 'already_disconnected',
                    'external_google_revoke' => $externalRevokeResult,
                    'local_connection_inactive' => true,
                    'partial_failure' => false,
                    'message' => $localResult['message'] ?? 'A conexão Google Calendar já estava desconectada.',
                ];
            }

            if (!($localResult['success'] ?? false)) {
                self::logDisconnectDiagnostic(
                    $localResult['message'] ?? 'Não foi possível confirmar a invalidação local da conexão; a revogação externa pode ter ocorrido, mas o estado final é incerto.',
                    $this->tenantId,
                    $this->userId,
                    $operationId
                );

                return [
                    'success' => false,
                    'status' => $localResult['status'] ?? 'unknown',
                    'external_google_revoke' => $externalRevokeResult,
                    'local_connection_inactive' => null,
                    'partial_failure' => true,
                    'message' => $localResult['message'] ?? 'Não foi possível confirmar a invalidação local da conexão; a revogação externa pode ter ocorrido, mas o estado final é incerto.',
                ];
            }

            return [
                'success' => $externalRevokeResult,
                'status' => $externalRevokeResult ? 'disconnected' : 'unknown',
                'external_google_revoke' => $externalRevokeResult,
                'local_connection_inactive' => true,
                'partial_failure' => !$externalRevokeResult,
                'message' => $externalRevokeResult
                    ? 'Conexão Google Calendar desativada com revogação externa confirmada.'
                    : 'Conexão local invalidada, mas a revogação externa falhou e o estado da conexão permanece incerto.',
            ];
        } catch (Throwable $e) {
            self::logDisconnectFailure($e, $this->tenantId, $this->userId, $operationId);

            return [
                'success' => false,
                'status' => 'unknown',
                'external_google_revoke' => false,
                'local_connection_inactive' => null,
                'partial_failure' => true,
                'message' => 'Falha inesperada ao processar a desconexão do Google Calendar; o estado final da conexão é incerto.',
            ];
        }
    }

    public function ensureReady(): void
    {
        if ($this->tenantId <= 0 || $this->userId <= 0) {
            throw new RuntimeException('Google Calendar exige tenant_id e user_id válidos.');
        }

        $connection = $this->repository->findForUser($this->tenantId, $this->userId);
        if (!$connection || ($connection['status'] ?? 'active') !== 'active' || empty($connection['access_token'])) {
            throw new RuntimeException('Google Calendar não está conectado para este tenant/usuário.');
        }

        $token = [
            'access_token' => $connection['access_token'],
            'refresh_token' => $connection['refresh_token'] ?? null,
            'expires_in' => $connection['expires_in'] ?? null,
            'scope' => $connection['scope'] ?? null,
            'token_type' => $connection['token_type'] ?? 'Bearer',
        ];

        $this->client->setAccessToken($token);
        $this->refreshTokenIfNeeded($this->client, $connection);
        $this->service = new Calendar($this->client);
    }

    public function createEvent(array $agendamento)
    {
        $this->ensureReady();

        $event = new Event();
        $event->setSummary($agendamento['title'] ?? 'Agendamento de Visita');
        $event->setDescription($agendamento['description'] ?? '');

        $startDateTime = new \DateTime($agendamento['data_hora']);
        $endDateTime = clone $startDateTime;
        $endDateTime->modify('+1 hour');

        $start = new \Google_Service_Calendar_EventDateTime();
        $start->setDateTime($startDateTime->format('c'));
        $start->setTimeZone('America/Sao_Paulo');
        $event->setStart($start);

        $end = new \Google_Service_Calendar_EventDateTime();
        $end->setDateTime($endDateTime->format('c'));
        $end->setTimeZone('America/Sao_Paulo');
        $event->setEnd($end);

        if (!empty($agendamento['location'])) {
            $event->setLocation($agendamento['location']);
        }

        if (!empty($agendamento['attendees'])) {
            $attendees = [];
            foreach ($agendamento['attendees'] as $email) {
                $attendees[] = ['email' => $email];
            }
            $event->setAttendees($attendees);
        }

        $reminders = new \Google_Service_Calendar_EventReminders();
        $reminders->setUseDefault(false);
        $reminders->setOverrides([
            ['method' => 'email', 'minutes' => 24 * 60],
            ['method' => 'popup', 'minutes' => 30],
        ]);
        $event->setReminders($reminders);

        try {
            return $this->service->events->insert($this->calendarId, $event);
        } catch (Exception $e) {
            throw new RuntimeException('Erro ao criar evento no Google Calendar: ' . $e->getMessage());
        }
    }

    public function updateEvent($eventId, array $agendamento)
    {
        $this->ensureReady();

        try {
            $event = $this->service->events->get($this->calendarId, $eventId);
            $event->setSummary($agendamento['title'] ?? 'Agendamento de Visita');
            $event->setDescription($agendamento['description'] ?? '');

            $startDateTime = new \DateTime($agendamento['data_hora']);
            $endDateTime = clone $startDateTime;
            $endDateTime->modify('+1 hour');

            $start = new \Google_Service_Calendar_EventDateTime();
            $start->setDateTime($startDateTime->format('c'));
            $start->setTimeZone('America/Sao_Paulo');
            $event->setStart($start);

            $end = new \Google_Service_Calendar_EventDateTime();
            $end->setDateTime($endDateTime->format('c'));
            $end->setTimeZone('America/Sao_Paulo');
            $event->setEnd($end);

            if (!empty($agendamento['location'])) {
                $event->setLocation($agendamento['location']);
            }

            return $this->service->events->update($this->calendarId, $eventId, $event);
        } catch (Exception $e) {
            throw new RuntimeException('Erro ao atualizar evento no Google Calendar: ' . $e->getMessage());
        }
    }

    public function deleteEvent($eventId)
    {
        $this->ensureReady();

        try {
            $this->service->events->delete($this->calendarId, $eventId);
            return true;
        } catch (Exception $e) {
            throw new RuntimeException('Erro ao deletar evento do Google Calendar: ' . $e->getMessage());
        }
    }

    public function getEvent($eventId)
    {
        $this->ensureReady();

        try {
            return $this->service->events->get($this->calendarId, $eventId);
        } catch (Exception $e) {
            throw new RuntimeException('Erro ao obter evento do Google Calendar: ' . $e->getMessage());
        }
    }

    public function listEvents($startDate, $endDate, $maxResults = 100)
    {
        $this->ensureReady();

        try {
            $optParams = [
                'maxResults' => $maxResults,
                'orderBy' => 'startTime',
                'singleEvents' => true,
                'timeMin' => (new \DateTime($startDate))->format('c'),
                'timeMax' => (new \DateTime($endDate))->format('c'),
                'timeZone' => 'America/Sao_Paulo',
            ];

            $results = $this->service->events->listEvents($this->calendarId, $optParams);
            return $results->getItems() ?? [];
        } catch (Exception $e) {
            throw new RuntimeException('Erro ao listar eventos do Google Calendar: ' . $e->getMessage());
        }
    }

    public function syncAllAgendamentos(int $userId, int $tenantId)
    {
        $this->ensureReady();

        try {
            $sql = 'SELECT id, user_id, property_id, data_agendamento AS data_hora, status, created_at FROM agendamentos WHERE tenant_id = ?';
            $params = [$tenantId];

            $sql .= ' AND user_id = ?';
            $params[] = $userId;
            $sql .= ' AND status IN ("confirmado", "realizado")';

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $synced = 0;
            $errors = [];

            foreach ($agendamentos as $agendamento) {
                try {
                    $syncRecord = $this->pdo->prepare(
                        'SELECT google_event_id FROM agendamentos_google_sync WHERE agendamento_id = ? AND tenant_id = ?'
                    );
                    $syncRecord->execute([$agendamento['id'], $tenantId]);
                    $syncData = $syncRecord->fetch(PDO::FETCH_ASSOC);

                    if ($syncData && !empty($syncData['google_event_id'])) {
                        $this->updateEvent($syncData['google_event_id'], [
                            'id' => $agendamento['id'],
                            'title' => 'Agendamento de Visita',
                            'data_hora' => $agendamento['data_hora'],
                            'description' => 'Status: ' . $agendamento['status'],
                        ]);
                    } else {
                        $event = $this->createEvent([
                            'id' => $agendamento['id'],
                            'title' => 'Agendamento de Visita',
                            'data_hora' => $agendamento['data_hora'],
                            'description' => 'Status: ' . $agendamento['status'],
                        ]);

                        $syncStmt = $this->pdo->prepare(
                            'INSERT INTO agendamentos_google_sync (tenant_id, agendamento_id, google_event_id) VALUES (?, ?, ?)'
                        );
                        $syncStmt->execute([$tenantId, $agendamento['id'], $event->getId()]);
                    }

                    $synced++;
                } catch (Exception $e) {
                    $errors[] = 'Agendamento #' . $agendamento['id'] . ': ' . $e->getMessage();
                }
            }

            return [
                'synced' => $synced,
                'total' => count($agendamentos),
                'errors' => $errors,
            ];
        } catch (Exception $e) {
            throw new RuntimeException('Erro ao sincronizar agendamentos: ' . $e->getMessage());
        }
    }

    public function isAuthenticated(): bool
    {
        if ($this->tenantId <= 0 || $this->userId <= 0) {
            return false;
        }

        $connection = $this->repository->findForUser($this->tenantId, $this->userId);
        if (!$connection || ($connection['status'] ?? 'active') !== 'active' || empty($connection['access_token'])) {
            return false;
        }

        if (!$this->client->getAccessToken()) {
            $this->client->setAccessToken([
                'access_token' => $connection['access_token'],
                'refresh_token' => $connection['refresh_token'] ?? null,
                'expires_in' => $connection['expires_in'] ?? null,
                'scope' => $connection['scope'] ?? null,
                'token_type' => $connection['token_type'] ?? 'Bearer',
            ]);
        }

        if ($this->client->isAccessTokenExpired() && !empty($connection['refresh_token'])) {
            $this->refreshTokenIfNeeded($this->client, $connection);
        }

        return (bool) $this->client->getAccessToken();
    }

    public function saveAccessToken(array $token): void
    {
        if ($this->tenantId <= 0 || $this->userId <= 0) {
            throw new RuntimeException('Google Calendar exige tenant_id e user_id válidos para salvar o token.');
        }

        $this->repository->upsertConnection($this->tenantId, $this->userId, $token);
        $this->client->setAccessToken($token);
        $this->service = new Calendar($this->client);
    }

    public function getAccessToken()
    {
        return $this->client ? $this->client->getAccessToken() : null;
    }
}

final class GoogleCalendarServiceFactory
{
    public static function create(?callable $factory = null, ?PDO $pdo = null)
    {
        if ($factory !== null) {
            $service = $factory();
            if (!is_object($service) || !method_exists($service, 'disconnectConnectionWithStatus')) {
                throw new TypeError('GoogleCalendarServiceFactory must return an object with a disconnectConnectionWithStatus() method.');
            }
            return $service;
        }

        try {
            return new GoogleCalendarService(null, null, $pdo);
        } catch (RuntimeException $e) {
            if ($pdo === null && file_exists(__DIR__ . '/../config/database.php')) {
                require_once __DIR__ . '/../config/database.php';
                if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
                    return new GoogleCalendarService(null, null, $pdo);
                }
            }

            throw $e;
        }
    }
}
