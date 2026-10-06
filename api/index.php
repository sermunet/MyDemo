<?php

/**
 * Front controller único (AGENTS.md §2 y §8).
 *
 * Es el único punto de entrada de PHP, tanto en Vercel como en local:
 *
 *   - Vercel:      `vercel.json` enruta todo a /api/index.php (runtime vercel-php).
 *   - Local:       `php -S localhost:8000 api/index.php`.
 *
 * Por eso /public solo contiene assets estáticos: en Vercel todo lo que hay en
 * /public se sirve tal cual, así que dentro no puede haber ni un `.php` (se
 * filtraría el código fuente). Los front controllers de verdad viven en /web
 * (web pública) y /backoffice (panel), fuera del árbol estático.
 */

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

/**
 * Rutas del servidor de desarrollo: `php -S … api/index.php` no sirve los
 * ficheros de /public por su cuenta, así que se delegan con `return false`.
 * En Vercel esto nunca se ejecuta: la ruta `{ "handle": "filesystem" }` de
 * vercel.json ya ha servido el asset antes de llegar aquí.
 */
$path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
$path = '/' . ltrim(rawurldecode($path), '/');

$asset = APP_ROOT . '/public' . $path;

if ($path !== '/' && is_file($asset) && !str_ends_with(strtolower($asset), '.php')) {
    return false; // Que lo sirva el servidor embebido como fichero estático.
}

/** Normaliza a una ruta sin barra final para compararla con una whitelist. */
$normalizada = rtrim($path, '/');

if ($normalizada === '') {
    $normalizada = '/';
}

/* -------------------------------------------------------------------------- */
/* Enrutado: tabla explícita, nunca un include con lo que llega del navegador.  */
/* -------------------------------------------------------------------------- */

switch (true) {
    case $normalizada === '/' || $normalizada === '/index.php' || $normalizada === '/index.html':
        require APP_ROOT . '/web/index.php';
        break;

    case $normalizada === '/suscribir.php' || $normalizada === '/suscribir':
        require APP_ROOT . '/web/suscribir.php';
        break;

    case $normalizada === '/backoffice' || str_starts_with($normalizada, '/backoffice/'):
        require APP_ROOT . '/backoffice/app.php';
        break;

    case $normalizada === '/robots.txt':
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\nDisallow: /backoffice/\n";
        break;

    case $normalizada === '/healthz':
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true], JSON_UNESCAPED_SLASHES);
        break;

    default:
        // htmlspecialchars en línea: el front controller no carga src/helpers (los
        // módulos que enruta los cargan con require y redeclararían sus funciones).
        $ruta = htmlspecialchars($path, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
            . '<title>404 · No encontrado</title>'
            . '<link rel="stylesheet" href="/assets/css/style.css"></head>'
            . '<body><main class="container" style="padding:6rem 1.5rem;text-align:center">'
            . '<h1>404</h1><p>La página <code>' . $ruta . '</code> no existe.</p>'
            . '<p><a class="btn btn-primary" href="/">Volver al inicio</a></p>'
            . '</main></body></html>';
        break;
}
