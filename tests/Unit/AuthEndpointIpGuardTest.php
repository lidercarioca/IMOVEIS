<?php

namespace Tests\Unit;

use App\Observability\RequestTracker;
use PHPUnit\Framework\TestCase;

class AuthEndpointIpGuardTest extends TestCase
{
    public function testRequestTrackerAcceptsValidIpv4AndIpv6(): void
    {
        $originalRemoteAddr = $_SERVER['REMOTE_ADDR'] ?? null;
        $originalCf = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null;
        $originalXff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null;

        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.50';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.99, 10.0.0.5';

        try {
            $this->assertSame('203.0.113.10', RequestTracker::resolveClientIp());
            $_SERVER['REMOTE_ADDR'] = '2001:db8::1';
            $this->assertSame('2001:db8::1', RequestTracker::resolveClientIp());
        } finally {
            if ($originalRemoteAddr === null) {
                unset($_SERVER['REMOTE_ADDR']);
            } else {
                $_SERVER['REMOTE_ADDR'] = $originalRemoteAddr;
            }

            if ($originalCf === null) {
                unset($_SERVER['HTTP_CF_CONNECTING_IP']);
            } else {
                $_SERVER['HTTP_CF_CONNECTING_IP'] = $originalCf;
            }

            if ($originalXff === null) {
                unset($_SERVER['HTTP_X_FORWARDED_FOR']);
            } else {
                $_SERVER['HTTP_X_FORWARDED_FOR'] = $originalXff;
            }
        }
    }

    public function testRequestTrackerRejectsMissingAndInvalidIp(): void
    {
        $originalRemoteAddr = $_SERVER['REMOTE_ADDR'] ?? null;

        unset($_SERVER['REMOTE_ADDR']);
        $this->assertNull(RequestTracker::resolveClientIp());

        $_SERVER['REMOTE_ADDR'] = 'not-an-ip';
        $this->assertNull(RequestTracker::resolveClientIp());

        if ($originalRemoteAddr === null) {
            unset($_SERVER['REMOTE_ADDR']);
        } else {
            $_SERVER['REMOTE_ADDR'] = $originalRemoteAddr;
        }
    }

    /**
     * Valida o contrato do guard de IP nos endpoints web/API sem depender de banco ou sessão.
     */
    public function testAuthEndpointsGuardBeforeRateLimiterUsage(): void
    {
        $files = [
            __DIR__ . '/../../login.php',
            __DIR__ . '/../../api/v1/auth/login.php',
            __DIR__ . '/../../api/v1/auth/refreshToken.php',
        ];

        foreach ($files as $file) {
            $source = file_get_contents($file);
            $this->assertNotFalse($source, sprintf('Arquivo não encontrado ou não legível: %s', $file));

            // Verificação estática: confirmamos a forma real do código-fonte, não o comportamento em tempo de execução.
            $this->assertStringContainsString('RequestTracker::resolveClientIp()', $source);
            $this->assertStringContainsString('if ($ip === null)', $source);
            $this->assertStringContainsString('$rateLimiter->isAllowed', $source);

            $this->assertStringContainsString('$rateLimiter->isAllowed', $source, sprintf('chamada real do rate limiter ausente em %s', $file));

            $pattern = '/\$ip\s*=\s*RequestTracker::resolveClientIp\(\);\s*if\s*\(\s*\$ip\s*===\s*null\s*\)\s*[\s\S]*?http_response_code\(400\);\s*[\s\S]*?\$rateLimiter->isAllowed\s*\(/';
            $this->assertMatchesRegularExpression($pattern, $source, sprintf('guard de IP antes do rate limiter não foi encontrado em %s', $file));

            $preRateLimitSection = substr($source, 0, strpos($source, '$rateLimiter->isAllowed'));
            $this->assertStringNotContainsString('$_SERVER[\'REMOTE_ADDR\'] ?? \'unknown\'', $preRateLimitSection);
        }
    }

    public function testAuthEndpointErrorContractUsesSafeClientIpFailureMessage(): void
    {
        $htmlLogin = file_get_contents(__DIR__ . '/../../login.php');
        $this->assertNotFalse($htmlLogin);
        $this->assertStringContainsString('Não foi possível validar sua conexão. Tente novamente.', $htmlLogin);
        $this->assertStringContainsString('http_response_code(400)', $htmlLogin);

        foreach (
            [
                __DIR__ . '/../../api/v1/auth/login.php',
                __DIR__ . '/../../api/v1/auth/refreshToken.php',
            ] as $file
        ) {
            $source = file_get_contents($file);
            $this->assertNotFalse($source, sprintf('Arquivo não encontrado ou não legível: %s', $file));
            $this->assertStringContainsString('Não foi possível validar sua conexão. Tente novamente.', $source);
            $this->assertStringContainsString('http_response_code(400)', $source);
            $this->assertStringContainsString('json_encode([', $source);
        }
    }
}
