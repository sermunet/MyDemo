<?php

/**
 * Auto-test de la capa de datos NoSQL (AGENTS.md §5 y §6).
 *
 * Uso:  php scripts/self-test.php
 *
 * Comprueba CRUD, filtros, orden, paginación, transacciones con rollback y
 * validación de rutas. Trabaja SIEMPRE en un directorio temporal: no toca /data.
 * Sale con código 1 si algo falla.
 */

require dirname(__DIR__) . '/src/helpers/autoload.php';

$fail = 0;
$ok   = 0;

function check(string $label, bool $condition): void
{
    global $fail, $ok;

    if ($condition) {
        $ok++;
        echo "  OK    $label\n";
        return;
    }

    $fail++;
    echo "  FALLO $label\n";
}

$root = sys_get_temp_dir() . '/mydemo_jsondb_' . bin2hex(random_bytes(4));
$db   = new JsonDatabase($root);

$especies = $db->collection('especies');

// --- insert / findById ---
$doc = $especies->insert(['nombre' => 'Verde', 'orden' => 1, 'tags' => ['mar', 'algas'], 'peso' => 5.5]);
check('insert genera id', isset($doc['id']) && strlen($doc['id']) === 24);
check('insert añade created_at/updated_at', isset($doc['created_at'], $doc['updated_at']));
check('findById lo recupera', $especies->findById($doc['id'])['nombre'] === 'Verde');
check('findById inexistente → null', $especies->findById('no-existe') === null);

$id2 = $especies->insert(['id' => 'esp-2', 'nombre' => 'Carey', 'orden' => 2, 'peso' => 40])['id'];
$especies->insert(['id' => 'esp-3', 'nombre' => 'Lauda', 'orden' => 3, 'peso' => 400, 'estado' => 'borrador']);
check('insert con id propio', $id2 === 'esp-2');

$duplicado = false;

try {
    $especies->insert(['id' => 'esp-2', 'nombre' => 'Otra']);
} catch (RuntimeException $exception) {
    $duplicado = true;
}

check('insert no sobrescribe un id existente', $duplicado);

// --- filtros ---
check('filtro por igualdad', count($especies->find(['nombre' => 'Carey'])) === 1);
check('filtro $gte', count($especies->find(['peso' => ['$gte' => 40]])) === 2);
check('filtro rango $gte+$lt', count($especies->find(['peso' => ['$gte' => 5, '$lt' => 40]])) === 1);
check('filtro $ne', count($especies->find(['estado' => ['$ne' => 'borrador']])) === 2);
check('filtro $in', count($especies->find(['id' => ['$in' => ['esp-2', 'esp-3']]])) === 2);
check('filtro $contains (sin distinguir mayúsculas)', count($especies->find(['nombre' => ['$contains' => 'ver']])) === 1);
check('filtro campo inexistente', count($especies->find(['nope' => 'x'])) === 0);
check('filtro lista vs scalar (tags)', count($especies->find(['tags' => 'mar'])) === 1);
check(
    'filtro anidado con puntos',
    $especies->insert(['id' => 'esp-4', 'nombre' => 'Cabezona', 'orden' => 4, 'autor' => ['nombre' => 'Ana']]) !== null
    && count($especies->find(['autor.nombre' => 'Ana'])) === 1
);
check('operador de nivel superior rechazado', (static function () use ($especies): bool {
    try {
        $especies->find(['$or' => []]);
        return false;
    } catch (InvalidArgumentException $exception) {
        return true;
    }
})());

// --- orden, paginación y conteo ---
check('sort ascendente', $especies->find([], ['sort' => ['orden' => 1]])[0]['nombre'] === 'Verde');
check('sort descendente', $especies->find([], ['sort' => ['orden' => -1]])[0]['id'] === 'esp-4');
check('limit', count($especies->find([], ['limit' => 2])) === 2);
check(
    'skip + limit (paginación)',
    $especies->find([], ['sort' => ['orden' => 1], 'skip' => 1, 'limit' => 1])[0]['id'] === 'esp-2'
);
check('count total', $especies->count() === 4);
check('count con filtro', $especies->count(['peso' => ['$gte' => 40]]) === 2);
check('findOne', $especies->findOne(['nombre' => 'Lauda'])['id'] === 'esp-3');
check('sort con dirección inválida → error', (static function () use ($especies): bool {
    try {
        $especies->find([], ['sort' => ['orden' => 9]]);
        return false;
    } catch (InvalidArgumentException $exception) {
        return true;
    }
})());

// --- update / upsert ---
$actualizado = $especies->update('esp-2', ['nombre' => 'Carey modificado', 'estado' => 'publicado', 'autor.nombre' => 'Ana']);
check('update devuelve el documento', $actualizado['nombre'] === 'Carey modificado');
check('update con notación de puntos', $especies->findById('esp-2')['autor']['nombre'] === 'Ana');
check('update toca updated_at', $actualizado['updated_at'] >= $actualizado['created_at']);
check('update no cambia el id', $especies->findById('esp-2')['id'] === 'esp-2');
check('update de id inexistente → null', $especies->update('no-existe', ['a' => 1]) === null);

$antes     = $especies->findById('esp-2');
$especies->upsert(['id' => 'esp-2', 'nombre' => 'Reemplazado']);
$reemplazado = $especies->findById('esp-2');
check('upsert reemplaza sin duplicar', $especies->count(['id' => 'esp-2']) === 1 && $reemplazado['nombre'] === 'Reemplazado');
check('upsert conserva created_at', $reemplazado['created_at'] === $antes['created_at']);
check('upsert de id nuevo → insert', $especies->upsert(['id' => 'esp-9', 'nombre' => 'Nueva']) !== null);

// --- transacciones ---
try {
    $db->transaction(static function (JsonDatabase $tx): void {
        $tx->collection('especies')->insert(['id' => 'tx-1', 'nombre' => 'Dentro']);
        $tx->collection('especies')->update('esp-9', ['nombre' => 'Cambiado']);
        throw new RuntimeException('fallo deliberado');
    });
    check('transacción: la excepción se relanza', false);
} catch (RuntimeException $exception) {
    check('transacción: la excepción se relanza', $exception->getMessage() === 'fallo deliberado');
}

check('rollback: el insert se revierte', $especies->findById('tx-1') === null);
check('rollback: el update se revierte', $especies->findById('esp-9')['nombre'] === 'Nueva');

$resultado = $db->transaction(static function (JsonDatabase $tx): string {
    $tx->collection('especies')->insert(['id' => 'tx-2', 'nombre' => 'Confirmado']);
    return 'hecho';
});
check('commit: devuelve el resultado', $resultado === 'hecho');
check('commit: los datos persisten', $especies->findById('tx-2') !== null);

// --- borrado ---
check('delete existente', $especies->delete('tx-2') === true);
check('delete inexistente', $especies->delete('tx-2') === false);
$borrados = $especies->deleteWhere(['id' => ['$exists' => true]]);
check('deleteWhere borra lo que coincide', $borrados > 0 && $especies->count() === 0);

// --- seguridad de rutas y nombres ---
$traversal = false;

try {
    $especies->findById('../../config/config');
} catch (InvalidArgumentException $exception) {
    $traversal = true;
}

check('id con path traversal rechazado', $traversal);

$coleccionMala = false;

try {
    new JsonCollection($db, '../fuera');
} catch (InvalidArgumentException $exception) {
    $coleccionMala = true;
}

check('nombre de colección con ../ rechazado', $coleccionMala);
check('collectionNames()', $db->collectionNames() === ['especies']);
check('stats() cuenta documentos', $db->stats() === ['especies' => 0]);

// --- limpieza del directorio temporal ---
foreach (glob($root . '/especies/*.json') ?: [] as $fichero) {
    unlink($fichero);
}

@rmdir($root . '/especies');
@unlink($root . '/.lock');
@rmdir($root);

echo PHP_EOL . 'Resultado: ' . $ok . ' correctas, ' . $fail . ' fallos' . PHP_EOL;

exit($fail === 0 ? 0 : 1);
