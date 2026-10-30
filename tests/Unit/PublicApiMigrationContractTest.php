<?php

namespace Tests\Unit;

use App\Repository\ApiKeyRepository;
use App\Service\ApiKeyService;
use PDO;
use PHPUnit\Framework\TestCase;

class PublicApiMigrationContractTest extends TestCase
{
    public function testPublicApiKeyValidationMigratedToServiceAndRepository(): void
    {
        $this->assertTrue(class_exists(ApiKeyService::class));
        $this->assertTrue(class_exists(ApiKeyRepository::class));
        $this->assertTrue(method_exists(ApiKeyService::class, 'validateKey'));
        $this->assertTrue(method_exists(ApiKeyRepository::class, 'findActiveByKey'));
        $this->assertTrue(method_exists(\App\Service\WebhookService::class, 'getActiveUrlsForEvent'));
    }

    public function testPublicApiKeyManagementUsesServiceAndRepository(): void
    {
        $this->assertTrue(method_exists(ApiKeyService::class, 'listKeysForUser'));
        $this->assertTrue(method_exists(ApiKeyService::class, 'createKeyForTenant'));
        $this->assertTrue(method_exists(ApiKeyService::class, 'revokeKeyForUser'));
        $this->assertTrue(method_exists(ApiKeyRepository::class, 'findByTenantAndUser'));
        $this->assertTrue(method_exists(ApiKeyRepository::class, 'createForTenant'));
        $this->assertTrue(method_exists(ApiKeyRepository::class, 'deleteByIdAndTenantAndUser'));
    }

    public function testPublicEndpointsUseServiceInsteadOfLegacyInlineSql(): void
    {
        $propertiesEndpoint = file_get_contents(__DIR__ . '/../../api/v1/public/properties.php');
        $portalFeedEndpoint = file_get_contents(__DIR__ . '/../../api/v1/public/portalFeed.php');
        $getKeysEndpoint = file_get_contents(__DIR__ . '/../../api/v1/public/getAPIKeys.php');
        $createKeyEndpoint = file_get_contents(__DIR__ . '/../../api/v1/public/createAPIKey.php');
        $revokeKeyEndpoint = file_get_contents(__DIR__ . '/../../api/v1/public/revokeAPIKey.php');

        $this->assertStringContainsString('new \\App\\Service\\ApiKeyService', $propertiesEndpoint);
        $this->assertStringContainsString('new \\App\\Service\\ApiKeyService', $portalFeedEndpoint);
        $this->assertStringContainsString('new \\App\\Service\\ApiKeyService', $getKeysEndpoint);
        $this->assertStringContainsString('new \\App\\Service\\ApiKeyService', $createKeyEndpoint);
        $this->assertStringContainsString('new \\App\\Service\\ApiKeyService', $revokeKeyEndpoint);
        $this->assertStringContainsString('new \\App\\Service\\WebhookService', $propertiesEndpoint);

        $this->assertStringNotContainsString('SELECT id, user_id, name', $getKeysEndpoint);
        $this->assertStringNotContainsString('INSERT INTO api_keys', $createKeyEndpoint);
        $this->assertStringNotContainsString('DELETE FROM api_keys', $revokeKeyEndpoint);
        $this->assertStringNotContainsString('SELECT url FROM webhooks WHERE FIND_IN_SET', strtolower($propertiesEndpoint));
    }

    public function testPublicPropertyUpdateUsesServicePatchContract(): void
    {
        $source = file_get_contents(__DIR__ . '/../../api/v1/public/properties.php');

        $this->assertStringContainsString('updatePropertyPatch', $source);
        $this->assertStringNotContainsString('UPDATE properties SET', $source);
        $this->assertStringNotContainsString('implode(", ", $updates)', $source);
        $this->assertStringNotContainsString('`$field` = ?', $source);
    }

    public function testApiKeyServiceRejectsExpiredOrInactiveKeysWithoutFallback(): void
    {
        $repository = $this->getMockBuilder(ApiKeyRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findActiveByKey'])
            ->getMock();

        $repository->expects($this->once())
            ->method('findActiveByKey')
            ->with('expired-key')
            ->willReturn(null);

        $service = new ApiKeyService($this->createMock(PDO::class), $repository);

        $this->assertNull($service->validateKey('expired-key'));
    }
}
