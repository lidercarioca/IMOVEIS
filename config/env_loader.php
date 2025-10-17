<?php
/**
 * Carrega as variáveis de ambiente do arquivo .env
 */
/**
 * Carrega as variáveis de ambiente do arquivo .env
 * @returns array Variáveis de ambiente carregadas
 */
function loadEnv() {
    $envFile = __DIR__ . '/../.env';
    
    if (!file_exists($envFile)) {
        // Verifica se é uma chamada de API
        if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
            header('Content-Type: application/json');
            http_response_code(500);
            die(json_encode(['error' => 'Erro de configuração do ambiente']));
        } else {
            die('Arquivo .env não encontrado. Por favor, configure o ambiente.');
        }
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // Ignora comentários
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        // Remove aspas se existirem
        if (preg_match('/^(["\']).*\1$/', $value)) {
            $value = substr($value, 1, -1);
        }
        
        putenv("$name=$value");
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

// Carrega as variáveis de ambiente
loadEnv();
