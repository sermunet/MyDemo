<?php

/**
 * Migración 001: crea las colecciones que necesita la web pública.
 * (AGENTS.md §6: todo cambio de esquema se hace por migración, nunca a mano.)
 */

return [
    'id'          => '001_esquema_inicial',
    'descripcion' => 'Crea las colecciones de la web pública.',
    'up'          => static function (JsonDatabase $db): void {
        $colecciones = [
            'especies',
            'estadisticas',
            'curiosidades',
            'protecciones',
            'suscripciones',
        ];

        foreach ($colecciones as $coleccion) {
            $db->collection($coleccion)->ensure();
        }
    },
];
