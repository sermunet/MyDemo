<?php

/**
 * Alta de suscripciones desde el formulario público (AGENTS.md §4 y §5).
 *
 * Lo enruta el front controller /api/index.php.
 *
 * Solo POST + token CSRF + validación en servidor; el JavaScript del cliente
 * no es seguridad. Guarda el documento en la colección `suscripciones` de la
 * base de datos NoSQL y redirige con un código de resultado (?suscripcion=…).
 *
 * En modo de solo lectura (Vercel, AGENTS.md §8) no se escribe nada: se avisa
 * con el mismo mecanismo de mensajes que el resto de la web.
 */

require_once dirname(__DIR__) . '/src/helpers/autoload.php';
require_once dirname(__DIR__) . '/src/helpers/escape.php';
require_once dirname(__DIR__) . '/src/helpers/csrf.php';
require_once dirname(__DIR__) . '/src/helpers/db.php';

sessionStart();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: /', true, 303);
    exit;
}

if (!csrfVerify($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>403 · Prohibido</title></head>'
        . '<body style="font-family: system-ui, sans-serif; padding: 3rem; text-align: center;">'
        . '<h1>403</h1><p>Token CSRF no válido o sesión caducada.</p>'
        . '<p><a href="/">Volver al inicio</a></p></body></html>';
    exit;
}

$email  = trim((string) ($_POST['email'] ?? ''));
$result = 'invalid';

if (appReadOnly()) {
    // Demo de solo lectura: no se intenta escribir en un disco que no lo permite.
    $result = filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? 'solo' : 'invalid';
} elseif (filter_var($email, FILTER_VALIDATE_EMAIL) !== false && strlen($email) <= 254) {
    $result = 'bd';

    try {
        $suscripciones = db()->collection('suscripciones');

        if ($suscripciones->exists(['email' => $email])) {
            $result = 'dup';
        } else {
            $suscripciones->insert(['email' => $email]);
            $result = 'ok';
        }
    } catch (ReadOnlyDatabaseException $exception) {
        error_log('[suscribir] ' . $exception->getMessage());
        $result = 'solo';
    } catch (Throwable $exception) {
        error_log('[suscribir] ' . $exception->getMessage());
        $result = 'bd';
    }
}

header('Location: /?suscripcion=' . rawurlencode($result), true, 303);
exit;
