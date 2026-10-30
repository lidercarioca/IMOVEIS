<?php

/**
 * Carrega as variáveis de ambiente do arquivo .env
 * @returns array Variáveis de ambiente carregadas
 */
function loadEnv()
{
    $candidates = [
        __DIR__ . '/../.env',    // padrão (raiz do projeto)
        __DIR__ . '/../../.env'  // fora do webroot (um nível acima do htdocs)
    ];

    $envFile = null;
    foreach ($candidates as $candidate) {
        if (file_exists($candidate) && is_readable($candidate)) {
            $envFile = $candidate;
            break;
        }
    }

    if (!$envFile) {
        // Verifica se é uma chamada de API
        if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
            header('Content-Type: application/json');
            http_response_code(500);
            die(json_encode(['error' => 'Erro de configuração do ambiente']));
        } else {
            die('Arquivo .env não encontrado. Por favor, configure o ambiente (verifique .env em raiz do projeto ou em um nível acima do webroot).');
        }
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $trimmedLine = trim($line);

        // Ignora linhas vazias e comentários
        if ($trimmedLine === '' || strpos($trimmedLine, '#') === 0) {
            continue;
        }

        // Ignora entradas malformadas sem '='
        if (strpos($trimmedLine, '=') === false) {
            continue;
        }

        list($name, $value) = explode('=', $trimmedLine, 2);
        $name = trim($name);
        $value = trim($value);

        // Remove aspas se existirem
        if (preg_match('/^("|\').*\1$/', $value)) {
            $value = substr($value, 1, -1);
        }

        if ($name !== '') {
            putenv("$name=$value");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Carrega as variáveis de ambiente
loadEnv();
