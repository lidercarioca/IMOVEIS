<?php

use PHPUnit\Framework\TestCase;

class IdempotencyRequestServiceTest extends TestCase
{
    private PDO $pdo;
    private \App\Infrastructure\User\IdempotencyRequestRepository $repository;
    private \App\Application\User\IdempotencyRequestService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE idempotency_requests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tenant_id INTEGER NOT NULL,
                actor_user_id INTEGER NOT NULL,
                http_method VARCHAR(16) NOT NULL,
                resource VARCHAR(64) NOT NULL,
                operation VARCHAR(32) NOT NULL,
                idempotency_key VARCHAR(255) NOT NULL,
                payload_hash CHAR(64) NOT NULL,
                status VARCHAR(16) NOT NULL DEFAULT "processing",
                http_status SMALLINT UNSIGNED,
                response_body TEXT,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL,
                updated_at DATETIME NULL,
                UNIQUE(tenant_id, actor_user_id, http_method, resource, operation, idempotency_key)
            )'
        );

        $this->repository = new \App\Infrastructure\User\IdempotencyRequestRepository($this->pdo);
        $this->service = new \App\Application\User\IdempotencyRequestService($this->repository);
    }

    public function testSameKeyAndPayloadReplaysPersistedResponse(): void
    {
        $payload = ['username' => 'novo_user', 'name' => 'Nova Pessoa', 'email' => 'novo@example.com'];

        $first = $this->service->executeWithKey(
            7,
            9,
            'POST',
            'users',
            'create',
            'key-replay-1',
            $payload,
            function () {
                return ['status' => 201, 'body' => ['success' => true, 'data' => ['id' => 44]]];
            }
        );

        $this->assertSame(201, $first['status']);
        $this->assertSame(['success' => true, 'data' => ['id' => 44]], $first['body']);

        $second = $this->service->executeWithKey(
            7,
            9,
            'POST',
            'users',
            'create',
            'key-replay-1',
            $payload,
            function () {
                $this->fail('O callback de mutação não deve ser executado em replay');
            }
        );

        $this->assertSame(201, $second['status']);
        $this->assertSame(['success' => true, 'data' => ['id' => 44]], $second['body']);
    }

    public function testDifferentPayloadForSameKeyIsRejected(): void
    {
        $firstPayload = ['username' => 'alpha', 'name' => 'Alpha', 'email' => 'alpha@example.com'];
        $secondPayload = ['username' => 'beta', 'name' => 'Beta', 'email' => 'beta@example.com'];

        $this->service->executeWithKey(
            7,
            9,
            'POST',
            'users',
            'create',
            'key-conflict-1',
            $firstPayload,
            function () {
                return ['status' => 201, 'body' => ['success' => true, 'data' => ['id' => 77]]];
            }
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Idempotency-Key já foi utilizada com outro payload');

        $this->service->executeWithKey(
            7,
            9,
            'POST',
            'users',
            'create',
            'key-conflict-1',
            $secondPayload,
            function () {
                $this->fail('Não deve executar mutação em conflito');
            }
        );
    }

    public function testProcessingKeyDoesNotExecuteMutationAgain(): void
    {
        $payload = ['username' => 'pending', 'name' => 'Pending', 'email' => 'pending@example.com'];
        $sortedPayload = $payload;
        ksort($sortedPayload);

        $this->repository->reserveRequest(7, 9, 'POST', 'users', 'create', 'key-pending-1', hash('sha256', json_encode($sortedPayload, JSON_UNESCAPED_UNICODE)), 'processing');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Operação em processamento');

        $this->service->executeWithKey(
            7,
            9,
            'POST',
            'users',
            'create',
            'key-pending-1',
            $payload,
            function () {
                $this->fail('Não deve executar mutação enquanto a operação ainda está em processamento');
            }
        );
    }
}
