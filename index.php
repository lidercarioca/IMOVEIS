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

// Se for solicitado um imóvel específico via query (?property=ID), busca dados e injeta meta tags OG
try {
    if (isset($_GET['property']) && is_numeric($_GET['property'])) {
        // Inclui configuração do DB e busca o imóvel
        require_once __DIR__ . '/config/database.php';
        $propId = intval($_GET['property']);
        $stmt = $pdo->prepare('SELECT id, title, description FROM properties WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $propId]);
        $prop = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($prop) {
            // Busca a primeira imagem do imóvel
            $imgStmt = $pdo->prepare('SELECT image_url FROM property_images WHERE property_id = :id ORDER BY id ASC LIMIT 1');
            $imgStmt->execute([':id' => $propId]);
            $img = $imgStmt->fetch(PDO::FETCH_COLUMN);

            // Normaliza URL/paths
            $host = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $propertyUrl = $host . ($_SERVER['REQUEST_URI'] ? strtok($_SERVER['REQUEST_URI'], '?') : '/');
            $propertyUrl = rtrim($propertyUrl, '/') . '/?property=' . $propId;

            $ogTitle = htmlspecialchars($prop['title'] ?: 'Imóvel', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $ogDescription = htmlspecialchars(substr(strip_tags($prop['description'] ?? ''), 0, 200), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $ogImage = '';
            if ($img && trim($img) !== '') {
                // Garante que o caminho seja absoluto
                if (strpos($img, 'http') === 0) {
                    $ogImage = $img;
                } else {
                    $ogImage = $host . (strpos($img, '/') === 0 ? $img : '/' . $img);
                }
            } else {
                // fallback para imagem OG padrão já presente no HTML
                $ogImage = $host . '/assets/imagens/og-image.jpg';
            }

            // Substitui as meta tags OG no HTML carregado
            // <title>
            $html = preg_replace('/<title>.*?<\/title>/is', '<title>' . $ogTitle . ' - DJ Imóveis</title>', $html, 1);
            // meta description
            $html = preg_replace('/<meta\s+name="description"\s+content="[^"]*"\s*\/?>/i', '<meta name="description" content="' . $ogDescription . '">', $html, 1);
            // canonical
            $html = preg_replace('/<link\s+rel="canonical"\s+href="[^"]*"\s*\/?>/i', '<link rel="canonical" href="' . $propertyUrl . '">', $html, 1);

            // OG tags
            $html = preg_replace('/<meta\s+property="og:url"\s+content="[^"]*"\s*\/?>/i', '<meta property="og:url" content="' . $propertyUrl . '">', $html, 1);
            $html = preg_replace('/<meta\s+property="og:title"\s+content="[^"]*"\s*\/?>/i', '<meta property="og:title" content="' . $ogTitle . '">', $html, 1);
            $html = preg_replace('/<meta\s+property="og:description"\s+content="[^"]*"\s*\/?>/i', '<meta property="og:description" content="' . $ogDescription . '">', $html, 1);
            $html = preg_replace('/<meta\s+property="og:image"\s+content="[^"]*"\s*\/?>/i', '<meta property="og:image" content="' . $ogImage . '">', $html, 1);
        }
    }
} catch (Exception $e) {
    error_log('Erro ao montar OG dinâmico: ' . $e->getMessage());
}

// Adiciona marca de debug
$html = "<!-- Servido via index.php em " . date('Y-m-d H:i:s') . " -->\n" . $html;

// Exibe o conteúdo
echo $html;
?>
