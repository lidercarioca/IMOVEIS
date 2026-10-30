<?php
require_once __DIR__ . '/BaseExporter.php';

class MetaAnunciosExporter extends BaseExporter {
    public function export(array $properties, string $format = 'json') : string {
        $data = [
            'ad_account_id' => $this->config['credentials']['ad_account_id'] ?? null,
            'properties' => $properties,
        ];

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function push(string $payload, array $options = []) : array {
        $endpoint = $options['endpoint'] ?? ($this->config['endpoint'] ?? '');
        if (empty($endpoint)) {
            return ['success' => false, 'response' => 'no_endpoint_configured', 'status' => null];
        }

        $accessToken = $options['access_token'] ?? $this->config['credentials']['access_token'] ?? null;
        $adAccountId = $options['ad_account_id'] ?? $this->config['credentials']['ad_account_id'] ?? null;

        $headers = ['Content-Type: application/json'];
        if (!empty($accessToken)) {
            $headers[] = 'Authorization: Bearer ' . $accessToken;
        }
        if (!empty($adAccountId)) {
            $headers[] = 'X-Ad-Account-Id: ' . $adAccountId;
        }

        $decoded = json_decode($payload, true);
        if (is_array($decoded)) {
            if (!empty($adAccountId)) {
                $decoded['ad_account_id'] = $adAccountId;
            }
            $payload = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp === false) {
            return ['success' => false, 'response' => $err, 'status' => $code ?: null];
        }

        return ['success' => ($code >= 200 && $code < 300), 'response' => $resp, 'status' => $code];
    }
}
