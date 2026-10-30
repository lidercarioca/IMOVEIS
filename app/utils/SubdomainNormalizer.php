<?php
/**
 * SubdomainNormalizer
 * 
 * Utilitário para normalizar nomes de empresas em subdomínios válidos
 * Segue RFC 1123 e regras gerais de DNS
 * 
 * Exemplos:
 * - "Empresa X" → "empresax"
 * - "Imóveis Rio" → "imoveisrio"
 * - "ABC Imóveis" → "abcimoveis"
 * - "A.B.C Ltda" → "abcltda"
 */

namespace App\Utils;

class SubdomainNormalizer {
    
    /**
     * Normaliza nome da empresa para subdomínio válido
     * 
     * @param string $companyName Nome da empresa
     * @param int|null $tenantId ID do tenant (opcional, para diferenciar duplicados)
     * @return string Subdomínio normalizado (max 63 caracteres por label DNS)
     */
    public static function normalize($companyName, $tenantId = null) {
        if (empty($companyName)) {
            return 'tenant-' . ($tenantId ?? 'unknown');
        }
        
        // 1. Converter para minúsculas
        $subdomain = strtolower($companyName);
        
        // 2. Remover acentos (translitera caracteres especiais)
        $subdomain = self::removeAccents($subdomain);
        
        // 3. Remover caracteres especiais, mantendo apenas letras e números
        //    e juntar tudo sem separadores.
        $subdomain = preg_replace('/[^a-z0-9]/', '', $subdomain);
        
        // 4. Remover espaços, pontos e outros caracteres extras já foi tratado acima.
        //    Aqui não usamos hífens: apenas concatenamos.
        $subdomain = preg_replace('/\s+/', '', $subdomain);
        
        // 5. Normalizar múltiplos caracteres sem separadores
        $subdomain = preg_replace('/[^a-z0-9]+/', '', $subdomain);
        
        // 7. Garantir tamanho máximo (63 caracteres é o limite de um label DNS)
        // Deixamos 8 caracteres para sufixo incremental se necessário
        $maxLength = 55;
        if (strlen($subdomain) > $maxLength) {
            $subdomain = substr($subdomain, 0, $maxLength);
            $subdomain = rtrim($subdomain, '-');
        }
        
        // 8. Se fornecido tenant_id e o subdomain fica vazio, usar tenant-ID
        if (empty($subdomain)) {
            $subdomain = 'tenant' . ($tenantId ?? 'unknown');
        }
        
        // 9. Adicionar sufixo do tenant se fornecido (para evitar colisões)
        if ($tenantId !== null && $tenantId > 0) {
            // Usar sufixo curto baseado no tenant_id
            $suffix = self::generateShortId($tenantId);
            
            // Garantir que não ultrapasse 63 caracteres
            $maxWithSuffix = 63 - strlen($suffix);
            if (strlen($subdomain) > $maxWithSuffix) {
                $subdomain = substr($subdomain, 0, $maxWithSuffix);
            }
            
            // Adicionar o sufixo juntos sem separador
            $subdomain .= $suffix;
        }
        
        return $subdomain;
    }
    
    /**
     * Remove acentos de um texto usando decomposição Unicode
     */
    private static function removeAccents($string) {
        // Normalizar para NFD (decomposição)
        if (function_exists('normalizer_normalize')) {
            $string = normalizer_normalize($string, \Normalizer::FORM_NFD);
        }
        
        // Remover marcas diacríticas (combinantes)
        $string = preg_replace('/[\p{Mn}]/u', '', $string);
        
        // Fallback para rotina manual (se a função normalizer não estiver disponível)
        $replacements = [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
            'Á' => 'A', 'À' => 'A', 'Ã' => 'A', 'Â' => 'A', 'Ä' => 'A',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Õ' => 'O', 'Ô' => 'O', 'Ö' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ç' => 'C', 'Ñ' => 'N'
        ];
        
        $string = strtr($string, $replacements);
        return $string;
    }
    
    /**
     * Gera ID curto baseado no tenant_id para evitar colisões
     * Usa Base36 para ficar legível e usar menos espaço
     */
    private static function generateShortId($tenantId) {
        // Converte para base36 (usa 0-9, a-z)
        return base_convert($tenantId, 10, 36);
    }
    
    /**
     * Verifica se um subdomínio é válido segundo regras DNS
     */
    public static function isValid($subdomain) {
        // Regras:
        // - Começa e termina com letra ou número
        // - Contém apenas letras e números
        // - Máximo 63 caracteres
        // - Não pode ser um número puro
        
        if (empty($subdomain) || strlen($subdomain) > 63) {
            return false;
        }
        
        // Não pode começar ou terminar com hífen
        if ($subdomain[0] === '-' || $subdomain[strlen($subdomain) - 1] === '-') {
            return false;
        }
        
        // Contém apenas letras e números
        if (!preg_match('/^[a-z0-9]+$/i', $subdomain)) {
            return false;
        }
        
        // Não pode ser só números (para evitar confundimento com IP)
        if (preg_match('/^\d+$/', $subdomain)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Normaliza e valida um subdomínio
     * Retorna o subdomínio normalizado ou null se inválido
     */
    public static function normalizeAndValidate($companyName, $tenantId = null) {
        $subdomain = self::normalize($companyName, $tenantId);
        
        if (!self::isValid($subdomain)) {
            // Fallback: usar tenant-ID se normalização falhar
            $subdomain = 'tenant' . ($tenantId ?? 'unknown');
        }
        
        return $subdomain;
    }
}
