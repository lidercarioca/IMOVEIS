<?php
// Carrega um arquivo .env simples para populações de $_ENV e getenv()
// Locais possíveis do arquivo .env: primeiro tenta um nível acima do webroot (fora do webroot),
// depois tenta o .env na raiz do projeto (webroot). Isso permite mover credenciais para fora do webroot.
$envPaths = [
    __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '.env', // fora do webroot
    __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '.env' // dentro do webroot (fallback)
];
$envPath = null;
foreach ($envPaths as $p) {
    if (file_exists($p) && is_readable($p)) {
        $envPath = $p;
        break;
    }
}
if ($envPath !== null) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        // Remove aspas simples/duplas ao redor
        if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') || (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
            $value = substr($value, 1, -1);
        }
        if (getenv($name) === false) {
            putenv("$name=$value");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}
