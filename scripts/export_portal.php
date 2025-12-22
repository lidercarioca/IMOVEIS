<?php
// CLI script to generate/export portal feed. Usage:
// php scripts/export_portal.php zap xml > /path/to/feed.xml

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/integrations.php';
require_once __DIR__ . '/../app/integrations/PortalManager.php';

$portal = $argv[1] ?? 'zap';
$format = $argv[2] ?? 'xml';
$limit = isset($argv[3]) ? intval($argv[3]) : 500;

try {
    $cfg = require __DIR__ . '/../config/integrations.php';
    $manager = new PortalManager($pdo, $cfg);
    $exporter = $manager->getExporter($portal);
    $props = $manager->fetchProperties($limit, $portal);

    $payload = $exporter->export($props, $format);
    echo $payload;
} catch (Exception $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
