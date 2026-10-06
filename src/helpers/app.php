<?php

/**
 * Entorno y secreto de la aplicación (AGENTS.md §4).
 *
 * No depende de config/ ni de la base de datos: lo usan tanto la sesión como
 * la capa de datos, que se cargan en ese orden.
 *
 * En Vercel las variables de entorno son la única configuración posible
 * (config/config.php no se versiona y el disco es de solo lectura), así que
 * aquí se leen `APP_ENV`, `APP_KEY` y `VERCEL_ENV`.
 */

declare(strict_types=1);

/**
 * Entiente de ejecución: 'local' | 'production'.
 *
 * `VERCEL_ENV` lo define Vercel solo (production / preview / development),
 * así que en Vercel siempre se resuelve como 'production' y las cookies de
 * sesión van con `secure`.
 */
function appEnv(): string
{
    $entorno = getenv('APP_ENV');

    if (is_string($entorno) && $entorno !== '') {
        return $entorno;
    }

    if (getenv('VERCEL_ENV') !== false) {
        return 'production';
    }

    return 'local';
}

/** ¿Se está en producción? (cookies `secure`, errores no visibles, etc.) */
function appIsProduction(): bool
{
    return appEnv() === 'production';
}

/**
 * Secreto para firmar cookies de sesión (HMAC-SHA256).
 *
 * Orden de resolución:
 *   1. `APP_KEY` (variable de entorno). **Obligatoria en Vercel**: sin ella
 *      cada instancia firmaría distinto y las sesiones fallarían al azar.
 *   2. `config/.session.key`, generado una vez en local (está en .gitignore).
 *   3. Una clave fija de desarrollo, solo como último recurso.
 */
function appKey(): string
{
    static $clave = null;

    if ($clave !== null) {
        return $clave;
    }

    $entorno = getenv('APP_KEY');

    if (is_string($entorno) && $entorno !== '') {
        return $clave = $entorno;
    }

    $fichero = dirname(__DIR__, 2) . '/config/.session.key';

    if (is_file($fichero)) {
        $contenido = trim((string) @file_get_contents($fichero));

        if ($contenido !== '') {
            return $clave = $contenido;
        }
    }

    // Local: se genera una vez y se reutiliza en las siguientes peticiones.
    $generada = bin2hex(random_bytes(32));

    if (@file_put_contents($fichero, $generada) !== false) {
        @chmod($fichero, 0600);

        return $clave = $generada;
    }

    // Sin APP_KEY y sin disco escribible: solo puede ser un entorno de
    // producción mal configurado. Se avisa en el log en vez de fallar.
    error_log(
        '[app] No hay APP_KEY ni config/.session.key escribible: se usa una clave fija. '
        . 'Define APP_KEY en el entorno o las sesiones no serán fiables.'
    );

    return $clave = 'dev-insecure-define-APP_KEY-en-produccion';
}
