<?php

/**
 * Punto de entrada de la web pública (AGENTS.md §2).
 *
 * Comprueba la conexión con la base de datos NoSQL, lee el contenido de las
 * colecciones y renderiza la landing. Si la BD no está disponible, la página
 * se muestra con un aviso en lugar de romperse.
 */

require dirname(__DIR__) . '/src/helpers/autoload.php';
require dirname(__DIR__) . '/src/helpers/escape.php';
require dirname(__DIR__) . '/src/helpers/csrf.php';
require dirname(__DIR__) . '/src/helpers/db.php';
require dirname(__DIR__) . '/src/helpers/view.php';

sessionStart();

$dbOk          = true;
$dbMessage     = '';
$estadisticas  = [];
$especies      = [];
$curiosidades  = [];
$protecciones  = [];

try {
    $db = db();

    $estadisticas = $db->collection('estadisticas')->find([], ['sort' => ['orden' => 1]]);
    $especies     = $db->collection('especies')->find([], ['sort' => ['orden' => 1]]);
    $curiosidades = $db->collection('curiosidades')->find([], ['sort' => ['orden' => 1]]);
    $protecciones = $db->collection('protecciones')->find([], ['sort' => ['orden' => 1]]);

    $dbMessage = sprintf(
        'Conexión correcta con la BD NoSQL: %d especies y %d suscriptor(es).',
        count($especies),
        $db->collection('suscripciones')->count()
    );
} catch (Throwable $exception) {
    $dbOk      = false;
    $dbMessage = $exception->getMessage();

    error_log('[index] ' . $exception->getMessage());
}

/**
 * Mensajes de la suscripción (whitelist: nunca se imprime el valor de
 * $_GET directamente, AGENTS.md §4).
 */
$flashMessages = [
    'ok'      => '✅ ¡Gracias! Te has suscrito correctamente 🐢',
    'dup'     => 'ℹ️ Ese correo ya estaba suscrito: no hace falta volver a hacerlo.',
    'invalid' => '✕ Ese correo no parece válido. Revísalo e inténtalo de nuevo.',
    'csrf'    => '✕ Tu sesión ha caducado. Vuelve a intentarlo.',
    'bd'      => '✕ No se pudo guardar la suscripción: la base de datos no está disponible.',
];

$code         = isset($_GET['suscripcion']) && is_string($_GET['suscripcion']) ? $_GET['suscripcion'] : null;
$flashMessage = $code !== null ? ($flashMessages[$code] ?? null) : null;

render('home.php', [
    'dbOk'          => $dbOk,
    'dbMessage'     => $dbMessage,
    'flashMessage'  => $flashMessage,
    'estadisticas'  => $estadisticas,
    'especies'      => $especies,
    'curiosidades'  => $curiosidades,
    'protecciones'  => $protecciones,
]);
