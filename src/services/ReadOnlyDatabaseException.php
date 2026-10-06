<?php

/**
 * Error de escritura en una base de datos abierta en modo de solo lectura
 * (AGENTS.md §8).
 *
 * Es una excepción propia y no un RuntimeException genérico para que el
 * BackOffice distinga "aquí no se puede escribir" de "la base de datos está
 * caída" y pueda avisar con el mensaje correcto.
 */

declare(strict_types=1);

final class ReadOnlyDatabaseException extends RuntimeException
{
    public function __construct(string $message = 'La base de datos está en modo de solo lectura.')
    {
        parent::__construct($message);
    }
}
