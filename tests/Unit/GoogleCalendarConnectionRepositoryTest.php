<?php

namespace Tests\Unit;

use App\Infrastructure\Google\GoogleCalendarConnectionRepository;
use PDO;
use PHPUnit\Framework\TestCase;

class GoogleCalendarConnectionRepositoryTest extends TestCase
{
    private PDO $pdo;
    private GoogleCalendarConnectionRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->repository = new GoogleCalendarConnectionRepository($this->pdo);
    }

    public function testItCreatesConnectionForTenantAndUser(): void
    {
        $this->repository->ensureTableExists();

        $saved = $this->repository->upsertConnection(
            15,
            23,
            [
                'access_token' => 'access-123',
                'refresh_token' => 'refresh-123',
                'expires_in' => 3600,
                'scope' => 'https://www.googleapis.com/auth/calendar',
                'token_type' => 'Bearer',
                'provider' => 'google',
                'status' => 'active',
                'created' => time(),
            ],
            'owner@example.com',
            'google-account-1'
        );

        $this->assertTrue($saved);

        $connection = $this->repository->findForUser(15, 23);
        $this->assertNotNull($connection);
        $this->assertSame(15, (int) $connection['tenant_id']);
        $this->assertSame(23, (int) $connection['user_id']);
        $this->assertSame('owner@example.com', $connection['google_user_email']);
        $this->assertSame('google-account-1', $connection['provider_account_id']);
        $this->assertSame('access-123', $connection['access_token']);
        $this->assertSame('refresh-123', $connection['refresh_token']);
    }

    public function testItFindsConnectionForExactOwnerOnly(): void
    {
        $this->repository->ensureTableExists();
        $this->repository->upsertConnection(10, 5, ['access_token' => 'token-a'], 'a@example.com');

        $this->assertNotNull($this->repository->findForUser(10, 5));
        $this->assertNull($this->repository->findForUser(10, 99));
        $this->assertNull($this->repository->findForUser(99, 5));
    }

    public function testItDoesNotLeakConnectionAcrossTenants(): void
    {
        $this->repository->ensureTableExists();

        $this->repository->upsertConnection(1, 5, ['access_token' => 'token-a'], 'a@example.com');
        $this->repository->upsertConnection(2, 5, ['access_token' => 'token-b'], 'b@example.com');

        $connection = $this->repository->findForUser(1, 5);
        $this->assertNotNull($connection);
        $this->assertSame('token-a', $connection['access_token']);

        $otherConnection = $this->repository->findForUser(2, 5);
        $this->assertNotNull($otherConnection);
        $this->assertSame('token-b', $otherConnection['access_token']);

        $this->assertNull($this->repository->findForUser(99, 5));
    }

    public function testItDoesNotAllowUserMismatchWithinSameTenant(): void
    {
        $this->repository->ensureTableExists();
        $this->repository->upsertConnection(7, 8, ['access_token' => 'owner-token'], 'owner@example.com');

        $this->assertNull($this->repository->findForUser(7, 9));
        $this->assertNull($this->repository->findForUser(9, 8));
    }

    public function testItUpdatesOnlyTheMatchingOwnerConnection(): void
    {
        $this->repository->ensureTableExists();

        $this->repository->upsertConnection(3, 4, ['access_token' => 'old-token'], 'old@example.com');
        $this->repository->upsertConnection(3, 4, ['access_token' => 'new-token', 'refresh_token' => 'refresh-2'], 'new@example.com');

        $this->assertSame('new-token', $this->repository->findForUser(3, 4)['access_token']);
        $this->assertSame('new@example.com', $this->repository->findForUser(3, 4)['google_user_email']);
    }

    public function testItDoesNotOverwriteAnotherTenantOrUserConnection(): void
    {
        $this->repository->ensureTableExists();

        $this->repository->upsertConnection(50, 11, ['access_token' => 'tenant-a-user-a'], 'a@example.com');
        $this->repository->upsertConnection(50, 12, ['access_token' => 'tenant-a-user-b'], 'b@example.com');
        $this->repository->upsertConnection(51, 11, ['access_token' => 'tenant-b-user-a'], 'c@example.com');

        $this->assertSame('tenant-a-user-a', $this->repository->findForUser(50, 11)['access_token']);
        $this->assertSame('tenant-a-user-b', $this->repository->findForUser(50, 12)['access_token']);
        $this->assertSame('tenant-b-user-a', $this->repository->findForUser(51, 11)['access_token']);
    }

    public function testItDeletesOnlyTheMatchingOwnerConnection(): void
    {
        $this->repository->ensureTableExists();

        $this->repository->upsertConnection(20, 1, ['access_token' => 'token-one'], 'one@example.com');
        $this->repository->upsertConnection(20, 2, ['access_token' => 'token-two'], 'two@example.com');

        $this->assertTrue($this->repository->deleteConnection(20, 1));

        $this->assertNull($this->repository->findForUser(20, 1));
        $this->assertSame('token-two', $this->repository->findForUser(20, 2)['access_token']);
    }

    public function testItReportsAlreadyDisconnectedConnectionAsNoOp(): void
    {
        $this->repository->ensureTableExists();
        $this->repository->upsertConnection(50, 11, ['access_token' => 'token-a', 'status' => 'disconnected'], 'a@example.com');

        $result = $this->repository->disconnectConnectionStatus(50, 11, 'disconnected');

        $this->assertTrue($result['success']);
        $this->assertSame('disconnected', $result['status']);
        $this->assertTrue($result['already_disconnected']);
        $this->assertTrue($result['found']);
        $this->assertStringContainsString('já estava desconectada', strtolower($result['message']));
    }

    public function testItReportsMissingConnectionAsNotFoundState(): void
    {
        $this->repository->ensureTableExists();

        $result = $this->repository->disconnectConnectionStatus(500, 500, 'disconnected');

        $this->assertFalse($result['success']);
        $this->assertSame('not_found', $result['status']);
        $this->assertFalse($result['updated']);
        $this->assertFalse($result['found']);
    }

    public function testItHandlesMissingConnectionSafely(): void
    {
        $this->repository->ensureTableExists();

        $this->assertNull($this->repository->findForUser(1000, 1000));
        $this->assertFalse($this->repository->upsertConnection(0, 1, ['access_token' => 'token']));
        $this->assertFalse($this->repository->deleteConnection(0, 1));
    }

    public function testItRejectsInvalidOwnershipContext(): void
    {
        $this->repository->ensureTableExists();

        $this->assertNull($this->repository->findForUser(0, 1));
        $this->assertNull($this->repository->findForUser(1, 0));
        $this->assertFalse($this->repository->upsertConnection(-1, 2, ['access_token' => 'token']));
        $this->assertFalse($this->repository->deleteConnection(-1, 2));
    }

    public function testItDisconnectsOnlyTheExactOwnerConnection(): void
    {
        $this->repository->ensureTableExists();

        $this->repository->upsertConnection(30, 7, ['access_token' => 'token-a', 'status' => 'active'], 'a@example.com');
        $this->repository->upsertConnection(30, 8, ['access_token' => 'token-b', 'status' => 'active'], 'b@example.com');

        $this->assertTrue($this->repository->disconnectConnection(30, 7, 'disconnected'));

        $connectionA = $this->repository->findForUser(30, 7);
        $this->assertNotNull($connectionA);
        $this->assertSame('disconnected', $connectionA['status']);
        $this->assertNull($connectionA['access_token']);
        $this->assertTrue($this->repository->isConnectionActiveForUser(30, 7) === false);

        $connectionB = $this->repository->findForUser(30, 8);
        $this->assertNotNull($connectionB);
        $this->assertSame('active', $connectionB['status']);
        $this->assertSame('token-b', $connectionB['access_token']);

        $this->assertFalse($this->repository->disconnectConnection(31, 7, 'disconnected'));
    }

    public function testItDoesNotAllowCrossTenantOrCrossUserDisconnect(): void
    {
        $this->repository->ensureTableExists();
        $this->repository->upsertConnection(40, 10, ['access_token' => 'owner-token', 'status' => 'active'], 'owner@example.com');

        $this->assertFalse($this->repository->disconnectConnection(41, 10, 'disconnected'));
        $this->assertFalse($this->repository->disconnectConnection(40, 11, 'disconnected'));

        $connection = $this->repository->findForUser(40, 10);
        $this->assertNotNull($connection);
        $this->assertSame('active', $connection['status']);
        $this->assertSame('owner-token', $connection['access_token']);
    }
}
