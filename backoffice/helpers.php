<?php

/**
 * Helpers del BackOffice (AGENTS.md §4 y §5).
 *
 * Reglas que aquí se hacen cumplir:
 *  - Ninguna ruta funciona sin `boRequireLogin()` (rol admin verificado).
 *  - Todo POST valida el token CSRF antes de tocar la base de datos.
 *  - Los identificadores que llegan del navegador pasan SIEMPRE por una
 *    whitelist: colección contra las carpetas reales de /data y `id` contra
 *    la misma expresión regular que usa JsonCollection.
 *  - Toda la lectura/escritura pasa por db() → JsonCollection: aquí no se
 *    abre, lee ni borra ningún fichero de /data a mano.
 */

/** Acciones GET que no exigen estar logueado. */
function boAccionesPublicas(): array
{
    return ['login'];
}

/** Usuario de la sesión actual, o null si no hay sesión válida. */
function boUsuarioActual(): ?array
{
    sessionStart();

    $id = $_SESSION['usuario_id'] ?? null;

    if (!is_string($id) || $id === '') {
        return null;
    }

    try {
        $usuario = db()->collection('usuarios')->findById($id);
    } catch (Throwable $exception) {
        error_log('[backoffice] ' . $exception->getMessage());
        return null;
    }

    if ($usuario === null || ($usuario['activo'] ?? false) !== true) {
        return null;
    }

    if (($usuario['rol'] ?? '') !== 'admin') {
        return null;
    }

    return $usuario;
}

/**
 * Exige sesión de administrador para la petición actual (AGENTS.md §4).
 * Sin sesión válida → redirect 303 a la pantalla de login.
 */
function boRequireLogin(): array
{
    $usuario = boUsuarioActual();

    if ($usuario === null) {
        header('Location: ' . boUrl(['accion' => 'login']), true, 303);
        exit;
    }

    return $usuario;
}

/**
 * Intento de login: valida en servidor y, si es correcto, regenera el id de
 * sesión (AGENTS.md §4). Devuelve ['ok' => bool, 'error' => ?string].
 */
function boTryLogin(string $email, string $password): array
{
    sessionStart();

    $intentos = (int) ($_SESSION['login_attempts'] ?? 0);

    if ($intentos >= 10) {
        return ['ok' => false, 'error' => 'Demasiados intentos fallidos. Cierra la sesión del navegador (o borra las cookies) y vuelve a intentarlo.'];
    }

    if ($email === '' || $password === '') {
        return ['ok' => false, 'error' => 'Escribe el correo y la contraseña.'];
    }

    $usuario = null;

    try {
        $usuario = db()->collection('usuarios')->findOne(['email' => $email]);
    } catch (Throwable $exception) {
        error_log('[backoffice] ' . $exception->getMessage());
        return ['ok' => false, 'error' => 'La base de datos no está disponible.'];
    }

    $valido = $usuario !== null
        && ($usuario['activo'] ?? false) === true
        && ($usuario['rol'] ?? '') === 'admin'
        && is_string($usuario['password_hash'] ?? null)
        && password_verify($password, $usuario['password_hash']);

    if (!$valido) {
        $_SESSION['login_attempts'] = $intentos + 1;
        usleep(300000); // Fuerza bruta un poco más lenta (AGENTS.md §4).

        return ['ok' => false, 'error' => 'Credenciales incorrectas.'];
    }

    unset($_SESSION['login_attempts']);

    sessionRegenerate();

    $_SESSION['usuario_id']   = $usuario['id'];
    $_SESSION['usuario_name'] = $usuario['nombre'] ?? $usuario['email'];

    return ['ok' => true, 'error' => null];
}

/** Cierra la sesión del administrador. */
function boLogout(): void
{
    sessionStart();
    sessionDestroy();
}

/**
 * ¿El BackOffice está en modo de solo lectura (Vercel, AGENTS.md §8)?
 *
 * En ese modo se sigue autenticando y se sigue leyendo, pero ninguna pantalla
 * ofrece altas, ediciones ni borrados: el panel sirve para inspeccionar los
 * datos que trae el despliegue.
 */
function boSoloLectura(): bool
{
    return appReadOnly();
}

/**
 * Corta una escritura cuando la base de datos es de solo lectura.
 *
 * Se llama al principio de cada acción que modifica datos, antes de tocar nada,
 * para que el usuario reciba un mensaje claro en vez de un error de permisos.
 */
function boExigeEscritura(): void
{
    if (boSoloLectura()) {
        boAborta(
            503,
            'El BackOffice está en modo de solo lectura: este despliegue no permite '
            . 'crear, editar ni eliminar documentos. Configura un backend de escritura '
            . 'para habilitar el CRUD.'
        );
    }
}

/**
 * Colección pedida en `?c=`, validada contra la whitelist real: las carpetas
 * que existen hoy en /data (más la expresión regular de nombre de colección).
 */
function boColeccion(): string
{
    $nombre = isset($_GET['c']) && is_string($_GET['c']) ? $_GET['c'] : '';

    if (!preg_match('/^[a-z_][a-z0-9_]{0,63}$/', $nombre)) {
        boAborta(400, 'Nombre de colección no válido.');
    }

    $permitidas = db()->collectionNames();

    if (!in_array($nombre, $permitidas, true)) {
        boAborta(404, 'La colección "' . $nombre . '" no existe en /data.');
    }

    return $nombre;
}

/** Id de documento pedido en `?id=`, con la misma whitelist que JsonCollection. */
function boId(): string
{
    $id = isset($_GET['id']) && is_string($_GET['id']) ? $_GET['id'] : '';

    if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/', $id)) {
        boAborta(400, 'Identificador de documento no válido.');
    }

    return $id;
}

/** Respuesta de error (sin datos sensibles) y fin de la petición. */
function boAborta(int $codigo, string $mensaje): never
{
    http_response_code($codigo);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>'
        . e($codigo) . ' · BackOffice</title></head>'
        . '<body style="font-family: system-ui, sans-serif; padding: 3rem; text-align: center;">'
        . '<h1>' . e($codigo) . '</h1><p>' . e($mensaje) . '</p>'
        . '<p><a href="/backoffice/">Volver al panel</a></p></body></html>';
    exit;
}

/** URL del BackOffice con parámetros (front controller: ?accion=…, AGENTS.md §8). */
function boUrl(array $params = []): string
{
    $params = array_merge(['accion' => 'panel'], $params);

    return '/backoffice/?' . http_build_query($params);
}

/** Mensajes flash con whitelist de códigos (nunca se imprime $_GET directo). */
function boFlash(): ?string
{
    $codigos = [
        'creado'          => '✅ Documento creado correctamente.',
        'guardado'        => '✅ Cambios guardados.',
        'borrado'         => '🗑️ Documento eliminado.',
        'login'           => '👋 Sesión iniciada. Bienvenido/a.',
        'logout'          => 'Sesión cerrada.',
        'csrf'            => '✕ Tu sesión ha caducado. Vuelve a intentarlo.',
        'no-borrado'      => '✕ Ese documento ya no existe.',
        'duplicado'       => '✕ Ya existe un documento con ese id. Usa otro distinto.',
        'bd'              => '✕ La base de datos no está disponible.',
        'usuarios-sin'    => '⚠️ No hay ningún usuario administrador. Ejecuta `php scripts/migrate.php` y `php scripts/seed.php`.',
    ];

    $codigo = $_GET['flash'] ?? null;

    return is_string($codigo) ? ($codigos[$codigo] ?? null) : null;
}

/** ¿El valor solo contiene texto? (para elegir input corto o área de texto). */
function boEsTextoLargo(mixed $valor): bool
{
    return is_string($valor) && (strlen($valor) > 80 || str_contains($valor, "\n"));
}

/**
 * Tipo de input para un campo, según los valores reales de los documentos.
 * La infierencia sale de los datos, nunca de lo que pide el navegador.
 *
 * @param array<int, mixed> $valores Valores del campo en toda la colección.
 */
function boTipoCampo(array $valores): string
{
    $valores = array_values(array_filter($valores, static fn (mixed $v): bool => $v !== null));

    if ($valores === []) {
        return 'json'; // Solo nulls: se edita como JSON.
    }

    $todoBooleano = true;
    $todoNumerico = true;
    $todoJson     = true;
    $hayLargo     = false;

    foreach ($valores as $valor) {
        if (is_bool($valor)) {
            $todoNumerico = false;
            $todoJson     = false;
            continue;
        }

        $todoBooleano = false;

        if (is_int($valor) || is_float($valor)) {
            $todoJson = false;
            continue;
        }

        $todoNumerico = false;

        if (is_array($valor)) {
            continue;
        }

        $todoJson = false;

        if (boEsTextoLargo($valor)) {
            $hayLargo = true;
        }
    }

    if ($todoBooleano) {
        return 'bool';
    }

    if ($todoNumerico) {
        return 'number';
    }

    if ($todoJson) {
        // ¿Hay algún array/objeto entre los valores? entonces JSON; si no, texto.
        foreach ($valores as $valor) {
            if (is_array($valor)) {
                return 'json';
            }
        }
    }

    return $hayLargo ? 'textarea' : 'text';
}

/**
 * Campos editables de una colección: unión de las claves de todos sus
 * documentos, con el tipo inferido de los valores reales (whitelist de datos).
 *
 * @return array<string, string> nombre de campo => tipo de input
 */
function boCamposColeccion(JsonCollection $coleccion): array
{
    $docs = $coleccion->find([], ['limit' => 200]);

    $porCampo = [];

    foreach ($docs as $doc) {
        foreach ($doc as $campo => $valor) {
            $porCampo[$campo][] = $valor;
        }
    }

    $tipos = [];

    foreach ($porCampo as $campo => $valores) {
        $tipos[$campo] = boTipoCampo($valores);
    }

    return $tipos;
}

/** Unión de claves de varios documentos, respetando el orden de la primera. */
function boCamposDocumento(array $docs): array
{
    $campos = [];

    foreach ($docs as $doc) {
        foreach (array_keys($doc) as $campo) {
            $campos[$campo] = true;
        }
    }

    return array_keys($campos);
}

/** ¿Es un campo de timestamp gestionado por JsonCollection (no editable en formulario)? */
function boEsTimestamp(string $campo): bool
{
    return in_array($campo, ['created_at', 'updated_at'], true);
}

/** Valida el nombre de un campo nuevo (misma whitelist que JsonCollection). */
function boNombreCampoValido(string $campo): bool
{
    return (bool) preg_match('/^[A-Za-z0-9_][A-Za-z0-9_-]{0,63}$/', $campo);
}

/**
 * Construye el documento a partir del modo "campos" del formulario.
 * Devuelve ['ok' => true, 'doc' => …] o ['ok' => false, 'error' => …].
 */
function boDocumentoDesdeCampos(array $post, string $id): array
{
    $campos  = is_array($post['campo'] ?? null) ? $post['campo'] : [];
    $tipos   = is_array($post['tipo'] ?? null) ? $post['tipo'] : [];
    $borrar  = is_array($post['borrar'] ?? null) ? $post['borrar'] : [];
    $doc     = [];

    foreach ($campos as $campo => $valor) {
        $campo = (string) $campo;

        if (!boNombreCampoValido($campo) || boEsTimestamp($campo)) {
            continue;
        }

        if (isset($borrar[$campo])) {
            continue;
        }

        $tipo = is_string($tipos[$campo] ?? null) ? $tipos[$campo] : 'text';

        $convertido = boConvertirValor($tipo, $valor, $campo);

        if ($convertido['ok'] === false) {
            return $convertido;
        }

        $doc[$campo] = $convertido['valor'];
    }

    // Campo nuevo añadido en el propio formulario.
    $nuevoNombre = trim((string) ($post['nuevo_nombre'] ?? ''));

    if ($nuevoNombre !== '') {
        if (!boNombreCampoValido($nuevoNombre)) {
            return ['ok' => false, 'error' => 'Nombre de campo no válido: "' . $nuevoNombre . '". Usa letras, dígitos y guion bajo (máx. 64).'];
        }

        if (array_key_exists($nuevoNombre, $doc)) {
            return ['ok' => false, 'error' => 'El campo "' . $nuevoNombre . '" ya existe: edítalo en lugar de añadirlo.'];
        }

        $nuevoTipo  = (string) ($post['nuevo_tipo'] ?? 'text');
        $nuevoValor = $post['nuevo_valor'] ?? '';

        if (!in_array($nuevoTipo, ['text', 'textarea', 'number', 'bool', 'json'], true)) {
            $nuevoTipo = 'text';
        }

        $convertido = boConvertirValor($nuevoTipo, $nuevoValor, $nuevoNombre);

        if ($convertido['ok'] === false) {
            return $convertido;
        }

        $doc[$nuevoNombre] = $convertido['valor'];
    }

    if ($id !== '') {
        $doc['id'] = $id;
    } else {
        $idForm = trim((string) ($post['id'] ?? ''));

        if ($idForm !== '') {
            if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/', $idForm)) {
                return ['ok' => false, 'error' => 'Id no válido: usa letras, dígitos, punto, guion y guion bajo (máx. 64).'];
            }

            $doc['id'] = $idForm;
        }
    }

    return boValidarDocumento($doc);
}

/**
 * Construye el documento desde el modo "JSON" (editor de texto).
 * Devuelve ['ok' => true, 'doc' => …] o ['ok' => false, 'error' => …].
 */
function boDocumentoDesdeJson(string $json, string $id): array
{
    if (trim($json) === '') {
        return ['ok' => false, 'error' => 'El JSON está vacío.'];
    }

    if (strlen($json) > 131072) {
        return ['ok' => false, 'error' => 'El documento supera el máximo de 128 KB.'];
    }

    try {
        $decodificado = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        return ['ok' => false, 'error' => 'JSON no válido: ' . $exception->getMessage()];
    }

    if ($decodificado instanceof stdClass) {
        $doc = get_object_vars($decodificado);
    } else {
        return ['ok' => false, 'error' => 'Se esperaba un objeto JSON {…}, no una lista o un valor suelto.'];
    }

    if ($id !== '') {
        $doc['id'] = $id;
    } else {
        $idForm = trim((string) ($doc['id'] ?? ''));

        if ($idForm !== '') {
            if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/', $idForm)) {
                return ['ok' => false, 'error' => 'Id no válido: usa letras, dígitos, punto, guion y guion bajo (máx. 64).'];
            }

            $doc['id'] = $idForm;
        }
    }

    return boValidarDocumento($doc);
}

/** Convierte el valor del formulario al tipo declarado. */
function boConvertirValor(string $tipo, mixed $valor, string $campo): array
{
    switch ($tipo) {
        case 'bool':
            if (is_string($valor)) {
                return ['ok' => true, 'valor' => in_array(mb_strtolower($valor), ['1', 'on', 'true', 'si', 'sí', 'yes'], true)];
            }

            return ['ok' => true, 'valor' => (bool) $valor];

        case 'number':
            $texto = trim((string) $valor);

            if ($texto === '') {
                return ['ok' => false, 'error' => 'El campo "' . $campo . '" debe ser un número.'];
            }

            if (!is_numeric($texto)) {
                return ['ok' => false, 'error' => 'El campo "' . $campo . '" debe ser un número (has escrito "' . boCortar($texto, 40) . '").'];
            }

            return ['ok' => true, 'valor' => str_contains($texto, '.') ? (float) $texto : (int) $texto];

        case 'json':
            try {
                $decodificado = json_decode((string) $valor, false, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                return ['ok' => false, 'error' => 'El campo "' . $campo . '" contiene JSON no válido: ' . $exception->getMessage()];
            }

            if ($decodificado instanceof stdClass) {
                $decodificado = get_object_vars($decodificado);
            }

            if ($decodificado !== null && !is_array($decodificado) && !is_scalar($decodificado)) {
                return ['ok' => false, 'error' => 'El campo "' . $campo . '" debe ser un objeto, una lista, un número, un texto o null.'];
            }

            return ['ok' => true, 'valor' => $decodificado];

        case 'textarea':
        case 'text':
        default:
            if (is_array($valor)) {
                return ['ok' => false, 'error' => 'Valor inesperado en el campo "' . $campo . '".'];
            }

            return ['ok' => true, 'valor' => (string) $valor];
    }
}

/**
 * Validación final común a ambos modos (AGENTS.md §5): nombres de campo
 * correctos, tamaño razonable y obligatoriedad del id en edición.
 */
function boValidarDocumento(array $doc): array
{
    foreach ($doc as $campo => $valor) {
        if (!is_string($campo) || !boNombreCampoValido($campo)) {
            return ['ok' => false, 'error' => 'Nombre de campo no válido: "' . boCortar((string) $campo, 40) . '".'];
        }

        if (str_starts_with($campo, '$')) {
            return ['ok' => false, 'error' => 'Los campos no pueden empezar por "$": "' . $campo . '".'];
        }
    }

    try {
        $json = json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        return ['ok' => false, 'error' => 'El documento no se puede serializar (¿valores no soportados?).'];
    }

    if (strlen($json) > 131072) {
        return ['ok' => false, 'error' => 'El documento supera el máximo de 128 KB.'];
    }

    return ['ok' => true, 'doc' => $doc, 'error' => null];
}

/** Corta un texto para mostrarlo en mensajes de error sin romper HTML. */
function boCortar(string $texto, int $longitud): string
{
    return mb_strlen($texto) > $longitud ? mb_substr($texto, 0, $longitud) . '…' : $texto;
}

/**
 * Buscador de la lista: filtra en memoria por id y campos de texto reales de
 * la colección (la whitelist sale de los datos, no del navegador).
 *
 * @param array<int, array> $docs
 * @return array<int, array>
 */
function boBuscar(array $docs, string $consulta): array
{
    $consulta = trim($consulta);

    if ($consulta === '') {
        return $docs;
    }

    $busqueda = mb_strtolower($consulta);

    return array_values(array_filter($docs, static function (array $doc) use ($busqueda): bool {
        foreach ($doc as $campo => $valor) {
            if ($campo === 'password_hash') {
                continue;
            }

            if (is_string($valor) && mb_stripos($valor, $busqueda) !== false) {
                return true;
            }

            if (($campo === 'id' || $campo === 'orden') && (string) $valor === $busqueda) {
                return true;
            }
        }

        return false;
    }));
}

/**
 * Ordena la lista: por `orden` si existe, si no por id (mismos criterios que
 * usa la web pública; nunca por un campo que pida el navegador).
 *
 * @param array<int, array> $docs
 * @return array<int, array>
 */
function boOrdenar(array $docs): array
{
    $tieneOrden = false;

    foreach ($docs as $doc) {
        if (array_key_exists('orden', $doc)) {
            $tieneOrden = true;
            break;
        }
    }

    if ($tieneOrden) {
        usort($docs, static fn (array $a, array $b): int => ($a['orden'] ?? 0) <=> ($b['orden'] ?? 0));
    } else {
        usort($docs, static fn (array $a, array $b): int => strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? '')));
    }

    return $docs;
}

/** JSON formateado para el editor de texto (nunca se escapa a mano: se imprime dentro de textarea). */
function boJsonFormateado(array $doc): string
{
    return json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Estado del formulario a partir de un documento ya guardado (pantallas GET).
 *
 * @return array{doc: array, tipos: array, json: string, borrar: array, nuevo: array, modo: string}
 */
function boFormDesdeDocumento(array $doc, array $tiposColeccion, string $modo = 'campos'): array
{
    $tipos = [];

    foreach ($doc as $campo => $valor) {
        if (boEsTimestamp((string) $campo)) {
            continue;
        }

        // Un valor vacío no revela el tipo: se respeta el que usa la colección
        // (así un campo "texto largo" no se muestra como input corto).
        if (($valor === '' || $valor === null) && isset($tiposColeccion[$campo])) {
            $tipos[$campo] = $tiposColeccion[$campo];
            continue;
        }

        $tipos[$campo] = boTipoCampo([$valor]);
    }

    // En un documento nuevo se completan los campos que usa la colección.
    foreach ($tiposColeccion as $campo => $tipo) {
        if (!array_key_exists($campo, $tipos)) {
            $tipos[$campo] = $tipo;
        }
    }

    return [
        'doc'   => $doc,
        'tipos' => $tipos,
        'json'  => boJsonFormateado($doc),
        'borrar'=> [],
        'nuevo' => ['nombre' => '', 'tipo' => 'text', 'valor' => ''],
        'modo'  => $modo,
    ];
}

/**
 * Estado del formulario a partir de lo que se envió (para re-renderizar el
 * formulario cuando la validación en servidor ha fallado).
 *
 * @return array{doc: array, tipos: array, json: ?string, borrar: array, nuevo: array, modo: string}
 */
function boFormDesdePost(array $post, string $idEdit, bool $modoJson): array
{
    $nuevo = [
        'nombre' => trim((string) ($post['nuevo_nombre'] ?? '')),
        'tipo'   => (string) ($post['nuevo_tipo'] ?? 'text'),
        'valor'  => (string) ($post['nuevo_valor'] ?? ''),
    ];

    if ($modoJson) {
        return [
            'doc'   => [],
            'tipos' => [],
            'json'  => (string) ($post['json'] ?? ''),
            'borrar'=> [],
            'nuevo' => $nuevo,
            'modo'  => 'json',
        ];
    }

    $campos = is_array($post['campo'] ?? null) ? $post['campo'] : [];
    $tipos  = is_array($post['tipo'] ?? null) ? $post['tipo'] : [];

    $doc   = [];
    $tiposDoc = [];

    foreach ($campos as $campo => $valor) {
        $campo = (string) $campo;

        if (!boNombreCampoValido($campo)) {
            continue;
        }

        $doc[$campo] = is_array($valor) ? '' : $valor;

        $tipo = is_string($tipos[$campo] ?? null) ? $tipos[$campo] : 'text';

        $tiposDoc[$campo] = in_array($tipo, ['text', 'textarea', 'number', 'bool', 'json'], true) ? $tipo : 'text';
    }

    if ($idEdit !== '') {
        $doc['id'] = $idEdit;
    } else {
        $idForm = trim((string) ($post['id'] ?? ''));

        if ($idForm !== '') {
            $doc['id'] = $idForm;
        }
    }

    return [
        'doc'   => $doc,
        'tipos' => $tiposDoc,
        'json'  => null,
        'borrar'=> is_array($post['borrar'] ?? null) ? $post['borrar'] : [],
        'nuevo' => $nuevo,
        'modo'  => 'campos',
    ];
}

/** Resumen corto de un valor para tablas y listas (siempre texto plano). */
function boResumen(mixed $valor): string
{
    if ($valor === null) {
        return 'null';
    }

    if (is_bool($valor)) {
        return $valor ? 'true' : 'false';
    }

    if (is_array($valor)) {
        $texto = json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $texto = $texto === false ? '' : $texto;
    } else {
        $texto = (string) $valor;
    }

    return boCortar($texto, 60);
}

/**
 * Esqueleto de documento nuevo con los campos habituales de la colección
 * (así el formulario de "crear" sale precargado con lo que espera la web).
 */
function boEsqueleto(string $coleccion): array
{
    $tipos  = boCamposColeccion(db()->collection($coleccion));
    $esqueleto = [];

    foreach ($tipos as $campo => $tipo) {
        if (boEsTimestamp($campo) || $campo === 'id') {
            continue;
        }

        $esqueleto[$campo] = match ($tipo) {
            'bool'   => false,
            'number' => 0,
            'json'   => null,
            default  => '',
        };
    }

    return $esqueleto;
}
