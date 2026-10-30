<?php

namespace Tests\Unit;

require_once dirname(__DIR__, 2) . '/app/security/TenantContext.php';

use App\Auth\AuthContext;
use App\Infrastructure\User\UserRepository;
use App\Tenant\TenantResolver;
use PHPUnit\Framework\TestCase;

class TenantBoundaryContractTest extends TestCase
{
    private \PDO $pdo;
    private UserRepository $repository;

    private function ensureTestSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (PHP_SAPI === 'cli' || headers_sent()) {
            $_SESSION = [];
            return;
        }

        session_start();
    }

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }

        $_SESSION = [];
        $this->ensureTestSession();

        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INTEGER NOT NULL, username TEXT, role TEXT, is_superadmin INTEGER DEFAULT 0, name TEXT, email TEXT, phone TEXT, created_at TEXT, last_login TEXT, active INTEGER DEFAULT 1, force_password_change INTEGER DEFAULT 0, password_changed_at TEXT)');
        $this->pdo->exec("INSERT INTO users (id, tenant_id, username, role, is_superadmin, name, email, phone, created_at, active) VALUES (1, 10, 'userA', 'user', 0, 'User A', 'usera@example.com', NULL, '2026-01-01 00:00:00', 1)");
        $this->pdo->exec("INSERT INTO users (id, tenant_id, username, role, is_superadmin, name, email, phone, created_at, active) VALUES (2, 20, 'userB', 'user', 0, 'User B', 'userb@example.com', NULL, '2026-01-01 00:00:00', 1)");
        $this->pdo->exec("INSERT INTO users (id, tenant_id, username, role, is_superadmin, name, email, phone, created_at, active) VALUES (99, 10, 'superadmin', 'admin', 1, 'Super Admin', 'super@example.com', NULL, '2026-01-01 00:00:00', 1)");

        $GLOBALS['pdo'] = $this->pdo;
        $this->repository = new UserRepository($this->pdo);
        AuthContext::reset();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }

        AuthContext::reset();
    }

    public function testUserBelongsToTenantAllowedWhenBound(): void
    {
        $this->assertTrue($this->repository->userBelongsToTenant(1, 10));
    }

    public function testUserBelongsToTenantDeniedWhenMismatch(): void
    {
        $this->assertFalse($this->repository->userBelongsToTenant(1, 20));
    }

    public function testAuthenticatedUserKeepsOwnTenantWhenRequestSaysDifferentTenant(): void
    {
        AuthContext::getInstance()->authenticate(1, 'user', 'token-1');

        $resolver = new TenantResolver(AuthContext::getInstance(), $this->repository);
        $resolution = $resolver->resolve([
            'session' => [],
            'query' => ['tenant_id' => 20],
            'server' => [],
            'headers' => [],
        ]);

        $this->assertSame(10, $resolution->getTenantId());
        $this->assertNotSame(20, $resolution->getTenantId());
    }

    public function testAuthenticatedUserKeepsOwnTenantWhenHeaderSaysDifferentTenant(): void
    {
        AuthContext::getInstance()->authenticate(1, 'user', 'token-1');

        $resolver = new TenantResolver(AuthContext::getInstance(), $this->repository);
        $resolution = $resolver->resolve([
            'session' => [],
            'query' => [],
            'server' => [],
            'headers' => ['x-tenant-id' => '20'],
        ]);

        $this->assertSame(10, $resolution->getTenantId());
    }

    public function testAuthenticatedUserWithoutValidTenantIsNotGivenTenantOne(): void
    {
        AuthContext::getInstance()->authenticate(999, 'user', 'token-invalid');

        $resolver = new TenantResolver(AuthContext::getInstance(), $this->repository);
        $resolution = $resolver->resolve([
            'session' => [],
            'query' => [],
            'server' => [],
            'headers' => [],
        ]);

        $this->assertNull($resolution->getTenantId());
        $this->assertSame('tenant_validation_failed', $resolution->getResolvedBy());
        $this->assertFalse($resolution->isFallback());
    }

    public function testSuperadminSelectedTenantIsAllowed(): void
    {
        AuthContext::getInstance()->authenticate(99, 'admin', 'token-super');

        $resolver = new TenantResolver(AuthContext::getInstance(), $this->repository);
        $resolution = $resolver->resolve([
            'session' => ['selected_tenant_id' => 20],
            'query' => [],
            'server' => [],
            'headers' => [],
        ]);

        $this->assertSame(20, $resolution->getTenantId());
        $this->assertTrue($resolution->isSuperAdmin());
    }

    public function testExplicitTenantSelectionAllowedWhenUserBelongsToTenant(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'user';
        AuthContext::getInstance()->authenticate(1, 'user', 'token-1');

        $this->assertTrue(\TenantContext::selectTenant(10));
        $this->assertSame(10, $_SESSION['tenant_id']);
        $this->assertSame(10, $_SESSION['selected_tenant_id']);
    }

    public function testExplicitTenantSelectionRejectedWhenUserDoesNotBelongToTenant(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'user';
        $_SESSION['tenant_id'] = 10;
        $_SESSION['selected_tenant_id'] = 10;
        AuthContext::getInstance()->authenticate(1, 'user', 'token-1');

        $this->assertFalse(\TenantContext::selectTenant(20));
        $this->assertSame(10, $_SESSION['tenant_id']);
        $this->assertSame(10, $_SESSION['selected_tenant_id']);
    }

    public function testExplicitTenantSelectionRejectedForUserWithoutMembership(): void
    {
        $_SESSION['user_id'] = 999;
        $_SESSION['role'] = 'user';
        $_SESSION['tenant_id'] = 10;
        $_SESSION['selected_tenant_id'] = 10;
        AuthContext::getInstance()->authenticate(999, 'user', 'token-invalid');

        $this->assertFalse(\TenantContext::selectTenant(20));
        $this->assertSame(10, $_SESSION['tenant_id']);
        $this->assertSame(10, $_SESSION['selected_tenant_id']);
    }

    public function testExplicitTenantSelectionRejectsInvalidTenantAndUserIds(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'user';
        AuthContext::getInstance()->authenticate(1, 'user', 'token-1');

        $this->assertFalse(\TenantContext::selectTenant(0));
        $this->assertFalse(\TenantContext::selectTenant(-5));

        $_SESSION['user_id'] = 0;
        $this->assertFalse(\TenantContext::selectTenant(10));
    }

    public function testMissingUsersTableUsesSameUserSessionTenant(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $GLOBALS['pdo'] = $pdo;

        AuthContext::getInstance()->authenticate(42, 'user', 'token-session');

        $resolver = new TenantResolver(AuthContext::getInstance());
        $resolution = $resolver->resolve([
            'session' => ['user_id' => 42, 'tenant_id' => 7],
            'query' => [],
            'server' => [],
            'headers' => [],
        ]);

        $this->assertSame(7, $resolution->getTenantId());
        $this->assertSame(TenantResolver::SOURCE_SESSION, $resolution->getResolvedBy());
    }

    public function testMissingUsersTableDoesNotTrustForeignSessionTenant(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $GLOBALS['pdo'] = $pdo;

        AuthContext::getInstance()->authenticate(42, 'user', 'token-session');

        $resolver = new TenantResolver(AuthContext::getInstance());
        $resolution = $resolver->resolve([
            'session' => ['user_id' => 99, 'tenant_id' => 7],
            'query' => [],
            'server' => [],
            'headers' => [],
        ]);

        $this->assertNull($resolution->getTenantId());
        $this->assertSame('tenant_validation_failed', $resolution->getResolvedBy());
        $this->assertFalse($resolution->isFallback());
    }
}
