<?php

namespace App\Repository;

use PDO;

class BannerRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function listActiveByTenant(int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM banners WHERE active = ? AND tenant_id = ? ORDER BY order_position, id DESC'
        );
        $stmt->execute([1, $tenantId]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static function (array $row): array {
            $row['image_url'] = $row['image_path'] ?? '';
            return $row;
        }, $rows);
    }

    public function createBanner(int $tenantId, array $data): array
    {
        $title = isset($data['title']) ? trim((string) $data['title']) : '';
        $description = isset($data['description']) ? trim((string) $data['description']) : '';
        $orderPosition = isset($data['order_position']) ? max(0, (int) $data['order_position']) : 0;

        $stmt = $this->pdo->prepare(
            'INSERT INTO banners (tenant_id, image_path, title, description, order_position, active) VALUES (?, ?, ?, ?, ?, 1)'
        );
        $stmt->execute([
            $tenantId,
            $data['image_path'] ?? '',
            $title,
            $description,
            $orderPosition,
        ]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'tenant_id' => $tenantId,
            'image_path' => $data['image_path'] ?? '',
            'title' => $title,
            'description' => $description,
            'order_position' => $orderPosition,
            'active' => 1,
        ];
    }

    public function updateBanner(int $tenantId, int $bannerId, array $fields): bool
    {
        if ($bannerId <= 0 || $fields === []) {
            return false;
        }

        $setClauses = [];
        $params = [];

        foreach ($fields as $field => $value) {
            $setClauses[] = $field . ' = ?';
            $params[] = $value;
        }

        $params[] = $bannerId;
        $params[] = $tenantId;

        $sql = 'UPDATE banners SET ' . implode(', ', $setClauses) . ' WHERE id = ? AND tenant_id = ?';
        $stmt = $this->pdo->prepare($sql);
        $executed = $stmt->execute($params);

        return $executed && $stmt->rowCount() > 0;
    }

    public function findImagePathByIdAndTenant(int $bannerId, int $tenantId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT image_path FROM banners WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$bannerId, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false || !isset($row['image_path'])) {
            return null;
        }

        return (string) $row['image_path'];
    }

    public function deleteBanner(int $tenantId, int $bannerId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM banners WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$bannerId, $tenantId]);

        return $stmt->rowCount() > 0;
    }
}
