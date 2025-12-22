<?php
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../config/integrations.php';
require __DIR__ . '/../app/integrations/PortalManager.php';

$portal = $argv[1] ?? 'zap';
$limit = isset($argv[2]) ? intval($argv[2]) : 200;

$cfg = require __DIR__ . '/../config/integrations.php';
$manager = new PortalManager($pdo, $cfg);
$props = $manager->fetchProperties($limit, $portal);

echo "Returned properties (count: " . count($props) . ")\n";
foreach ($props as $i => $p) {
    $id = $p['id'] ?? ($p['ID'] ?? null);
    if ($id === null) {
        // if mapped properties, id may be under different key (e.g., 'id' mapped to 'id')
        // try to detect common id fields
        foreach (['id','ID','property_id'] as $k) { if (isset($p[$k])) { $id = $p[$k]; break; } }
    }
    echo ($i+1) . ". id=" . ($id ?? '(none)') . "\n";
}
