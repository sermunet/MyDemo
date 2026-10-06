<?php

/**
 * Configuración local de desarrollo (no se versiona: ver .gitignore).
 * Base de datos NoSQL documental en disco (AGENTS.md §6): sin MySQL,
 * sin credenciales y sin servidor externo.
 */

return [
    'env' => 'local',

    'db' => [
        'driver'  => 'json',
        'dataDir' => dirname(__DIR__) . '/data',
    ],
];
