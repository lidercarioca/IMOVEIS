<?php

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/app/integrations/PortalIntegrationStore.php';

class PortalIntegrationLegacyStoreContractTest extends TestCase
{
    public function testPortalIntegrationLegacyStoreUsesRepositoryContractAndNoDirectSql(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE portal_integrations (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, portal_slug TEXT NOT NULL, enabled INTEGER NOT NULL DEFAULT 0, endpoint TEXT NULL, credentials TEXT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE(tenant_id, portal_slug));');

        $this->assertTrue(savePortalIntegration($pdo, 42, 'facebook_pixel', true, 'https://example.test/pixel', ['pixel_id' => 'abc123']));

        $integration = getPortalIntegration($pdo, 42, 'facebook_pixel');
        $this->assertNotNull($integration);
        $this->assertSame('facebook_pixel', $integration['portal_slug']);
        $this->assertTrue((bool) $integration['enabled']);
        $this->assertSame('abc123', $integration['credentials']['pixel_id'] ?? null);

        $source = file_get_contents(dirname(__DIR__, 2) . '/app/integrations/PortalIntegrationStore.php');
        $this->assertStringNotContainsString('SELECT portal_slug, enabled, endpoint, credentials FROM portal_integrations', $source);
        $this->assertStringNotContainsString('UPDATE portal_integrations SET', $source);
        $this->assertStringNotContainsString('INSERT INTO portal_integrations', $source);
    }
}
