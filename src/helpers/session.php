<?php

/**
 * Sesión en cookie firmada (AGENTS.md §4).
 *
 * Sustituye a `session_start()` de PHP a propósito: en Vercel el sistema de
 * ficheros es de solo lectura y `/tmp` no se comparte entre instancias, así que
 * una sesión en fichero se perdería en cuanto la petición landingase en otra
 * instancia (el login del BackOffice fallaría "a ratos").
 *
 * Los datos van dentro de la propia cookie, firmados con HMAC-SHA256 y `APP_KEY`:
 * el cliente no puede alterarlos sin romper la firma. El contenido es mínimo
 * (usuario_id, nombre, token CSRF e intentos de login), muy por debajo del
 * límite de ~4 KB de una cookie.
 *
 * Límite conocido: al vivir en el cliente, el contador `login_attempts` de la
 * protections contra fuerza bruta se reinicia si el usuario borra la cookie. Es
 * la contrapartida habitual de las sesiones sin estado en serverless.
 */

declare(strict_types=1);

require_once __DIR__ . '/app.php';

const SESSION_COOKIE_NAME = 'mydemo_sid';
const SESSION_TTL          = 43200;  // 12 horas de validez en el servidor
const SESSION_MAX_SIZE     = 3800;   // margen de seguridad sobre las ~4 KB del navegador

/**
 * Estado interno de la sesión. Se devuelve por referencia para poder mutarlo
 * desde funciones auxiliares sin variables globales.
 *
 * @return array{started: bool, dirty: bool, destroyed: bool, sid: string, data: array}
 */
function &sessionState(): array
{
    static $estado = [
        'started'   => false,
        'dirty'     => false,
        'destroyed' => false,
        'sid'       => '',
        'data'      => [],
    ];

    return $estado;
}

/**
 * Arranca la sesión y rellena `$_SESSION`.
 *
 * Además abre un búfer de salida: hace falta para poder enviar la cabecera
 * Set-Cookie al final, cuando el HTML ya está generado pero todavía no se ha
 * escrito nada en la respuesta.
 */
function sessionStart(): void
{
    $estado = &sessionState();

    if ($estado['started']) {
        return;
    }

    $estado['started']   = true;
    $estado['destroyed'] = false;
    $estado['dirty']     = false;
    $estado['sid']       = '';
    $estado['data']      = [];

    $carga = sessionRead((string) ($_COOKIE[SESSION_COOKIE_NAME] ?? ''));

    if ($carga !== null) {
        $estado['sid']  = (string) ($carga['sid'] ?? '');
        $estado['data'] = is_array($carga['d'] ?? null) ? $carga['d'] : [];
    }

    $_SESSION = $estado['data'];

    register_shutdown_function('sessionPersist');

    if (!headers_sent() && ob_get_level() === 0) {
        ob_start();
    }
}

/** ¿La sesión de esta petición está iniciada? */
function sessionIsStarted(): bool
{
    $estado = &sessionState();

    return $estado['started'];
}

/** Identificador de sesión actual (vacío si no hay cookie válida). */
function sessionId(): string
{
    $estado = &sessionState();

    return $estado['sid'];
}

/**
 * Identificador nuevo conservando los datos: se llama tras un login correcto
 * para evitar el secuestro de sesión por fijar el identificador (AGENTS.md §4).
 */
function sessionRegenerate(): void
{
    $estado = &sessionState();

    $estado['sid']   = bin2hex(random_bytes(16));
    $estado['dirty'] = true;
}

/** Vacía la sesión y caduca la cookie en el navegador. */
function sessionDestroy(): void
{
    $estado = &sessionState();

    $estado['destroyed'] = true;
    $estado['sid']       = '';
    $estado['data']      = [];

    $_SESSION = [];

    sessionSendCookie('', time() - 42000);
}

/* --------------------------------------------------------------------------- */
/* Persistencia                                                                */
/* --------------------------------------------------------------------------- */

/** Escribe la cookie al terminar la petición y vacía los búferes de salida. */
function sessionPersist(): void
{
    $estado = &sessionState();

    if ($estado['started'] && !$estado['destroyed']) {
        if ($_SESSION !== $estado['data']) {
            $estado['data']  = $_SESSION;
            $estado['dirty'] = true;
        }

        if ($estado['dirty']) {
            sessionWrite($estado['sid'] !== '' ? $estado['sid'] : bin2hex(random_bytes(16)), $estado['data']);
        }
    }

    while (ob_get_level() > 0) {
        ob_end_flush();
    }
}

/** Firma y manda la cookie con los datos de sesión. */
function sessionWrite(string $sid, array $data): void
{
    $json = json_encode(
        ['sid' => $sid, 'iat' => time(), 'exp' => time() + SESSION_TTL, 'd' => $data],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    if ($json === false) {
        error_log('[session] No se pudo serializar la sesión.');
        return;
    }

    $cuerpo = sessionBase64UrlEncode($json);
    $firma  = sessionBase64UrlEncode(hash_hmac('sha256', $cuerpo, appKey(), true));
    $valor  = $cuerpo . '.' . $firma;

    if (strlen($valor) > SESSION_MAX_SIZE) {
        error_log('[session] La sesión supera los ' . SESSION_MAX_SIZE . ' bytes y no se guarda.');

        return;
    }

    sessionSendCookie($valor, 0);
}

/** Envía la cabecera Set-Cookie con los atributos de AGENTS.md §4. */
function sessionSendCookie(string $valor, int $expira): void
{
    if (headers_sent()) {
        error_log('[session] No se puede guardar la cookie: las cabeceras ya se enviaron.');

        return;
    }

    setcookie(SESSION_COOKIE_NAME, $valor, [
        'expires'  => $expira,   // 0 = cookie de sesión (se caduca al cerrar el navegador)
        'path'     => '/',
        'secure'   => appIsProduction(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/* --------------------------------------------------------------------------- */
/* Lectura y firma                                                             */
/* --------------------------------------------------------------------------- */

/**
 * Valida la cookie y devuelve su carga útil, o null si no vale (ausente,
 * manipulada, caducada o de una versión anterior).
 *
 * @return array{sid?: string, iat?: int, exp?: int, d?: array}|null
 */
function sessionRead(string $cookie): ?array
{
    if ($cookie === '' || !str_contains($cookie, '.')) {
        return null;
    }

    [$cuerpo, $firma] = explode('.', $cookie, 2);

    $esperada = sessionBase64UrlEncode(hash_hmac('sha256', $cuerpo, appKey(), true));

    if (!hash_equals($esperada, $firma)) {
        return null;
    }

    $json = sessionBase64UrlDecode($cuerpo);

    if ($json === '') {
        return null;
    }

    $carga = json_decode($json, true);

    if (!is_array($carga) || (int) ($carga['exp'] ?? 0) <= time()) {
        return null;
    }

    return $carga;
}

function sessionBase64UrlEncode(string $binario): string
{
    return rtrim(strtr(base64_encode($binario), '+/', '-_'), '=');
}

function sessionBase64UrlDecode(string $texto): string
{
    $decodificado = base64_decode(strtr($texto, '-_', '+/'), true);

    return $decodificado === false ? '' : $decodificado;
}
