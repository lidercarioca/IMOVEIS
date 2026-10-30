<?php
/**
 * Validador centralizado para entrada de dados
 * Fornece métodos para validar e sanitizar inputs de usuário
 */
class InputValidator {
    
    // Constantes para limites
    const MAX_STRING_LENGTH = 1000;
    const MAX_TEXT_LENGTH = 10000;
    const MAX_EMAIL_LENGTH = 254;
    const MAX_URL_LENGTH = 2000;
    const MAX_FILENAME_LENGTH = 255;
    
    /**
     * Valida e retorna um inteiro
     */
    public static function getInt($value, $name = 'valor', $min = null, $max = null) {
        if (!is_numeric($value)) {
            throw new Exception("$name deve ser um número inteiro");
        }
        
        $int = (int)$value;
        
        if ($min !== null && $int < $min) {
            throw new Exception("$name deve ser no mínimo $min");
        }
        
        if ($max !== null && $int > $max) {
            throw new Exception("$name deve ser no máximo $max");
        }
        
        return $int;
    }
    
    /**
     * Valida e retorna um float
     */
    public static function getFloat($value, $name = 'valor', $min = null, $max = null) {
        if (!is_numeric($value)) {
            throw new Exception("$name deve ser um número");
        }
        
        $float = (float)$value;
        
        if ($min !== null && $float < $min) {
            throw new Exception("$name deve ser no mínimo $min");
        }
        
        if ($max !== null && $float > $max) {
            throw new Exception("$name deve ser no máximo $max");
        }
        
        return $float;
    }
    
    /**
     * Valida e retorna uma string
     */
    public static function getString($value, $name = 'valor', $required = true, $maxLength = self::MAX_STRING_LENGTH) {
        if (is_null($value) || $value === '') {
            if ($required) {
                throw new Exception("$name é obrigatório");
            }
            return '';
        }
        
        if (!is_string($value)) {
            throw new Exception("$name deve ser texto");
        }
        
        $trimmed = trim($value);
        
        if (strlen($trimmed) > $maxLength) {
            throw new Exception("$name deve ter no máximo $maxLength caracteres");
        }
        
        // Remove caracteres de controle perigosos mas mantém acentuação
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $trimmed);
        
        return $clean;
    }
    
    /**
     * Valida e retorna um texto longo (para descrições)
     */
    public static function getText($value, $name = 'valor', $required = true, $maxLength = self::MAX_TEXT_LENGTH) {
        if (is_null($value) || $value === '') {
            if ($required) {
                throw new Exception("$name é obrigatório");
            }
            return '';
        }
        
        if (!is_string($value)) {
            throw new Exception("$name deve ser texto");
        }
        
        $trimmed = trim($value);
        
        if (strlen($trimmed) > $maxLength) {
            throw new Exception("$name deve ter no máximo $maxLength caracteres");
        }
        
        // Remove caracteres de controle perigosos
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $trimmed);
        
        return $clean;
    }
    
    /**
     * Valida e retorna um email
     */
    public static function getEmail($value, $name = 'email', $required = true) {
        if (is_null($value) || $value === '') {
            if ($required) {
                throw new Exception("$name é obrigatório");
            }
            return '';
        }
        
        if (!is_string($value)) {
            throw new Exception("$name deve ser texto");
        }
        
        $email = trim(strtolower($value));
        
        if (strlen($email) > self::MAX_EMAIL_LENGTH) {
            throw new Exception("$name inválido");
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("$name inválido");
        }
        
        return $email;
    }
    
    /**
     * Valida e retorna uma URL
     */
    public static function getUrl($value, $name = 'URL', $required = true) {
        if (is_null($value) || $value === '') {
            if ($required) {
                throw new Exception("$name é obrigatória");
            }
            return '';
        }
        
        if (!is_string($value)) {
            throw new Exception("$name deve ser texto");
        }
        
        $url = trim($value);
        
        if (strlen($url) > self::MAX_URL_LENGTH) {
            throw new Exception("$name inválida");
        }
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new Exception("$name inválida");
        }
        
        return $url;
    }
    
    /**
     * Valida e retorna um valor de um enum (lista de valores permitidos)
     */
    public static function getEnum($value, $allowedValues, $name = 'valor', $default = null) {
        if (is_null($value) || $value === '') {
            if ($default !== null) {
                return $default;
            }
            throw new Exception("$name é obrigatório");
        }
        
        if (!is_string($value)) {
            throw new Exception("$name deve ser texto");
        }
        
        $value = trim($value);
        
        if (!in_array($value, $allowedValues, true)) {
            throw new Exception("$name inválido");
        }
        
        return $value;
    }
    
    /**
     * Valida e retorna um array
     */
    public static function getArray($value, $name = 'array', $required = true, $maxItems = 100) {
        if (is_null($value)) {
            if ($required) {
                throw new Exception("$name é obrigatório");
            }
            return [];
        }
        
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                throw new Exception("$name inválido");
            }
            $value = $decoded;
        }
        
        if (!is_array($value)) {
            throw new Exception("$name deve ser um array");
        }
        
        if (count($value) > $maxItems) {
            throw new Exception("$name pode ter no máximo $maxItems itens");
        }
        
        return $value;
    }
    
    /**
     * Valida um nome de arquivo
     */
    public static function getFilename($value, $name = 'arquivo', $required = true) {
        if (is_null($value) || $value === '') {
            if ($required) {
                throw new Exception("$name é obrigatório");
            }
            return '';
        }
        
        if (!is_string($value)) {
            throw new Exception("$name deve ser texto");
        }
        
        $filename = basename($value);
        
        if (strlen($filename) > self::MAX_FILENAME_LENGTH) {
            throw new Exception("$name muito longo");
        }
        
        // Remove caracteres perigosos mantendo extensão
        if (!preg_match('/^[\w\-\. \(\)]+$/u', $filename)) {
            throw new Exception("$name contém caracteres inválidos");
        }
        
        return $filename;
    }
    
    /**
     * Valida um token (CSRF, JWT, etc)
     */
    public static function getToken($value, $name = 'token', $required = true) {
        if (is_null($value) || $value === '') {
            if ($required) {
                throw new Exception("$name inválido");
            }
            return '';
        }
        
        if (!is_string($value)) {
            throw new Exception("$name inválido");
        }
        
        $token = trim($value);
        
        // Tokens devem ser strings alfanuméricas + alguns caracteres (para JWT)
        if (!preg_match('/^[a-zA-Z0-9\.\-_]+$/', $token)) {
            throw new Exception("$name inválido");
        }
        
        if (strlen($token) < 10 || strlen($token) > 2000) {
            throw new Exception("$name inválido");
        }
        
        return $token;
    }
    
    /**
     * Valida um booleano
     */
    public static function getBool($value, $name = 'booleano', $default = false) {
        if (is_null($value) || $value === '') {
            return $default;
        }
        
        if (is_bool($value)) {
            return $value;
        }
        
        if (is_string($value)) {
            if (in_array(strtolower($value), ['true', '1', 'on', 'yes'], true)) {
                return true;
            }
            if (in_array(strtolower($value), ['false', '0', 'off', 'no'], true)) {
                return false;
            }
        }
        
        if (is_numeric($value)) {
            return (bool)$value;
        }
        
        throw new Exception("$name deve ser um booleano");
    }
    
    /**
     * Valida uma data no formato YYYY-MM-DD
     */
    public static function getDate($value, $name = 'data', $required = true) {
        if (is_null($value) || $value === '') {
            if ($required) {
                throw new Exception("$name é obrigatória");
            }
            return null;
        }
        
        if (!is_string($value)) {
            throw new Exception("$name deve ser texto");
        }
        
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new Exception("$name deve estar no formato YYYY-MM-DD");
        }
        
        $parts = explode('-', $value);
        if (!checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0])) {
            throw new Exception("$name inválida");
        }
        
        return $value;
    }
    
    /**
     * Sanitiza uma string para HTML (XSS protection)
     */
    public static function sanitizeHtml($value) {
        if (!is_string($value)) {
            return $value;
        }
        
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Valida um CPF (básico)
     */
    public static function getCPF($value, $name = 'CPF', $required = true) {
        if (is_null($value) || $value === '') {
            if ($required) {
                throw new Exception("$name é obrigatório");
            }
            return '';
        }
        
        if (!is_string($value)) {
            throw new Exception("$name deve ser texto");
        }
        
        // Remove formatação
        $cpf = preg_replace('/\D/', '', $value);
        
        if (strlen($cpf) !== 11) {
            throw new Exception("$name inválido");
        }
        
        // Verifica se todos os dígitos são iguais
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            throw new Exception("$name inválido");
        }
        
        return $cpf;
    }
}
?>
