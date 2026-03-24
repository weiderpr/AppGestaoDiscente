<?php
/**
 * Vértice Acadêmico — Utilitários CSRF
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class CsrfToken {
    private const TOKEN_NAME = 'csrf_token';
    private const TOKEN_LENGTH = 32;

    public static function generate(): string {
        if (empty($_SESSION[self::TOKEN_NAME])) {
            $_SESSION[self::TOKEN_NAME] = bin2hex(random_bytes(self::TOKEN_LENGTH));
        }
        return $_SESSION[self::TOKEN_NAME];
    }

    public static function get(): string {
        return self::generate();
    }

    public static function verify(string $token): bool {
        if (empty($_SESSION[self::TOKEN_NAME]) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION[self::TOKEN_NAME], $token);
    }

    public static function getField(): string {
        return '<input type="hidden" name="csrf_token" value="' . self::generate() . '">';
    }

    public static function refresh(): string {
        $_SESSION[self::TOKEN_NAME] = bin2hex(random_bytes(self::TOKEN_LENGTH));
        return $_SESSION[self::TOKEN_NAME];
    }
}

function csrf_token(): string {
    return CsrfToken::get();
}

function csrf_field(): string {
    return CsrfToken::getField();
}

function csrf_verify(string $token): bool {
    return CsrfToken::verify($token);
}
