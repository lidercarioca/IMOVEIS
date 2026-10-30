<?php
header('Content-Type: application/json');

// Load env
require __DIR__ . '/env_loader.php';

// Get VAPID public key
$publicKey = getenv('VAPID_PUBLIC_KEY');

if (!$publicKey) {
    http_response_code(500);
    echo json_encode(['error' => 'VAPID_PUBLIC_KEY not configured']);
    exit;
}

echo json_encode([
    'publicKey' => $publicKey
]);
?>
