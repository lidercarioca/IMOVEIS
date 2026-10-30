<?php

namespace Tests\Unit;

use App\Auth\RequestAuthenticationResult;
use PHPUnit\Framework\TestCase;

class RequestAuthenticationResultTest extends TestCase
{
    public function testAuthenticatedJwtResultIsValid(): void
    {
        $result = new RequestAuthenticationResult(
            RequestAuthenticationResult::STATUS_JWT_AUTHENTICATED,
            RequestAuthenticationResult::SOURCE_JWT,
            42,
            'admin',
            ['create_property', 'view_property'],
            true,
            null,
            'request-1'
        );

        $this->assertTrue($result->isAuthenticated());
        $this->assertSame(RequestAuthenticationResult::SOURCE_JWT, $result->getSource());
        $this->assertSame(42, $result->getUserId());
        $this->assertSame('admin', $result->getRole());
        $this->assertTrue($result->isSuperAdmin());
        $this->assertSame('request-1', $result->getRequestId());
    }

    public function testAuthenticatedSessionResultIsValid(): void
    {
        $result = new RequestAuthenticationResult(
            RequestAuthenticationResult::STATUS_SESSION_AUTHENTICATED,
            RequestAuthenticationResult::SOURCE_SESSION,
            7,
            'user',
            ['view_property']
        );

        $this->assertTrue($result->isAuthenticated());
        $this->assertSame(RequestAuthenticationResult::SOURCE_SESSION, $result->getSource());
        $this->assertSame(7, $result->getUserId());
    }

    public function testNoCredentialResultIsValid(): void
    {
        $result = new RequestAuthenticationResult(
            RequestAuthenticationResult::STATUS_NO_CREDENTIAL,
            RequestAuthenticationResult::SOURCE_NONE
        );

        $this->assertFalse($result->isAuthenticated());
        $this->assertNull($result->getUserId());
        $this->assertSame(RequestAuthenticationResult::SOURCE_NONE, $result->getSource());
    }

    public function testAuthenticatedWithoutUserIdIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new RequestAuthenticationResult(
            RequestAuthenticationResult::STATUS_JWT_AUTHENTICATED,
            RequestAuthenticationResult::SOURCE_JWT,
            null,
            'user'
        );
    }

    public function testJwtInvalidIsNotAuthenticated(): void
    {
        $result = new RequestAuthenticationResult(
            RequestAuthenticationResult::STATUS_JWT_INVALID,
            RequestAuthenticationResult::SOURCE_JWT,
            null,
            null,
            [],
            false,
            'JWT_INVALID'
        );

        $this->assertFalse($result->isAuthenticated());
        $this->assertNull($result->getUserId());
        $this->assertSame(RequestAuthenticationResult::STATUS_JWT_INVALID, $result->getStatus());
    }

    public function testJwtExpiredIsNotAuthenticated(): void
    {
        $result = new RequestAuthenticationResult(
            RequestAuthenticationResult::STATUS_JWT_EXPIRED,
            RequestAuthenticationResult::SOURCE_JWT,
            null,
            null,
            [],
            false,
            'JWT_EXPIRED'
        );

        $this->assertFalse($result->isAuthenticated());
        $this->assertNull($result->getUserId());
    }

    public function testJwtRevokedIsNotAuthenticated(): void
    {
        $result = new RequestAuthenticationResult(
            RequestAuthenticationResult::STATUS_JWT_REVOKED,
            RequestAuthenticationResult::SOURCE_JWT,
            null,
            null,
            [],
            false,
            'JWT_REVOKED'
        );

        $this->assertFalse($result->isAuthenticated());
        $this->assertNull($result->getUserId());
    }

    public function testMalformedCredentialIsNotAuthenticated(): void
    {
        $result = new RequestAuthenticationResult(
            RequestAuthenticationResult::STATUS_MALFORMED_CREDENTIAL,
            RequestAuthenticationResult::SOURCE_JWT,
            null,
            null,
            [],
            false,
            'MALFORMED_CREDENTIAL'
        );

        $this->assertFalse($result->isAuthenticated());
        $this->assertNull($result->getUserId());
    }

    public function testObjectIsImmutable(): void
    {
        $result = new RequestAuthenticationResult(
            RequestAuthenticationResult::STATUS_NO_CREDENTIAL,
            RequestAuthenticationResult::SOURCE_NONE
        );

        $this->assertSame(RequestAuthenticationResult::STATUS_NO_CREDENTIAL, $result->getStatus());
        $this->assertSame(RequestAuthenticationResult::SOURCE_NONE, $result->getSource());

        $reflection = new \ReflectionClass($result);
        $properties = $reflection->getProperties();

        foreach ($properties as $property) {
            $this->assertTrue($property->isPrivate());
        }
    }
}
