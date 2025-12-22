<?php
require_once __DIR__ . '/BaseExporter.php';

class ZAPExporter extends BaseExporter {
    public function export(array $properties, string $format = 'xml') : string {
        if (strtolower($format) === 'json') {
            return json_encode(['properties' => $properties], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        // XML export (simple structure, extensible)
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        $root = $xml->createElement('properties');
        $xml->appendChild($root);

        foreach ($properties as $p) {
            $item = $xml->createElement('property');
            $item->appendChild($xml->createElement('id', htmlspecialchars($p['id'])));
            $item->appendChild($xml->createElement('title', htmlspecialchars($p['title'] ?? '')));
            $item->appendChild($xml->createElement('description', htmlspecialchars($p['description'] ?? '')));
            $item->appendChild($xml->createElement('price', htmlspecialchars($p['price'] ?? '')));
            $item->appendChild($xml->createElement('city', htmlspecialchars($p['city'] ?? '')));
            $item->appendChild($xml->createElement('state', htmlspecialchars($p['state'] ?? '')));
            $item->appendChild($xml->createElement('neighborhood', htmlspecialchars($p['neighborhood'] ?? '')));
            $item->appendChild($xml->createElement('bedrooms', htmlspecialchars($p['bedrooms'] ?? '')));
            $item->appendChild($xml->createElement('bathrooms', htmlspecialchars($p['bathrooms'] ?? '')));
            $item->appendChild($xml->createElement('area', htmlspecialchars($p['area'] ?? '')));

            // Images
            $imagesNode = $xml->createElement('images');
            if (!empty($p['images']) && is_array($p['images'])) {
                foreach ($p['images'] as $img) {
                    $imagesNode->appendChild($xml->createElement('image', htmlspecialchars($img)));
                }
            }
            $item->appendChild($imagesNode);

            $root->appendChild($item);
        }

        return $xml->saveXML();
    }

    public function push(string $payload, array $options = []) : array {
        if (empty($this->config['endpoint'])) {
            return ['success' => false, 'response' => 'no_endpoint_configured'];
        }

        // Example POST (basic). Portal-specific authentication must be implemented per portal.
        $ch = curl_init($this->config['endpoint']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/xml'));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp === false) {
            return ['success' => false, 'response' => $err];
        }

        return ['success' => ($code >= 200 && $code < 300), 'response' => $resp, 'status' => $code];
    }
}
