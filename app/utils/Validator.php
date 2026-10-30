<?php
/**
 * Validator - Validador centralizado para dados
 * 
 * Fornece métodos reutilizáveis de validação
 * Uso: Validator::email($email) retorna true/false
 */

namespace App\Utils;

class Validator {
    
    /**
     * Validar email
     */
    public static function email(string $email): bool {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validar URL
     */
    public static function url(string $url): bool {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * Validar número inteiro
     */
    public static function integer($value): bool {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }
    
    /**
     * Validar número flutuante
     */
    public static function float($value): bool {
        return filter_var($value, FILTER_VALIDATE_FLOAT) !== false;
    }
    
    /**
     * Validar booleano
     */
    public static function boolean($value): bool {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) !== false;
    }
    
    /**
     * Validar tamanho mínimo de string
     */
    public static function minLength(string $value, int $min): bool {
        return strlen($value) >= $min;
    }
    
    /**
     * Validar tamanho máximo de string
     */
    public static function maxLength(string $value, int $max): bool {
        return strlen($value) <= $max;
    }
    
    /**
     * Validar string entre limites
     */
    public static function length(string $value, int $min, int $max): bool {
        $len = strlen($value);
        return $len >= $min && $len <= $max;
    }
    
    /**
     * Validar padrão regex
     */
    public static function regex(string $value, string $pattern): bool {
        return preg_match($pattern, $value) === 1;
    }
    
    /**
     * Validar telefone brasileiro
     */
    public static function phone(string $phone): bool {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        return strlen($digits) >= 10 && strlen($digits) <= 11;
    }
    
    /**
     * Validar CPF
     */
    public static function cpf(string $cpf): bool {
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        
        if (strlen($cpf) != 11) {
            return false;
        }
        
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }
        
        // Validar primeiro dígito
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int)$cpf[$i] * (10 - $i);
        }
        $digit = 11 - ($sum % 11);
        $digit = $digit >= 10 ? 0 : $digit;
        
        if ((int)$cpf[9] != $digit) {
            return false;
        }
        
        // Validar segundo dígito
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += (int)$cpf[$i] * (11 - $i);
        }
        $digit = 11 - ($sum % 11);
        $digit = $digit >= 10 ? 0 : $digit;
        
        return (int)$cpf[10] == $digit;
    }
    
    /**
     * Validar CNPJ
     */
    public static function cnpj(string $cnpj): bool {
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
        
        if (strlen($cnpj) != 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }
        
        // Validar primeiro dígito
        $size = strlen($cnpj) - 2;
        $numbers = substr($cnpj, 0, $size);
        $digits = substr($cnpj, $size);
        
        $sum = 0;
        $position = 2;
        
        for ($i = $size - 1; $i >= 0; $i--, $position++) {
            if ($position > 9) {
                $position = 2;
            }
            $sum += $numbers[$i] * $position;
        }
        
        $result = $sum % 11 < 2 ? 0 : 11 - $sum % 11;
        
        if ($result != $digits[0]) {
            return false;
        }
        
        // Validar segundo dígito
        $sum = 0;
        $position = 2;
        
        for ($i = $size; $i >= 0; $i--, $position++) {
            if ($position > 9) {
                $position = 2;
            }
            $sum += $digits[0] * $position;
        }
        
        $result = $sum % 11 < 2 ? 0 : 11 - $sum % 11;
        
        return $result == $digits[1];
    }
    
    /**
     * Validar data no formato Y-m-d
     */
    public static function date(string $date, string $format = 'Y-m-d'): bool {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
    
    /**
     * Validar mês no formato Y-m
     */
    public static function month(string $month): bool {
        return self::regex($month, '/^\d{4}-\d{2}$/');
    }
    
    /**
     * Validar um campo obrigatório
     */
    public static function required($value): bool {
        return !empty($value);
    }
    
    /**
     * Validar múltiplos campos obrigatórios
     */
    public static function requiredFields(array $data, array $fields): array {
        $errors = [];
        
        foreach ($fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[$field] = "Campo '{$field}' é obrigatório";
            }
        }
        
        return $errors;
    }
    
    /**
     * Validar tipo de dado
     */
    public static function type($value, string $type): bool {
        return gettype($value) === $type;
    }
    
    /**
     * Validar se está em um conjunto de valores
     */
    public static function inArray($value, array $allowed): bool {
        return in_array($value, $allowed, true);
    }
    
    /**
     * Validar valor numérico com limites
     * 
     * Exemplo: Validator::range(50, 10, 100) → true
     */
    public static function range($value, int $min, int $max): bool {
        return $value >= $min && $value <= $max;
    }
    
    /**
     * Validar se é um IP válido
     */
    public static function ip(string $ip): bool {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }
    
    /**
     * Validar moeda (valor monetário)
     * 
     * Exemplo: Validator::money('1.234,56') → true
     * Exemplo: Validator::money('1234.56') → true
     */
    public static function money(string $value): bool {
        return preg_match('/^\d+([.,]\d{2})?$/', $value) === 1;
    }
    
    /**
     * Converter valor monetário para float
     * 
     * Exemplo: Validator::moneyToFloat('1.234,56') → 1234.56
     */
    public static function moneyToFloat(string $value): float {
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);
        return (float)$value;
    }
    
    /**
     * Validar data não é no passado
     */
    public static function dateNotPast(string $date, string $format = 'Y-m-d'): bool {
        $d = \DateTime::createFromFormat($format, $date);
        if (!$d) return false;
        
        $today = new \DateTime();
        return $d >= $today;
    }
    
    /**
     * Validar data não é no futuro
     */
    public static function dateNotFuture(string $date, string $format = 'Y-m-d'): bool {
        $d = \DateTime::createFromFormat($format, $date);
        if (!$d) return false;
        
        $today = new \DateTime();
        return $d <= $today;
    }
    
    /**
     * Validar arquivo enviado
     * Verifica se é um upload válido
     */
    public static function uploadedFile(array $file): bool {
        return isset($file['tmp_name']) &&
               isset($file['error']) &&
               $file['error'] === UPLOAD_ERR_OK &&
               is_uploaded_file($file['tmp_name']);
    }
    
    /**
     * Validar extensão de arquivo
     */
    public static function fileExtension(string $filename, array $allowed): bool {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($ext, $allowed);
    }
    
    /**
     * Validar tamanho de arquivo (em MB)
     */
    public static function fileSize(int $sizeInBytes, int $maxMB): bool {
        return $sizeInBytes <= ($maxMB * 1024 * 1024);
    }
    
    /**
     * Sanitizar string removendo caracteres perigosos
     */
    public static function sanitize(string $value, string $type = 'string'): string {
        switch($type) {
            case 'int':
                return filter_var($value, FILTER_SANITIZE_NUMBER_INT);
            case 'float':
                return filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT);
            case 'email':
                return filter_var($value, FILTER_SANITIZE_EMAIL);
            case 'url':
                return filter_var($value, FILTER_SANITIZE_URL);
            case 'string':
                return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
            default:
                return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
        }
    }
    
    /**
     * Validar senha forte
     * - Mínimo 8 caracteres
     * - Pelo menos 1 maiúscula
     * - Pelo menos 1 minúscula
     * - Pelo menos 1 número
     * - Pelo menos 1 caractere especial
     */
    public static function strongPassword(string $password): bool {
        return preg_match(
            '/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/',
            $password
        ) === 1;
    }
    
    /**
     * Validar username (alfanumérico e underscore)
     */
    public static function username(string $username): bool {
        return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username) === 1;
    }
    
    /**
     * Validar slug URL-friendly
     */
    public static function slug(string $slug): bool {
        return preg_match('/^[a-z0-9-]+$/', $slug) === 1;
    }
    
    /**
     * Validar JSON string
     */
    public static function json(string $value): bool {
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }
    
    /**
     * Validar UUID
     */
    public static function uuid(string $uuid): bool {
        return preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $uuid
        ) === 1;
    }
    
    /**
     * Validar hora no formato HH:MM
     */
    public static function time(string $time): bool {
        return preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/', $time) === 1;
    }
}
