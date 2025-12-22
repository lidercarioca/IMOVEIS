<?php
require_once __DIR__ . '/BaseExporter.php';
require_once __DIR__ . '/ZAPExporter.php';
require_once __DIR__ . '/OLXExporter.php';

class PortalManager {
    protected $pdo;
    protected $config;
    protected $baseUrl;

    // Logger helper
    protected $loggerIncluded = false;

    public function __construct(PDO $pdo, array $config = []) {
        $this->pdo = $pdo;
        $this->config = $config;

        // Resolve baseUrl: prioridade para config->base_url, senão tenta inferir de SERVER
        $cfgBase = $this->config['base_url'] ?? '';
        if (!empty($cfgBase)) {
            $this->baseUrl = rtrim($cfgBase, '/');
        } else {
            // Tentativa de inferência para execuções via web
            if (!empty($_SERVER['HTTP_HOST'])) {
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                          (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
                $this->baseUrl = $scheme . '://' . $_SERVER['HTTP_HOST'];
            } else {
                // Em CLI sem base_url configurado, deixa vazio e o caller deve fornecer base_url
                $this->baseUrl = '';
            }
        }
    }

    public function getExporter(string $portal) {
        $portal = strtolower($portal);
        $cfg = $this->config[$portal] ?? [];

        switch ($portal) {
            case 'zap':
                return new ZAPExporter($cfg);
            case 'olx':
                return new OLXExporter($cfg);
            default:
                throw new Exception('Unknown portal exporter: ' . $portal);
        }
    }

    /**
     * Fetch properties from DB. Simple implementation (can be extended with filters)
     * @param int $limit
     * @return array
     */
    public function fetchProperties($limit = 500, $portal = null) {
        $sql = "SELECT * FROM properties ORDER BY id DESC LIMIT :lim";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':lim', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        $props = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Deduplicate by local id (ensure unique property ids)
        $unique = [];
        $duplicates = [];
        foreach ($props as $p) {
            $pid = $p['id'] ?? null;
            if ($pid === null) continue;
            if (isset($unique[$pid])) {
                $duplicates[] = $pid;
                continue;
            }
            $unique[$pid] = $p;
        }
        // Rebuild props as unique list preserving newest order
        $props = array_values($unique);

        // Attach images
        foreach ($props as &$p) {
            $stmt = $this->pdo->prepare("SELECT image_url FROM property_images WHERE property_id = ? ORDER BY id ASC");
            $stmt->execute([$p['id']]);
            $imgs = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $images = $imgs ?: [];

            // Constrói URLs absolutas para imagens quando possível
            $absoluteImages = [];
            foreach ($images as $img) {
                if (preg_match('#^https?://#i', $img)) {
                    $absoluteImages[] = $img;
                } else {
                    if (!empty($this->baseUrl)) {
                        $absoluteImages[] = $this->baseUrl . '/assets/imagens/' . $p['id'] . '/' . ltrim($img, '/');
                    } else {
                        // fallback para caminho relativo
                        $absoluteImages[] = '/assets/imagens/' . $p['id'] . '/' . ltrim($img, '/');
                    }
                }
            }

            // Aplica limite de imagens por portal se configurado
            $maxImages = null;
            if (!empty($portal) && isset($this->config[$portal]) && isset($this->config[$portal]['options']['max_images'])) {
                $maxImages = (int)$this->config[$portal]['options']['max_images'];
            }
            if ($maxImages && count($absoluteImages) > $maxImages) {
                $absoluteImages = array_slice($absoluteImages, 0, $maxImages);
            }

            $p['images'] = $absoluteImages;
        }
        // Important: break the reference to avoid unexpected side-effects later
        unset($p);

        // Merge defaults and portal options
        $defaults = $this->config['defaults'] ?? [];
        $portalOpts = [];
        if ($portal && isset($this->config[$portal]['options'])) {
            $portalOpts = $this->config[$portal]['options'];
        }
        $opts = array_merge($defaults, $portalOpts);

        // Exclude items based on required fields or require_images
        $excluded = [];
        $filtered = [];
        foreach ($props as $p) {
            $exclude = false;
            // require_images
            if (!empty($opts['require_images']) && empty($p['images'])) {
                $exclude = true;
                $excluded[] = ['id' => $p['id'], 'reason' => 'no_images'];
            }

            // required_fields check (fields are local keys)
            $reqs = $opts['required_fields'] ?? [];
            foreach ($reqs as $req) {
                if ($req === 'url') continue; // url is generated later
                if (!array_key_exists($req, $p) || $p[$req] === null || $p[$req] === '') {
                    $exclude = true;
                    $excluded[] = ['id' => $p['id'], 'reason' => 'missing_' . $req];
                    break;
                }
            }

            if (!$exclude) $filtered[] = $p;
        }

        // If there were duplicates or exclusions, log them for auditing
        if ((!empty($duplicates) || !empty($excluded)) && !$this->loggerIncluded) {
            // include logger helper
            require_once __DIR__ . '/../utils/logger_functions.php';
            $this->loggerIncluded = true;
        }
        if (!empty($duplicates) || !empty($excluded)) {
            log_user_action('export_data_cleanup', [
                'portal' => $portal,
                'duplicates' => array_values(array_unique($duplicates)),
                'excluded' => $excluded,
                'original_count' => $limit,
                'after_dedup_count' => count($filtered)
            ]);
        }

        // Use filtered list for mapping
        $props = $filtered;

        // Se foi passado um portal e houver mapeamento de campos, aplica field_map
        if ($portal && isset($this->config[$portal]) && isset($this->config[$portal]['field_map']) && is_array($this->config[$portal]['field_map'])) {
            $map = $this->config[$portal]['field_map'];
            $mappedProps = [];
            foreach ($props as $p) {
                $mp = [];
                    foreach ($map as $localKey => $portalKey) {
                        // images já tratado
                        if ($localKey === 'images') {
                            $mp[$portalKey] = $p['images'];
                            continue;
                        }

                        // url gerada a partir do id
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

                        // Valor padrão se existir na linha
                        if (array_key_exists($localKey, $p)) {
                            $value = $p[$localKey];

                            // Normaliza price para formato ponto com 2 decimais ou centavos se configurado
                            if ($localKey === 'price' || strtolower($portalKey) === 'price') {
                                // Remove separadores comuns e converte
                                $numeric = preg_replace('/[^0-9,\.]/', '', (string)$value);
                                // Troca vírgula por ponto se houver
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
}
