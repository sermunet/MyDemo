<?php

/**
 * Migración 002: crea la colección `usuarios` del BackOffice (AGENTS.md §4 y §6).
 *
 * El usuario inicial (admin) lo carga `seeds/usuarios.json` con contraseña
 * cifrada con password_hash() (BCRYPT). Nunca se guarda contraseña en claro.
 */

return [
    'id'          => '002_usuarios_backoffice',
    'descripcion' => 'Crea la colección de usuarios del BackOffice.',
    'up'          => static function (JsonDatabase $db): void {
        $db->collection('usuarios')->ensure();
    },
];
