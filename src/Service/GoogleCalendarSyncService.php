<?php

namespace App\Service;

use App\Repository\GoogleCalendarSyncRepository;
use InvalidArgumentException;
use PDO;

class GoogleCalendarSyncService
{
    private PDO $pdo;
    private GoogleCalendarSyncRepository $repository;

    public function __construct(PDO $pdo, ?GoogleCalendarSyncRepository $repository = null)
    {
        $this->pdo = $pdo;
        $this->repository = $repository ?? new GoogleCalendarSyncRepository($pdo);
    }

    public function validateUserBelongsToTenant(int $userId, int $tenantId): bool
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('Usuário inválido para sincronização do Google Calendar');
        }

        if ($tenantId <= 0) {
            throw new InvalidArgumentException('Tenant inválido para sincronização do Google Calendar');
        }

        return $this->repository->userExistsInTenant($userId, $tenantId);
    }

    public function resolveOwnedAppointmentForUser(int $tenantId, int $userId, int $appointmentId): ?array
    {
        $this->assertTenantAndUser($tenantId, $userId);

        if ($appointmentId <= 0) {
            return null;
        }

        return $this->repository->findOwnedAppointmentForUser($tenantId, $userId, $appointmentId);
    }

    public function getPropertyAddressForTenant(int $tenantId, int $propertyId): ?string
    {
        if ($tenantId <= 0 || $propertyId <= 0) {
            return null;
        }

        return $this->repository->findPropertyAddressForTenant($tenantId, $propertyId);
    }

    public function findSyncRecordForAppointment(int $tenantId, int $appointmentId): ?array
    {
        if ($tenantId <= 0 || $appointmentId <= 0) {
            return null;
        }

        return $this->repository->findSyncRecordForAppointment($tenantId, $appointmentId);
    }

    public function saveSyncRecord(int $tenantId, int $appointmentId, string $googleEventId): bool
    {
        if ($tenantId <= 0 || $appointmentId <= 0 || trim($googleEventId) === '') {
            return false;
        }

        return $this->repository->upsertSyncRecord($tenantId, $appointmentId, $googleEventId);
    }

    public function deleteSyncRecord(int $tenantId, int $appointmentId): bool
    {
        if ($tenantId <= 0 || $appointmentId <= 0) {
            return false;
        }

        return $this->repository->deleteSyncRecord($tenantId, $appointmentId);
    }

    public function countSyncedEventsForTenant(int $tenantId): int
    {
        if ($tenantId <= 0) {
            return 0;
        }

        return $this->repository->countSyncedEventsForTenant($tenantId);
    }

    public function getAllowedGoogleEventIdsForUser(int $tenantId, int $userId): array
    {
        $this->assertTenantAndUser($tenantId, $userId);

        return $this->repository->listAllowedGoogleEventIdsForUser($tenantId, $userId);
    }

    private function assertTenantAndUser(int $tenantId, int $userId): void
    {
        if ($tenantId <= 0) {
            throw new InvalidArgumentException('Tenant inválido para sincronização do Google Calendar');
        }

        if ($userId <= 0) {
            throw new InvalidArgumentException('Usuário inválido para sincronização do Google Calendar');
        }
    }
}
