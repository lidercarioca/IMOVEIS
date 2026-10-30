<?php
namespace App\Cache;

/**
 * FileCache - Driver de cache baseado em arquivos
 *
 * Este driver é um fallback leve quando Redis não estiver disponível.
 * Ele armazena valores serializados em arquivos no diretório temporário.
 */
class FileCache implements CacheInterface
{
    private string $directory;

    public function __construct(string $directory = null)
    {
        $directory = $directory ?? sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'imoveis_cache';
        $this->directory = rtrim($directory, DIRECTORY_SEPARATOR);

        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0777, true);
        }
    }

    public function get(string $key)
    {
        $filePath = $this->getFilePath($key);
        if (!file_exists($filePath)) {
            return null;
        }

        $data = @file_get_contents($filePath);
        if ($data === false) {
            return null;
        }

        $payload = @unserialize($data);
        if (!is_array($payload) || !isset($payload['expires'], $payload['value'])) {
            return null;
        }

        if ($payload['expires'] !== 0 && $payload['expires'] < time()) {
            $this->forget($key);
            return null;
        }

        return $payload['value'];
    }

    public function set(string $key, $value, int $ttl = 3600): void
    {
        $filePath = $this->getFilePath($key);
        $payload = [
            'expires' => $ttl > 0 ? time() + $ttl : 0,
            'value' => $value,
        ];

        $data = serialize($payload);
        @file_put_contents($filePath, $data, LOCK_EX);
    }

    public function forget(string $key): void
    {
        $filePath = $this->getFilePath($key);
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }

    public function has(string $key): bool
    {
        $filePath = $this->getFilePath($key);
        if (!file_exists($filePath)) {
            return false;
        }

        $data = @file_get_contents($filePath);
        if ($data === false) {
            return false;
        }

        $payload = @unserialize($data);
        if (!is_array($payload) || !isset($payload['expires'])) {
            return false;
        }

        if ($payload['expires'] !== 0 && $payload['expires'] < time()) {
            $this->forget($key);
            return false;
        }

        return true;
    }

    private function getFilePath(string $key): string
    {
        $hash = sha1($key);
        return $this->directory . DIRECTORY_SEPARATOR . $hash . '.cache';
    }
}
