<?php
require_once __DIR__ . '/load_env.php';

// Determina a URL base do aplicativo em ordem de prioridade:
// 1. APP_URL (preferido quando definido para ngrok/local com URL ativa)
// 2. NGROK_URL (fallback antigo para desenvolvimento tunneling)
// 3. SITE_URL (fallback para localhost ou URL pública)
// 4. Host atual do request
$baseUrlCandidates = [
    getenv('APP_URL'),
    getenv('NGROK_URL'),
    getenv('SITE_URL')
];

$detectedSiteUrl = '';
foreach ($baseUrlCandidates as $candidate) {
    if (empty($candidate)) {
        continue;
    }

    $candidate = trim($candidate);
    if (!preg_match('#^https?://#i', $candidate)) {
        $candidate = 'https://' . ltrim($candidate, '/');
    }

    $candidate = rtrim($candidate, '/');
    if (filter_var($candidate, FILTER_VALIDATE_URL)) {
        $detectedSiteUrl = $candidate;
        break;
    }
}

if (empty($detectedSiteUrl)) {
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $detectedSiteUrl = rtrim($scheme . '://' . $host, '/');
}

return [
    'stripe' => [
        'secret_key' => getenv('STRIPE_SECRET_KEY') ?: '',
        'publishable_key' => getenv('STRIPE_PUBLISHABLE_KEY') ?: '',
        'webhook_secret' => getenv('STRIPE_WEBHOOK_SECRET') ?: '',
        'currency' => getenv('STRIPE_CURRENCY') ?: 'brl',
        'site_url' => $detectedSiteUrl
    ]
];
