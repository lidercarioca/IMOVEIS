<?php
/**
 * Script de validação de feed para integrações com portais.
 * Uso (CLI):
 *   php validate_feed.php portal=zap format=xml limit=100
 * Uso (web):
 *   /scripts/validate_feed.php?portal=zap&format=json&limit=100
 */

// Permite tanto execução CLI quanto web
$params = [];
if (php_sapi_name() === 'cli') {
    global $argv;
    foreach ($argv as $arg) {
        if (strpos($arg, '=') !== false) {
            list($k, $v) = explode('=', $arg, 2);
            $params[$k] = $v;
        }
    }
} else {
    $params = $_GET;
}

$portal = $params['portal'] ?? 'zap';
$format = strtolower($params['format'] ?? 'xml');
$limit = isset($params['limit']) ? intval($params['limit']) : 500;

require_once __DIR__ . '/../config/integrations.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/integrations/PortalManager.php';
require_once __DIR__ . '/../app/utils/logger_functions.php';

try {
    $cfg = require __DIR__ . '/../config/integrations.php';
    $manager = new PortalManager($pdo, $cfg);
    $exporter = $manager->getExporter($portal);
    $props = $manager->fetchProperties($limit, $portal);

    $result = ['success' => true, 'issues' => []];

    // Se houver XSD para o portal e formato XML, tentar validação por XSD
    $xsdPath = __DIR__ . '/../schemas/' . $portal . '.xsd';
    if ($format === 'xml' && file_exists($xsdPath)) {
        // Gerar XML via exporter
        $xml = $exporter->export($props, 'xml');
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        if (!$dom->loadXML($xml)) {
            $errors = libxml_get_errors();
            foreach ($errors as $e) {
                $result['issues'][] = 'XML parse error: ' . trim($e->message);
            }
            libxml_clear_errors();
            $result['success'] = false;
        } else {
            if (!@$dom->schemaValidate($xsdPath)) {
                $errors = libxml_get_errors();
                foreach ($errors as $e) {
                    $result['issues'][] = 'Schema validation: ' . trim($e->message);
                }
                libxml_clear_errors();
                $result['success'] = false;
            }
        }
    } else {
        // Validação básica baseada em required_fields e formatos
        $defaults = $cfg['defaults'] ?? [];
        $portalOptions = $cfg[$portal]['options'] ?? [];

        $required = $portalOptions['required_fields'] ?? $defaults['required_fields'] ?? ['title','price','city'];
        $requireImages = $portalOptions['require_images'] ?? $defaults['require_images'] ?? false;

        foreach ($props as $p) {
            $pid = $p['id'] ?? null;
            foreach ($required as $field) {
                if (!isset($p[$field]) || $p[$field] === '' || $p[$field] === null) {
                    $result['issues'][] = "property_id={$pid}: missing required field '{$field}'";
                    $result['success'] = false;
                }
            }

            // Price format check
            if (isset($p['price']) && !is_numeric($p['price'])) {
                $result['issues'][] = "property_id={$pid}: price is not numeric ('{$p['price']}')";
                $result['success'] = false;
            }

            // Images requirement
            $images = $p['images'] ?? [];
            if ($requireImages && empty($images)) {
                $result['issues'][] = "property_id={$pid}: requires images but none provided";
                $result['success'] = false;
            }

            // URL checks
            if (isset($p['url']) && !preg_match('#^https?://#i', $p['url'])) {
                $result['issues'][] = "property_id={$pid}: url not absolute ('{$p['url']}')";
                $result['success'] = false;
            }
            foreach ($images as $img) {
                if (!preg_match('#^https?://#i', $img)) {
                    $result['issues'][] = "property_id={$pid}: image not absolute ('{$img}')";
                    $result['success'] = false;
                }
            }
        }
    }

    // Resultado
    if (php_sapi_name() === 'cli') {
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit( ($result['success']) ? 0 : 2 );
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;

} catch (Exception $e) {
    $err = ['success' => false, 'error' => $e->getMessage()];
    if (php_sapi_name() === 'cli') {
        echo json_encode($err, JSON_PRETTY_PRINT) . PHP_EOL;
        exit(1);
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($err);
    exit;
}
