<?php
require __DIR__ . '/../config/database.php';

$stmt = $pdo->prepare('SELECT * FROM properties ORDER BY id DESC LIMIT :lim');
$stmt->bindValue(':lim', 500, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Raw rows count: " . count($rows) . "\n";
$counts = [];
foreach ($rows as $r) {
    $id = $r['id'] ?? null;
    if ($id === null) continue;
    if (!isset($counts[$id])) $counts[$id] = 0;
    $counts[$id]++;
}

foreach ($counts as $id => $c) {
    if ($c > 1) echo "Duplicate in raw rows: id={$id} count={$c}\n";
}

// Print rows where id==66
echo "All raw ids in order:\n";
foreach ($rows as $i => $r) {
    echo ($i+1) . ". id=" . ($r['id'] ?? '(none)') . "\n";
}

echo "\n";
// Print rows where id==66
foreach ($rows as $i => $r) {
    if ((string)($r['id'] ?? '') === '66') {
        echo "-- Raw index {$i} --\n" . json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }
}
