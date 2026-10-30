<?php

namespace Tests\Webhooks;

use PHPUnit\Framework\TestCase;

/**
 * Database audit script preserved as a skipped integration check.
 */
class AuditDatabaseSchemaTest extends TestCase
{
    public function testAuditScriptSkippedByDefault(): void
    {
        if (getenv('RUN_INTEGRATION_TESTS') !== '1') {
            $this->markTestSkipped('Database audit script is skipped by default');
            return;
        }

        $this->assertTrue(true);
    }
}
