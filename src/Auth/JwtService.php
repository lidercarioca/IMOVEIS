<?php

namespace App\Auth;

use App\Cache\CacheFactory;
use App\Cache\CacheInterface;
use App\Auth\Roles;
use DateTimeImmutable;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\ValidAt;

/**
 * JwtService - Gerenciamento seguro de JWT
 *
 * Responsável por:
 * - Gerar access tokens
 * - Gerar refresh tokens
 * - Validar e decodificar tokens
 * - Rotação segura de refresh tokens
 * - Blacklist de tokens revogados
 */
class JwtService
{
    private const ACCESS_TOKEN_TTL = 900;      // 15 minutos
    private const REFRESH_TOKEN_TTL = 604800;  // 7 dias
    public const TYPE_ACCESS = 'access';
    public const TYPE_REFRESH = 'refresh';
    private const BLACKLIST_PREFIX = 'jwt:blacklist:';
    private const MIN_SECRET_BYTES = 32;
    private const PUBLIC_FALLBACK_SECRETS = [
        'secret-key-change-in-production',
        'your-secret-key-change-this-in-production',
    ];

    private string $secretKey;
    private string $issuer;
    private Configuration $configuration;
    private CacheInterface $cache;

    public static function resolveSecretKey(?string $secretKey = null): string
    {
        $candidate = $secretKey;

        if ($candidate === null || $candidate === '') {
            $candidate = getenv('JWT_SECRET');
            if ($candidate === false || $candidate === '') {
                $candidate = $_ENV['JWT_SECRET'] ?? $_SERVER['JWT_SECRET'] ?? null;
            }
        }

        if (is_string($candidate)) {
            $candidate = trim($candidate);
        } else {
            $candidate = null;
        }

        if ($candidate === null || $candidate === '') {
            throw new \RuntimeException('JWT_SECRET is required and cannot be empty.');
        }

        if (strlen($candidate) < self::MIN_SECRET_BYTES) {
            throw new \RuntimeException('JWT_SECRET must be at least 32 bytes long.');
        }

        $normalized = strtolower($candidate);
        foreach (self::PUBLIC_FALLBACK_SECRETS as $fallback) {
            if ($normalized === strtolower($fallback)) {
                throw new \RuntimeException('JWT_SECRET uses a public or insecure fallback value.');
            }
        }

        return $candidate;
    }

    public function __construct(
        ?string $secretKey = null,
        ?string $issuer = null,
        ?CacheInterface $cache = null
    ) {
        $this->secretKey = self::resolveSecretKey($secretKey);
        $this->issuer = $issuer ?? ($_ENV['APP_URL'] ?? $_SERVER['APP_URL'] ?? 'http://localhost');
        $this->cache = $cache ?? CacheFactory::make();
        $this->configuration = $this->createConfiguration();
    }

    private function createConfiguration(): Configuration
    {
        return Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText($this->secretKey)
        );
    }

    public function generateAccessToken(int $userId, string $role, ?int $expiresIn = null): string
    {
        $expiresIn = $expiresIn ?? self::ACCESS_TOKEN_TTL;
        $now = new DateTimeImmutable();
        $jti = uniqid('jti_', true);

        $token = $this->configuration
            ->builder()
            ->issuedBy($this->issuer)
            ->relatedTo((string)$userId)
            ->identifiedBy($jti)
            ->issuedAt($now)
            ->expiresAt($now->modify("+{$expiresIn} seconds"))
            ->withClaim('role', $role)
            ->withClaim('type', self::TYPE_ACCESS)
            ->getToken($this->configuration->signer(), $this->configuration->signingKey());

        return $token->toString();
    }

    public function generateRefreshToken(int $userId, string $role, ?int $expiresIn = null): string
    {
        $expiresIn = $expiresIn ?? self::REFRESH_TOKEN_TTL;
        $now = new DateTimeImmutable();
        $jti = uniqid('jti_', true);

        $token = $this->configuration
            ->builder()
            ->issuedBy($this->issuer)
            ->relatedTo((string)$userId)
            ->identifiedBy($jti)
            ->issuedAt($now)
            ->expiresAt($now->modify("+{$expiresIn} seconds"))
            ->withClaim('role', $role)
            ->withClaim('type', self::TYPE_REFRESH)
            ->getToken($this->configuration->signer(), $this->configuration->signingKey());

        return $token->toString();
    }

    public function generateTokenPair(int $userId, string $role): array
    {
        return [
            'access' => $this->generateAccessToken($userId, $role),
            'refresh' => $this->generateRefreshToken($userId, $role),
        ];
    }

    public function validateAndDecode(string $token): \stdClass
    {
        $plainToken = $this->parseToken($token);

        $this->configuration->validator()->assert(
            $plainToken,
            new SignedWith($this->configuration->signer(), $this->configuration->signingKey()),
            new ValidAt(SystemClock::fromUTC())
        );

        if ($this->isTokenRevoked($plainToken)) {
            throw new \RuntimeException('Token revogado');
        }

        return $this->tokenToObject($plainToken);
    }

    public function refreshTokenPair(string $refreshToken): array
    {
        $payload = $this->validateAndDecode($refreshToken);

        if (($payload->type ?? '') !== self::TYPE_REFRESH) {
            throw new \RuntimeException('Token de refresh inválido');
        }

        $this->revokeToken($refreshToken);

        return $this->generateTokenPair((int)$payload->sub, $payload->role ?? Roles::USER);
    }

    public function revokeToken(string $token): void
    {
        try {
            $plainToken = $this->parseToken($token);
            $jti = $this->getTokenId($plainToken);
            $ttl = $this->getTimeToExpirationFromToken($plainToken);

            if ($jti !== null && $ttl > 0) {
                $this->cache->set($this->getBlacklistKey($jti), true, $ttl);
            }
        } catch (\Exception $e) {
            error_log('[JWT] Falha ao revogar token: ' . $e->getMessage());
        }
    }

    public function validateType(string $token, string $expectedType = self::TYPE_ACCESS): bool
    {
        try {
            $payload = $this->validateAndDecode($token);
            return ($payload->type ?? '') === $expectedType;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getUserId(string $token): ?int
    {
        try {
            $payload = $this->validateAndDecode($token);
            return isset($payload->sub) ? (int)$payload->sub : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getRole(string $token): ?string
    {
        try {
            $payload = $this->validateAndDecode($token);
            return $payload->role ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function isExpired(string $token): bool
    {
        try {
            $plainToken = $this->parseToken($token);
            $expiration = $plainToken->claims()->get('exp');
            if ($expiration instanceof DateTimeImmutable) {
                return $expiration->getTimestamp() < time();
            }
        } catch (\Exception $e) {
            return true;
        }

        return false;
    }

    public function getTimeToExpiration(string $token): ?int
    {
        try {
            $plainToken = $this->parseToken($token);
            return $this->getTimeToExpirationFromToken($plainToken);
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function getDurations(): array
    {
        return [
            'access' => self::ACCESS_TOKEN_TTL,
            'refresh' => self::REFRESH_TOKEN_TTL,
        ];
    }

    private function parseToken(string $token): Plain
    {
        $parsed = $this->configuration->parser()->parse($token);
        if (!$parsed instanceof Plain) {
            throw new \InvalidArgumentException('Token inválido');
        }

        return $parsed;
    }

    private function isTokenRevoked(Plain $token): bool
    {
        $jti = $this->getTokenId($token);
        if ($jti === null) {
            return false;
        }

        return $this->cache->has($this->getBlacklistKey($jti));
    }

    private function getTimeToExpirationFromToken(Plain $token): int
    {
        $expiration = $token->claims()->get('exp');
        if ($expiration instanceof DateTimeImmutable) {
            return max(0, $expiration->getTimestamp() - time());
        }

        return 0;
    }

    private function getTokenId(Plain $token): ?string
    {
        $jti = $token->claims()->get('jti');
        return is_string($jti) ? $jti : null;
    }

    private function getBlacklistKey(string $jti): string
    {
        return self::BLACKLIST_PREFIX . $jti;
    }

    private function tokenToObject(Plain $token): \stdClass
    {
        return (object)$token->claims()->all();
    }
}
