<?php

namespace App\Infrastructure\User;

use PDO;
use PDOException;
use RuntimeException;

class IdempotencyRequestRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function beginTransaction(): void
    {
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
        }
    }

    public function commit(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->commit();
        }
    }

    public function rollback(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function reserveRequest(
        int $tenantId,
        int $actorUserId,
        string $httpMethod,
        string $resource,
        string $operation,
        string $idempotencyKey,
        string $payloadHash,
        string $status = 'processing'
    ): ?array {
        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));
        $resourceNormalized = strtolower($resource);
        $operationNormalized = strtolower($operation);
        $httpMethodNormalized = strtoupper($httpMethod);
        $keyNormalized = trim($idempotencyKey);

        $sql = 'INSERT INTO idempotency_requests (
                    tenant_id,
                    actor_user_id,
                    http_method,
                    resource,
                    operation,
                    idempotency_key,
                    payload_hash,
                    status,
                    created_at,
                    expires_at
                ) VALUES (
                    :tenant_id,
                    :actor_user_id,
                    :http_method,
                    :resource,
                    :operation,
                    :idempotency_key,
                    :payload_hash,
                    :status,
                    :created_at,
                    :expires_at
                )';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':tenant_id' => $tenantId,
                ':actor_user_id' => $actorUserId,
                ':http_method' => $httpMethodNormalized,
                ':resource' => $resourceNormalized,
                ':operation' => $operationNormalized,
                ':idempotency_key' => $keyNormalized,
                ':payload_hash' => trim($payloadHash),
                ':status' => $status,
                ':created_at' => $now,
                ':expires_at' => $expiresAt,
            ]);

            $record = $this->findByScope($tenantId, $actorUserId, $httpMethodNormalized, $resourceNormalized, $operationNormalized, $keyNormalized);
            if ($record === null) {
                return null;
            }

            return ['created' => true, 'record' => $record];
        } catch (PDOException $exception) {
            if ($this->isDuplicateKeyException($exception)) {
                $record = $this->findByScope($tenantId, $actorUserId, $httpMethodNormalized, $resourceNormalized, $operationNormalized, $keyNormalized);
                if ($record !== null) {
                    return ['created' => false, 'record' => $record];
                }
            }

            throw $exception;
        }
    }

    public function findByScope(
        int $tenantId,
        int $actorUserId,
        string $httpMethod,
        string $resource,
        string $operation,
        string $idempotencyKey
    ): ?array {
        $sql = 'SELECT id, tenant_id, actor_user_id, http_method, resource, operation, idempotency_key, payload_hash, status, http_status, response_body, created_at, expires_at, updated_at
                FROM idempotency_requests
                WHERE tenant_id = :tenant_id
                  AND actor_user_id = :actor_user_id
                  AND http_method = :http_method
                  AND resource = :resource
                  AND operation = :operation
                  AND idempotency_key = :idempotency_key
                ORDER BY id DESC
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':actor_user_id' => $actorUserId,
            ':http_method' => strtoupper($httpMethod),
            ':resource' => strtolower($resource),
            ':operation' => strtolower($operation),
            ':idempotency_key' => trim($idempotencyKey),
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function markSucceeded(int $requestId, int $httpStatus, array $responseBody): bool
    {
        $payload = json_encode($responseBody, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $sql = 'UPDATE idempotency_requests
                SET status = :status,
                    http_status = :http_status,
                    response_body = :response_body,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':status' => 'succeeded',
            ':http_status' => $httpStatus,
            ':response_body' => $payload,
            ':id' => $requestId,
        ]);
    }

    public function markFailed(int $requestId, int $httpStatus, array $payload): bool
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $sql = 'UPDATE idempotency_requests
                SET status = :status,
                    http_status = :http_status,
                    response_body = :response_body,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':status' => 'failed',
            ':http_status' => $httpStatus,
            ':response_body' => $body,
            ':id' => $requestId,
        ]);
    }

    private function isDuplicateKeyException(PDOException $exception): bool
    {
        $message = $exception->getMessage();
        $lower = strtolower($message);

        if (strpos($lower, 'idempotency_requests') === false) {
            return false;
        }

        return strpos($lower, 'duplicate') !== false
            || strpos($lower, 'unique constraint') !== false
            || strpos($lower, 'already exists') !== false;
    }
}
