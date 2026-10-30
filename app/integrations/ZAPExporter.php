<?php
require_once __DIR__ . '/BaseExporter.php';

class ZAPExporter extends BaseExporter {
    public function export(array $properties, string $format = 'xml') : string {
        if (strtolower($format) === 'json') {
            return json_encode(['properties' => $properties], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        $root = $xml->createElement('properties');
        $xml->appendChild($root);

        foreach ($properties as $p) {
            $propertyNode = $xml->createElement('property');
            foreach ($p as $key => $value) {
                if ($value === null || $value === '') {
                    continue;
                }

                if (is_array($value)) {
                    $container = $xml->createElement($key);
                    $itemTag = $key === 'images' ? 'image' : 'item';
                    foreach ($value as $itemValue) {
                        $container->appendChild($xml->createElement($itemTag, htmlspecialchars((string)$itemValue, ENT_XML1 | ENT_COMPAT, 'UTF-8')));
                    }
                    $propertyNode->appendChild($container);
                    continue;
                }

                $propertyNode->appendChild($xml->createElement($key, htmlspecialchars((string)$value, ENT_XML1 | ENT_COMPAT, 'UTF-8')));
            }
            $root->appendChild($propertyNode);
        }

        return $xml->saveXML();
    }

    public function push(string $payload, array $options = []) : array {
        return parent::push($payload, $options);
    }
}
