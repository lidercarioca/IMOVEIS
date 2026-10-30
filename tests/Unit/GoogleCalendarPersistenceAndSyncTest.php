<?php

namespace Tests\Unit;

require_once dirname(__DIR__, 2) . '/google/GoogleCalendarService.php';
require_once dirname(__DIR__, 2) . '/src/Infrastructure/Google/GoogleCalendarConnectionRepository.php';

use App\Infrastructure\Google\GoogleCalendarConnectionRepository;
use PDO;
use PHPUnit\Framework\TestCase;

class GoogleCalendarPersistenceAndSyncTest extends TestCase
{
    private PDO $pdo;
    private GoogleCalendarConnectionRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE agendamentos ('
                . 'id INTEGER PRIMARY KEY, '
                . 'tenant_id INTEGER NOT NULL, '
                . 'user_id INTEGER NOT NULL, '
                . 'property_id INTEGER, '
                . 'data_agendamento TEXT, '
                . 'status TEXT, '
                . 'created_at TEXT'
                . ')'
        );
        $this->pdo->exec(
            'CREATE TABLE agendamentos_google_sync ('
                . 'id INTEGER PRIMARY KEY AUTOINCREMENT, '
                . 'tenant_id INTEGER NOT NULL, '
                . 'agendamento_id INTEGER NOT NULL, '
                . 'google_event_id TEXT, '
                . 'synced_at TEXT DEFAULT CURRENT_TIMESTAMP, '
                . 'last_updated TEXT DEFAULT CURRENT_TIMESTAMP, '
                . 'UNIQUE (tenant_id, agendamento_id)'
                . ')'
        );

        $this->repository = new GoogleCalendarConnectionRepository($this->pdo);
    }

    public function testItPersistsAndRetrievesOnlyTheMatchingTenantAndUserConnection(): void
    {
        $this->repository->ensureTableExists();

        $service = new class(15, 23, $this->pdo) extends \GoogleCalendarService {
            public function __construct(int $tenantId, int $userId, PDO $pdo)
            {
                parent::__construct($tenantId, $userId, $pdo);
            }
        };

        $service->saveAccessToken([
            'access_token' => 'access-for-15-23',
            'refresh_token' => 'refresh-for-15-23',
            'expires_in' => 3600,
            'scope' => 'https://www.googleapis.com/auth/calendar',
            'token_type' => 'Bearer',
            'status' => 'active',
        ]);

        $connection = $this->repository->findForUser(15, 23);
        $this->assertNotNull($connection);
        $this->assertSame('access-for-15-23', $connection['access_token']);
        $this->assertSame('refresh-for-15-23', $connection['refresh_token']);
        $this->assertNull($this->repository->findForUser(15, 24));
        $this->assertNull($this->repository->findForUser(16, 23));
    }

    public function testItCreatesEventsOnceAndUpdatesExistingMappingsWithoutDuplicatingThem(): void
    {
        $this->pdo->exec("INSERT INTO agendamentos (id, tenant_id, user_id, property_id, data_agendamento, status, created_at) VALUES (1, 7, 9, 101, '2026-10-13 09:00:00', 'confirmado', '2026-10-13 00:00:00')");
        $this->pdo->exec("INSERT INTO agendamentos (id, tenant_id, user_id, property_id, data_agendamento, status, created_at) VALUES (2, 7, 9, 102, '2026-10-13 11:00:00', 'realizado', '2026-10-13 00:00:00')");

        $service = new class(7, 9, $this->pdo) extends \GoogleCalendarService {
            public int $createCalls = 0;
            public int $updateCalls = 0;

            public function __construct(int $tenantId, int $userId, PDO $pdo)
            {
                parent::__construct($tenantId, $userId, $pdo);
            }

            public function ensureReady(): void {}

            public function createEvent(array $agendamento)
            {
                $this->createCalls++;

                return new class('google-event-' . $this->createCalls) {
                    private string $id;

                    public function __construct(string $id)
                    {
                        $this->id = $id;
                    }

                    public function getId(): string
                    {
                        return $this->id;
                    }
                };
            }

            public function updateEvent($eventId, array $agendamento)
            {
                $this->updateCalls++;
                return (object) ['id' => $eventId];
            }
        };

        $firstRun = $service->syncAllAgendamentos(9, 7);
        $this->assertSame(2, $firstRun['synced']);
        $this->assertSame(2, $firstRun['total']);
        $this->assertSame(2, $this->pdo->query('SELECT COUNT(*) FROM agendamentos_google_sync')->fetchColumn());
        $this->assertSame(2, $service->createCalls);
        $this->assertSame(0, $service->updateCalls);

        $secondRun = $service->syncAllAgendamentos(9, 7);
        $this->assertSame(2, $secondRun['synced']);
        $this->assertSame(2, $this->pdo->query('SELECT COUNT(*) FROM agendamentos_google_sync')->fetchColumn());
        $this->assertSame(2, $service->createCalls);
        $this->assertSame(2, $service->updateCalls);
    }

    public function testItReportsPartialSyncFailureWithoutDuplicatingSuccessfulMappings(): void
    {
        $this->pdo->exec("INSERT INTO agendamentos (id, tenant_id, user_id, property_id, data_agendamento, status, created_at) VALUES (1, 7, 9, 101, '2026-10-13 09:00:00', 'confirmado', '2026-10-13 00:00:00')");
        $this->pdo->exec("INSERT INTO agendamentos (id, tenant_id, user_id, property_id, data_agendamento, status, created_at) VALUES (2, 7, 9, 102, '2026-10-13 11:00:00', 'realizado', '2026-10-13 00:00:00')");

        $service = new class(7, 9, $this->pdo) extends \GoogleCalendarService {
            public function __construct(int $tenantId, int $userId, PDO $pdo)
            {
                parent::__construct($tenantId, $userId, $pdo);
            }

            public function ensureReady(): void {}

            public function createEvent(array $agendamento)
            {
                if (($agendamento['data_hora'] ?? '') === '2026-10-13 09:00:00') {
                    throw new \RuntimeException('falha externa simulada');
                }

                return new class('google-event-ok') {
                    private string $id;

                    public function __construct(string $id)
                    {
                        $this->id = $id;
                    }

                    public function getId(): string
                    {
                        return $this->id;
                    }
                };
            }
        };

        $result = $service->syncAllAgendamentos(9, 7);

        $this->assertSame(1, $result['synced']);
        $this->assertSame(2, $result['total']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('Agendamento #1', $result['errors'][0]);
        $this->assertSame(1, $this->pdo->query('SELECT COUNT(*) FROM agendamentos_google_sync')->fetchColumn());
    }

    public function testItDoesNotRemoveLocalSyncMappingsWhenDeletingTheGoogleEvent(): void
    {
        $this->pdo->exec("INSERT INTO agendamentos_google_sync (tenant_id, agendamento_id, google_event_id) VALUES (7, 1, 'google-evt-tenant-7-1')");
        $this->pdo->exec("INSERT INTO agendamentos_google_sync (tenant_id, agendamento_id, google_event_id) VALUES (9, 99, 'google-evt-tenant-9-99')");

        $service = new class(7, 9, $this->pdo) extends \GoogleCalendarService {
            public function __construct(int $tenantId, int $userId, PDO $pdo)
            {
                parent::__construct($tenantId, $userId, $pdo);
            }

            public function ensureReady(): void {}
        };

        $fakeGoogleService = new class extends \Google\Service\Calendar {
            public function __construct()
            {
                parent::__construct(new \Google\Client());
                $this->events = new class {
                    public int $deleteCalls = 0;

                    public function delete(string $calendarId, string $eventId): bool
                    {
                        $this->deleteCalls++;
                        return true;
                    }
                };
            }
        };

        $reflectedProperty = new \ReflectionProperty(\GoogleCalendarService::class, 'service');
        $reflectedProperty->setValue($service, $fakeGoogleService);
        $reflectedProperty = new \ReflectionProperty(\GoogleCalendarService::class, 'calendarId');
        $reflectedProperty->setValue($service, 'primary');

        $result = $service->deleteEvent('google-evt-tenant-7-1');

        $this->assertTrue($result);
        $this->assertSame(2, $this->pdo->query('SELECT COUNT(*) FROM agendamentos_google_sync')->fetchColumn());
        $this->assertSame('google-evt-tenant-7-1', $this->pdo->query("SELECT google_event_id FROM agendamentos_google_sync WHERE tenant_id = 7 AND agendamento_id = 1")->fetchColumn());
        $this->assertSame('google-evt-tenant-9-99', $this->pdo->query("SELECT google_event_id FROM agendamentos_google_sync WHERE tenant_id = 9 AND agendamento_id = 99")->fetchColumn());

        $this->pdo->exec('DELETE FROM agendamentos_google_sync WHERE tenant_id = 7 AND agendamento_id = 1');
        $this->assertTrue($service->deleteEvent('google-evt-absent'));
        $this->assertSame(1, $this->pdo->query('SELECT COUNT(*) FROM agendamentos_google_sync')->fetchColumn());
    }

    public function testItReportsRealLocalPersistenceFailureWithoutInformingSuccessfulSync(): void
    {
        $this->pdo->exec("INSERT INTO agendamentos (id, tenant_id, user_id, property_id, data_agendamento, status, created_at) VALUES (1, 7, 9, 101, '2026-10-13 09:00:00', 'confirmado', '2026-10-13 00:00:00')");
        $this->pdo->exec('CREATE TRIGGER before_insert_google_sync BEFORE INSERT ON agendamentos_google_sync BEGIN SELECT RAISE(ABORT, "local persistence failed"); END;');

        $service = new class(7, 9, $this->pdo) extends \GoogleCalendarService {
            public function __construct(int $tenantId, int $userId, PDO $pdo)
            {
                parent::__construct($tenantId, $userId, $pdo);
            }

            public function ensureReady(): void {}

            public function createEvent(array $agendamento)
            {
                return new class('google-event-from-db-fail-test') {
                    private string $id;

                    public function __construct(string $id)
                    {
                        $this->id = $id;
                    }

                    public function getId(): string
                    {
                        return $this->id;
                    }
                };
            }
        };

        $result = $service->syncAllAgendamentos(9, 7);

        $this->assertSame(0, $result['synced']);
        $this->assertSame(1, $result['total']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('Agendamento #1', $result['errors'][0]);
        $this->assertStringContainsString('local persistence failed', strtolower($result['errors'][0]));
        $this->assertSame(0, $this->pdo->query('SELECT COUNT(*) FROM agendamentos_google_sync')->fetchColumn());
    }
}
