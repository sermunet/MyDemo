<?php

/**
 * Punto de entrada del BackOffice (AGENTS.md §2 y §8).
 *
 * El DocumentRoot es /public, así que esta es la única URL servida del panel:
 *  http://localhost:8000/backoffice/?accion=…
 *
 * Toda la lógica vive fuera de /public, en /backoffice (nada de la raíz se
 * sirve directamente al navegador): aquí solo se carga el front controller.
 */

require dirname(__DIR__, 2) . '/backoffice/app.php';
