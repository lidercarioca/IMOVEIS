<?php
// API para listar arquivos de backup
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

require_once '../auth.php';
checkAuth();
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado']);
    exit;
}

$backupDir = realpath(__DIR__ . '/../backups');
if ($backupDir === false) {
    echo json_encode(['success' => false, 'message' => 'Diretório de backups não encontrado']);
    exit;
}

$files = glob($backupDir . '/db_backup_*.sql');
$backups = [];
foreach ($files as $file) {
    $backups[] = [
        'file' => basename($file),
        'size' => filesize($file),
        'date' => date('Y-m-d H:i:s', filemtime($file)),
        'timestamp' => filemtime($file)
    ];
}

// Ordenar backups do mais recente para o mais antigo
usort($backups, function($a, $b) {
    return $b['timestamp'] - $a['timestamp'];
});

// Remove o timestamp antes de enviar (foi usado só para ordenação)
foreach ($backups as &$backup) {
    unset($backup['timestamp']);
}

echo json_encode([
    'success' => true,
    'data' => $backups
]);
