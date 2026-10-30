<?php

namespace App\Services;

use App\Infrastructure\Alert\AlertRepository;
use PDO;

class AlertService
{
    private PDO $pdo;
    private AlertRepository $repository;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->repository = new AlertRepository($pdo);
    }

    public function getAlerts(int $tenantId, ?int $userId, array $filters, int $page, int $limit): array
    {
        return $this->repository->findAlerts($tenantId, $userId, $filters, $page, $limit);
    }

    public function getStats(int $tenantId, ?int $userId = null): array
    {
        return $this->repository->getStats($tenantId, $userId);
    }

    public function acknowledgeAlert(int $tenantId, int $alertId): array
    {
        $success = $this->repository->acknowledgeAlert($tenantId, $alertId);
        if ($success) {
            return ['success' => true, 'message' => 'Alerta marcado como reconhecido'];
        }

        return ['success' => false, 'error' => 'Falha ao reconhecer alerta'];
    }

    public function dismissAlert(int $tenantId, int $alertId, ?int $userId = null): array
    {
        $success = $this->repository->dismissAlert($tenantId, $alertId, $userId);
        if ($success) {
            return ['success' => true, 'message' => 'Alerta descartado'];
        }

        return ['success' => false, 'error' => 'Falha ao descartar alerta'];
    }

    public function updateAlert(int $tenantId, int $alertId, array $payload, ?int $resolvedBy = null): array
    {
        $success = $this->repository->updateAlert($tenantId, $alertId, $payload, $resolvedBy);
        if ($success) {
            return ['success' => true, 'message' => 'Alerta atualizado com sucesso'];
        }

        return ['success' => false, 'error' => 'Falha ao atualizar alerta'];
    }

    public function deleteAlert(int $tenantId, int $alertId, ?int $userId = null): array
    {
        $success = $this->repository->deleteAlert($tenantId, $alertId, $userId);
        if ($success) {
            return ['success' => true, 'message' => 'Alerta removido com sucesso'];
        }

        return ['success' => false, 'error' => 'Falha ao remover alerta'];
    }

    public function processAllAlerts(int $tenantId, ?int $userId = null, bool $isAdmin = false): array
    {
        $generator = new AlertGeneratorService($this->pdo, $userId, $isAdmin, $tenantId);
        $result = $generator->generateAndPersistAlerts();

        return [
            'success' => $result['success'] ?? false,
            'message' => $result['success'] ? 'Alertas processados com sucesso' : 'Falha ao processar alertas',
            'summary' => [
                'created' => $result['created'] ?? 0,
                'candidates' => $result['candidates'] ?? 0,
            ],
        ];
    }

    public function dismissDynamicAlert(int $tenantId, ?string $alertType, $relatedEntityId, ?int $alertId = null, ?int $userId = null): array
    {
        if ($alertId !== null && is_numeric($alertId)) {
            return $this->deleteAlert($tenantId, (int)$alertId, $userId);
        }

        if (empty($alertType) || $relatedEntityId === null) {
            return ['success' => false, 'error' => 'alert_type e related_entity_id obrigatórios'];
        }

        $success = $this->repository->deleteAlertByTypeAndEntity($tenantId, $alertType, $relatedEntityId, $userId);
        if ($success) {
            return ['success' => true, 'message' => 'Alerta excluído com sucesso'];
        }

        return ['success' => false, 'error' => 'Alerta não encontrado ou já foi excluído'];
    }

    public function cleanupResolvedAlerts(int $tenantId): array
    {
        $removed = $this->repository->cleanupResolvedAlerts($tenantId);
        $stats = $this->repository->getStats($tenantId);

        return [
            'success' => true,
            'removed' => $removed,
            'message' => 'Alertas resolvidos limpos com sucesso',
            'stats_after' => [
                'total' => $stats['data']['total'] ?? 0,
                'pendentes' => $stats['data']['pending'] ?? 0,
                'criticos' => $stats['data']['critical'] ?? 0,
                'resolvidos' => $stats['data']['resolved'] ?? 0,
            ],
        ];
    }
}
