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
     * @return array ['success' => bool, 'response' => mixed]
     */
    public function push(string $payload, array $options = []) : array {
        // Default no-op implementation (override in subclass if portal supports push)
        return ['success' => false, 'response' => 'push_not_implemented'];
    }
}
