<?php

namespace Tests\Integration;

use App\Application\User\IdempotencyRequestService;
use App\Infrastructure\User\IdempotencyRequestRepository;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/config/test_database.php';

class IdempotencyRequestMysqlIntegrationTest extends TestCase
{
    private string $dbName;
    private string $dbHost;
    private string $dbPort;
    private string $dbUser;
    private string $dbPass;

    private PDO $pdo;
    private IdempotencyRequestRepository $repository;
    private IdempotencyRequestService $service;

    protected function setUp(): void
    {
        assertTestDatabaseIsIsolated();
        $config = getTestDatabaseConfig();

        $this->dbName = $config['name'];
        $this->dbHost = $config['host'];
        $this->dbPort = $config['port'];
        $this->dbUser = $config['user'];
        $this->dbPass = $config['pass'];

        $root = new PDO(
            sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $this->dbHost, $this->dbPort),
            $this->dbUser,
            $this->dbPass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );

        $root->exec('DROP DATABASE IF EXISTS `' . $this->dbName . '`');
        $root->exec('CREATE DATABASE `' . $this->dbName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

        $this->pdo = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $this->dbHost, $this->dbPort, $this->dbName),
            $this->dbUser,
            $this->dbPass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        $this->pdo->exec('CREATE TABLE test_users (
            id INT NOT NULL AUTO_INCREMENT,
            tenant_id INT NOT NULL,
            actor_user_id INT NOT NULL,
            username VARCHAR(100) NOT NULL,
            email VARCHAR(180) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_test_users_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        $this->pdo->exec(file_get_contents(dirname(__DIR__, 2) . '/migrations/2026_10_23_create_idempotency_requests_table.sql'));

        $this->repository = new IdempotencyRequestRepository($this->pdo);
        $this->service = new IdempotencyRequestService($this->repository);
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            $this->pdo->exec('DROP TABLE IF EXISTS test_users');
            $this->pdo->exec('DROP TABLE IF EXISTS idempotency_requests');
            $this->pdo->exec('DROP DATABASE IF EXISTS `' . $this->dbName . '`');
        }
    }

    public function testSameRequestReplayReturnsOriginalResponseWithoutMutatingAgain(): void
    {
        $payload = ['username' => 'replay_user', 'email' => 'replay@example.com', 'tenant_id' => 7];
        $key = 'mysql-replay-1';

        $first = $this->service->executeWithKey(
            7,
            9,
            'POST',
            'users',
            'create',
            $key,
            $payload,
            function () use ($payload) {
                $this->pdo->exec(
                    'INSERT INTO test_users (tenant_id, actor_user_id, username, email) VALUES (7, 9, ' . $this->pdo->quote($payload['username']) . ', ' . $this->pdo->quote($payload['email']) . ')'
                );

                return [
                    'status' => 201,
                    'body' => ['success' => true, 'data' => ['id' => 1, 'username' => $payload['username']]],
                ];
            },
            201
        );

        $this->assertSame(201, $first['status']);
        $this->assertSame(['success' => true, 'data' => ['id' => 1, 'username' => 'replay_user']], $first['body']);
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM test_users')->fetchColumn());

        $second = $this->service->executeWithKey(
            7,
            9,
            'POST',
            'users',
            'create',
            $key,
            $payload,
            function () {
                $this->fail('A mutação não deve ser executada em replay.');
            },
            201
        );

        $this->assertSame(201, $second['status']);
        $this->assertSame(['success' => true, 'data' => ['id' => 1, 'username' => 'replay_user']], $second['body']);
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM test_users')->fetchColumn());
    }

    public function testSameKeyWithDifferentPayloadIsRejectedWithoutSecondMutation(): void
    {
        $firstPayload = ['username' => 'alpha', 'email' => 'alpha@example.com', 'tenant_id' => 7];
        $secondPayload = ['username' => 'beta', 'email' => 'beta@example.com', 'tenant_id' => 7];

        $this->service->executeWithKey(
            7,
            9,
            'POST',
            'users',
            'create',
            'mysql-conflict-1',
            $firstPayload,
            function () use ($firstPayload) {
                $this->pdo->exec(
                    'INSERT INTO test_users (tenant_id, actor_user_id, username, email) VALUES (7, 9, ' . $this->pdo->quote($firstPayload['username']) . ', ' . $this->pdo->quote($firstPayload['email']) . ')'
                );

                return ['status' => 201, 'body' => ['success' => true, 'data' => ['id' => 11]]];
            },
            201
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Idempotency-Key já foi utilizada com outro payload');

        $this->service->executeWithKey(
            7,
            9,
            'POST',
            'users',
            'create',
            'mysql-conflict-1',
            $secondPayload,
            function () {
                $this->fail('A segunda mutação não deve acontecer em conflito de payload.');
            },
            201
        );

        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM test_users')->fetchColumn());
    }

    public function testConcurrentSameKeySamePayloadOnlyCreatesSingleMutation(): void
    {
        $scriptPath = tempnam(sys_get_temp_dir(), 'idempotency_mysql_');
        $autoloadPath = dirname(__DIR__, 2) . '/vendor/autoload.php';
        $script = "<?php\nrequire_once " . var_export($autoloadPath, true) . ";\n\nuse App\\Application\\User\\IdempotencyRequestService;\nuse App\\Infrastructure\\User\\IdempotencyRequestRepository;\n\n\$pdo = new PDO('mysql:host=" . addslashes($this->dbHost) . ";port=" . addslashes((string) $this->dbPort) . ";dbname=" . addslashes($this->dbName) . ";charset=utf8mb4', " . var_export($this->dbUser, true) . ", " . var_export($this->dbPass, true) . ", [\n    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,\n    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n    PDO::ATTR_EMULATE_PREPARES => false,\n]);\n\n\$repo = new IdempotencyRequestRepository(\$pdo);\n\$service = new IdempotencyRequestService(\$repo);\n\$payload = ['username' => 'concurrent_user', 'email' => 'concurrent@example.com', 'tenant_id' => 7];\n\ntry {\n    \$result = \$service->executeWithKey(\n        7,\n        9,\n        'POST',\n        'users',\n        'create',\n        'mysql-concurrent-1',\n        \$payload,\n        function () use (\$pdo, \$payload) {\n            \$pdo->exec(\n                'INSERT INTO test_users (tenant_id, actor_user_id, username, email) VALUES (7, 9, ' . \$pdo->quote(\$payload['username']) . ', ' . \$pdo->quote(\$payload['email']) . ') '\n            );\n\n            return ['status' => 201, 'body' => ['success' => true, 'data' => ['id' => 99]]];\n        },\n        201\n    );\n\n    echo json_encode(['status' => \$result['status'], 'body' => \$result['body']]);\n} catch (Throwable \$e) {\n    echo json_encode(['error' => \$e->getMessage()]);\n}\n";
        file_put_contents($scriptPath, $script);

        $phpBinary = escapeshellarg(PHP_BINARY);
        $command1 = $phpBinary . ' ' . escapeshellarg($scriptPath);
        $command2 = $phpBinary . ' ' . escapeshellarg($scriptPath);

        $process1 = proc_open($command1, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes1);
        $process2 = proc_open($command2, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes2);

        $this->assertNotFalse($process1);
        $this->assertNotFalse($process2);

        $stdout1 = stream_get_contents($pipes1[1]);
        $stdout2 = stream_get_contents($pipes2[1]);
        $stderr1 = stream_get_contents($pipes1[2]);
        $stderr2 = stream_get_contents($pipes2[2]);

        fclose($pipes1[1]);
        fclose($pipes1[2]);
        fclose($pipes2[1]);
        fclose($pipes2[2]);
        proc_close($process1);
        proc_close($process2);
        unlink($scriptPath);

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM test_users')->fetchColumn();
        $this->assertSame(1, $count, 'A concorrência real deve manter apenas uma mutação aceita.');

        $payloads = [$stdout1, $stdout2];
        foreach ($payloads as $payloadOut) {
            $decoded = json_decode((string) $payloadOut, true);
            $this->assertIsArray($decoded);
            $this->assertTrue(
                isset($decoded['status']) || isset($decoded['error']),
                'Cada processo concorrente deve terminar com um resultado coerente.'
            );
        }
    }

    public function testRollbackReversesReservationAndMutationAllowingSafeRetry(): void
    {
        $payload = ['username' => 'rollback_user', 'email' => 'rollback@example.com', 'tenant_id' => 7];

        $this->repository->beginTransaction();
        $reserved = $this->repository->reserveRequest(
            7,
            9,
            'POST',
            'users',
            'create',
            'mysql-rollback-1',
            hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)),
            'processing'
        );

        $this->assertNotNull($reserved);
        $this->pdo->exec(
            'INSERT INTO test_users (tenant_id, actor_user_id, username, email) VALUES (7, 9, ' . $this->pdo->quote($payload['username']) . ', ' . $this->pdo->quote($payload['email']) . ')'
        );
        $this->repository->rollback();

        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM test_users')->fetchColumn());
        $this->assertNull($this->repository->findByScope(7, 9, 'POST', 'users', 'create', 'mysql-rollback-1'));

        $result = $this->service->executeWithKey(
            7,
            9,
            'POST',
            'users',
            'create',
            'mysql-rollback-1',
            $payload,
            function () use ($payload) {
                $this->pdo->exec(
                    'INSERT INTO test_users (tenant_id, actor_user_id, username, email) VALUES (7, 9, ' . $this->pdo->quote($payload['username']) . ', ' . $this->pdo->quote($payload['email']) . ')'
                );

                return ['status' => 201, 'body' => ['success' => true, 'data' => ['id' => 55]]];
            },
            201
        );

        $this->assertSame(201, $result['status']);
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM test_users')->fetchColumn());
    }

    public function testDifferentActorOrTenantDoesNotReplayStoredResponse(): void
    {
        $payload = ['username' => 'tenant_user', 'email' => 'tenant@example.com', 'tenant_id' => 7];

        $first = $this->service->executeWithKey(
            7,
            9,
            'POST',
            'users',
            'create',
            'mysql-tenant-scope-1',
            $payload,
            function () use ($payload) {
                $this->pdo->exec(
                    'INSERT INTO test_users (tenant_id, actor_user_id, username, email) VALUES (7, 9, ' . $this->pdo->quote($payload['username']) . ', ' . $this->pdo->quote($payload['email']) . ')'
                );

                return ['status' => 201, 'body' => ['success' => true, 'data' => ['id' => 77]]];
            },
            201
        );

        $this->assertSame(201, $first['status']);
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM test_users')->fetchColumn());

        $secondPayload = ['username' => 'tenant_user', 'email' => 'tenant-different@example.com', 'tenant_id' => 8];

        $second = $this->service->executeWithKey(
            8,
            9,
            'POST',
            'users',
            'create',
            'mysql-tenant-scope-1',
            $secondPayload,
            function () use ($secondPayload) {
                $this->pdo->exec(
                    'INSERT INTO test_users (tenant_id, actor_user_id, username, email) VALUES (8, 9, ' . $this->pdo->quote($secondPayload['username']) . ', ' . $this->pdo->quote($secondPayload['email']) . ')'
                );

                return ['status' => 201, 'body' => ['success' => true, 'data' => ['id' => 88]]];
            },
            201
        );

        $this->assertSame(201, $second['status']);
        $this->assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM test_users')->fetchColumn());
        $this->assertSame(['success' => true, 'data' => ['id' => 88]], $second['body']);
    }
}
