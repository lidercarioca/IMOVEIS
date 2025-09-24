<?php
session_start();

// Headers de segurança aprimorados
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer-when-downgrade');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://*.google.com https://*.googleapis.com https://*.gstatic.com https://*.google-analytics.com https://kit.fontawesome.com https://cdn.jsdelivr.net/npm/@popperjs/ https://polyfill.io; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com https://ka-f.fontawesome.com; font-src 'self' data: https://fonts.gstatic.com https://ka-f.fontawesome.com https://cdnjs.cloudflare.com; img-src 'self' data: https: http:; connect-src 'self' https://ka-f.fontawesome.com https://*.jsdelivr.net https://cdnjs.cloudflare.com https://wa.me https://cdn.jsdelivr.net; frame-src 'self' https://wa.me;");
header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');

// Cache control para melhor performance
header('Cache-Control: public, max-age=3600');
header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime(__FILE__)).' GMT');

// Configuração de erros
if (getenv('ENVIRONMENT') === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Define o tipo de conteúdo como HTML
header('Content-Type: text/html; charset=UTF-8');

// Diretório base para os assets
$baseDir = '/';

// Caminho para o arquivo index.html
$publicFile = __DIR__ . '/views/public/index.html';

// Verifica se o arquivo existe
if (!file_exists($publicFile)) {
    die('Erro: arquivo index.html não encontrado em: ' . $publicFile);
}

// Lê o conteúdo do arquivo
$html = file_get_contents($publicFile);

// Verifica se o arquivo foi lido corretamente
if ($html === false) {
    die('Erro ao ler o arquivo index.html');
}

// Corrige os caminhos dos assets
$html = str_replace('src="assets/', 'src="' . $baseDir . 'assets/', $html);
$html = str_replace('href="assets/', 'href="' . $baseDir . 'assets/', $html);

// Adiciona marca de debug
$html = "<!-- Servido via index.php em " . date('Y-m-d H:i:s') . " -->\n" . $html;

// Exibe o conteúdo
echo $html;
?>
