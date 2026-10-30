<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Application\Integration\PortalService;

class PortalManager
{
    protected $service;

    public function __construct(PDO $pdo, array $config = [], ?int $tenantId = null)
    {
        $this->service = new PortalService($pdo, $config, $tenantId);
    }

    public function getExporter(string $portal)
    {
        return $this->service->getExporter($portal);
    }

    public function fetchProperties($limit = 500, $portal = null)
    {
        return $this->service->fetchProperties((int)$limit, $portal);
    }

    public function generatePayload(string $portal, string $format = 'xml', int $limit = 500)
    {
        return $this->service->generatePayload($portal, $format, $limit);
    }

    public function runManualExport(
        string $portal,
        string $format = 'xml',
        int $limit = 500,
        ?string $endpoint = null,
        ?string $apiKey = null,
        bool $push = false,
        ?string $contentType = null,
        ?int $userId = null
    ) {
        return $this->service->runManualExport($portal, $format, $limit, $endpoint, $apiKey, $push, $contentType, $userId);
    }
}
