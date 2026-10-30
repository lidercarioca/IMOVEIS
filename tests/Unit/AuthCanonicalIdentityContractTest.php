<?php

namespace Tests\Unit;

require_once __DIR__ . '/../../config/session_manager.php';

use App\Auth\AuthenticatedIdentity;
use App\Auth\AuthContext;
use App\Auth\AuthenticationPolicy;
use App\Auth\JwtService;
use App\Auth\RequestAuthenticationResult;
use App\Auth\Roles;
use App\Middleware\AuthMiddleware;
use PHPUnit\Framework\TestCase;

final class AuthCanonicalIdentityContractTest extends TestCase
{
    protected function setUp(): void
    {
        AuthContext::reset();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        AuthContext::reset();
        $_SESSION = [];
    }

    public function testAuthenticationPolicyRejectsJwtSessionIdentityConflict(): void
    {
        $jwtResult = new RequestAuthenticationResult(
            RequestAuthenticationResult::STATUS_JWT_AUTHENTICATED,
            RequestAuthenticationResult::SOURCE_JWT,
            101,
            'user',
            ['read'],
            false
        );

        $sessionResult = new RequestAuthenticationResult(
            RequestAuthenticationResult::STATUS_SESSION_AUTHENTICATED,
            RequestAuthenticationResult::SOURCE_SESSION,
            202,
            'admin',
            ['write'],
            true
        );

        $resolved = AuthenticationPolicy::decide($jwtResult, $sessionResult, true);

        $this->assertSame(RequestAuthenticationResult::STATUS_AUTHENTICATION_ERROR, $resolved->getStatus());
        $this->assertSame('jwt_session_identity_conflict', $resolved->getConflictState());
        $this->assertNull($resolved->getUserId());
        $this->assertFalse($resolved->isAuthenticated());
    }

    public function testAuthenticationPolicyRejectsInvalidJwtFallbackToSession(): void
    {
        $jwtResult = new RequestAuthenticationResult(
            RequestAuthenticationResult::STATUS_JWT_INVALID,
            RequestAuthenticationResult::SOURCE_JWT,
            null,
            null,
            [],
            false,
            'JWT_INVALID'
        );

        $sessionResult = new RequestAuthenticationResult(
            RequestAuthenticationResult::STATUS_SESSION_AUTHENTICATED,
            RequestAuthenticationResult::SOURCE_SESSION,
            42,
            'user',
            ['read'],
            false
        );

        $resolved = AuthenticationPolicy::decide($jwtResult, $sessionResult, true);

        $this->assertSame(RequestAuthenticationResult::STATUS_JWT_INVALID, $resolved->getStatus());
        $this->assertSame(RequestAuthenticationResult::SOURCE_JWT, $resolved->getSource());
        $this->assertFalse($resolved->isAuthenticated());
    }

    public function testAuthContextRejectsDifferentIdentityAfterMaterialization(): void
    {
        $identityA = AuthenticatedIdentity::fromRequestAuthenticationResult(
            new RequestAuthenticationResult(
                RequestAuthenticationResult::STATUS_JWT_AUTHENTICATED,
                RequestAuthenticationResult::SOURCE_JWT,
                11,
                'user',
                ['read'],
                false
            )
        );

        $identityB = AuthenticatedIdentity::fromRequestAuthenticationResult(
            new RequestAuthenticationResult(
                RequestAuthenticationResult::STATUS_SESSION_AUTHENTICATED,
                RequestAuthenticationResult::SOURCE_SESSION,
                22,
                'admin',
                ['write'],
                true
            )
        );

        $ctx = AuthContext::getInstance();
        $ctx->materializeIdentity($identityA);

        $this->expectException(\LogicException::class);
        $ctx->materializeIdentity($identityB);
    }

    public function testCanonicalRoleNormalizationForSuperadminMaterialization(): void
    {
        $identity = AuthenticatedIdentity::fromRequestAuthenticationResult(
            new RequestAuthenticationResult(
                RequestAuthenticationResult::STATUS_JWT_AUTHENTICATED,
                RequestAuthenticationResult::SOURCE_JWT,
                77,
                'superadmin',
                ['read', 'write'],
                true
            )
        );

        $ctx = AuthContext::getInstance();
        $ctx->materializeIdentity($identity);

        $this->assertTrue($ctx->isAuthenticated());
        $this->assertSame(77, $ctx->getUserId());
        $this->assertSame(Roles::ADMIN, $ctx->getRole());
        $this->assertTrue($ctx->isAdmin());
        $this->assertTrue($ctx->isSuperAdmin());
        $this->assertTrue($ctx->hasRole(Roles::ADMIN));
    }

    public function testCanonicalRoleNormalizationForLegacyAliasMaterialization(): void
    {
        $identity = AuthenticatedIdentity::fromRequestAuthenticationResult(
            new RequestAuthenticationResult(
                RequestAuthenticationResult::STATUS_SESSION_AUTHENTICATED,
                RequestAuthenticationResult::SOURCE_SESSION,
                88,
                'administrator',
                ['read'],
                false
            )
        );

        $ctx = AuthContext::getInstance();
        $ctx->materializeIdentity($identity);

        $this->assertTrue($ctx->isAdmin());
        $this->assertFalse($ctx->isSuperAdmin());
        $this->assertTrue($ctx->hasRole(Roles::ADMIN));
    }

    public function testCanonicalRoleNormalizationForCommonAdminMaterialization(): void
    {
        $identity = AuthenticatedIdentity::fromRequestAuthenticationResult(
            new RequestAuthenticationResult(
                RequestAuthenticationResult::STATUS_SESSION_AUTHENTICATED,
                RequestAuthenticationResult::SOURCE_SESSION,
                89,
                'admin',
                ['read', 'write'],
                false
            )
        );

        $ctx = AuthContext::getInstance();
        $ctx->materializeIdentity($identity);

        $this->assertTrue($ctx->isAdmin());
        $this->assertFalse($ctx->isSuperAdmin());
        $this->assertTrue($ctx->hasRole(Roles::ADMIN));
    }

    public function testCanonicalRoleNormalizationForUserMaterialization(): void
    {
        $identity = AuthenticatedIdentity::fromRequestAuthenticationResult(
            new RequestAuthenticationResult(
                RequestAuthenticationResult::STATUS_SESSION_AUTHENTICATED,
                RequestAuthenticationResult::SOURCE_SESSION,
                99,
                'user',
                ['read'],
                false
            )
        );

        $ctx = AuthContext::getInstance();
        $ctx->materializeIdentity($identity);

        $this->assertFalse($ctx->isAdmin());
        $this->assertFalse($ctx->hasRole(Roles::ADMIN));
        $this->assertSame(Roles::USER, $ctx->getRole());
        $this->assertFalse($ctx->isSuperAdmin());
    }

    public function testCanonicalRoleNormalizationIsIdempotentForRepeatedMaterialization(): void
    {
        $identity = AuthenticatedIdentity::fromRequestAuthenticationResult(
            new RequestAuthenticationResult(
                RequestAuthenticationResult::STATUS_JWT_AUTHENTICATED,
                RequestAuthenticationResult::SOURCE_JWT,
                100,
                'superadmin',
                ['read', 'write'],
                true
            )
        );

        $ctx = AuthContext::getInstance();
        $ctx->materializeIdentity($identity);
        $ctx->materializeIdentity($identity);

        $this->assertSame(Roles::ADMIN, $ctx->getRole());
        $this->assertTrue($ctx->isAdmin());
        $this->assertTrue($ctx->isSuperAdmin());
        $this->assertTrue($ctx->hasRole(Roles::ADMIN));
    }

    public function testModernMiddlewareAcceptsSessionWithoutBearer(): void
    {
        $_SESSION['user_id'] = 42;
        $_SESSION['role'] = Roles::USER;
        $_SESSION['last_activity'] = time();

        $middleware = new AuthMiddleware(new JwtService('test-secret-key-32-characters-long!!'));

        $result = $middleware->handle([
            'path' => '/api/test',
            'headers' => [],
            'query' => [],
        ], function () {
            return ['status' => 200, 'data' => 'ok'];
        });

        $this->assertSame(200, $result['status']);
        $this->assertSame(RequestAuthenticationResult::STATUS_SESSION_AUTHENTICATED, $result['authentication_result']->getStatus());
        $this->assertSame(RequestAuthenticationResult::SOURCE_SESSION, $result['authentication_result']->getSource());
        $this->assertTrue($result['authentication_result']->isAuthenticated());
        $this->assertSame(42, AuthContext::getInstance()->getUserId());
    }

    public function testModernMiddlewareRejectsJwtSessionIdentityConflict(): void
    {
        $_SESSION['user_id'] = 42;
        $_SESSION['role'] = Roles::USER;
        $_SESSION['last_activity'] = time();

        $jwtService = new JwtService('test-secret-key-32-characters-long!!');
        $jwt = $jwtService->generateAccessToken(99, Roles::ADMIN);

        $middleware = new AuthMiddleware($jwtService);

        $result = $middleware->handle([
            'path' => '/api/test',
            'headers' => ['Authorization' => 'Bearer ' . $jwt],
            'query' => [],
        ], function () {
            return ['status' => 200, 'data' => 'ok'];
        });

        $this->assertSame(401, $result['status']);
        $this->assertSame(RequestAuthenticationResult::STATUS_AUTHENTICATION_ERROR, $result['authentication_result']->getStatus());
        $this->assertSame('jwt_session_identity_conflict', $result['authentication_result']->getConflictState());
        $this->assertNull($result['authentication_result']->getUserId());
        $this->assertFalse(AuthContext::getInstance()->isAuthenticated());
    }
}
