<?php

/**
 * Carga las semillas de /seeds en la base de datos (AGENTS.md §6).
 *
 * Uso:  php scripts/seed.php
 * Idempotente: cada documento lleva "id" fijo, por lo que se sobrescribe en
 * vez de duplicar. Se puede ejecutar tantas veces como haga falta.
 */

require dirname(__DIR__) . '/src/helpers/autoload.php';
require dirname(__DIR__) . '/src/helpers/db.php';

$database = db();
$files    = glob(dirname(__DIR__) . '/seeds/*.json') ?: [];

sort($files);

if ($files === []) {
    fwrite(STDERR, 'No hay semillas en /seeds.' . PHP_EOL);
    exit(1);
}

foreach ($files as $file) {
    $collectionName = basename($file, '.json');

    try {
        $documents = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        fwrite(STDERR, 'ERROR: JSON no válido en ' . basename($file) . ': ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }

    if (!is_array($documents)) {
        fwrite(STDERR, 'ERROR: ' . basename($file) . ' debe contener una lista de documentos.' . PHP_EOL);
        exit(1);
    }

    $collection = $database->collection($collectionName);
    $collection->ensure();

    $count = 0;

    try {
        $database->transaction(
            static function () use ($collection, $documents, $collectionName, &$count): void {
                foreach ($documents as $document) {
                    if (!is_array($document) || empty($document['id']) || !is_string($document['id'])) {
                        throw new RuntimeException(
                            'Cada documento de ' . $collectionName . '.json necesita un campo "id" (cadena).'
                        );
                    }

                    $collection->upsert($document);
                    $count++;
                }
            }
        );
    } catch (Throwable $exception) {
        fwrite(STDERR, 'ERROR: no se pudo sembrar ' . $collectionName . ': ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }

    fwrite(STDOUT, sprintf('%-20s %d documento(s)%s', $collectionName, $count, PHP_EOL));
}

fwrite(STDOUT, 'Semillas cargadas.' . PHP_EOL);
