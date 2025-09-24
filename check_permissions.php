<?php
$errors = [];

// Verifica mod_rewrite
if (!in_array('mod_rewrite', apache_get_modules())) {
    $errors[] = "mod_rewrite não está habilitado";
}

// Verifica permissões de diretório
$dirs = [
    __DIR__,
    __DIR__ . '/public',
    __DIR__ . '/views',
    __DIR__ . '/views/admin',
    __DIR__ . '/assets'
];

foreach ($dirs as $dir) {
    if (!is_readable($dir)) {
        $errors[] = "Diretório não tem permissão de leitura: " . basename($dir);
    }
    if (!is_writable($dir)) {
        $errors[] = "Diretório não tem permissão de escrita: " . basename($dir);
    }
}

// Verifica se o .htaccess está sendo lido
if (!file_exists(__DIR__ . '/.htaccess')) {
    $errors[] = "Arquivo .htaccess não encontrado na raiz";
}

if (!empty($errors)) {
    echo "Erros encontrados:<br>";
    foreach ($errors as $error) {
        echo "- " . htmlspecialchars($error) . "<br>";
    }
}
// Se não houver erros, não exibe nada
?>
