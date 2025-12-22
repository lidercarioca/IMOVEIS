<?php
/**
 * app/security/SecurityHeaders.php
 * Implementa headers de segurança recomendados por OWASP
 */

class SecurityHeaders {
    
    public static function applyHeaders() {
        // X-Frame-Options: Previne clickjacking
        header('X-Frame-Options: SAMEORIGIN', true);
        
        // X-Content-Type-Options: Previne MIME type sniffing
        header('X-Content-Type-Options: nosniff', true);
        
        // Referrer-Policy: Controla informações de referer
        header('Referrer-Policy: strict-origin-when-cross-origin', true);
        
        // Permissions-Policy: Desabilita APIs potencialmente perigosas
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()', true);
        
        // HSTS (HTTP Strict Transport Security): Força HTTPS
        // Nota: Remover comentário quando site estiver em HTTPS permanente
        // header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload', true);
        
        // Content-Security-Policy: Reduz risco de XSS
        $csp = "default-src 'self'; "
            . "script-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com 'unsafe-inline'; "
            . "style-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com 'unsafe-inline'; "
            . "img-src 'self' data: https:; "
            . "font-src 'self' https://cdnjs.cloudflare.com; "
            . "connect-src 'self' https://ka-f.fontawesome.com https://cdnjs.cloudflare.com https://*.jsdelivr.net; "
            . "frame-ancestors 'self'; "
            . "base-uri 'self'; "
            . "form-action 'self';";
        header('Content-Security-Policy: ' . $csp, true);
        
        // Desabilita cache para páginas sensíveis
        header('Cache-Control: no-cache, no-store, must-revalidate', true);
        header('Pragma: no-cache', true);
        header('Expires: 0', true);
    }
}

SecurityHeaders::applyHeaders();
