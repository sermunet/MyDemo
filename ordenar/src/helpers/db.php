<?php

/**
 * Conexión a la base de datos NoSQL (almacén documental JSON en disco,
 * AGENTS.md §3 y §6). Todas las lecturas y escrituras pasan por db() →
 * JsonDatabase/JsonCollection. Nunca se tocan los ficheros de /data a mano.
 */

require_once __DIR__ . '/autoload.php';

function loadConfig(): array
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $local   = dirname(__DIR__, 2) . '/config/config.php';
    $example = dirname(__DIR__, 2) . '/config/config.example.php';

    if (is_file($local)) {
        $config = require $local;
    } elseif (is_file($example)) {
        $config = require $example;
    } else {
        throw new RuntimeException(
            'Falta la configuración: copia config/config.example.php a config/config.php.'
        );
    }

    return $config;
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
        $database = new JsonDatabase($dataDir);
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
