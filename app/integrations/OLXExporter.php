<?php
require_once __DIR__ . '/BaseExporter.php';

class OLXExporter extends BaseExporter {
    /**
     * Export properties according to a simple OLX-compatible JSON/XML structure.
     * The PortalManager should already apply field_map so this exporter consumes portal-field-named arrays.
     */
    public function export(array $properties, string $format = 'json') : string {
        $format = strtolower($format);

        // If portal provided fields already mapped, we can directly return JSON
        if ($format === 'json') {
            return json_encode(['listings' => $properties], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        // XML fallback
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        $root = $xml->createElement('listings');
        $xml->appendChild($root);

        foreach ($properties as $p) {
            $item = $xml->createElement('listing');
            foreach ($p as $k => $v) {
                if (is_array($v)) {
                    $sub = $xml->createElement($k);
                    foreach ($v as $elem) {
                        $sub->appendChild($xml->createElement('item', htmlspecialchars((string)$elem)));
                    }
                    $item->appendChild($sub);
                } else {
                    $item->appendChild($xml->createElement($k, htmlspecialchars((string)$v)));
                }
            }
            $root->appendChild($item);
        }

        return $xml->saveXML();
    }

    public function push(string $payload, array $options = []) : array {
        if (empty($this->config['endpoint'])) return ['success' => false, 'response' => 'no_endpoint_configured'];

        $ch = curl_init($this->config['endpoint']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        $headers = ['Content-Type: application/json'];
        if (!empty($this->config['credentials']['token'])) {
            $headers[] = 'Authorization: Bearer ' . $this->config['credentials']['token'];
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp === false) return ['success' => false, 'response' => $err];
        return ['success' => ($code >= 200 && $code < 300), 'response' => $resp, 'status' => $code];
    }
}
