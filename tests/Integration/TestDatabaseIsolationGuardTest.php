<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/config/test_database.php';

class TestDatabaseIsolationGuardTest extends TestCase
{
    public function testMissingTestDatabaseConfigurationIsRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('MySQL integration config is incomplete');

        putenv('TEST_DB_HOST');
        putenv('TEST_DB_PORT');
        putenv('TEST_DB_USER');
        putenv('TEST_DB_PASS');
        putenv('TEST_DB_NAME');
        putenv('TEST_DB_ISOLATED');

        getTestDatabaseConfig();
    }

    public function testMissingIsolationFlagIsRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('TEST_DB_ISOLATED=true');

        putenv('TEST_DB_HOST=localhost');
        putenv('TEST_DB_PORT=3306');
        putenv('TEST_DB_USER=testuser');
        putenv('TEST_DB_PASS=testpass');
        putenv('TEST_DB_NAME=test_schema');
        putenv('TEST_DB_ISOLATED=false');

        getTestDatabaseConfig();
    }

    public function testSameDatabaseNameAsApplicationIsRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('application database name');

        putenv('DB_HOST=localhost');
        putenv('DB_NAME=app_schema_prod');
        putenv('TEST_DB_HOST=127.0.0.1');
        putenv('TEST_DB_PORT=3306');
        putenv('TEST_DB_USER=testuser');
        putenv('TEST_DB_PASS=testpass');
        putenv('TEST_DB_NAME=app_schema_prod');
        putenv('TEST_DB_ISOLATED=true');

        assertTestDatabaseIsIsolated();
    }

    public function testSameHostWithDifferentSchemaStillRequiresDedicatedIsolation(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('same host with a different schema is not sufficient evidence of isolation');

        putenv('DB_HOST=localhost');
        putenv('DB_NAME=sys_imoveis');
        putenv('TEST_DB_HOST=localhost');
        putenv('TEST_DB_PORT=3306');
        putenv('TEST_DB_USER=testuser');
        putenv('TEST_DB_PASS=testpass');
        putenv('TEST_DB_NAME=test_schema');
        putenv('TEST_DB_ISOLATED=true');

        assertTestDatabaseIsIsolated();
    }

    public function testExplicitlyIsolatedAndStructurallyValidConfigPasses(): void
    {
        putenv('DB_HOST=localhost');
        putenv('DB_NAME=sys_imoveis');
        putenv('TEST_DB_HOST=127.0.0.1');
        putenv('TEST_DB_PORT=3306');
        putenv('TEST_DB_USER=testuser');
        putenv('TEST_DB_PASS=testpass');
        putenv('TEST_DB_NAME=qa_sys_imoveis_test');
        putenv('TEST_DB_ISOLATED=true');

        $this->assertTrue(true);
        $config = getTestDatabaseConfig();
        $this->assertSame('127.0.0.1', $config['host']);
        $this->assertSame('qa_sys_imoveis_test', $config['name']);
        $this->assertSame('true', $config['isolated']);

        assertTestDatabaseIsIsolated();
    }
}
