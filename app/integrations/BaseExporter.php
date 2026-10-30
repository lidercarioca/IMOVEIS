<?php
abstract class BaseExporter {
    protected $config = [];

    public function __construct(array $config = []) {
        $this->config = $config;
    }

    /**
     * Export properties to a format (xml|json)
     * @param array $properties
     * @param string $format
     * @return string
     */
    abstract public function export(array $properties, string $format = 'xml') : string;

    /**
     * Optional: push the feed to remote endpoint (portal)
     * @param string $payload
     * @param array $options
     * @return array ['success' => bool, 'response' => mixed, 'status' => int|null]
     */
    public function push(string $payload, array $options = []) : array {
        $endpoint = $options['endpoint'] ?? ($this->config['endpoint'] ?? '');
        if (empty($endpoint)) {
            return ['success' => false, 'response' => 'no_endpoint_configured', 'status' => null];
        }

        $contentType = $options['content_type'] ?? ($options['format'] === 'json' ? 'application/json' : 'application/xml');
        $headers = ['Content-Type: ' . $contentType];

        $apiKey = $options['api_key'] ?? $this->config['credentials']['api_key'] ?? $this->config['credentials']['token'] ?? null;
        $username = $options['username'] ?? $this->config['credentials']['username'] ?? null;
        $password = $options['password'] ?? $this->config['credentials']['password'] ?? null;

        if (!empty($username)) {
            $headers[] = 'Authorization: Basic ' . base64_encode($username . ':' . ($password ?? ''));
        } elseif (!empty($apiKey)) {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
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
