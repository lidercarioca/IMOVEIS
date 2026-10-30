<?php

namespace App\Repository;

use PDO;

class GoogleCalendarSyncRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function userExistsInTenant(int $userId, int $tenantId): bool
    {
        if ($userId <= 0 || $tenantId <= 0) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id FROM users WHERE id = :user_id AND tenant_id = :tenant_id LIMIT 1'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':tenant_id' => $tenantId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    public function findOwnedAppointmentForUser(int $tenantId, int $userId, int $appointmentId): ?array
    {
        if ($tenantId <= 0 || $userId <= 0 || $appointmentId <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT a.* FROM agendamentos a WHERE a.id = :appointment_id AND a.tenant_id = :tenant_id AND a.user_id = :user_id LIMIT 1'
        );
        $stmt->execute([
            ':appointment_id' => $appointmentId,
            ':tenant_id' => $tenantId,
            ':user_id' => $userId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function findPropertyAddressForTenant(int $tenantId, int $propertyId): ?string
    {
        if ($tenantId <= 0 || $propertyId <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT endereco FROM properties WHERE id = :property_id AND tenant_id = :tenant_id LIMIT 1'
        );
        $stmt->execute([
            ':property_id' => $propertyId,
            ':tenant_id' => $tenantId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['endereco'] ?? null;
    }

    public function findSyncRecordForAppointment(int $tenantId, int $appointmentId): ?array
    {
        if ($tenantId <= 0 || $appointmentId <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT google_event_id FROM agendamentos_google_sync WHERE agendamento_id = :appointment_id AND tenant_id = :tenant_id LIMIT 1'
        );
        $stmt->execute([
            ':appointment_id' => $appointmentId,
            ':tenant_id' => $tenantId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function upsertSyncRecord(int $tenantId, int $appointmentId, string $googleEventId): bool
    {
        if ($tenantId <= 0 || $appointmentId <= 0 || trim($googleEventId) === '') {
            return false;
        }

        $existing = $this->findSyncRecordForAppointment($tenantId, $appointmentId);
        if ($existing) {
            $stmt = $this->pdo->prepare(
                'UPDATE agendamentos_google_sync SET google_event_id = :google_event_id, updated_at = NOW() WHERE agendamento_id = :appointment_id AND tenant_id = :tenant_id'
            );
            return $stmt->execute([
                ':google_event_id' => $googleEventId,
                ':appointment_id' => $appointmentId,
                ':tenant_id' => $tenantId,
            ]);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO agendamentos_google_sync (tenant_id, agendamento_id, google_event_id) VALUES (:tenant_id, :appointment_id, :google_event_id)'
        );
        return $stmt->execute([
            ':tenant_id' => $tenantId,
            ':appointment_id' => $appointmentId,
            ':google_event_id' => $googleEventId,
        ]);
    }

    public function deleteSyncRecord(int $tenantId, int $appointmentId): bool
    {
        if ($tenantId <= 0 || $appointmentId <= 0) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'DELETE FROM agendamentos_google_sync WHERE agendamento_id = :appointment_id AND tenant_id = :tenant_id'
        );
        return $stmt->execute([
            ':appointment_id' => $appointmentId,
            ':tenant_id' => $tenantId,
        ]);
    }

    public function countSyncedEventsForTenant(int $tenantId): int
    {
        if ($tenantId <= 0) {
            return 0;
        }

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) as count FROM agendamentos_google_sync WHERE tenant_id = :tenant_id'
        );
        $stmt->execute([':tenant_id' => $tenantId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return isset($row['count']) ? (int) $row['count'] : 0;
    }

    public function listAllowedGoogleEventIdsForUser(int $tenantId, int $userId): array
    {
        if ($tenantId <= 0 || $userId <= 0) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT s.google_event_id FROM agendamentos_google_sync s INNER JOIN agendamentos a ON a.id = s.agendamento_id WHERE s.tenant_id = :tenant_id AND a.tenant_id = :tenant_id AND a.user_id = :user_id'
        );
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':user_id' => $userId,
        ]);

        return array_values(array_filter(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN))));
    }
}
