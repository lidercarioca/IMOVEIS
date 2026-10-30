<?php

namespace App\Application\User;

use App\Infrastructure\User\IdempotencyRequestRepository;
use InvalidArgumentException;
use RuntimeException;

class IdempotencyRequestService
{
    private IdempotencyRequestRepository $repository;

    public function __construct(IdempotencyRequestRepository $repository)
    {
        $this->repository = $repository;
    }

    public function executeWithKey(
        int $tenantId,
        int $actorUserId,
        string $httpMethod,
        string $resource,
        string $operation,
        string $idempotencyKey,
        array $payload,
        callable $mutationCallback,
        int $defaultStatus = 200
    ): array {
        $normalizedKey = $this->normalizeKey($idempotencyKey);
        $payloadHash = $this->hashPayload($payload);

        $this->repository->beginTransaction();

        try {
            $record = $this->repository->findByScope(
                $tenantId,
                $actorUserId,
                $httpMethod,
                $resource,
                $operation,
                $normalizedKey
            );

            if ($record !== null) {
                $this->assertCompatibleRecord($record, $payloadHash);

                $this->repository->commit();
                return $this->buildReplayResult($record, $defaultStatus);
            }

            $reserved = $this->repository->reserveRequest(
                $tenantId,
                $actorUserId,
                $httpMethod,
                $resource,
                $operation,
                $normalizedKey,
                $payloadHash,
                'processing'
            );

            if ($reserved === null) {
                throw new RuntimeException('Não foi possível reservar a chave idempotente');
            }

            $record = $reserved['record'] ?? $reserved;
            $wasCreated = (bool)($reserved['created'] ?? true);

            if (!$wasCreated) {
                $this->assertCompatibleRecord($record, $payloadHash);
                $this->repository->commit();
                return $this->buildReplayResult($record, $defaultStatus);
            }

            $result = $mutationCallback();
            $status = (int)($result['status'] ?? $defaultStatus);
            $body = is_array($result['body'] ?? null) ? $result['body'] : [];

            $persisted = $this->repository->markSucceeded((int) $record['id'], $status, $body);
            if (!$persisted) {
                throw new RuntimeException('Falha ao persistir o resultado idempotente');
            }

            $this->repository->commit();

            return [
                'status' => $status,
                'body' => $body,
            ];
        } catch (RuntimeException $exception) {
            $this->repository->rollback();
            throw $exception;
        } catch (\Throwable $exception) {
            $this->repository->rollback();
            throw new RuntimeException('Erro ao processar operação idempotente: ' . $exception->getMessage(), 0, $exception);
        }
    }

    private function normalizeKey(string $idempotencyKey): string
    {
        $key = trim((string) $idempotencyKey);
        if ($key === '') {
            throw new InvalidArgumentException('Idempotency-Key inválida');
        }

        return $key;
    }

    private function hashPayload(array $payload): string
    {
        $normalized = $this->normalizePayload($payload);
        $encoded = json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return hash('sha256', $encoded);
    }

    private function normalizePayload(array $payload): array
    {
        ksort($payload);

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->normalizePayload($value);
            }
        }

        return $payload;
    }

    private function assertCompatibleRecord(array $record, string $payloadHash): void
    {
        $recordStatus = strtolower((string) ($record['status'] ?? ''));

        if ($record['payload_hash'] !== $payloadHash) {
            throw new RuntimeException('Idempotency-Key já foi utilizada com outro payload');
        }

        if ($recordStatus === 'processing') {
            throw new RuntimeException('Operação em processamento');
        }

        if ($recordStatus === 'failed') {
            throw new RuntimeException('Operação falhou anteriormente e não pode ser reexecutada com a mesma chave');
        }
    }

    private function buildReplayResult(array $record, int $defaultStatus): array
    {
        $status = (int) ($record['http_status'] ?? $defaultStatus);
        $body = $record['response_body'] ?? null;

        if ($body === null || $body === '') {
            return [
                'status' => $status,
                'body' => ['success' => true],
            ];
        }

        $decoded = json_decode((string) $body, true);

        return [
            'status' => $status,
            'body' => is_array($decoded) ? $decoded : ['success' => true],
        ];
    }
}
