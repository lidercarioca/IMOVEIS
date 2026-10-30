<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../api/v1/google_calendar.php';

class GoogleCalendarDisconnectRouteIsolatedTest extends TestCase
{
    private function invokeDisconnectRoute(string $method, array $payload, array $session, object $service): array
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../../api/v1/google_calendar.php';
        $_SERVER['argv'] = ['google_calendar.php'];
        $_GET = ['action' => 'disconnect'];
        $_POST = $payload;
        $_SESSION = $session;
        $GLOBALS['tenantId'] = (int) ($session['tenant_id'] ?? 0);

        $handler = new \GoogleCalendarDisconnectHttpHandler(
            $service,
            (int) ($session['tenant_id'] ?? 0),
            (int) ($session['user_id'] ?? 0),
            $method,
            $payload,
            static function (\Throwable $e): void {},
            static fn(string $token): bool => $token === ($_SESSION['csrf_token'] ?? '')
        );

        return $handler->handle();
    }

    public function testItRejectsInvalidMethodWithoutCallingService(): void
    {
        $service = new class {
            public array $calls = [];

            public function disconnectConnectionWithStatus(): array
            {
                $this->calls[] = 'disconnect';
                return ['success' => true, 'status' => 'disconnected'];
            }
        };

        [$status, $payload] = $this->invokeDisconnectRoute('GET', [], [
            'user_id' => 42,
            'tenant_id' => 99,
            'csrf_token' => 'valid-token',
        ], $service);

        $this->assertSame(405, $status);
        $this->assertSame('Método inválido. Use POST para desconectar o Google Calendar.', $payload['error'] ?? '');
        $this->assertSame([], $service->calls);
    }

    public function testItRejectsInvalidCsrfToken(): void
    {
        $service = new class {
            public array $calls = [];

            public function disconnectConnectionWithStatus(): array
            {
                $this->calls[] = 'disconnect';
                return ['success' => true, 'status' => 'disconnected'];
            }
        };

        [$status, $payload] = $this->invokeDisconnectRoute('POST', ['csrf_token' => 'bad-token'], [
            'user_id' => 42,
            'tenant_id' => 99,
            'csrf_token' => 'valid-token',
        ], $service);

        $this->assertSame(403, $status);
        $this->assertSame('Token CSRF inválido.', $payload['error'] ?? '');
        $this->assertSame([], $service->calls);
    }

    public function testItRejectsInvalidSessionBeforeCallingService(): void
    {
        $service = new class {
            public array $calls = [];

            public function disconnectConnectionWithStatus(): array
            {
                $this->calls[] = 'disconnect';
                return ['success' => true, 'status' => 'disconnected'];
            }
        };

        [$status, $payload] = $this->invokeDisconnectRoute('POST', ['csrf_token' => 'valid-token'], [
            'csrf_token' => 'valid-token',
        ], $service);

        $this->assertSame(401, $status);
        $this->assertSame('Sessão inválida para desconectar o Google Calendar.', $payload['error'] ?? '');
        $this->assertSame([], $service->calls);
    }

    public function testItReturnsSuccessPayloadWhenLocalDisconnectIsConfirmed(): void
    {
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

        [$status, $payload] = $this->invokeDisconnectRoute('POST', ['csrf_token' => 'valid-token'], [
            'user_id' => 42,
            'tenant_id' => 99,
            'csrf_token' => 'valid-token',
        ], $service);

        $this->assertSame(200, $status);
        $this->assertTrue((bool) ($payload['success'] ?? false));
        $this->assertSame('disconnected', $payload['status'] ?? '');
        $this->assertTrue((bool) ($payload['external_google_revoke'] ?? false));
        $this->assertTrue((bool) ($payload['local_connection_inactive'] ?? false));
        $this->assertFalse((bool) ($payload['partial_failure'] ?? true));
    }

    public function testItReturnsFailurePayloadWhenLocalDisconnectCannotBeConfirmed(): void
    {
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

        [$status, $payload] = $this->invokeDisconnectRoute('POST', ['csrf_token' => 'valid-token'], [
            'user_id' => 42,
            'tenant_id' => 99,
            'csrf_token' => 'valid-token',
        ], $service);

        $this->assertSame(500, $status);
        $this->assertFalse((bool) ($payload['success'] ?? true));
        $this->assertSame('unknown', $payload['status'] ?? '');
        $this->assertNull($payload['local_connection_inactive'] ?? null);
        $this->assertTrue((bool) ($payload['partial_failure'] ?? false));
    }
}
