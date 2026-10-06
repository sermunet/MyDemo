<?php

/**
 * Aplica las migraciones pendientes de /migrations (AGENTS.md §6).
 *
 * Uso:  php scripts/migrate.php
 * Cada migración es un fichero PHP que devuelve:
 *   ['id' => '001_nombre', 'descripcion' => '...', 'up' => function (JsonDatabase $db): void]
 * Se ejecuta una sola vez y queda registrada en la colección `_migraciones`.
 */

require dirname(__DIR__) . '/src/helpers/autoload.php';
require dirname(__DIR__) . '/src/helpers/db.php';

$database     = db();
$migrations   = dirname(__DIR__) . '/migrations';
$registradas  = $database->collection('_migraciones');

$files = glob($migrations . '/*.php') ?: [];
sort($files);

if ($files === []) {
    fwrite(STDOUT, 'No hay migraciones en /migrations.' . PHP_EOL);
    exit(0);
}

foreach ($files as $file) {
    $migration = require $file;

    if (!is_array($migration) || empty($migration['id']) || !isset($migration['up']) || !is_callable($migration['up'])) {
        fwrite(STDERR, 'ERROR: migración mal formada: ' . basename($file) . PHP_EOL);
        exit(1);
    }

    $id = (string) $migration['id'];

    if ($registradas->findById($id) !== null) {
        fwrite(STDOUT, 'Omitida (ya aplicada): ' . $id . PHP_EOL);
        continue;
    }

    try {
        $database->transaction(
            static function (JsonDatabase $db) use ($registradas, $id, $migration, $file): void {
                ($migration['up'])($db);
                $registradas->insert([
                    'id'          => $id,
                    'descripcion' => (string) ($migration['descripcion'] ?? ''),
                    'archivo'     => basename($file),
                ]);
            }
        );
    } catch (Throwable $exception) {
        fwrite(STDERR, 'ERROR: no se pudo aplicar ' . $id . ': ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }

    fwrite(STDOUT, 'Aplicada: ' . $id . PHP_EOL);
}

fwrite(STDOUT, 'Migraciones al día.' . PHP_EOL);
