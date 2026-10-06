<?php

/**
 * Comprobación de conexión con la base de datos NoSQL (AGENTS.md §7).
 *
 * Uso:  php scripts/check-db.php
 * Sale con código 1 si la BD no está disponible.
 */

require dirname(__DIR__) . '/src/helpers/autoload.php';
require dirname(__DIR__) . '/src/helpers/db.php';

$config   = loadConfig();
$dbConfig = $config['db'] ?? [];

fwrite(STDOUT, 'Entorno : ' . ($config['env'] ?? 'desconocido') . PHP_EOL);
fwrite(STDOUT, 'Driver  : ' . ($dbConfig['driver'] ?? 'json') . ' (almacén documental JSON, sin MySQL)' . PHP_EOL);

try {
    $database = db();
} catch (Throwable $exception) {
    fwrite(STDERR, 'ERROR   : ' . $exception->getMessage() . PHP_EOL);
    fwrite(STDERR, '          Comprueba el directorio de datos y ejecuta: php scripts/migrate.php && php scripts/seed.php' . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, 'Datos   : ' . $database->root() . PHP_EOL);

$stats = $database->stats();

if ($stats === []) {
    fwrite(STDOUT, 'Estado  : conexión correcta, pero aún no hay colecciones.' . PHP_EOL);
    fwrite(STDOUT, '          Inicializa la BD: php scripts/migrate.php && php scripts/seed.php' . PHP_EOL);
    exit(0);
}

fwrite(STDOUT, 'Estado  : conexión correcta' . PHP_EOL . PHP_EOL);
fwrite(STDOUT, 'Colecciones:' . PHP_EOL);

foreach ($stats as $name => $count) {
    fwrite(STDOUT, sprintf('  %-20s %4d documento(s)%s', $name, $count, PHP_EOL));
}

fwrite(STDOUT, PHP_EOL . 'Total: ' . array_sum($stats) . ' documento(s).' . PHP_EOL);

$sample = $database->collection('especies')->findOne();

if ($sample !== null) {
    fwrite(
        STDOUT,
        PHP_EOL . 'Ejemplo (especies):' . PHP_EOL
        . json_encode($sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL
    );
}

exit(0);
