<?php

namespace Tests\Unit;

use App\Auth\AuthenticatedIdentityAdapter;
use App\Auth\AuthContext;
use App\Auth\Permissions;
use App\Auth\RequestAuthenticationResult;
use App\Auth\Roles;
use PHPUnit\Framework\TestCase;

final class AuthenticatedIdentityAdapterTest extends TestCase
{
    protected function setUp(): void
    {
        AuthContext::reset();
    }

    public function test_jwt_authenticated_materializes_identity_and_authcontext(): void
    {
        $r = new RequestAuthenticationResult(
            RequestAuthenticationResult::STATUS_JWT_AUTHENTICATED,
            RequestAuthenticationResult::SOURCE_JWT,
            123,
            Roles::ADMIN,
            [Permissions::CREATE_PROPERTY, Permissions::VIEW_DASHBOARD],
            true
        );

        $identity = AuthenticatedIdentityAdapter::adapt($r);

        $this->assertNotNull($identity);
        $this->assertSame(123, $identity->getUserId());
        $this->assertSame(RequestAuthenticationResult::SOURCE_JWT, $identity->getSource());
        $this->assertSame(Roles::ADMIN, $identity->getRole());
        $this->assertSame([Permissions::CREATE_PROPERTY, Permissions::VIEW_DASHBOARD], $identity->getPermissions());
        $this->assertTrue($identity->isSuperAdmin());

        $ctx = AuthContext::getInstance();
        $this->assertTrue($ctx->isAuthenticated());
        $this->assertSame(123, $ctx->getUserId());
        $this->assertSame(Roles::ADMIN, $ctx->getRole());
    }

    public function test_session_authenticated_materializes_identity_and_authcontext(): void
    {
        $r = new RequestAuthenticationResult(
            RequestAuthenticationResult::STATUS_SESSION_AUTHENTICATED,
            RequestAuthenticationResult::SOURCE_SESSION,
            222,
            Roles::MANAGER,
            [Permissions::MANAGE_USERS],
            false
        );

        $identity = AuthenticatedIdentityAdapter::adapt($r);

        $this->assertNotNull($identity);
        $this->assertSame(222, $identity->getUserId());
        $this->assertSame(RequestAuthenticationResult::SOURCE_SESSION, $identity->getSource());
        $this->assertSame(Roles::MANAGER, $identity->getRole());
        $this->assertSame([Permissions::MANAGE_USERS], $identity->getPermissions());
        $this->assertFalse($identity->isSuperAdmin());

        $ctx = AuthContext::getInstance();
        $this->assertTrue($ctx->isAuthenticated());
        $this->assertSame(222, $ctx->getUserId());
        $this->assertSame(Roles::MANAGER, $ctx->getRole());
    }

    public function provide_non_authenticated_statuses(): array
    {
        return [
            [RequestAuthenticationResult::STATUS_NO_CREDENTIAL, RequestAuthenticationResult::SOURCE_NONE],
            [RequestAuthenticationResult::STATUS_JWT_INVALID, RequestAuthenticationResult::SOURCE_JWT],
            [RequestAuthenticationResult::STATUS_JWT_EXPIRED, RequestAuthenticationResult::SOURCE_JWT],
            [RequestAuthenticationResult::STATUS_JWT_REVOKED, RequestAuthenticationResult::SOURCE_JWT],
            [RequestAuthenticationResult::STATUS_MALFORMED_CREDENTIAL, RequestAuthenticationResult::SOURCE_JWT],
        ];
    }

    /**
     * @dataProvider provide_non_authenticated_statuses
     */
    public function test_non_authenticated_results_do_not_materialize_authcontext(string $status, string $source): void
    {
        $r = new RequestAuthenticationResult($status, $source);

        $identity = AuthenticatedIdentityAdapter::adapt($r);

        $this->assertNull($identity);

        $ctx = AuthContext::getInstance();
        $this->assertFalse($ctx->isAuthenticated());
        $this->assertNull($ctx->getUserId());
    }
}
