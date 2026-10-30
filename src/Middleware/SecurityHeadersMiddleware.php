<?php
namespace App\Middleware;

use App\Logging\LoggerInterface;
use App\Logging\LoggerFactory;

/**
 * SecurityHeadersMiddleware - Headers de segurança HTTP
 *
 * Adiciona headers de segurança recomendados para produção:
 * - X-Frame-Options: DENY (previne clickjacking)
 * - X-Content-Type-Options: nosniff (previne MIME sniffing)
 * - Strict-Transport-Security (HSTS)
 * - Content-Security-Policy (CSP básico)
 * - X-XSS-Protection
 */
class SecurityHeadersMiddleware implements MiddlewareInterface
{
    private LoggerInterface $logger;

    // Headers de segurança padrão
    private array $headers = [
        'X-Frame-Options' => 'DENY',
        'X-Content-Type-Options' => 'nosniff',
        'X-XSS-Protection' => '1; mode=block',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
    ];

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? LoggerFactory::make();

        // HSTS (HTTPS only) - apenas em produção
        if ($this->isProduction()) {
            $this->headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains; preload';
        }

        // CSP básico
        $this->headers['Content-Security-Policy'] = $this->getContentSecurityPolicy();
    }

    /**
     * {@inheritdoc}
     */
    public function handle(array $request, callable $next): array
    {
        try {
            // Continuar para próximo middleware
            $response = $next($request);

            // Adicionar headers de segurança
            if (is_array($response)) {
                if (!isset($response['headers'])) {
                    $response['headers'] = [];
                }

                foreach ($this->headers as $headerName => $headerValue) {
                    if (!isset($response['headers'][$headerName])) {
                        $response['headers'][$headerName] = $headerValue;
                    }
                }
            }

            return $response;

        } catch (\Exception $e) {
            $this->logger->error('Security headers middleware error', [
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 500,
                'error' => 'Erro de segurança',
            ];
        }
    }

    /**
     * Obter Content-Security-Policy customizada
     *
     * @return string
     */
    private function getContentSecurityPolicy(): string
    {
        // CSP básico e permissivo
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline'",  // Relação: remover 'unsafe-inline' em produção
            "style-src 'self' 'unsafe-inline'",    // Relação: remover 'unsafe-inline' em produção
            "img-src 'self' data: https:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);
    }

    /**
     * Definir header customizado
     *
     * @param string $name
     * @param string $value
     * @return void
     */
    public function setHeader(string $name, string $value): void
    {
        $this->headers[$name] = $value;
    }

    /**
     * Remover header
     *
     * @param string $name
     * @return void
     */
    public function removeHeader(string $name): void
    {
        unset($this->headers[$name]);
    }

    /**
     * Verificar se está em produção
     *
     * @return bool
     */
    private function isProduction(): bool
    {
        $env = $_ENV['APP_ENV'] ?? 'development';
        return $env === 'production';
    }
}
