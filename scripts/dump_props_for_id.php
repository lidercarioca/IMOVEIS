<?php
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../config/integrations.php';
require __DIR__ . '/../app/integrations/PortalManager.php';

$portal = $argv[1] ?? 'zap';
$idToDump = $argv[2] ?? null;
if (!$idToDump) { echo "Usage: php dump_props_for_id.php <portal> <id>\n"; exit(2); }

$cfg = require __DIR__ . '/../config/integrations.php';
$manager = new PortalManager($pdo, $cfg);
$props = $manager->fetchProperties(500, $portal);

foreach ($props as $idx => $p) {
    if ((string)($p['id'] ?? '') === (string)$idToDump) {
        echo "-- Index: {$idx} --\n";
        echo json_encode($p, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }
}
