<?php

/**
 * Secure JWT Wrapper
 * Substitui firebase/php-jwt vulnerável pela lcobucci/jwt segura
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Auth/JwtService.php';

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\ValidAt;
use Lcobucci\Clock\SystemClock;

class SecureJWT
{
    private static $config;

    private static function getConfig()
    {
        if (self::$config === null) {
            $secret = \App\Auth\JwtService::resolveSecretKey();
            self::$config = Configuration::forSymmetricSigner(
                new Sha256(),
                InMemory::plainText($secret)
            );
        }
        return self::$config;
    }

    /**
     * Codifica um payload em JWT
     */
    public static function encode($payload, $key = null, $alg = 'HS256')
    {
        $config = self::getConfig();
        $now = new DateTimeImmutable();

        $builder = $config->builder()
            ->issuedAt($now);

        // Adiciona claims do payload
        foreach ($payload as $name => $value) {
            switch ($name) {
                case 'exp':
                    $builder = $builder->expiresAt(DateTimeImmutable::createFromFormat('U', $value));
                    break;
                case 'iat':
                    $builder = $builder->issuedAt(DateTimeImmutable::createFromFormat('U', $value));
                    break;
                case 'nbf':
                    $builder = $builder->canOnlyBeUsedAfter(DateTimeImmutable::createFromFormat('U', $value));
                    break;
                case 'iss':
                    $builder = $builder->issuedBy($value);
                    break;
                case 'aud':
                    $builder = $builder->permittedFor($value);
                    break;
                case 'sub':
                    $builder = $builder->relatedTo($value);
                    break;
                case 'jti':
                    $builder = $builder->identifiedBy($value);
                    break;
                default:
                    $builder = $builder->withClaim($name, $value);
                    break;
            }
        }

        // Define expiração padrão se não especificada
        if (!isset($payload['exp'])) {
            $builder = $builder->expiresAt($now->modify('+1 hour'));
        }

        return $builder->getToken($config->signer(), $config->signingKey())->toString();
    }

    /**
     * Decodifica um JWT
     */
    public static function decode($jwt, $key = null, $verify = true)
    {
        $config = self::getConfig();

        try {
            $token = $config->parser()->parse($jwt);

            if ($verify) {
                $constraints = [
                    new SignedWith($config->signer(), $config->signingKey()),
                    new ValidAt(SystemClock::fromUTC())
                ];

                $config->validator()->assert($token, ...$constraints);
            }

            return (object) $token->claims()->all();
        } catch (Exception $e) {
            throw new Exception('Invalid token: ' . $e->getMessage());
        }
    }

    /**
     * Verifica se um JWT é válido
     */
    public static function verify($jwt, $key = null)
    {
        try {
            self::decode($jwt, $key, true);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}

// Não mais alias para Firebase\JWT\JWT. Use SecureJWT diretamente para compatibilidade com lcobucci/jwt.
