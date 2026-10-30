<?php

use PHPUnit\Framework\TestCase;

class UserAuditServiceTest extends TestCase
{
    protected function setUp(): void
    {
        \App\Auth\AuthContext::reset();
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';
    }

    protected function tearDown(): void
    {
        \App\Auth\AuthContext::reset();
        unset($_SESSION['user_id'], $_SESSION['tenant_id'], $_SESSION['role']);
    }

    public function testLogUserChangeMapsLegacyFieldsAndActorContext(): void
    {
        $repo = $this->createMock(\App\Infrastructure\User\UserAuditRepository::class);
        $repo->expects($this->once())
            ->method('recordUserAudit')
            ->with(
                9,
                7,
                'update',
                'user',
                42,
                'Perfil atualizado',
                $this->callback(static function (array $oldValues): bool {
                    return isset($oldValues['name']) && $oldValues['name'] === 'Old Name';
                }),
                $this->callback(static function (array $newValues): bool {
                    return isset($newValues['name']) && $newValues['name'] === 'New Name';
                }),
                '127.0.0.1',
                'PHPUnit',
                'success'
            )
            ->willReturn(true);

        $context = \App\Auth\AuthContext::getInstance();
        $context->authenticate(9, 'admin');

        $service = new \App\Application\User\UserAuditService($repo);

        $this->assertTrue($service->logUserChange(
            42,
            ['name' => 'Old Name'],
            ['name' => 'New Name'],
            'update',
            9,
            7,
            'Perfil atualizado',
            '127.0.0.1',
            'PHPUnit'
        ));
    }

    public function testLogUserChangeStripsSensitiveFieldsBeforePersisting(): void
    {
        $repo = $this->createMock(\App\Infrastructure\User\UserAuditRepository::class);
        $repo->expects($this->once())
            ->method('recordUserAudit')
            ->with(
                9,
                3,
                'update',
                'user',
                11,
                'Senha alterada',
                $this->callback(static function (array $oldValues): bool {
                    return !array_key_exists('password', $oldValues)
                        && !array_key_exists('password_hash', $oldValues)
                        && $oldValues['email'] === 'old@example.com';
                }),
                $this->callback(static function (array $newValues): bool {
                    return !array_key_exists('password', $newValues)
                        && !array_key_exists('password_hash', $newValues)
                        && $newValues['email'] === 'new@example.com';
                }),
                '127.0.0.1',
                'PHPUnit',
                'success'
            )
            ->willReturn(true);

        $context = \App\Auth\AuthContext::getInstance();
        $context->authenticate(9, 'admin');

        $service = new \App\Application\User\UserAuditService($repo);

        $this->assertTrue($service->logUserChange(
            11,
            [
                'email' => 'old@example.com',
                'password' => 'old-secret',
                'password_hash' => 'hash-old',
            ],
            [
                'email' => 'new@example.com',
                'password' => 'new-secret',
                'password_hash' => 'hash-new',
            ],
            'update',
            9,
            3,
            'Senha alterada',
            '127.0.0.1',
            'PHPUnit'
        ));
    }

    public function testLogUserChangeReturnsFalseWhenActorContextIsMissing(): void
    {
        $repo = $this->createMock(\App\Infrastructure\User\UserAuditRepository::class);
        $repo->expects($this->never())->method('recordUserAudit');

        $service = new \App\Application\User\UserAuditService($repo);

        $this->assertFalse($service->logUserChange(
            42,
            ['name' => 'Old Name'],
            ['name' => 'New Name'],
            'update'
        ));
    }

    public function testLogUserChangeSkipsInsertWhenRecentDuplicateExists(): void
    {
        $repo = $this->createMock(\App\Infrastructure\User\UserAuditRepository::class);
        $repo->expects($this->once())
            ->method('hasRecentDuplicate')
            ->willReturn(true);
        $repo->expects($this->never())
            ->method('recordUserAudit');

        $context = \App\Auth\AuthContext::getInstance();
        $context->authenticate(9, 'admin');

        $service = new \App\Application\User\UserAuditService($repo);

        $this->assertTrue($service->logUserChange(
            42,
            ['name' => 'Old Name'],
            ['name' => 'New Name'],
            'update',
            9,
            7,
            'Perfil atualizado',
            '127.0.0.1',
            'PHPUnit'
        ));
    }

    public function testRepositoryPersistAuditRowInSqliteSchema(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            tenant_id INTEGER,
            action_type TEXT,
            resource_type TEXT,
            resource_id INTEGER,
            description TEXT,
            old_values TEXT,
            new_values TEXT,
            ip_address TEXT,
            user_agent TEXT,
            status TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )');

        $repo = new \App\Infrastructure\User\UserAuditRepository($pdo);

        $result = $repo->recordUserAudit(
            21,
            12,
            'update',
            'user',
            99,
            'Edição do usuário',
            ['name' => 'Old'],
            ['name' => 'New'],
            '127.0.0.1',
            'PHPUnit',
            'success'
        );

        $this->assertTrue($result);

        $row = $pdo->query('SELECT user_id, tenant_id, action_type, resource_type, resource_id, description, old_values, new_values, ip_address, user_agent, status FROM audit_logs LIMIT 1')->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame(21, (int) $row['user_id']);
        $this->assertSame(12, (int) $row['tenant_id']);
        $this->assertSame('update', $row['action_type']);
        $this->assertSame('user', $row['resource_type']);
        $this->assertSame(99, (int) $row['resource_id']);
        $this->assertSame('Edição do usuário', $row['description']);
        $this->assertSame('{"name":"Old"}', $row['old_values']);
        $this->assertSame('{"name":"New"}', $row['new_values']);
        $this->assertSame('127.0.0.1', $row['ip_address']);
        $this->assertSame('PHPUnit', $row['user_agent']);
        $this->assertSame('success', $row['status']);
    }

    public function testRepositoryDeduplicatesOnlyWhenExplicitIdempotencyKeyMatches(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            tenant_id INTEGER,
            action_type TEXT,
            resource_type TEXT,
            resource_id INTEGER,
            description TEXT,
            old_values TEXT,
            new_values TEXT,
            ip_address TEXT,
            user_agent TEXT,
            status TEXT,
            idempotency_key TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )');

        $repo = new \App\Infrastructure\User\UserAuditRepository($pdo);

        $this->assertFalse($repo->hasRecentDuplicate(7, 9, 'update', 42, ['name' => 'Old Name'], ['name' => 'New Name'], 'user-42-update-1'));

        $pdo->exec("INSERT INTO audit_logs (user_id, tenant_id, action_type, resource_type, resource_id, description, old_values, new_values, ip_address, user_agent, status, idempotency_key, created_at)
            VALUES (9, 7, 'update', 'user', 42, 'Perfil atualizado', '{\"name\":\"Old Name\"}', '{\"name\":\"New Name\"}', '127.0.0.1', 'PHPUnit', 'success', 'user-42-update-1', datetime('now'))");

        $this->assertTrue($repo->hasRecentDuplicate(7, 9, 'update', 42, ['name' => 'Old Name'], ['name' => 'New Name'], 'user-42-update-1'));
        $this->assertFalse($repo->hasRecentDuplicate(7, 9, 'update', 42, ['name' => 'Old Name'], ['name' => 'New Name'], 'user-42-update-2'));
    }
}
