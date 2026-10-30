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

        // XML fallback for OLX
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        $root = $xml->createElement('Imoveis');
        $xml->appendChild($root);

        foreach ($properties as $p) {
            $propertyNode = $xml->createElement('Imovel');

            // Ensure CEP is cleaned and numeric, and numeric values are integer strings
            $cleanup = function ($key, $value) use ($xml) {
                if ($value === null || $value === '') {
                    return null;
                }

                $value = (string)$value;
                if (in_array($key, ['CEP'], true)) {
                    $value = preg_replace('/[^0-9]/', '', $value);
                }

                if (in_array($key, ['PrecoVenda', 'PrecoLocacao', 'PrecoLocacaoTemporada', 'PrecoCondominio', 'ValorIPTU', 'AreaTotal', 'AreaUtil'], true)) {
                    $value = preg_replace('/[^0-9]/', '', $value);
                    if ($value === '') {
                        return null;
                    }
                }

                if ($key === 'Observacao') {
                    $value = strip_tags($value);
                    $value = str_replace(["\r\n", "\r"], "\n", $value);
                }

                return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            };

            $hasTituloAnuncio = isset($p['TituloAnuncio']) && $p['TituloAnuncio'] !== '';
            $hasTitulo = isset($p['Titulo']) && $p['Titulo'] !== '';

            foreach ($p as $k => $v) {
                if ($k === 'Fotos' && is_array($v)) {
                    $photosNode = $xml->createElement('Fotos');
                    foreach ($v as $index => $photoUrl) {
                        $photoNode = $xml->createElement('Foto');
                        $urlNode = $xml->createElement('URLArquivo', htmlspecialchars((string)$photoUrl, ENT_XML1 | ENT_QUOTES, 'UTF-8'));
                        $photoNode->appendChild($urlNode);
                        if ($index === 0) {
                            $photoNode->appendChild($xml->createElement('Principal', '1'));
                        }
                        $photosNode->appendChild($photoNode);
                    }
                    $propertyNode->appendChild($photosNode);
                    continue;
                }

                if ($k === 'Videos' && is_array($v)) {
                    $videosNode = $xml->createElement('Videos');
                    foreach ($v as $videoUrl) {
                        $videosNode->appendChild($xml->createElement('Video', htmlspecialchars((string)$videoUrl, ENT_XML1 | ENT_QUOTES, 'UTF-8')));
                    }
                    $propertyNode->appendChild($videosNode);
                    continue;
                }

                if ($k === 'Foto' && is_array($v)) {
                    // Legacy fallback if field_map provides a single Foto parent
                    $photosNode = $xml->createElement('Fotos');
                    foreach ($v as $index => $photoUrl) {
                        $photoNode = $xml->createElement('Foto');
                        $urlNode = $xml->createElement('URLArquivo', htmlspecialchars((string)$photoUrl, ENT_XML1 | ENT_QUOTES, 'UTF-8'));
                        $photoNode->appendChild($urlNode);
                        if ($index === 0) {
                            $photoNode->appendChild($xml->createElement('Principal', '1'));
                        }
                        $photosNode->appendChild($photoNode);
                    }
                    $propertyNode->appendChild($photosNode);
                    continue;
                }

                if (is_array($v)) {
                    $subNode = $xml->createElement($k);
                    foreach ($v as $elem) {
                        $subNode->appendChild($xml->createElement('item', htmlspecialchars((string)$elem, ENT_XML1 | ENT_QUOTES, 'UTF-8')));
                    }
                    $propertyNode->appendChild($subNode);
                    continue;
                }

                $value = $cleanup($k, $v);
                if ($value !== null) {
                    $propertyNode->appendChild($xml->createElement($k, $value));
                }
            }

            // Add fallback title tag if required by OLX and only if TituloAnuncio was not provided
            if (!$hasTituloAnuncio && !$hasTitulo) {
                $fallbackTitle = trim((string)($p['Bairro'] ?? '') . ' ' . ($p['Cidade'] ?? ''));
                if ($fallbackTitle !== '') {
                    $propertyNode->appendChild($xml->createElement('Titulo', htmlspecialchars($fallbackTitle, ENT_XML1 | ENT_QUOTES, 'UTF-8')));
                }
            }

            $root->appendChild($propertyNode);
        }

        return str_replace('encoding="UTF-8"', 'encoding="utf-8"', $xml->saveXML());
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
