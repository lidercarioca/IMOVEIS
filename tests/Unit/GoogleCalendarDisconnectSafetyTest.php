<?php

namespace Tests\Unit;

use App\Infrastructure\Google\GoogleCalendarConnectionRepository;
use GoogleCalendarDisconnectHttpHandler;
use GoogleCalendarService;
use PDO;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../google/GoogleCalendarService.php';
require_once __DIR__ . '/../../api/v1/google_calendar.php';

class GoogleCalendarDisconnectSafetyTest extends TestCase
{
    private PDO $pdo;
    private GoogleCalendarConnectionRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->repository = new GoogleCalendarConnectionRepository($this->pdo);
        $this->repository->ensureTableExists();
    }

    public function testItMarksLocalConnectionDisconnectedEvenWhenGoogleRevokeFails(): void
    {
        $this->repository->upsertConnection(
            99,
            42,
            [
                'access_token' => 'real-access-token',
                'refresh_token' => 'real-refresh-token',
                'status' => 'active',
            ],
            'owner@example.com',
            'google-account-1'
        );

        $service = new class(99, 42, $this->pdo) extends GoogleCalendarService {
            public function revokeExternalGoogleToken(?array $connection = null): bool
            {
                return false;
            }
        };

        $result = $service->disconnectConnectionWithStatus();

        $this->assertFalse($result['success']);
        $this->assertSame('unknown', $result['status']);
        $this->assertFalse($result['external_google_revoke']);
        $this->assertTrue($result['local_connection_inactive']);
        $this->assertTrue($result['partial_failure']);
        $this->assertStringContainsString('estado da conexão permanece incerto', $result['message']);

        $connection = $this->repository->findForUser(99, 42);
        $this->assertNotNull($connection);
        $this->assertSame('disconnected', $connection['status']);
        $this->assertNull($connection['access_token']);
        $this->assertNull($connection['refresh_token']);
    }

    public function testItReturnsStructuredFailureWhenUnexpectedRevokeErrorOccurs(): void
    {
        $this->repository->upsertConnection(
            99,
            42,
            [
                'access_token' => 'real-access-token',
                'refresh_token' => 'real-refresh-token',
                'status' => 'active',
            ],
            'owner@example.com',
            'google-account-1'
        );

        $service = new class(99, 42, $this->pdo) extends GoogleCalendarService {
            public function revokeExternalGoogleToken(?array $connection = null): bool
            {
                throw new \TypeError('Unexpected Google revoke error');
            }
        };

        $result = $service->disconnectConnectionWithStatus();

        $this->assertFalse($result['success']);
        $this->assertSame('unknown', $result['status']);
        $this->assertFalse($result['external_google_revoke']);
        $this->assertNull($result['local_connection_inactive']);
        $this->assertTrue($result['partial_failure']);
        $this->assertStringContainsString('estado final da conexão é incerto', $result['message']);
    }

    public function testItSanitizesTokenPayloadsFromGoogleErrorMessages(): void
    {
        $details = GoogleCalendarService::sanitizeGoogleErrorDetails([
            'error' => 'invalid_grant',
            'access_token' => 'secret-token-value',
            'refresh_token' => 'refresh-token-value',
            'code' => 'auth-code-123',
            'status' => 'failed',
        ]);

        $this->assertStringContainsString('invalid_grant', $details);
        $this->assertStringContainsString('[REDACTED]', $details);
        $this->assertStringNotContainsString('secret-token-value', $details);
        $this->assertStringNotContainsString('refresh-token-value', $details);
        $this->assertStringNotContainsString('auth-code-123', $details);
    }

    public function testItRejectsNonPostMethodForDisconnect(): void
    {
        $service = new class {
            public array $calls = [];

            public function disconnectConnectionWithStatus(): array
            {
                $this->calls[] = 'disconnect';
                return ['status' => 'active', 'success' => true];
            }
        };

        $handler = new GoogleCalendarDisconnectHttpHandler($service, 1, 42, 'GET', ['csrf_token' => 'ignored']);
        [$statusCode, $payload] = $handler->handle();

        $this->assertSame(405, $statusCode);
        $this->assertSame('Método inválido. Use POST para desconectar o Google Calendar.', $payload['error']);
        $this->assertSame([], $service->calls);
    }

    public function testItRejectsInvalidCsrfToken(): void
    {
        $_SESSION['csrf_token'] = 'valid-token';
        $service = new class {
            public array $calls = [];

            public function disconnectConnectionWithStatus(): array
            {
                $this->calls[] = 'disconnect';
                return ['status' => 'disconnected', 'success' => true];
            }
        };

        $handler = new GoogleCalendarDisconnectHttpHandler($service, 1, 42, 'POST', ['csrf_token' => 'bad-token']);
        [$statusCode, $payload] = $handler->handle();

        $this->assertSame(403, $statusCode);
        $this->assertSame('Token CSRF inválido.', $payload['error']);
        $this->assertSame([], $service->calls);
    }

    public function testItRejectsUnauthenticatedSession(): void
    {
        $_SESSION['csrf_token'] = 'valid-token';

        $service = new class {
            public array $calls = [];

            public function disconnectConnectionWithStatus(): array
            {
                $this->calls[] = 'disconnect';
                return ['status' => 'disconnected', 'success' => true];
            }
        };

        $handler = new GoogleCalendarDisconnectHttpHandler($service, 0, 0, 'POST', ['csrf_token' => 'valid-token'], null, static fn(string $token): bool => $token === 'valid-token');
        [$statusCode, $payload] = $handler->handle();

        $this->assertSame(401, $statusCode);
        $this->assertSame('Sessão inválida para desconectar o Google Calendar.', $payload['error']);
        $this->assertSame([], $service->calls);
    }

    public function testItReturnsUnauthorizedJsonForApiAuthException(): void
    {
        $_SESSION = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        try {
            require_once __DIR__ . '/../../api/check_auth.php';
            checkApiAuth();
            $this->fail('Expected ApiAuthException for missing user session.');
        } catch (\Throwable $e) {
            $this->assertInstanceOf(\Exception::class, $e);
            $this->assertStringContainsString('Não autorizado', $e->getMessage());
        }
    }

    public function testItReturnsStructuredUnknownStateWhenHandlerThrows(): void
    {
        $_SESSION['csrf_token'] = 'valid-token';

        $service = new class {
            public function disconnectConnectionWithStatus(): array
            {
                throw new \TypeError('Unexpected disconnect handler error');
            }
        };

        $handler = new GoogleCalendarDisconnectHttpHandler($service, 1, 42, 'POST', ['csrf_token' => 'valid-token'], static function (\Throwable $e): void {}, static fn(string $token): bool => $token === 'valid-token');
        [$statusCode, $payload] = $handler->handle();

        $this->assertSame(500, $statusCode);
        $this->assertSame('unknown', $payload['status']);
        $this->assertSame('Falha inesperada ao processar a desconexão do Google Calendar; o estado final da conexão é incerto.', $payload['message']);
        $this->assertFalse($payload['success']);
        $this->assertNull($payload['local_connection_inactive']);
    }

    public function testItReturnsJsonPayloadAndHttpStatusForDisconnectFailure(): void
    {
        $_SESSION['csrf_token'] = 'valid-token';

        $service = new class {
            public function disconnectConnectionWithStatus(): array
            {
                return [
                    'success' => false,
                    'status' => 'unknown',
                    'external_google_revoke' => false,
                    'local_connection_inactive' => null,
                    'partial_failure' => true,
                    'message' => 'Não foi possível confirmar o estado final da conexão.',
                ];
            }
        };

        $handler = new GoogleCalendarDisconnectHttpHandler($service, 1, 42, 'POST', ['csrf_token' => 'valid-token'], static function (\Throwable $e): void {}, static fn(string $token): bool => $token === 'valid-token');
        [$statusCode, $payload] = $handler->handle();

        $this->assertSame(500, $statusCode);
        $this->assertFalse($payload['success']);
        $this->assertSame('unknown', $payload['status']);
        $this->assertFalse($payload['external_google_revoke']);
        $this->assertNull($payload['local_connection_inactive']);
        $this->assertTrue($payload['partial_failure']);
        $this->assertStringContainsString('estado final', strtolower($payload['message']));
    }

    public function testItRejectsInvalidTenantOrUserBeforeCallingService(): void
    {
        $_SESSION['csrf_token'] = 'valid-token';

        $service = new class {
            public bool $called = false;

            public function disconnectConnectionWithStatus(): array
            {
                $this->called = true;
                return ['success' => true, 'status' => 'disconnected'];
            }
        };

        $handler = new GoogleCalendarDisconnectHttpHandler($service, 0, 42, 'POST', ['csrf_token' => 'valid-token'], null, static fn(string $token): bool => $token === 'valid-token');
        [$statusCode, $payload] = $handler->handle();

        $this->assertSame(401, $statusCode);
        $this->assertSame('Sessão inválida para desconectar o Google Calendar.', $payload['error']);
        $this->assertFalse($service->called);
    }

    public function testItReturnsSuccessfulJsonPayloadWhenDisconnectIsConfirmed(): void
    {
        $_SESSION['csrf_token'] = 'valid-token';

        $service = new class {
            public function disconnectConnectionWithStatus(): array
            {
                return [
                    'success' => true,
                    'status' => 'disconnected',
                    'external_google_revoke' => true,
                    'local_connection_inactive' => true,
                    'partial_failure' => false,
                    'message' => 'Conexão Google Calendar desativada com revogação externa confirmada.',
                ];
            }
        };

        $handler = new GoogleCalendarDisconnectHttpHandler($service, 1, 42, 'POST', ['csrf_token' => 'valid-token'], static function (\Throwable $e): void {}, static fn(string $token): bool => $token === 'valid-token');
        [$statusCode, $payload] = $handler->handle();

        $this->assertSame(200, $statusCode);
        $this->assertTrue($payload['success']);
        $this->assertSame('disconnected', $payload['status']);
        $this->assertTrue($payload['external_google_revoke']);
        $this->assertTrue($payload['local_connection_inactive']);
        $this->assertFalse($payload['partial_failure']);
        $this->assertStringContainsString('desativada', strtolower($payload['message']));
    }

    public function testItUsesInjectedServiceDoubleWithoutInstantiatingGoogleClient(): void
    {
        $_SESSION['csrf_token'] = 'valid-token';

        $service = new class {
            public bool $googleClientWasConstructed = false;

            public function disconnectConnectionWithStatus(): array
            {
                // This stub intentionally never touches Google\Client or any network layer.
                return [
                    'success' => true,
                    'status' => 'disconnected',
                    'external_google_revoke' => false,
                    'local_connection_inactive' => true,
                    'partial_failure' => false,
                    'message' => 'Stubbed success without external client usage.',
                ];
            }
        };

        $handler = new GoogleCalendarDisconnectHttpHandler($service, 1, 42, 'POST', ['csrf_token' => 'valid-token'], static function (\Throwable $e): void {}, static fn(string $token): bool => $token === 'valid-token');
        [$statusCode, $payload] = $handler->handle();

        $this->assertSame(200, $statusCode);
        $this->assertTrue($payload['success']);
        $this->assertSame('disconnected', $payload['status']);
        $this->assertFalse($payload['external_google_revoke']);
    }

    public function testItAllowsCodeLevelServiceFactoryOverrideWithoutHttpPayloadControl(): void
    {
        $fakeService = new class {
            public function disconnectConnectionWithStatus(): array
            {
                return ['success' => true, 'status' => 'disconnected', 'external_google_revoke' => false, 'local_connection_inactive' => true, 'partial_failure' => false, 'message' => 'teste fake'];
            }
        };

        $this->assertSame($fakeService, \GoogleCalendarServiceFactory::create(static fn() => $fakeService));
        $this->assertInstanceOf(GoogleCalendarService::class, \GoogleCalendarServiceFactory::create(null, $this->pdo));
    }

    public function testItDoesNotReadFactoryOverrideFromHttpRequestData(): void
    {
        $_GET['factory_override'] = 'fake';
        $_POST['factory_override'] = 'fake';

        $service = \GoogleCalendarServiceFactory::create(null, $this->pdo);

        $this->assertInstanceOf(GoogleCalendarService::class, $service);

        unset($_GET['factory_override'], $_POST['factory_override']);
    }

    public function testItRunsTheEndpointBootstrapWithExplicitInjectedFakeService(): void
    {
        session_start();
        $_SESSION = [
            'user_id' => 42,
            'tenant_id' => 1,
            'role' => 'admin',
            'csrf_token' => 'valid-token',
            'last_activity' => time(),
        ];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../../api/v1/google_calendar.php';
        $_GET = ['action' => 'disconnect'];
        $_POST = ['csrf_token' => 'valid-token'];

        $fakeService = new class {
            public function disconnectConnectionWithStatus(): array
            {
                return [
                    'success' => true,
                    'status' => 'disconnected',
                    'external_google_revoke' => false,
                    'local_connection_inactive' => true,
                    'partial_failure' => false,
                    'message' => 'fake-service-success',
                ];
            }
        };

        ob_start();
        runGoogleCalendarHttpEndpoint(static fn() => $fakeService, $this->pdo);
        $output = ob_get_clean();

        $this->assertStringContainsString('"success":true', $output);
        $this->assertStringContainsString('"status":"disconnected"', $output);
        $this->assertStringContainsString('fake-service-success', $output);
    }

    public function testItRejectsInvalidCsrfWhenRunningRealEndpointBootstrap(): void
    {
        session_start();
        $_SESSION = [
            'user_id' => 42,
            'tenant_id' => 1,
            'role' => 'admin',
            'csrf_token' => 'valid-token',
            'last_activity' => time(),
        ];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../../api/v1/google_calendar.php';
        $_GET = ['action' => 'disconnect'];
        $_POST = ['csrf_token' => 'bad-token'];

        $fakeService = new class {
            public function disconnectConnectionWithStatus(): array
            {
                return ['success' => true, 'status' => 'disconnected'];
            }
        };

        ob_start();
        runGoogleCalendarHttpEndpoint(static fn() => $fakeService, $this->pdo);
        $output = ob_get_clean();

        $this->assertStringContainsString('Token CSRF inválido', $output);
        $this->assertStringContainsString('"success":false', $output);
    }

    public function testItRequiresAValidPdoBeforeResolvingTenantContext(): void
    {
        $originalGlobalPdo = $GLOBALS['pdo'] ?? null;
        unset($GLOBALS['pdo']);

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Google Calendar endpoint requires a valid PDO connection.');

            resolveGoogleCalendarPdo();
        } finally {
            if ($originalGlobalPdo instanceof PDO) {
                $GLOBALS['pdo'] = $originalGlobalPdo;
            }
        }
    }
}
