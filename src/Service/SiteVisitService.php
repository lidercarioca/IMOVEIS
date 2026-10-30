<?php

namespace App\Service;

use App\Repository\SiteVisitRepository;
use InvalidArgumentException;
use PDO;

class SiteVisitService
{
    private PDO $pdo;
    private SiteVisitRepository $repository;

    public function __construct(PDO $pdo, ?SiteVisitRepository $repository = null)
    {
        $this->pdo = $pdo;
        $this->repository = $repository ?? new SiteVisitRepository($pdo);
    }

    public function trackVisit(array $payload): bool
    {
        $tenantId = (int)($payload['tenant_id'] ?? 0);
        $sessionId = trim((string)($payload['session_id'] ?? ''));

        if ($tenantId <= 0) {
            return false;
        }

        if ($sessionId === '') {
            throw new InvalidArgumentException('Sessão inválida');
        }

        if ($this->repository->hasRecentVisitForSession($sessionId, 5, $tenantId)) {
            return false;
        }

        return $this->repository->insertVisit([
            'tenant_id' => $tenantId,
            'visitor_ip' => $payload['visitor_ip'] ?? null,
            'visitor_ua' => $payload['visitor_ua'] ?? null,
            'page_url' => $payload['page_url'] ?? '/',
            'referrer' => $payload['referrer'] ?? null,
            'session_id' => $sessionId,
        ]);
    }
}
