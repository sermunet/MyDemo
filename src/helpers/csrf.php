<?php

/**
 * CSRF (AGENTS.md §4): token por sesión validado en todos los POST.
 *
 * El token vive en la sesión firmada en cookie (src/helpers/session.php); al no
 * haber estado en servidor, la comparación se hace con `hash_equals`.
 */

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/escape.php';

function csrfToken(): string
{
    sessionStart();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function csrfVerify(mixed $token): bool
{
    sessionStart();

    return is_string($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals((string) $_SESSION['csrf_token'], $token);
}
