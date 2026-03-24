<?php
/**
 * Vértice Acadêmico — Rate Limiting
 * Protege contra ataques de força bruta
 */

class RateLimit {
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_TIME = 300; // 5 minutos em segundos

    public static function check(string $key): bool {
        $attempts = self::getAttempts($key);
        
        if ($attempts >= self::MAX_ATTEMPTS) {
            $lockoutEnd = self::getLockoutEnd($key);
            if ($lockoutEnd && time() < $lockoutEnd) {
                return false;
            }
            // Lockout expirou, reset
            self::clear($key);
            return true;
        }
        
        return true;
    }

    public static function record(string $key): void {
        $attempts = self::getAttempts($key);
        
        if ($attempts === 0) {
            self::setAttempt($key, 1);
            self::setFirstAttempt($key, time());
        } else {
            self::setAttempt($key, $attempts + 1);
        }
        
        // Se atingiu limite, define lockout
        if ($attempts + 1 >= self::MAX_ATTEMPTS) {
            self::setLockoutEnd($key, time() + self::LOCKOUT_TIME);
        }
    }

    public static function clear(string $key): void {
        self::setAttempt($key, 0);
        self::setFirstAttempt($key, 0);
        self::setLockoutEnd($key, 0);
    }

    public static function getAttempts(string $key): int {
        $key = self::sanitizeKey($key);
        return (int)($_SESSION["rate_limit_{$key}"] ?? 0);
    }

    public static function getRemainingAttempts(string $key): int {
        $attempts = self::getAttempts($key);
        return max(0, self::MAX_ATTEMPTS - $attempts);
    }

    public static function getLockoutRemaining(string $key): int {
        $key = self::sanitizeKey($key);
        $lockoutEnd = $_SESSION["rate_limit_lockout_{$key}"] ?? 0;
        
        if (!$lockoutEnd) {
            return 0;
        }
        
        $remaining = $lockoutEnd - time();
        return max(0, $remaining);
    }

    private static function setAttempt(string $key, int $value): void {
        $key = self::sanitizeKey($key);
        $_SESSION["rate_limit_{$key}"] = $value;
    }

    private static function setFirstAttempt(string $key, int $value): void {
        $key = self::sanitizeKey($key);
        $_SESSION["rate_limit_first_{$key}"] = $value;
    }

    private static function setLockoutEnd(string $key, int $value): void {
        $key = self::sanitizeKey($key);
        $_SESSION["rate_limit_lockout_{$key}"] = $value;
    }

    private static function getLockoutEnd(string $key): int {
        $key = self::sanitizeKey($key);
        return (int)($_SESSION["rate_limit_lockout_{$key}"] ?? 0);
    }

    private static function sanitizeKey(string $key): string {
        return preg_replace('/[^a-zA-Z0-9_]/', '_', $key);
    }
}

function rate_limit_check(string $key): bool {
    return RateLimit::check($key);
}

function rate_limit_record(string $key): void {
    RateLimit::record($key);
}

function rate_limit_clear(string $key): void {
    RateLimit::clear($key);
}

function rate_limit_remaining(string $key): int {
    return RateLimit::getRemainingAttempts($key);
}

function rate_limit_lockout(string $key): int {
    return RateLimit::getLockoutRemaining($key);
}
