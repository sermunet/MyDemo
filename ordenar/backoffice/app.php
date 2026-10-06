<?php

/**
 * BackOffice: front controller único (AGENTS.md §5 y §8).
 *
 * Enrutado por query string (/?accion=…&c=…&id=…) porque el DocumentRoot es
 * /public y el servidor de desarrollo no lleva router: ver AGENTS.md §8.
 *
 * GET  → mostrar (panel, login, listar, ver, crear, editar, eliminar/confirmar)
 * POST → mutar (login, logout, guardar, eliminar), siempre con token CSRF.
 */

require dirname(__DIR__) . '/src/helpers/autoload.php';
require dirname(__DIR__) . '/src/helpers/escape.php';
require dirname(__DIR__) . '/src/helpers/csrf.php';
require dirname(__DIR__) . '/src/helpers/db.php';
require dirname(__DIR__) . '/src/helpers/view.php';
require __DIR__ . '/helpers.php';

sessionStart();

/** Renderiza una vista dentro de la plantilla común. */
function boVista(string $template, array $datos = []): void
{
    ob_start();

    try {
        render($template, $datos);
    } catch (Throwable $exception) {
        ob_end_clean();
        throw $exception;
    }

    $contenido = ob_get_clean();

    render('backoffice/layout.php', array_merge($datos, ['contenido' => $contenido]));
}

/** Redirección 303 a otra pantalla del BackOffice. */
function boRedirigir(array $params): never
{
    header('Location: ' . boUrl($params), true, 303);
    exit;
}

/** Valida el CSRF de un POST; si falla, redirige con el mensaje flash. */
function boExigeCsrf(array $params): void
{
    if (!csrfVerify($_POST['csrf_token'] ?? null)) {
        boRedirigir(array_merge($params, ['flash' => 'csrf']));
    }
}

/** Documento actual (404 si no existe). */
function boDocumento(): array
{
    $doc = db()->collection(boColeccion())->findById(boId());

    if ($doc === null) {
        boAborta(404, 'Documento no encontrado.');
    }

    return $doc;
}

/**
 * Acción recibida. `accion` para GET, `do` para POST (whitelist estricta:
 * cualquier valor desconocido cae en el panel).
 */
function boAccion(): string
{
    $clave = (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') ? 'do' : 'accion';

    $valor = isset($_POST[$clave]) && is_string($_POST[$clave]) ? $_POST[$clave] : ($_GET['accion'] ?? 'panel');

    $permitidas = ['panel', 'login', 'listar', 'ver', 'crear', 'editar', 'eliminar', 'logout', 'guardar'];

    return is_string($valor) && in_array($valor, $permitidas, true) ? $valor : 'panel';
}

$metodo   = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$accion   = boAccion();
$usuario  = boUsuarioActual();

if ($accion !== 'login' && $accion !== 'logout') {
    $usuario = boRequireLogin();
}

try {
    switch ($accion) {
        /* ------------------------------- login ------------------------------- */

        case 'login':
            if ($metodo === 'POST') {
                boExigeCsrf(['accion' => 'login']);

                $resultado = boTryLogin(
                    trim((string) ($_POST['email'] ?? '')),
                    (string) ($_POST['password'] ?? '')
                );

                if ($resultado['ok']) {
                    boRedirigir(['accion' => 'panel', 'flash' => 'login']);
                }

                boVista('backoffice/login.php', [
                    'titulo' => 'Iniciar sesión',
                    'error'  => $resultado['error'],
                    'email'  => trim((string) ($_POST['email'] ?? '')),
                    'solo'   => true,
                ]);
            }

            if (boUsuarioActual() !== null) {
                boRedirigir(['accion' => 'panel']);
            }

            boVista('backoffice/login.php', [
                'titulo' => 'Iniciar sesión',
                'error'  => null,
                'email'  => '',
                'solo'   => true,
            ]);
            break;

        case 'logout':
            if ($metodo !== 'POST') {
                boRedirigir(['accion' => 'login']);
            }

            boExigeCsrf(['accion' => 'login']);
            boLogout();
            boRedirigir(['accion' => 'login', 'flash' => 'logout']);

        /* ------------------------------- panel ------------------------------- */

        case 'panel':
            $estadisticas = db()->stats();
            $colecciones  = array_values(array_filter(
                db()->collectionNames(),
                static fn (string $nombre): bool => !str_starts_with($nombre, '_')
            ));

            boVista('backoffice/panel.php', [
                'titulo'       => 'Panel',
                'usuario'      => $usuario,
                'colecciones'  => $colecciones,
                'estadisticas' => $estadisticas,
            ]);
            break;

        /* ------------------------------ listar ------------------------------- */

        case 'listar':
            $coleccion = boColeccion();
            $consulta  = isset($_GET['q']) && is_string($_GET['q']) ? mb_substr($_GET['q'], 0, 100) : '';

            $coleccionDb = db()->collection($coleccion);
            $documentos  = boOrdenar($coleccionDb->find([]));
            $resultados  = boBuscar($documentos, $consulta);

            $columnas = array_values(array_filter(
                boCamposDocumento($documentos),
                static fn (string $campo): bool => !boEsTimestamp($campo) && $campo !== 'id'
            ));
            $columnas = array_slice($columnas, 0, 4);

            boVista('backoffice/lista.php', [
                'titulo'      => $coleccion,
                'usuario'     => $usuario,
                'coleccion'   => $coleccion,
                'documentos'  => $resultados,
                'total'       => count($documentos),
                'columnas'    => $columnas,
                'consulta'    => $consulta,
            ]);
            break;

        /* -------------------------------- ver -------------------------------- */

        case 'ver':
            $coleccion = boColeccion();
            $doc       = boDocumento();

            boVista('backoffice/ver.php', [
                'titulo'     => $coleccion . ' · ' . ($doc['id'] ?? ''),
                'usuario'    => $usuario,
                'coleccion'  => $coleccion,
                'doc'        => $doc,
            ]);
            break;

        /* ------------------------------ crear -------------------------------- */

        case 'crear':
            $coleccion = boColeccion();
            $esqueleto = boEsqueleto($coleccion);
            $form      = boFormDesdeDocumento($esqueleto, boCamposColeccion(db()->collection($coleccion)));

            boVista('backoffice/form.php', [
                'titulo'     => 'Crear en ' . $coleccion,
                'usuario'    => $usuario,
                'coleccion'  => $coleccion,
                'modo'       => 'crear',
                'idEdit'     => '',
                'doc'        => $form['doc'],
                'tipos'      => $form['tipos'],
                'json'       => $form['json'],
                'borrar'     => $form['borrar'],
                'nuevo'      => $form['nuevo'],
                'modoActual' => $form['modo'],
                'error'      => null,
            ]);
            break;

        /* ------------------------------ editar ------------------------------- */

        case 'editar':
            $coleccion = boColeccion();
            $doc       = boDocumento();
            $form      = boFormDesdeDocumento($doc, boCamposColeccion(db()->collection($coleccion)));

            boVista('backoffice/form.php', [
                'titulo'     => 'Editar ' . ($doc['id'] ?? ''),
                'usuario'    => $usuario,
                'coleccion'  => $coleccion,
                'modo'       => 'editar',
                'idEdit'     => (string) $doc['id'],
                'doc'        => $form['doc'],
                'tipos'      => $form['tipos'],
                'json'       => $form['json'],
                'borrar'     => $form['borrar'],
                'nuevo'      => $form['nuevo'],
                'modoActual' => $form['modo'],
                'error'      => null,
            ]);
            break;

        /* ----------------------------- guardar (POST) ------------------------ */

        case 'guardar':
            if ($metodo !== 'POST') {
                boRedirigir(['accion' => 'panel']);
            }

            $coleccion = boColeccion();
            $idEdit    = isset($_GET['id']) && is_string($_GET['id']) ? $_GET['id'] : '';

            if ($idEdit !== '' && !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/', $idEdit)) {
                boAborta(400, 'Identificador de documento no válido.');
            }

            boExigeCsrf($idEdit === ''
                ? ['accion' => 'crear', 'c' => $coleccion]
                : ['accion' => 'editar', 'c' => $coleccion, 'id' => $idEdit]);

            $modoJson = (($_POST['modo'] ?? 'campos') === 'json');

            $resultado = $modoJson
                ? boDocumentoDesdeJson((string) ($_POST['json'] ?? ''), $idEdit)
                : boDocumentoDesdeCampos($_POST, $idEdit);

            if ($resultado['ok']) {
                try {
                    $coleccionDb = db()->collection($coleccion);

                    if ($idEdit === '') {
                        $coleccionDb->insert($resultado['doc']);
                        boRedirigir(['accion' => 'listar', 'c' => $coleccion, 'flash' => 'creado']);
                    }

                    $resultado['doc']['id'] = $idEdit;
                    $coleccionDb->upsert($resultado['doc']);
                    boRedirigir(['accion' => 'listar', 'c' => $coleccion, 'flash' => 'guardado']);
                } catch (RuntimeException $exception) {
                    error_log('[backoffice] ' . $exception->getMessage());
                    $resultado = [
                        'ok'    => false,
                        'error' => str_contains($exception->getMessage(), 'ya existe')
                            ? 'Ya existe un documento con ese id: usa otro distinto.'
                            : 'No se pudo guardar: ' . $exception->getMessage(),
                    ];
                } catch (Throwable $exception) {
                    error_log('[backoffice] ' . $exception->getMessage());
                    $resultado = ['ok' => false, 'error' => 'La base de datos no está disponible.'];
                }
            }

            // Con error se re-renderiza el formulario con lo que se había escrito.
            $form = boFormDesdePost($_POST, $idEdit, $modoJson);

            boVista('backoffice/form.php', [
                'titulo'     => ($idEdit === '' ? 'Crear en ' : 'Editar ') . $coleccion,
                'usuario'    => $usuario,
                'coleccion'  => $coleccion,
                'modo'       => $idEdit === '' ? 'crear' : 'editar',
                'idEdit'     => $idEdit,
                'doc'        => $form['doc'],
                'tipos'      => $form['tipos'],
                'json'       => $form['json'],
                'borrar'     => $form['borrar'],
                'nuevo'      => $form['nuevo'],
                'modoActual' => $form['modo'],
                'error'      => $resultado['error'] ?? null,
            ]);
            break;

        /* ----------------------------- eliminar ------------------------------ */

        case 'eliminar':
            $coleccion = boColeccion();
            $id        = boId();

            if ($metodo === 'POST') {
                boExigeCsrf(['accion' => 'eliminar', 'c' => $coleccion, 'id' => $id]);

                $borrado = db()->collection($coleccion)->delete($id);

                boRedirigir([
                    'accion' => 'listar',
                    'c'      => $coleccion,
                    'flash'  => $borrado ? 'borrado' : 'no-borrado',
                ]);
            }

            $doc = boDocumento();

            boVista('backoffice/eliminar.php', [
                'titulo'    => 'Eliminar ' . $doc['id'],
                'usuario'   => $usuario,
                'coleccion' => $coleccion,
                'doc'       => $doc,
            ]);
            break;

        default:
            boRedirigir(['accion' => 'panel']);
    }
} catch (Throwable $exception) {
    error_log('[backoffice] ' . $exception->getMessage());
    boAborta(500, 'Error en el BackOffice: ' . $exception->getMessage());
}
