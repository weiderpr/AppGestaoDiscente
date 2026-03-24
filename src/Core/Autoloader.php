<?php
/**
 * Vértice Acadêmico — Autoloader PSR-4
 */

spl_autoload_register(function (string $class): void {
    $prefixes = [
        'App\\' => __DIR__ . '/../App/',
        'Core\\' => __DIR__ . '/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }

        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});
