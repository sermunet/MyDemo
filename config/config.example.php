<?php

/**
 * Plantilla de configuración (AGENTS.md §6).
 *
 * Copiar a config/config.php y ajustar los valores del entorno local.
 * config/config.php está en .gitignore: nunca subir credenciales reales.
 *
 * El proyecto NO usa MySQL: la base de datos es un almacén NoSQL documental
 * (un fichero JSON por documento) dentro de `dataDir`.
 */

return [
    'env' => 'local', // 'local' | 'production' (en producción las cookies de sesión son secure)

    'db' => [
        'driver'  => 'json',                     // único driver soportado: almacén documental JSON en disco
        'dataDir' => dirname(__DIR__) . '/data', // colección = carpeta, documento = fichero JSON
    ],
];
