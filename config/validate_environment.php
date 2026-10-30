<?php
/**
 * Script de Validação do Ambiente
 * Verifica se todas as variáveis de ambiente obrigatórias estão configuradas
 * 
 * Uso:
 *   - Acessar via navegador: http://localhost/config/validate_environment.php
 *   - Ou executar via CLI: php config/validate_environment.php
 */

// Carrega env_loader
require_once __DIR__ . '/env_loader.php';

// Define as variáveis obrigatórias e opcionais
$requiredVars = [
    'DB_HOST' => 'Host do Banco de Dados',
    'DB_USER' => 'Usuário do Banco de Dados',
    'DB_NAME' => 'Nome do Banco de Dados',
    'ENVIRONMENT' => 'Ambiente (development/production)',
    'SMTP_HOST' => 'Host SMTP para Email',
    'SMTP_USER' => 'Usuário SMTP',
];

$optionalVars = [
    'GOOGLE_CLIENT_ID' => [
        'description' => 'Client ID do Google OAuth (para Gmail)',
        'required_for' => 'Integração Gmail ou login social do Google'
    ],
    'GOOGLE_CLIENT_SECRET' => [
        'description' => 'Client Secret do Google OAuth (para Gmail)',
        'required_for' => 'Integração Gmail ou login social do Google'
    ],
    'GOOGLE_OAUTH_REDIRECT' => [
        'description' => 'Redirect URI do Google OAuth',
        'required_for' => 'Integração Gmail'
    ],
    'GOOGLE_LOGIN_OAUTH_REDIRECT' => [
        'description' => 'Redirect URI do Google Login OAuth',
        'required_for' => 'Login social do Google'
    ],
    'FACEBOOK_CLIENT_ID' => [
        'description' => 'App ID do Facebook OAuth',
        'required_for' => 'Login social do Facebook'
    ],
    'FACEBOOK_CLIENT_SECRET' => [
        'description' => 'App Secret do Facebook OAuth',
        'required_for' => 'Login social do Facebook'
    ],
    'FACEBOOK_OAUTH_REDIRECT' => [
        'description' => 'Redirect URI do Facebook OAuth',
        'required_for' => 'Login social do Facebook'
    ],
];

$isCli = php_sapi_name() === 'cli';

if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
}

$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'environment' => getenv('ENVIRONMENT') ?: 'não definido',
    'required' => [],
    'optional' => [],
    'status' => 'OK'
];

// Verifica variáveis obrigatórias
foreach ($requiredVars as $varName => $description) {
    $value = getenv($varName);
    $isMissing = !$value;
    
    $results['required'][$varName] = [
        'description' => $description,
        'defined' => !$isMissing,
        'value' => $isMissing ? null : (strlen($value) > 20 ? substr($value, 0, 20) . '...' : $value)
    ];
    
    if ($isMissing) {
        $results['status'] = 'ERRO';
    }
}

// Verifica variáveis opcionais (Gmail / login social)
foreach ($optionalVars as $varName => $info) {
    $description = $info['description'];
    $requiredFor = $info['required_for'];
    $value = getenv($varName);
    $isDefined = !empty($value);
    
    $results['optional'][$varName] = [
        'description' => $description,
        'defined' => $isDefined,
        'value' => $isDefined ? (strlen($value) > 20 ? substr($value, 0, 20) . '...' : $value) : null,
        'required_for' => $requiredFor
    ];
}

// Verifica arquivo .env
$envPath = __DIR__ . '/../.env';
$results['env_file'] = [
    'path' => $envPath,
    'exists' => file_exists($envPath),
    'readable' => is_readable($envPath)
];

// Verifica arquivo google_token.json
$tokenPath = __DIR__ . '/google_token.json';
$results['google_token'] = [
    'path' => $tokenPath,
    'exists' => file_exists($tokenPath),
    'note' => 'Não é obrigatório. Será criado após primeira autenticação em /google/oauth.php'
];

// Verifica dependências
$results['dependencies'] = [
    'vendor/autoload.php' => [
        'exists' => file_exists(__DIR__ . '/../vendor/autoload.php'),
        'note' => 'Executar: composer install'
    ],
    'google/GmailService.php' => [
        'exists' => file_exists(__DIR__ . '/../google/GmailService.php')
    ]
];

// Output
if ($isCli) {
    // CLI Output
    echo "\n";
    echo "╔═══════════════════════════════════════════════════════════════╗\n";
    echo "║          VALIDAÇÃO DE AMBIENTE - rrImoveis                    ║\n";
    echo "╚═══════════════════════════════════════════════════════════════╝\n\n";
    
    echo "Status Geral: " . ($results['status'] === 'OK' ? '✅ OK' : '❌ ERRO') . "\n";
    echo "Ambiente: " . $results['environment'] . "\n";
    echo "Data/Hora: " . $results['timestamp'] . "\n\n";
    
    echo "─── VARIÁVEIS OBRIGATÓRIAS ───\n";
    foreach ($results['required'] as $varName => $info) {
        $status = $info['defined'] ? '✅' : '❌';
        $value = $info['value'] ? " = {$info['value']}" : '';
        echo "$status $varName$value\n   → {$info['description']}\n";
    }
    
    echo "\n─── VARIÁVEIS OPCIONAIS (Gmail) ───\n";
    foreach ($results['optional'] as $varName => $info) {
        $status = $info['defined'] ? '✅' : '⚠️';
        $value = $info['value'] ? " = {$info['value']}" : '';
        echo "$status $varName$value\n   → {$info['description']}\n";
    }
    
    echo "\n─── ARQUIVOS ───\n";
    echo ($results['env_file']['exists'] ? '✅' : '❌') . " {$results['env_file']['path']}\n";
    echo ($results['google_token']['exists'] ? '✅' : '⚠️') . " {$results['google_token']['path']}\n   → {$results['google_token']['note']}\n";
    
    echo "\n─── DEPENDÊNCIAS ───\n";
    foreach ($results['dependencies'] as $dep => $info) {
        $status = $info['exists'] ? '✅' : '❌';
        echo "$status $dep\n";
        if (isset($info['note'])) {
            echo "   → {$info['note']}\n";
        }
    }
    
    echo "\n";
} else {
    // HTTP JSON Output
    echo json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
?>
