<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Auth\AuthenticatedIdentity;
use App\Auth\RequestAuthenticationResult;
use App\Auth\RequestAuthenticationResult as RAR;

class AuthenticatedIdentityTest extends TestCase
{
    public function testCreatesFromJwtResult()
    {
        $r = new RequestAuthenticationResult(
            RAR::STATUS_JWT_AUTHENTICATED,
            RAR::SOURCE_JWT,
            42,
            'admin',
            ['perm1', 'perm2'],
            true
        );

        $id = AuthenticatedIdentity::fromRequestAuthenticationResult($r);

        $this->assertInstanceOf(AuthenticatedIdentity::class, $id);
        $this->assertSame(42, $id->getUserId());
        $this->assertSame(RAR::SOURCE_JWT, $id->getSource());
        $this->assertSame('admin', $id->getRole());
        $this->assertSame(['perm1', 'perm2'], $id->getPermissions());
        $this->assertTrue($id->isSuperAdmin());
    }

    public function testCreatesFromSessionResult()
    {
        $r = new RequestAuthenticationResult(
            RAR::STATUS_SESSION_AUTHENTICATED,
            RAR::SOURCE_SESSION,
            7,
            'user',
            ['read'],
            false
        );

        $id = AuthenticatedIdentity::fromRequestAuthenticationResult($r);

        $this->assertInstanceOf(AuthenticatedIdentity::class, $id);
        $this->assertSame(7, $id->getUserId());
        $this->assertSame(RAR::SOURCE_SESSION, $id->getSource());
    }

    public function testDoesNotCreateFromInvalidResults()
    {
        $invalidStatuses = [
            RAR::STATUS_NO_CREDENTIAL,
            RAR::STATUS_JWT_INVALID,
            RAR::STATUS_JWT_EXPIRED,
            RAR::STATUS_JWT_REVOKED,
            RAR::STATUS_MALFORMED_CREDENTIAL,
            RAR::STATUS_AUTHENTICATION_ERROR,
        ];

        foreach ($invalidStatuses as $status) {
            $r = new RequestAuthenticationResult($status, RAR::SOURCE_NONE, null, null, [], false);
            $id = AuthenticatedIdentity::fromRequestAuthenticationResult($r);
            $this->assertNull($id, "Expected null for status $status");
        }
    }

    public function testIsImmutablePublicView()
    {
        $r = new RequestAuthenticationResult(
            RAR::STATUS_JWT_AUTHENTICATED,
            RAR::SOURCE_JWT,
            99,
            'user',
            [],
            false
        );

        $id = AuthenticatedIdentity::fromRequestAuthenticationResult($r);

        // No public writable properties should exist
        $vars = get_object_vars($id);
        $this->assertEmpty($vars);
    }
}
