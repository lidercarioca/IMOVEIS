<?php

namespace App\Application\Integration;

use App\Infrastructure\PortalIntegration\PortalIntegrationRepository;
use App\Infrastructure\PortalIntegration\PortalSyncRepository;
use App\Application\Integration\PortalIntegrationService;
use App\Service\PropertyService;

class PortalService
{
    private $pdo;
    private array $config;
    private ?int $tenantId;
    private PortalIntegrationService $portalIntegrationService;
    private PortalSyncRepository $portalSyncRepository;
    private string $baseUrl;

    public function __construct($pdo, array $config = [], ?int $tenantId = null, ?PortalIntegrationService $portalIntegrationService = null, ?PortalSyncRepository $portalSyncRepository = null)
    {
        $this->pdo = $pdo;
        $this->config = $config;
        $this->tenantId = $tenantId;
        $this->portalIntegrationService = $portalIntegrationService ?? new PortalIntegrationService(new PortalIntegrationRepository($pdo));
        $this->portalSyncRepository = $portalSyncRepository ?? new PortalSyncRepository($pdo);
        $this->baseUrl = $this->resolveBaseUrl();
    }

    private function resolveBaseUrl(): string
    {
        $cfgBase = $this->config['base_url'] ?? '';
        if (!empty($cfgBase)) {
            return rtrim($cfgBase, '/');
        }

        if (!empty($_SERVER['HTTP_HOST'])) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
            return $scheme . '://' . $_SERVER['HTTP_HOST'];
        }

        return '';
    }

    public function validatePortal(string $portal): bool
    {
        return isset($this->config[strtolower($portal)]);
    }

    private function ensurePortalExists(string $portal): void
    {
        if (!$this->validatePortal($portal)) {
            throw new \InvalidArgumentException('Portal inválido');
        }
    }

    public function getExporter(string $portal)
    {
        $portal = strtolower($portal);
        $cfg = $this->config[$portal] ?? [];

        require_once __DIR__ . '/../../../app/integrations/BaseExporter.php';
        require_once __DIR__ . '/../../../app/integrations/ZAPExporter.php';
        require_once __DIR__ . '/../../../app/integrations/OLXExporter.php';
        require_once __DIR__ . '/../../../app/integrations/AdOptExporter.php';
        require_once __DIR__ . '/../../../app/integrations/MetaAnunciosExporter.php';
        require_once __DIR__ . '/../../../app/integrations/GoogleTagManagerExporter.php';
        require_once __DIR__ . '/../../../app/integrations/ActiveCampaignExporter.php';
        require_once __DIR__ . '/../../../app/integrations/MailChimpExporter.php';

        switch ($portal) {
            case 'zap':
                return new \ZAPExporter($cfg);
            case 'olx':
                return new \OLXExporter($cfg);
            case 'adopt':
                return new \AdOptExporter($cfg);
            case 'meta_anuncios':
                return new \MetaAnunciosExporter($cfg);
            case 'google_tag_manager':
                return new \GoogleTagManagerExporter($cfg);
            case 'active_campaign':
                return new \ActiveCampaignExporter($cfg);
            case 'mailchimp':
                return new \MailChimpExporter($cfg);
            default:
                throw new \Exception('Unknown portal exporter: ' . $portal);
        }
    }

    public function fetchProperties(int $limit = 500, ?string $portal = null): array
    {
        $propertyService = new PropertyService($this->pdo);
        $res = $propertyService->getPropertiesForPortalExport($this->tenantId, $limit, $portal, $this->config);

        $props = $res['properties'] ?? [];
        $duplicates = $res['duplicates'] ?? [];

        foreach ($props as &$p) {
            $images = $p['images'] ?? [];
            $absoluteImages = [];
            foreach ($images as $img) {
                if (preg_match('#^https?://#i', $img)) {
                    $absoluteImages[] = $img;
                } else {
                    if (!empty($this->baseUrl)) {
                        $absoluteImages[] = rtrim($this->baseUrl, '/') . '/assets/imagens/' . $p['id'] . '/' . ltrim($img, '/');
                    } else {
                        $absoluteImages[] = '/assets/imagens/' . $p['id'] . '/' . ltrim($img, '/');
                    }
                }
            }

            $maxImages = null;
            if (!empty($portal) && isset($this->config[$portal]) && isset($this->config[$portal]['options']['max_images'])) {
                $maxImages = (int)$this->config[$portal]['options']['max_images'];
            }
            if ($maxImages && count($absoluteImages) > $maxImages) {
                $absoluteImages = array_slice($absoluteImages, 0, $maxImages);
            }

            $p['images'] = $absoluteImages;
        }
        unset($p);

        $defaults = $this->config['defaults'] ?? [];
        $portalOpts = [];
        if ($portal && isset($this->config[$portal]['options'])) {
            $portalOpts = $this->config[$portal]['options'];
        }
        $opts = array_merge($defaults, $portalOpts);

        $excluded = [];
        $filtered = [];
        foreach ($props as $p) {
            $exclude = false;
            if (!empty($opts['require_images']) && empty($p['images'])) {
                $exclude = true;
                $excluded[] = ['id' => $p['id'], 'reason' => 'no_images'];
            }

            $reqs = $opts['required_fields'] ?? [];
            foreach ($reqs as $req) {
                if ($req === 'url') {
                    continue;
                }
                if (!array_key_exists($req, $p) || $p[$req] === null || $p[$req] === '') {
                    $exclude = true;
                    $excluded[] = ['id' => $p['id'], 'reason' => 'missing_' . $req];
                    break;
                }
            }

            if (!$exclude) {
                $filtered[] = $p;
            }
        }

        if ((!empty($duplicates) || !empty($excluded))) {
            require_once __DIR__ . '/../../../app/utils/logger_functions.php';
            log_user_action('export_data_cleanup', [
                'portal' => $portal,
                'duplicates' => array_values(array_unique($duplicates)),
                'excluded' => $excluded,
                'original_count' => $limit,
                'after_dedup_count' => count($filtered),
            ]);
        }

        $props = $filtered;

        if ($portal && isset($this->config[$portal]) && isset($this->config[$portal]['field_map']) && is_array($this->config[$portal]['field_map'])) {
            $map = $this->config[$portal]['field_map'];
            $mappedProps = [];
            foreach ($props as $p) {
                $mp = [];
                foreach ($map as $localKey => $portalKey) {
                    if ($localKey === 'images') {
                        $mp[$portalKey] = $p['images'];
                        continue;
                    }

                    if ($localKey === 'url' || $portalKey === 'url') {
                        if (!empty($p['id'])) {
                            if (!empty($this->baseUrl)) {
                                $mp[$portalKey] = $this->baseUrl . '/detalhes.php?id=' . $p['id'];
                            } else {
                                $mp[$portalKey] = '/detalhes.php?id=' . $p['id'];
                            }
                        } else {
                            $mp[$portalKey] = null;
                        }
                        continue;
                    }

                    if (array_key_exists($localKey, $p)) {
                        $value = $p[$localKey];
                        if ($localKey === 'price' || strtolower($portalKey) === 'price') {
                            $numeric = preg_replace('/[^0-9,\.]/', '', (string)$value);
                            $numeric = str_replace(',', '.', $numeric);
                            $floatVal = (float)$numeric;
                            $priceInCents = $opts['price_in_cents'] ?? false;
                            if ($priceInCents) {
                                $mp[$portalKey] = (int)round($floatVal * 100);
                            } else {
                                $mp[$portalKey] = number_format($floatVal, 2, '.', '');
                            }
                        } else {
                            $mp[$portalKey] = $value;
                        }
                    } else {
                        $mp[$portalKey] = null;
                    }
                }
                $mappedProps[] = $mp;
            }
            return $mappedProps;
        }

        return $props;
    }

    public function generatePayload(string $portal, string $format = 'xml', int $limit = 500): string
    {
        $this->ensurePortalExists($portal);
        $exporter = $this->getExporter($portal);
        $props = $this->fetchProperties($limit, $portal);
        return $exporter->export($props, $format);
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
    ): array {
        $this->ensurePortalExists($portal);

        $savedPortal = $this->portalIntegrationService->getPortalIntegration($this->tenantId, strtolower($portal));
        if ($savedPortal) {
            if (empty($endpoint) && !empty($savedPortal['endpoint'])) {
                $endpoint = $savedPortal['endpoint'];
            }
            if (empty($apiKey) && !empty($savedPortal['credentials'])) {
                $apiKey = $savedPortal['credentials']['api_key'] ?? $savedPortal['credentials']['token'] ?? null;
            }
        }

        $props = $this->fetchProperties($limit, $portal);
        $exporter = $this->getExporter($portal);
        $payload = $exporter->export($props, $format);

        require_once __DIR__ . '/../../../app/utils/logger_functions.php';
        log_user_action('export_run_manual', [
            'portal' => $portal,
            'format' => $format,
            'limit' => $limit,
            'by_user' => $userId,
        ]);

        $response = [
            'success' => true,
            'message' => 'Export gerado com sucesso',
            'details' => ['count' => count($props)],
        ];

        $this->persistExportInfo($portal, $props);

        if ($push && !empty($endpoint)) {
            $contentType = $contentType ?? ($format === 'json' ? 'application/json' : 'application/xml');
            $pushResult = $exporter->push($payload, [
                'endpoint' => $endpoint,
                'api_key' => $apiKey,
                'format' => $format,
                'content_type' => $contentType,
            ]);

            log_user_action('export_push', [
                'portal' => $portal,
                'endpoint' => $endpoint,
                'http_code' => $pushResult['status'] ?? null,
            ]);

            $response['push'] = [
                'http_code' => $pushResult['status'] ?? null,
                'response' => $pushResult['response'] ?? null,
                'success' => $pushResult['success'] ?? false,
            ];

            if ($pushResult['success'] ?? false) {
                $response['message'] .= ' e enviado com sucesso.';
            } else {
                $response['success'] = false;
                $response['error'] = 'Push falhou: ' . ($pushResult['response'] ?? 'unknown');
            }
        }

        return $response;
    }

    private function persistExportInfo(string $portal, array $props): void
    {
        if (empty($props)) {
            return;
        }

        $exportedIds = [];
        foreach ($props as $p) {
            if (is_array($p) && isset($p['id'])) {
                $exportedIds[] = intval($p['id']);
            }
        }

        if (empty($exportedIds)) {
            return;
        }

        $now = (new \DateTime())->format('Y-m-d H:i:s');

        try {
            $this->portalSyncRepository->upsertMany($exportedIds, $portal, $now, 'exported');
            require_once __DIR__ . '/../../../app/utils/logger_functions.php';
            log_user_action('export_persist', ['portal' => $portal, 'count' => count($exportedIds)]);
        } catch (\Exception $e) {
            require_once __DIR__ . '/../../../app/utils/logger_functions.php';
            log_user_action('export_persist_failed', ['portal' => $portal, 'error' => $e->getMessage()]);
        }
    }
}
