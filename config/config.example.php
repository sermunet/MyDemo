<?php

/**
 * Plantilla de configuración (AGENTS.md §6).
 *
 * Este fichero SÍ se versiona y es la base de la configuración en cualquier
 * entorno. `config/config.php` (opcional, en .gitignore) lo sobrescribe para el
 * desarrollo local, y las variables de entorno lo ajustan por último.
 *
 * El proyecto NO usa MySQL: la base de datos es un almacén NoSQL documental
 * (un fichero JSON por documento) dentro de `dataDir`.
 *
 * Variables de entorno:
 *   APP_ENV      'local' | 'production' (por defecto 'production' en Vercel)
 *   APP_READONLY '1' para forzar el modo de solo lectura
 *   APP_KEY      secreto HMAC de las cookies de sesión (obligatoria en Vercel)
 *   APP_DATA_DIR ruta alternativa del almacén documental
 */

declare(strict_types=1);

return [
    // 'local' | 'production' (en producción las cookies de sesión son secure)
    'env' => 'local',

    /**
     * Modo de solo lectura: la base de datos se abre sin exigir escritura y
     * cualquier intento de modificar un documento falla con una excepción
     * propia en lugar de un error de permisos.
     *
     * Es lo que hace falta en Vercel, donde el disco es de solo lectura
     * (AGENTS.md §8). Por defecto se activa solo si detecta Vercel, así que en
     * local el CRUD completo sigue funcionando.
     */
    'readOnly' => false,

    'db' => [
        'driver'  => 'json',                     // único driver soportado: almacén documental JSON en disco
        'dataDir' => dirname(__DIR__) . '/data', // colección = carpeta, documento = fichero JSON
    ],
];
