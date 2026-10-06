<?php

/**
 * Conexión a la base de datos NoSQL (almacén documental JSON en disco,
 * AGENTS.md §3 y §6). Todas las lecturas y escrituras pasan por db() →
 * JsonDatabase/JsonCollection. Nunca se tocan los ficheros de /data a mano.
 */

require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/app.php';

/**
 * Configuración por capas, de menos a más específica:
 *
 *   1. config/config.example.php  → base, siempre versionada.
 *   2. config/config.php          → ajustes locales (opcional, está en .gitignore).
 *   3. Variables de entorno       → lo que define el hosting (Vercel).
 *
 * Así el mismo código funciona en local y en Vercel sin tocar ficheros: allí
 * `config/config.php` no existe y todo se resuelve con variables de entorno.
 */
function loadConfig(): array
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $ejemplo = dirname(__DIR__, 2) . '/config/config.example.php';
    $local   = dirname(__DIR__, 2) . '/config/config.php';

    if (!is_file($ejemplo)) {
        throw new RuntimeException('Falta la configuración base: config/config.example.php.');
    }

    $config = require $ejemplo;

    if (is_file($local)) {
        $config = array_replace_recursive($config, require $local);
    }

    $config = array_replace_recursive($config, configDesdeEntorno());

    return $config;
}

/**
 * Ajustes que llegan del entorno de ejecución (Vercel y compañía).
 *
 * @return array<string, mixed>
 */
function configDesdeEntorno(): array
{
    $ajustes = ['env' => appEnv()];

    $soloLectura = getenv('APP_READONLY');

    if (is_string($soloLectura) && $soloLectura !== '') {
        $ajustes['readOnly'] = in_array(mb_strtolower($soloLectura), ['1', 'true', 'si', 'sí', 'yes', 'on'], true);
    } elseif (getenv('VERCEL_ENV') !== false) {
        // En Vercel el disco es de solo lectura (AGENTS.md §8): aunque nadie lo
        // configured, la app se abre en modo lectura en vez de romperse al
        // intentar escribir.
        $ajustes['readOnly'] = true;
    }

    $dataDir = getenv('APP_DATA_DIR');

    if (is_string($dataDir) && $dataDir !== '') {
        $ajustes['db'] = ['dataDir' => $dataDir];
    }

    return $ajustes;
}

/**
 * ¿La base de datos está en modo de solo lectura?
 *
 * En modo lectura no hay altas, ni ediciones ni borrados: el BackOffice y el
 * formulario de suscripción lo consultan y lo muestran, pero cualquier
 * escritura falla con ReadOnlyDatabaseException.
 */
function appReadOnly(): bool
{
    return (bool) (loadConfig()['readOnly'] ?? false);
}

function db(): JsonDatabase
{
    static $database = null;

    if ($database instanceof JsonDatabase) {
        return $database;
    }

    $dbConfig = loadConfig()['db'] ?? [];

    $driver = $dbConfig['driver'] ?? 'json';

    if ($driver !== 'json') {
        throw new RuntimeException(
            'Driver de base de datos no soportado: "' . $driver
            . '". Este proyecto trabaja sin MySQL: usa el almacén documental JSON ("json").'
        );
    }

    $dataDir = $dbConfig['dataDir'] ?? dirname(__DIR__, 2) . '/data';

    try {
        $database = new JsonDatabase($dataDir, appReadOnly());
    } catch (RuntimeException $exception) {
        throw new RuntimeException(
            'No se puede conectar a la base de datos NoSQL en "' . $dataDir . '": '
            . $exception->getMessage(),
            0,
            $exception
        );
    }

    return $database;
}
