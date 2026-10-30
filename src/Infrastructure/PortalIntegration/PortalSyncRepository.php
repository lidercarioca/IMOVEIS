<?php

namespace App\Infrastructure\PortalIntegration;

use PDO;

class PortalSyncRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Find sync row by property and portal
     * @param int $propertyId
     * @param string $portal
     * @return array|null
     */
    public function findByPropertyAndPortal(int $propertyId, string $portal): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, property_id, portal, last_exported_at, status FROM property_portal_sync WHERE property_id = :property_id AND portal = :portal LIMIT 1');
        $stmt->execute([':property_id' => $propertyId, ':portal' => $portal]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row : null;
    }

    /**
     * Create a new sync row
     */
    public function createSync(int $propertyId, string $portal, string $lastExportedAt, string $status = 'exported'): bool
    {
        $stmt = $this->pdo->prepare('INSERT INTO property_portal_sync (property_id, portal, last_exported_at, status) VALUES (:property_id, :portal, :last_exported_at, :status)');
        return $stmt->execute([':property_id' => $propertyId, ':portal' => $portal, ':last_exported_at' => $lastExportedAt, ':status' => $status]);
    }

    /**
     * Update existing sync row
     */
    public function updateSync(int $id, string $lastExportedAt, string $status = 'exported'): bool
    {
        $stmt = $this->pdo->prepare('UPDATE property_portal_sync SET last_exported_at = :last_exported_at, status = :status WHERE id = :id');
        return $stmt->execute([':last_exported_at' => $lastExportedAt, ':status' => $status, ':id' => $id]);
    }

    /**
     * Upsert many property ids within a transaction
     */
    public function upsertMany(array $propertyIds, string $portal, string $lastExportedAt, string $status = 'exported'): bool
    {
        if (empty($propertyIds)) {
            return true;
        }

        $this->pdo->beginTransaction();

        $selectStmt = $this->pdo->prepare('SELECT id FROM property_portal_sync WHERE property_id = :property_id AND portal = :portal LIMIT 1');
        $updateStmt = $this->pdo->prepare('UPDATE property_portal_sync SET last_exported_at = :last_exported_at, status = :status WHERE id = :id');
        $insertStmt = $this->pdo->prepare('INSERT INTO property_portal_sync (property_id, portal, last_exported_at, status) VALUES (:property_id, :portal, :last_exported_at, :status)');

        try {
            foreach ($propertyIds as $pid) {
                if (!$selectStmt->execute([':property_id' => $pid, ':portal' => $portal])) {
                    throw new \Exception('Failed to select sync row for property ' . $pid);
                }

                $row = $selectStmt->fetch(PDO::FETCH_ASSOC);
                if ($row && !empty($row['id'])) {
                    if (!$updateStmt->execute([':last_exported_at' => $lastExportedAt, ':status' => $status, ':id' => $row['id']])) {
                        throw new \Exception('Failed to update sync row id ' . $row['id']);
                    }
                } else {
                    if (!$insertStmt->execute([':property_id' => $pid, ':portal' => $portal, ':last_exported_at' => $lastExportedAt, ':status' => $status])) {
                        throw new \Exception('Failed to insert sync row for property ' . $pid);
                    }
                }
            }

            $this->pdo->commit();
            return true;
        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
