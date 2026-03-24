<?php
/**
 * Vértice Acadêmico — Input Sanitization
 * Funções para sanitizar e validar inputs
 */

class Sanitizer {
    public static function string($value, int $maxLength = 255): string {
        $value = trim($value ?? '');
        $value = mb_substr($value, 0, $maxLength);
        return $value;
    }

    public static function email(string $value): string {
        $value = trim(strtolower($value ?? ''));
        return filter_var($value, FILTER_SANITIZE_EMAIL);
    }

    public static function int($value, int $default = 0): int {
        return (int) filter_var($value ?? $default, FILTER_SANITIZE_NUMBER_INT);
    }

    public static function float($value, float $default = 0.0): float {
        $value = str_replace(',', '.', ($value ?? $default));
        return (float) filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }

    public static function boolean($value): bool {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }

    public static function html($value, int $maxLength = 10000): string {
        $value = trim($value ?? '');
        $value = mb_substr($value, 0, $maxLength);
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    public static function username(string $value): string {
        $value = trim($value ?? '');
        $value = preg_replace('/[^a-zA-Z0-9_@.-]/', '', $value);
        return mb_substr($value, 0, 100);
    }

    public static function phone(string $value): string {
        $value = preg_replace('/[^0-9]/', '', ($value ?? ''));
        return mb_substr($value, 0, 20);
    }

    public static function cpf(string $value): string {
        return preg_replace('/[^0-9]/', '', ($value ?? ''));
    }

    public static function cnpj(string $value): string {
        return preg_replace('/[^0-9]/', '', ($value ?? ''));
    }

    public static function arrayInt($value): array {
        if (!is_array($value)) {
            return [];
        }
        return array_map('intval', $value);
    }

    public static function arrayString($value, int $maxLength = 255): array {
        if (!is_array($value)) {
            return [];
        }
        return array_map(fn($v) => self::string($v, $maxLength), $value);
    }

    public static function url(string $value): string {
        $value = trim($value ?? '');
        return filter_var($value, FILTER_SANITIZE_URL);
    }
}

function sanitize_string($value, int $maxLength = 255): string {
    return Sanitizer::string($value, $maxLength);
}

function sanitize_email(string $value): string {
    return Sanitizer::email($value);
}

function sanitize_int($value, int $default = 0): int {
    return Sanitizer::int($value, $default);
}

function sanitize_float($value, float $default = 0.0): float {
    return Sanitizer::float($value, $default);
}

function sanitize_boolean($value): bool {
    return Sanitizer::boolean($value);
}

function sanitize_html($value, int $maxLength = 10000): string {
    return Sanitizer::html($value, $maxLength);
}

function sanitize_username(string $value): string {
    return Sanitizer::username($value);
}

function sanitize_phone(string $value): string {
    return Sanitizer::phone($value);
}

function sanitize_cpf(string $value): string {
    return Sanitizer::cpf($value);
}

function sanitize_cnpj(string $value): string {
    return Sanitizer::cnpj($value);
}
