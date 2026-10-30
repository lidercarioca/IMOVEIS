<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ApiKeyManagerMigrationContractTest extends TestCase
{
    public function testLegacyApiKeyManagerDelegatesToModernServiceLayer(): void
    {
        $source = file_get_contents(__DIR__ . '/../../app/services/ApiKeyManager.php');

        $this->assertIsString($source);
        $this->assertStringContainsString('App\\Service\\ApiKeyService', $source);
        $this->assertStringContainsString('App\\Repository\\ApiKeyRepository', $source);
        $this->assertStringNotContainsString('INSERT INTO api_keys', $source);
        $this->assertStringNotContainsString('SELECT id, user_id, name FROM api_keys', $source);
        $this->assertStringNotContainsString('SELECT id, name, created_at, expires_at, active FROM api_keys', $source);
    }
}
