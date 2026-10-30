<?php

namespace App\Service;

use App\Repository\BannerRepository;
use InvalidArgumentException;
use PDO;

class BannerService
{
    public const MAX_UPLOAD_SIZE_BYTES = 10 * 1024 * 1024;

    private PDO $pdo;
    private BannerRepository $repository;

    public function __construct(PDO $pdo, ?BannerRepository $repository = null)
    {
        $this->pdo = $pdo;
        $this->repository = $repository ?? new BannerRepository($pdo);
    }

    public static function validateUploadSize(array $file, int $maxBytes = self::MAX_UPLOAD_SIZE_BYTES): void
    {
        $reportedSize = $file['size'] ?? null;

        if (!is_numeric($reportedSize) || (float) $reportedSize < 0) {
            throw new InvalidArgumentException('Tamanho do arquivo inválido');
        }

        $reportedSize = (int) $reportedSize;
        if ($reportedSize <= 0) {
            throw new InvalidArgumentException('Tamanho do arquivo inválido');
        }

        if ($reportedSize > $maxBytes) {
            throw new InvalidArgumentException('O arquivo excede o tamanho máximo permitido');
        }

        $tmpName = $file['tmp_name'] ?? null;
        if (is_string($tmpName) && $tmpName !== '' && is_file($tmpName)) {
            $effectiveSize = @filesize($tmpName);
            if ($effectiveSize === false || $effectiveSize <= 0) {
                throw new InvalidArgumentException('Tamanho do arquivo inválido');
            }

            if ($effectiveSize > $maxBytes) {
                throw new InvalidArgumentException('O arquivo excede o tamanho máximo permitido');
            }
        }
    }

    public function listBanners(int $tenantId): array
    {
        if ($tenantId <= 0) {
            throw new InvalidArgumentException('Tenant inválido');
        }

        return $this->repository->listActiveByTenant($tenantId);
    }

    public function createBanner(int $tenantId, array $data): array
    {
        if ($tenantId <= 0) {
            throw new InvalidArgumentException('Tenant inválido');
        }

        $imagePath = trim((string) ($data['image_path'] ?? ''));
        if ($imagePath === '') {
            throw new InvalidArgumentException('Imagem obrigatória');
        }

        $payload = [
            'image_path' => $imagePath,
            'title' => isset($data['title']) ? trim((string) $data['title']) : '',
            'description' => isset($data['description']) ? trim((string) $data['description']) : '',
            'order_position' => isset($data['order_position']) ? max(0, (int) $data['order_position']) : 0,
        ];

        return $this->repository->createBanner($tenantId, $payload);
    }

    public function updateBanner(int $tenantId, int $bannerId, array $fields): bool
    {
        if ($tenantId <= 0) {
            throw new InvalidArgumentException('Tenant inválido');
        }

        if ($bannerId <= 0) {
            throw new InvalidArgumentException('ID do banner obrigatório');
        }

        $allowedFields = ['title', 'description', 'order_position'];
        $data = [];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $fields)) {
                $value = $fields[$field];
                if (is_string($value)) {
                    $value = trim($value);
                }

                if ($field === 'order_position') {
                    $value = (int) $value;
                }

                $data[$field] = $value;
            }
        }

        if ($data === []) {
            throw new InvalidArgumentException('Nenhum dado para atualizar');
        }

        $updated = $this->repository->updateBanner($tenantId, $bannerId, $data);
        if (!$updated) {
            throw new InvalidArgumentException('Banner não encontrado ou nenhuma alteração realizada');
        }

        return true;
    }

    public function deleteBanner(int $tenantId, int $bannerId): bool
    {
        if ($tenantId <= 0) {
            throw new InvalidArgumentException('Tenant inválido');
        }

        if ($bannerId <= 0) {
            throw new InvalidArgumentException('ID do banner obrigatório');
        }

        $imagePath = $this->repository->findImagePathByIdAndTenant($bannerId, $tenantId);
        if ($imagePath !== null) {
            $relativePath = ltrim(str_replace(['assets/imagens/', '/assets/imagens/'], '', $imagePath), '/');
            $absolutePath = dirname(__DIR__, 2) . '/assets/imagens/' . $relativePath;

            if (file_exists($absolutePath) && is_file($absolutePath)) {
                @unlink($absolutePath);
            }
        }

        $deleted = $this->repository->deleteBanner($tenantId, $bannerId);
        if (!$deleted) {
            throw new InvalidArgumentException('Banner não encontrado');
        }

        return true;
    }
}
