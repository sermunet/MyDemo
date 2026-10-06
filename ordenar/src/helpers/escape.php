<?php

/**
 * Escapado de salida (AGENTS.md §4): todo dato que se imprime en HTML pasa
 * por aquí. Nunca concatenar entradas del usuario a mano.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
