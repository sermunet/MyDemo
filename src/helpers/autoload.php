<?php

/**
 * Autoload simple sin dependencias: busca la clase en models, services y
 * controllers (AGENTS.md §2 → /src).
 */

spl_autoload_register(static function (string $class): void {
    $bases = [
        dirname(__DIR__) . '/models/',
        dirname(__DIR__) . '/services/',
        dirname(__DIR__) . '/controllers/',
    ];

    foreach ($bases as $base) {
        $file = $base . $class . '.php';
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});
