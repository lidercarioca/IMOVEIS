<?php

namespace Tests\Unit;

require_once __DIR__ . '/../../config/session_manager.php';

use PHPUnit\Framework\TestCase;
use App\Auth\AuthenticationPolicy;
use App\Auth\JwtService;
use App\Auth\Roles;
use App\Auth\Permissions;
use App\Auth\AuthContext;
use App\Auth\RequestAuthenticationResult;
use App\Security\RateLimiter;
use App\Middleware\AuthMiddleware;
use App\Middleware\AuthorizationMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Logging\FileLogger;
use App\Audit\AuditLogger;

/**
 * SecurityTest - Testes para sistema de segurança (PHASE 12)
 *
 * Cobre:
 * - JWT (geração, validação, expiração)
 * - Roles e Permissions (RBAC)
 * - Rate Limiter (proteção contra abuso)
 * - AuthContext (contexto de autenticação)
 * - Middlewares de segurança
 */
class SecurityTest extends TestCase
{
    private JwtService $jwtService;
    private FileLogger $logger;
    private string $logDir;

    protected function setUp(): void
    {
        $this->jwtService = new JwtService('test-secret-key-32-characters-long!!');

        $this->logDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'security_test_' . uniqid();
        $this->logger = new FileLogger($this->logDir);

        AuthContext::reset();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        AuthContext::reset();
        $_SESSION = [];
        putenv('ENVIRONMENT');
        putenv('JWT_SECRET');
        unset($_ENV['ENVIRONMENT'], $_ENV['JWT_SECRET'], $_SERVER['ENVIRONMENT'], $_SERVER['JWT_SECRET']);

        if (is_dir($this->logDir)) {
            $files = @scandir($this->logDir);
            if ($files) {
                foreach ($files as $file) {
                    if ($file !== '.' && $file !== '..') {
                        @unlink($this->logDir . DIRECTORY_SEPARATOR . $file);
                    }
                }
            }
            @rmdir($this->logDir);
        }
    }

    private function setEnv(string $name, ?string $value): void
    {
        if ($value === null || $value === '') {
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
            return;
        }

        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }

    // ================================================================
    // JWT Tests
    // ================================================================

    public function testJwtRejectsMissingSecretInProduction(): void
    {
        $this->setEnv('ENVIRONMENT', 'production');
        $this->setEnv('JWT_SECRET', null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('JWT_SECRET');

        new JwtService();
    }

    public function testJwtRejectsShortSecret(): void
    {
        $this->setEnv('ENVIRONMENT', 'development');
        $this->setEnv('JWT_SECRET', 'short');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('JWT_SECRET');

        new JwtService();
    }

    public function testJwtRejectsPublicFallbackSecret(): void
    {
        $this->setEnv('ENVIRONMENT', 'development');
        $this->setEnv('JWT_SECRET', 'secret-key-change-in-production');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('JWT_SECRET');

        new JwtService();
    }

    /**
     * Teste: Gerar access token válido
     *
     * @test
     */
    public function testJwtGenerateAccessToken(): void
    {
        $this->setEnv('ENVIRONMENT', 'development');
        $this->setEnv('JWT_SECRET', '12345678901234567890123456789012');

        // Act
        $token = $this->jwtService->generateAccessToken(1, Roles::USER);

        // Assert
        $this->assertIsString($token);
        $this->assertGreaterThan(0, strlen($token));
    }

    /**
     * Teste: Gerar refresh token válido
     *
     * @test
     */
    public function testJwtGenerateRefreshToken(): void
    {
        // Act
        $token = $this->jwtService->generateRefreshToken(1, Roles::USER);

        // Assert
        $this->assertIsString($token);
        $this->assertGreaterThan(0, strlen($token));
    }

    /**
     * Teste: Gerar par de tokens
     *
     * @test
     */
    public function testJwtGenerateTokenPair(): void
    {
        // Act
        $pair = $this->jwtService->generateTokenPair(1, Roles::ADMIN);

        // Assert
        $this->assertArrayHasKey('access', $pair);
        $this->assertArrayHasKey('refresh', $pair);
        $this->assertNotEmpty($pair['access']);
        $this->assertNotEmpty($pair['refresh']);
    }

    /**
     * Teste: Refresh token gera novo par e revoga o refresh anterior
     *
     * @test
     */
    public function testJwtRefreshTokenPair(): void
    {
        // Arrange
        $pair = $this->jwtService->generateTokenPair(1, Roles::USER);

        // Act
        $newPair = $this->jwtService->refreshTokenPair($pair['refresh']);

        // Assert
        $this->assertArrayHasKey('access', $newPair);
        $this->assertArrayHasKey('refresh', $newPair);
        $this->assertNotEquals($pair['access'], $newPair['access']);
        $this->assertNotEquals($pair['refresh'], $newPair['refresh']);
        $this->assertFalse($this->jwtService->validateType($pair['refresh'], JwtService::TYPE_REFRESH));
    }

    /**
     * Teste: Validar token válido
     *
     * @test
     */
    public function testJwtValidateToken(): void
    {
        // Arrange
        $token = $this->jwtService->generateAccessToken(1, Roles::USER);

        // Act & Assert
        $this->assertTrue($this->jwtService->validateType($token, JwtService::TYPE_ACCESS));
    }

    /**
     * Teste: Rejeitar token inválido
     *
     * @test
     */
    public function testJwtRejectInvalidToken(): void
    {
        // Act & Assert
        $this->assertFalse($this->jwtService->validateType('invalid-token', JwtService::TYPE_ACCESS));
    }

    /**
     * Teste: Extrair user ID do token
     *
     * @test
     */
    public function testJwtGetUserId(): void
    {
        // Arrange
        $token = $this->jwtService->generateAccessToken(42, Roles::USER);

        // Act
        $userId = $this->jwtService->getUserId($token);

        // Assert
        $this->assertEquals(42, $userId);
    }

    /**
     * Teste: Extrair role do token
     *
     * @test
     */
    public function testJwtGetRole(): void
    {
        // Arrange
        $token = $this->jwtService->generateAccessToken(1, Roles::ADMIN);

        // Act
        $role = $this->jwtService->getRole($token);

        // Assert
        $this->assertEquals(Roles::ADMIN, $role);
    }

    /**
     * Teste: Verificar expiração de token
     *
     * @test
     */
    public function testJwtCheckExpiration(): void
    {
        // Arrange: Criar token com expiração curta
        $token = $this->jwtService->generateAccessToken(1, Roles::USER, 1); // 1 segundo

        // Esperar expiração
        sleep(2);

        // Act
        $isExpired = $this->jwtService->isExpired($token);

        // Assert
        $this->assertTrue($isExpired);
    }

    /**
     * Teste: Obter tempo até expiração
     *
     * @test
     */
    public function testJwtGetTimeToExpiration(): void
    {
        // Arrange
        $token = $this->jwtService->generateAccessToken(1, Roles::USER, 100);

        // Act
        $timeToExpiration = $this->jwtService->getTimeToExpiration($token);

        // Assert
        $this->assertGreaterThan(0, $timeToExpiration);
        $this->assertLessThanOrEqual(100, $timeToExpiration);
    }

    // ================================================================
    // Roles Tests
    // ================================================================

    /**
     * Teste: Validar roles válidos
     *
     * @test
     */
    public function testRolesValidation(): void
    {
        // Assert
        $this->assertTrue(Roles::isValid(Roles::ADMIN));
        $this->assertTrue(Roles::isValid(Roles::MANAGER));
        $this->assertTrue(Roles::isValid(Roles::USER));
        $this->assertTrue(Roles::isValid(Roles::GUEST));
        $this->assertFalse(Roles::isValid('invalid_role'));
    }

    /**
     * Teste: Hierarquia de roles
     *
     * @test
     */
    public function testRolesHierarchy(): void
    {
        // Assert
        $this->assertTrue(Roles::isHigherThan(Roles::ADMIN, Roles::USER));
        $this->assertTrue(Roles::isHigherThan(Roles::MANAGER, Roles::USER));
        $this->assertFalse(Roles::isHigherThan(Roles::USER, Roles::ADMIN));
    }

    /**
     * Teste: Verificar nível mínimo
     *
     * @test
     */
    public function testRolesAtLeast(): void
    {
        // Assert
        $this->assertTrue(Roles::isAtLeast(Roles::ADMIN, Roles::USER));
        $this->assertTrue(Roles::isAtLeast(Roles::ADMIN, Roles::ADMIN));
        $this->assertFalse(Roles::isAtLeast(Roles::USER, Roles::ADMIN));
    }

    // ================================================================
    // Permissions Tests
    // ================================================================

    /**
     * Teste: Verificar permissão de role
     *
     * @test
     */
    public function testPermissionsCan(): void
    {
        // Assert
        $this->assertTrue(Permissions::can(Roles::ADMIN, Permissions::PROPERTY_DELETE));
        $this->assertTrue(Permissions::can(Roles::USER, Permissions::PROPERTY_VIEW));
        $this->assertTrue(Permissions::can(Roles::USER, Permissions::ADMIN_SETTINGS));
        $this->assertFalse(Permissions::can(Roles::GUEST, Permissions::PROPERTY_CREATE));
    }

    /**
     * Teste: Verificar todas as permissões
     *
     * @test
     */
    public function testPermissionsCanAll(): void
    {
        // Assert
        $this->assertTrue(Permissions::canAll(Roles::ADMIN, [
            Permissions::PROPERTY_CREATE,
            Permissions::PROPERTY_DELETE,
        ]));

        $this->assertFalse(Permissions::canAll(Roles::USER, [
            Permissions::PROPERTY_CREATE,
            Permissions::PROPERTY_DELETE,
        ]));
    }

    /**
     * Teste: Verificar alguma permissão
     *
     * @test
     */
    public function testPermissionsCanAny(): void
    {
        // Assert
        $this->assertTrue(Permissions::canAny(Roles::USER, [
            Permissions::PROPERTY_DELETE,
            Permissions::PROPERTY_VIEW,
        ]));

        $this->assertFalse(Permissions::canAny(Roles::GUEST, [
            Permissions::PROPERTY_DELETE,
            Permissions::ADMIN_SETTINGS,
        ]));
    }

    /**
     * Teste: Obter permissões de role
     *
     * @test
     */
    public function testPermissionsGetPermissions(): void
    {
        // Act
        $userPermissions = Permissions::getPermissions(Roles::USER);
        $adminPermissions = Permissions::getPermissions(Roles::ADMIN);

        // Assert
        $this->assertContains(Permissions::PROPERTY_VIEW, $userPermissions);
        $this->assertContains(Permissions::ADMIN_SETTINGS, $adminPermissions);
        $this->assertContains(Permissions::ADMIN_SETTINGS, $userPermissions);
    }

    /**
     * Teste: Obter roles com permissão
     *
     * @test
     */
    public function testPermissionsGetRolesWithPermission(): void
    {
        // Act
        $rolesCanView = Permissions::getRolesWithPermission(Permissions::PROPERTY_VIEW);
        $rolesCanAdmin = Permissions::getRolesWithPermission(Permissions::ADMIN_SETTINGS);

        // Assert
        $this->assertContains(Roles::ADMIN, $rolesCanView);
        $this->assertContains(Roles::ADMIN, $rolesCanAdmin);
        $this->assertNotContains(Roles::GUEST, $rolesCanAdmin);
    }

    // ================================================================
    // AuthContext Tests
    // ================================================================

    /**
     * Teste: AuthContext autenticação
     *
     * @test
     */
    public function testAuthContextAuthenticate(): void
    {
        // Arrange
        $context = AuthContext::getInstance();

        // Act
        $context->authenticate(1, Roles::USER, 'token');

        // Assert
        $this->assertTrue($context->isAuthenticated());
        $this->assertEquals(1, $context->getUserId());
        $this->assertEquals(Roles::USER, $context->getRole());
    }

    /**
     * Teste: AuthContext logout
     *
     * @test
     */
    public function testAuthContextLogout(): void
    {
        // Arrange
        $context = AuthContext::getInstance();
        $context->authenticate(1, Roles::USER, 'token');

        // Act
        $context->logout();

        // Assert
        $this->assertFalse($context->isAuthenticated());
        $this->assertNull($context->getUserId());
    }

    /**
     * Teste: AuthContext verificar permissão
     *
     * @test
     */
    public function testAuthContextCan(): void
    {
        // Arrange
        $context = AuthContext::getInstance();
        $context->authenticate(1, Roles::ADMIN, 'token');

        // Act & Assert
        $this->assertTrue($context->can(Permissions::ADMIN_SETTINGS));
        $this->assertTrue($context->can(Permissions::PROPERTY_DELETE));
    }

    /**
     * Teste: AuthContext verificar role
     *
     * @test
     */
    public function testAuthContextHasRole(): void
    {
        // Arrange
        $context = AuthContext::getInstance();
        $context->authenticate(1, Roles::MANAGER, 'token');

        // Act & Assert
        $this->assertTrue($context->hasRole(Roles::MANAGER));
        $this->assertFalse($context->hasRole(Roles::USER));
        $this->assertTrue($context->isAdmin() === false);
    }

    // ================================================================
    // RateLimiter Tests - Removidos temporariamente
    // (Focar em RBAC e Middleware primeiro)
    // ================================================================

    // NOTE: RateLimiter é testado através de RateLimitMiddleware
    // Testes específicos serão adicionados em iteração futura



    /**
     * Teste: AuthMiddleware extrai token válido
     *
     * @test
     */
    public function testAuthMiddlewareValidToken(): void
    {
        // Arrange
        $token = $this->jwtService->generateAccessToken(1, Roles::USER);
        $middleware = new AuthMiddleware($this->jwtService, $this->logger);

        $request = [
            'path' => '/api/properties',
            'headers' => [
                'Authorization' => "Bearer {$token}",
            ],
        ];

        // Act
        $result = $middleware->handle($request, function ($req) {
            return ['status' => 200, 'data' => 'ok'];
        });

        // Assert
        $this->assertEquals(200, $result['status']);
        $this->assertTrue(AuthContext::getInstance()->isAuthenticated());
        $this->assertArrayHasKey('authentication_result', $result);
        $this->assertSame('JWT_AUTHENTICATED', $result['authentication_result']->getStatus());
        $this->assertSame('jwt', $result['authentication_result']->getSource());
        $this->assertSame(1, $result['authentication_result']->getUserId());
    }

    /**
     * Teste: Sessão válida produz resultado de autenticação de sessão
     *
     * @test
     */
    public function testSessionAuthenticationResultAuthenticated(): void
    {
        require_once __DIR__ . '/../../config/session_manager.php';

        $_SESSION['user_id'] = 7;
        $_SESSION['role'] = Roles::ADMIN;
        $_SESSION['last_activity'] = time();

        $result = buildSessionAuthenticationResult();

        $this->assertSame('SESSION_AUTHENTICATED', $result->getStatus());
        $this->assertSame('session', $result->getSource());
        $this->assertTrue($result->isAuthenticated());
        $this->assertSame(7, $result->getUserId());
        $this->assertSame(Roles::ADMIN, $result->getRole());
        $this->assertNotEmpty($result->getPermissions());
    }

    /**
     * Teste: Sessão ausente produz ausência de credencial
     *
     * @test
     */
    public function testSessionAuthenticationResultNoSession(): void
    {
        require_once __DIR__ . '/../../config/session_manager.php';

        unset($_SESSION['user_id'], $_SESSION['role'], $_SESSION['last_activity']);

        $result = buildSessionAuthenticationResult();

        $this->assertSame('NO_CREDENTIAL', $result->getStatus());
        $this->assertSame('none', $result->getSource());
        $this->assertFalse($result->isAuthenticated());
    }

    /**
     * Teste: Sessão expirada produz falha de autenticação sem mexer em JWT/Tenant
     *
     * @test
     */
    public function testSessionAuthenticationResultExpired(): void
    {
        require_once __DIR__ . '/../../config/session_manager.php';

        $_SESSION['user_id'] = 9;
        $_SESSION['role'] = Roles::USER;
        $_SESSION['last_activity'] = time() - (SESSION_TIMEOUT + 5);

        $result = buildSessionAuthenticationResult();

        $this->assertSame('AUTHENTICATION_ERROR', $result->getStatus());
        $this->assertSame('none', $result->getSource());
        $this->assertFalse($result->isAuthenticated());
    }

    /**
     * Teste: AuthMiddleware rejeita token ausente
     *
     * @test
     */
    public function testAuthMiddlewareNoToken(): void
    {
        // Arrange
        $middleware = new AuthMiddleware($this->jwtService, $this->logger);

        $request = [
            'path' => '/api/properties',
            'headers' => [],
        ];

        // Act
        $result = $middleware->handle($request, function ($req) {
            return ['status' => 200];
        });

        // Assert
        $this->assertEquals(401, $result['status']);
        $this->assertArrayHasKey('authentication_result', $result);
        $this->assertSame('NO_CREDENTIAL', $result['authentication_result']->getStatus());
        $this->assertFalse($result['authentication_result']->isAuthenticated());
    }

    /**
     * Teste: AuthMiddleware rejeita token inválido
     *
     * @test
     */
    public function testAuthMiddlewareInvalidToken(): void
    {
        // Arrange
        $middleware = new AuthMiddleware($this->jwtService, $this->logger);

        $request = [
            'path' => '/api/properties',
            'headers' => [
                'Authorization' => 'Bearer invalid-token-123',
            ],
        ];

        // Act
        $result = $middleware->handle($request, function ($req) {
            return ['status' => 200];
        });

        // Assert
        $this->assertEquals(401, $result['status']);
        $this->assertArrayHasKey('authentication_result', $result);
        $this->assertSame('JWT_INVALID', $result['authentication_result']->getStatus());
        $this->assertFalse($result['authentication_result']->isAuthenticated());
    }

    /**
     * Teste: Política central deve priorizar JWT quando o header Bearer existe.
     *
     * @test
     */
    public function testAuthenticationPolicyPrefersJwtWhenBearerHeaderIsPresent(): void
    {
        $jwtResult = new RequestAuthenticationResult(
            RequestAuthenticationResult::STATUS_JWT_AUTHENTICATED,
            RequestAuthenticationResult::SOURCE_JWT,
            99,
            Roles::ADMIN,
            ['read'],
            true
        );

        $sessionResult = new RequestAuthenticationResult(
            RequestAuthenticationResult::STATUS_SESSION_AUTHENTICATED,
            RequestAuthenticationResult::SOURCE_SESSION,
            99,
            Roles::USER,
            ['write'],
            false
        );

        $resolved = AuthenticationPolicy::decide($jwtResult, $sessionResult, true);

        $this->assertSame(
            RequestAuthenticationResult::STATUS_JWT_AUTHENTICATED,
            $resolved->getStatus()
        );
        $this->assertSame(RequestAuthenticationResult::SOURCE_JWT, $resolved->getSource());
        $this->assertTrue($resolved->isAuthenticated());
    }

    /**
     * Teste: Política central deve rejeitar JWT inválido mesmo com sessão válida.
     *
     * @test
     */
    public function testAuthenticationPolicyRejectsInvalidJwtWhenBearerHeaderIsPresent(): void
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
            Roles::USER,
            ['read'],
            false
        );

        $resolved = AuthenticationPolicy::decide($jwtResult, $sessionResult, true);

        $this->assertSame(RequestAuthenticationResult::STATUS_JWT_INVALID, $resolved->getStatus());
        $this->assertSame(RequestAuthenticationResult::SOURCE_JWT, $resolved->getSource());
        $this->assertFalse($resolved->isAuthenticated());
    }

    /**
     * Teste: Política central deve cair na sessão quando não houver header Bearer.
     *
     * @test
     */
    public function testAuthenticationPolicyAllowsSessionWhenNoBearerHeaderIsPresent(): void
    {
        $jwtResult = new RequestAuthenticationResult(
            RequestAuthenticationResult::STATUS_NO_CREDENTIAL,
            RequestAuthenticationResult::SOURCE_NONE
        );

        $sessionResult = new RequestAuthenticationResult(
            RequestAuthenticationResult::STATUS_SESSION_AUTHENTICATED,
            RequestAuthenticationResult::SOURCE_SESSION,
            42,
            Roles::USER,
            ['read'],
            false
        );

        $resolved = AuthenticationPolicy::decide($jwtResult, $sessionResult, false);

        $this->assertSame(
            RequestAuthenticationResult::STATUS_SESSION_AUTHENTICATED,
            $resolved->getStatus()
        );
        $this->assertSame(RequestAuthenticationResult::SOURCE_SESSION, $resolved->getSource());
        $this->assertTrue($resolved->isAuthenticated());
    }

    /**
     * Teste: JWT expirado com Bearer deve rejeitar, não cair em sessão.
     *
     * @test
     */
    public function testAuthenticationPolicyRejectsExpiredJwtWhenBearerHeaderIsPresent(): void
    {
        $jwtResult = new RequestAuthenticationResult(
            RequestAuthenticationResult::STATUS_JWT_EXPIRED,
            RequestAuthenticationResult::SOURCE_JWT,
            null,
            null,
            [],
            false,
            'JWT_EXPIRED'
        );

        $sessionResult = new RequestAuthenticationResult(
            RequestAuthenticationResult::STATUS_SESSION_AUTHENTICATED,
            RequestAuthenticationResult::SOURCE_SESSION,
            42,
            Roles::USER,
            ['read'],
            false
        );

        $resolved = AuthenticationPolicy::decide($jwtResult, $sessionResult, true);

        $this->assertSame(RequestAuthenticationResult::STATUS_JWT_EXPIRED, $resolved->getStatus());
        $this->assertSame(RequestAuthenticationResult::SOURCE_JWT, $resolved->getSource());
        $this->assertFalse($resolved->isAuthenticated());
    }

    /**
     * Teste: JWT revogado com Bearer deve rejeitar, não cair em sessão.
     *
     * @test
     */
    public function testAuthenticationPolicyRejectsRevokedJwtWhenBearerHeaderIsPresent(): void
    {
        $jwtResult = new RequestAuthenticationResult(
            RequestAuthenticationResult::STATUS_JWT_REVOKED,
            RequestAuthenticationResult::SOURCE_JWT,
            null,
            null,
            [],
            false,
            'JWT_REVOKED'
        );

        $sessionResult = new RequestAuthenticationResult(
            RequestAuthenticationResult::STATUS_SESSION_AUTHENTICATED,
            RequestAuthenticationResult::SOURCE_SESSION,
            42,
            Roles::USER,
            ['read'],
            false
        );

        $resolved = AuthenticationPolicy::decide($jwtResult, $sessionResult, true);

        $this->assertSame(RequestAuthenticationResult::STATUS_JWT_REVOKED, $resolved->getStatus());
        $this->assertSame(RequestAuthenticationResult::SOURCE_JWT, $resolved->getSource());
        $this->assertFalse($resolved->isAuthenticated());
    }

    /**
     * Teste: JWT malformado com Bearer deve rejeitar, não cair em sessão.
     *
     * @test
     */
    public function testAuthenticationPolicyRejectsMalformedJwtWhenBearerHeaderIsPresent(): void
    {
        $jwtResult = new RequestAuthenticationResult(
            RequestAuthenticationResult::STATUS_MALFORMED_CREDENTIAL,
            RequestAuthenticationResult::SOURCE_JWT,
            null,
            null,
            [],
            false,
            'MALFORMED_CREDENTIAL'
        );

        $sessionResult = new RequestAuthenticationResult(
            RequestAuthenticationResult::STATUS_SESSION_AUTHENTICATED,
            RequestAuthenticationResult::SOURCE_SESSION,
            42,
            Roles::USER,
            ['read'],
            false
        );

        $resolved = AuthenticationPolicy::decide($jwtResult, $sessionResult, true);

        $this->assertSame(RequestAuthenticationResult::STATUS_MALFORMED_CREDENTIAL, $resolved->getStatus());
        $this->assertSame(RequestAuthenticationResult::SOURCE_JWT, $resolved->getSource());
        $this->assertFalse($resolved->isAuthenticated());
    }

    // ================================================================
    // Integration Tests
    // ================================================================

    /**
     * Teste: Stack de middlewares completo
     *
     * @test
     */
    public function testMiddlewareStack(): void
    {
        // Arrange
        $token = $this->jwtService->generateAccessToken(1, Roles::USER);
        $authMiddleware = new AuthMiddleware($this->jwtService, $this->logger);
        $authzMiddleware = new AuthorizationMiddleware($this->logger);

        // Registrar que 'property.edit' requer PROPERTY_EDIT
        $authzMiddleware->requirePermission('property.edit', Permissions::PROPERTY_EDIT);

        $request = [
            'path' => '/api/properties/edit',
            'action' => 'property.edit',
            'headers' => [
                'Authorization' => "Bearer {$token}",
            ],
        ];

        // Act: Executar auth middleware, depois authorization middleware
        $result = $authMiddleware->handle($request, function ($req) use ($authzMiddleware) {
            // Após auth middleware, o AuthContext deve estar preenchido
            // Agora o middleware de autorização deve verificar
            return $authzMiddleware->handle($req, function ($req) {
                // Se chegou aqui, autorização passou (não deveria chegar)
                return ['status' => 200, 'data' => 'edited'];
            });
        });

        // Assert: USER não pode EDIT, então deve ser 403
        $this->assertEquals(403, $result['status']);
    }

    /**
     * Teste: Admin pode acessar todas as rotas
     *
     * @test
     */
    public function testAdminAccessAll(): void
    {
        // Arrange
        $token = $this->jwtService->generateAccessToken(1, Roles::ADMIN);
        $authMiddleware = new AuthMiddleware($this->jwtService, $this->logger);
        $authzMiddleware = new AuthorizationMiddleware($this->logger);
        $authzMiddleware->requirePermission('admin.settings', Permissions::ADMIN_SETTINGS);

        $request = [
            'path' => '/admin/settings',
            'action' => 'admin.settings',
            'headers' => [
                'Authorization' => "Bearer {$token}",
            ],
        ];

        // Act
        $result = $authMiddleware->handle($request, function ($req) use ($authzMiddleware) {
            return $authzMiddleware->handle($req, function ($req) {
                return ['status' => 200, 'data' => 'settings'];
            });
        });

        // Assert: ADMIN pode acessar tudo
        $this->assertEquals(200, $result['status']);
    }
}
