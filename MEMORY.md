# MEMORY.md · Memoria del proyecto

Archivo de memoria para agentes de IA y desarrolladores. Complementa a `AGENTS.md`
(allí están las **reglas**; aquí está el **estado**). Se actualiza al final de cada
tarea relevante: lo que no se escriba aquí, en la próxima sesión no existirá.

---

## 1. Estado actual (snapshot)

| Área | Estado | Notas |
|------|--------|-------|
| Estructura de carpetas | ✅ Creada | `/api`, `/web`, `/public` (solo assets), `/src`, `/templates`, `/backoffice`, `/config`, `/data`, `/seeds`, `/migrations`, `/scripts` |
| Configuración (`/config`) | ✅ Por capas | `config.example.php` versionada (base) + `config.php` local + variables de entorno (`APP_ENV`, `APP_READONLY`, `APP_KEY`, `APP_DATA_DIR`) |
| Base de datos **NoSQL** (JSON en disco) | ✅ Operativa | `src/services/JsonDatabase.php` + `JsonCollection.php`; helper `db()` en `src/helpers/db.php`. **Sin MySQL, PDO ni SQL** |
| Modo de solo lectura | ✅ Nuevo | `JsonDatabase($root, $readOnly)`: no exige escritura, corta con `ReadOnlyDatabaseException`. Automático en Vercel (`VERCEL_ENV`), desactivado en local |
| Esquema / migraciones | ✅ Creadas | `migrations/001_esquema_inicial.php` + `002_usuarios_backoffice.php` + runner `scripts/migrate.php` |
| Semillas de datos | ✅ Cargadas | `seeds/*.json` → `scripts/seed.php` (idempotente) |
| Web pública | ✅ Funciona | `web/index.php` → `templates/home.php`, todo el contenido sale de la BD |
| Formulario de suscripción | ✅ Funciona | `web/suscribir.php`: POST + CSRF + validación → colección `suscripciones` (en solo lectura avisa y no guarda) |
| Comprobación de conexión | ✅ | `php scripts/check-db.php` (salida 0/1) + auto-test `php scripts/self-test.php` (44/44) |
| BackOffice CRUD | ✅ Creado | `/backoffice` + vistas en `templates/backoffice/`. Login con sesión, CSRF y CRUD genérico sobre cualquier colección |
| Sesiones | ✅ Cookie firmada | `src/helpers/session.php` (HMAC-SHA256 + `APP_KEY`). Sustituye a `session_start()` por el disco de solo lectura de Vercel |
| **Despliegue en Vercel** | ✅ Listo | `vercel.json` (`vercel-php@0.8.0` = PHP 8.4) + `/api/index.php` como front controller único. Demo de solo lectura |

**Contenido real del repositorio hoy:** `AGENTS.md`, `MEMORY.md`, `README.md`,
`.gitignore`, `.vercelignore`, `vercel.json`, `api/` (1), `web/` (2),
`src/helpers/` (7), `src/services/` (3), `backoffice/` (2),
`templates/` (`home.php` + `backoffice/` 6), `public/assets/` (css 2 + js 1),
`config/` (2), `seeds/` (6), `migrations/` (2), `scripts/` (4) y `/data`
(23 documentos JSON, **versionado**: es el dataset que sirve el despliegue).

### 1.1 Colecciones y campos (sembradas en `seeds/`)

| Colección | Nº docs | Campos |
|-----------|---------|--------|
| `especies` | 7 | `id`, `slug`, `nombre`, `etiqueta`, `emoji`, `descripcion`, `orden`, `amenazada` |
| `estadisticas` | 4 | `id`, `clave`, `valor`, `etiqueta`, `orden` |
| `curiosidades` | 5 | `id`, `titulo`, `texto`, `icono`, `orden` |
| `protecciones` | 4 | `id`, `titulo`, `texto`, `icono`, `orden` |
| `suscripciones` | 0 (vacía) | `id`, `email` |
| `usuarios` | 1 | `id`, `nombre`, `email`, `password_hash`, `rol`, `activo` (BackOffice) |
| `_migraciones` | 2 | `id`, `descripcion`, `archivo` (interna) |

Todos los documentos añaden `created_at` / `updated_at` automáticamente.

## 2. Cambio grande: se eliminó MySQL → BD NoSQL

El 2026-09-30 el proyecto pasó a funcionar **sin MySQL**. Se ofrecieron 4
opciones (MongoDB, CouchDB, almacén JSON en disco, Redis) y el usuario eligió
**almacén JSON en disco**, con alcance de "crear estructura mínima".

Qué cambió:

- `src/helpers/db.php`: de `getPdo()` (DSN mysql) a `db(): JsonDatabase`.
- `config/*.php`: de `host/port/name/user/pass/charset` a `driver: json` + `dataDir`.
- `/sql` (schema.sql + migraciones .sql) → **`/migrations` (PHP) + `/seeds` (JSON) + `/scripts`**.
- `AGENTS.md` y `MEMORY.md` reescritos por completo (secciones 1, 2, 3, 5, 6, 7 y 8).
- `.gitignore`: además de `config.php`, ahora ignora `/data/`.
- La landing se sirve desde PHP: `index.html` (raíz) → `templates/home.php` con
  datos de la BD; el CSS se movió a `public/assets/css/style.css` (sin cambios,
  comprobado por diff).

## 3. Cambio grande: despliegue en Vercel (demo de solo lectura)

El 2026-10-06 el proyecto se reorganizó para poder desplegarse en **Vercel** con
el runtime de la comunidad `vercel-php`. La restricción que lo condiciona todo:
**las funciones de Vercel tienen el disco de solo lectura** (solo `/tmp`, que
además no se comparte entre instancias).

Qué cambió:

- **`/api/index.php` es ahora el front controller único**, tanto en Vercel
  (`vercel.json` enruta todo ahí) como en local
  (`php -S localhost:8000 -t public api/index.php`). Antes el entry point de la
  web era `public/index.php`.
- **`/public` contiene solo assets estáticos.** Motivo: Vercel entrega tal cual
  todo lo que hay en `/public`, así que un `.php` dentro se publicaría como
  código fuente. Los entry points se movieron a `/web/` (pública) y
  `/backoffice/` (panel).
- **Sesiones en cookie firmada** (`src/helpers/session.php`, HMAC-SHA256 con
  `APP_KEY`) en lugar de `session_start()`. Con ficheros, `/tmp` no se comparte
  entre instancias y el login fallaría de forma intermitente.
- **Modo de solo lectura** en la capa de datos: `new JsonDatabase($dir, true)`
  no exige escritura y corta cualquier modificación con
  `ReadOnlyDatabaseException`. Se activa solo al detectar `VERCEL_ENV` (o con
  `APP_READONLY`), de modo que **en local el CRUD sigue entero**.
- **`/data` ahora se versiona**: es el dataset que sirve el despliegue. Se
  regenera con `seeds/` + `migrations/`.
- Configuración por capas (`config.example.php` → `config.php` → variables de
  entorno) porque en Vercel `config/config.php` no existe.

Se ofrecieron cuatro backends para las escrituras (MongoDB Atlas, Vercel KV,
SQLite en `/tmp`, solo lectura) y se eligió **solo lectura** para esta
iteración. Para habilitar el CRUD en producción hay que poner un backend de
escritura real detrás de la misma API de `JsonCollection`.


## 4. Decisiones ya tomadas

- **Idioma:** todo el contenido y la comunicación en español.
- **`MEMORY.md` = memoria de agentes**, no documentación de usuario. Secciones
  fijas: estado, decisiones, pendientes, aprendizajes, log de cambios.
- **Motor de datos:** almacén documental JSON propio (PHP puro, cero
  dependencias, sin servidor). Reglas en `AGENTS.md` §6.
- **La landing se backoffice-iza por partes:** el contenido estático pasó a
  colecciones de la BD y ya se edita desde el BackOffice (especies,
  estadisticas, curiosidades, protecciones).
- **Se añadió la 7ª especie** (`esp-banon`, tortuga bañón) para que las tarjetas
  cuadren con el propio dato de la página ("7 especies"). Las otras 6 se
  conservan con su texto original; `esp-bastarda` queda como `amenazada: false`
  para mantener el dato "6 de 7".
- **Las semillas llevan `id` fijo** (`esp-verde`, `est-especies`…) para que
  `seed.php` sea idempotente (upsert, sin duplicados).
- **Enrutado del BackOffice por query string** (`/backoffice/?accion=…&c=…&id=…`)
  en un solo front controller: el DocumentRoot es `/public` y las rutas
  `/backoffice/entidad/editar` (con subcarpetas y sin `.php`) darían 404.
  Decisión anotada en `AGENTS.md` §5 y §8.
- **Hosting:** Vercel con el runtime `vercel-php@0.8.0` (PHP 8.4). Runtimes más
  recientes: `@0.9.0` es PHP 8.5; el `@0.7.3` que había en `vercel.json` estaba
  desfasado (PHP 8.3).
- **Demo de solo lectura en producción:** en Vercel no hay escrituras; el CRUD
  se desarrolla en local. Los puntos de escritura se cortan antes de tocar la
  base de datos, con `ReadOnlyDatabaseException` en la capa de datos y
  `boExigeEscritura()` (503) en el BackOffice.
- **El editor de documentos es genérico** (no hay esquema en la BD): dos modos,
  *Campos* (inputs generados a partir de los tipos reales de los datos) y
  *JSON* (texto libre). La validación siempre es en servidor y es la misma en
  ambos modos.
- **Usuario inicial del BackOffice:** `admin@localhost.test` /
  `admin1234`, sembrado con `password_hash()` (BCRYPT) en `seeds/usuarios.json`.
  Es una contraseña de desarrollo: cambiarla antes de exponer el panel.

## 5. Pendientes / TODO

- [x] **BackOffice**: `require_login()`, sesión de administrador, contraseñas
      con `password_hash()`, y el patrón CRUD de `AGENTS.md` §5 sobre las
      colecciones existentes. *(Hecho: crear → ver → editar → eliminar probado.)*
- [x] Conectar `templates/home.php` a un CRUD de BackOffice. *(Hecho: el
      contenido de la landing se edita desde el panel.)*
- [x] **Desplegar en Vercel** con el formato que exige (`/api/index.php` +
      `vercel.json`, runtime `vercel-php`). *(Hecho: demo de solo lectura.)*
- [ ] **Definir `APP_KEY` en el proyecto de Vercel** antes del primer
      despliegue: sin ella las cookies de sesión no son fiables.
- [ ] **Backend de escritura para producción.** El despliegue actual no acepta
      Altas/Ediciones/Borrados. Opciones, manteniendo la API de `JsonCollection`:
      MongoDB Atlas (extensión `mongodb` incluida en `vercel-php`) o Vercel KV
      (Upstash Redis). Es el paso que convierte la demo en una app de verdad.
- [ ] Cambiar la contraseña por defecto del admin (`admin1234`) y el correo si
      el panel se despliega fuera de local. **Más urgente ahora que está
      desplegado en internet**: ese usuario queda sembrado en el repositorio.
      Añadir un aviso en la pantalla de login cuando siga siendo la sembrada.
- [ ] **Ojo con `seed.php`:** re-ejecutarlo restaura `usuarios` (y todo lo demás)
      al valor de `seeds/`, incluida la contraseña. Valorar sacar el admin de las
      semillas y crearlo en la migración 002 o con un script de alta.
- [ ] El editor genérico permite editar `usuarios` (incluido `password_hash`) y
      las colecciones internas `_*` quedan fuera del panel. Valorar proteger la
      colección `usuarios` (p. ej. exigir un formulario específico para cambiar
      la contraseña en lugar del JSON crudo).
- [ ] Si el proyecto crece (muchos escritores concurrentes o despliegue en
      NFS), valorar migrar a un servidor NoSQL real (MongoDB/CouchDB) manteniendo
      la API de `JsonCollection`.
- [ ] Con `/data` versionado, cualquier edición hecha desde el BackOffice **en
      local** aparece como diff en git (y `seed.php` la revierte). Decide si el
      dataset que sirve el despliegue se edita en local y se sube, o si se
      cambia con migraciones.

## 6. Aprendizajes y trampas detectadas

- Arranque real hoy: `php scripts/check-db.php && php scripts/migrate.php &&
  php scripts/seed.php && php -S localhost:8000 -t public api/index.php`. **Sin
  esos pasos la web no muestra datos** (sale un banner rojo, no rompe).
- `/data` debe ser escribible por quien lanza PHP; si no lo es, `db()` lanza un
  `RuntimeException` con el motivo. En modo solo lectura solo se exige lectura.
- El bloqueo es `flock` sobre `data/.lock`: sirve para un servidor PHP local,
  no para muchos escritores en red. Escrituras atómicas (temporal + `rename`).
- `transaction()` hace rollback real (diario de cambios): se ha probado con
  insert + update dentro de una transacción que lanza excepción.
- Re-ejecutar `seed.php` **sobrescribe** por `id` (`upsert`): si editas un dato
  a mano en `/data`, la siguiente siembra lo pone de nuevo al valor del JSON.
- Validaciones clave de seguridad probadas: `id` con `../../` → rechazado;
  nombre de colección con `../` → rechazado; POST sin token CSRF → 403;
  `?suscripcion=<script>` → no se imprime (whitelist de mensajes).
- La capa de datos tiene auto-test: **`php scripts/self-test.php`** (44
  aserciones; usa un directorio temporal, no toca `/data`).
- **BackOffice (2026-09-30):**
  - `json_decode(..., JSON_THROW_ON_ERROR)` **no** rellena `json_last_error_msg()`:
    dentro del `catch` hay que usar `$exception->getMessage()` (si no, el usuario
    ve "JSON no válido: No error.").
  - Un checkbox booleano se envía como *hidden + checkbox* con el mismo nombre:
    al desmarcarlo llega `""` y el campo pasa a `false` en lugar de desaparecer
    del documento.
  - La inferencia de tipos de un campo vacío (`''`/`null`) es ambigua: se toma el
    tipo de la colección, si no, al *crear*, un campo "texto largo" se mostraría
    como input corto.
  - `delete()` solo se ejecuta en POST con CSRF; el GET de la URL de eliminar
    muestra la pantalla de confirmación (probado: el GET no borra nada).
  - El *flash* y las URLs del panel pasan por whitelist (`boFlash()`, `boUrl()`):
    nunca se imprime un valor que venga de `$_GET`.
- Trampa del servidor de desarrollo: con `php -S … -t public` (sin router), las
  rutas **sin extensión** caen en `public/index.php`, así que `/no-existe`
  devuelve la landing con 200 en vez de 404. Es comportamiento de `php -S`, no
  del proyecto: con Apache/nginx y DocumentRoot `/public` devolvería 404.
  Por eso el BackOffice se enruta con `?accion=` (ver `AGENTS.md` §5 y §8).
- **Vercel (2026-10-06):**
  - **El disco de las funciones es de solo lectura** (solo `/tmp`, y no se
    comparte entre instancias). Rompe tanto la BD en ficheros como las sesiones
    de PHP. De ahí el modo de solo lectura y la cookie firmada.
  - **`/public` se sirve tal cual**: un `.php` dentro se devuelve como código
    fuente. Por eso `/public` solo tiene assets y los entry points viven en
    `/web` y `/backoffice`.
  - **`-t public` es obligatorio en local**: sin él, `/assets/css/style.css` da
    404 (el DocumentRoot sería la raíz del repo, no `public/`).
  - **Rutas de Vercel:** hace falta `{ "handle": "filesystem" }` **antes** del
    `catch-all`, o los assets estáticos acabarían en el PHP.
  - **`.vercelignore` anula a `.gitignore`**: cuando existe, la CLI deja de leer
    `.gitignore`, así que hay que repetir ahí lo que no debe subirse (si no,
    `config/config.php` acaba en el despliegue).
  - **La cookie de sesión necesita su Set-Cookie antes de enviar el HTML.** Por
    eso `sessionStart()` abre un búfer de salida y la escritura de la cookie
    ocurre en el *shutdown*, antes de que PHP vacíe el búfer. Sin eso, la
    pantalla de login generaría un token CSRF que no llega al cliente.
  - `APP_KEY` es obligatoria en Vercel; sin ella, cada instancia firma con un
    secreto distinto y el login se rompe de forma intermitente.
  - Probado con `VERCEL_ENV=production APP_READONLY=1` y `chmod -R a-w data`:
    la web y el panel funcionan, las escrituras dan 503 y **no se crea ningún
    fichero en `/data`** (ni `.lock`).

## 7. Cómo trabajar aquí (recordatorio rápido)

1. Leer `AGENTS.md` antes de escribir código (reglas de seguridad, CRUD, BD NoSQL).
2. Acceso a datos solo vía `db()` + `JsonCollection` (nunca ficheros de `/data` a mano).
3. CSRF + validación en servidor + `e()` (escape) en cualquier operación.
4. Cambio de estructura → migración PHP en `/migrations` + `seeds/` si aplica.
5. Probar crear → ver → editar → eliminar (y `php scripts/self-test.php`) antes
   de dar una tarea por terminada.
6. **No meter nunca un `.php` en `/public`** y no tocar a mano la configuración
   de Vercel: pasa por `vercel.json` y variables de entorno.
7. **Actualizar este archivo** con el estado resultante.

## 8. Log de cambios

| Fecha | Agente/persona | Cambio |
|-------|----------------|--------|
| 2026-09-30 | — | Creación inicial de `MEMORY.md` con el estado del proyecto. |
| 2026-09-30 | Agente IA | **Migración completa MySQL → BD NoSQL JSON en disco**: servicios `JsonDatabase`/`JsonCollection` (CRUD, filtros con operadores, orden/paginación, bloqueo `flock`, escritura atómica y transacciones con rollback), `db()` en helpers, config con `driver: json`, `.gitignore` con `/data/`. Estructura nueva `/public`, `/templates`, `/seeds`, `/migrations`, `/scripts`. Landing migrada de `index.html` a `templates/home.php` con datos desde la BD (se añade la 7ª especie) y CSS a `public/assets/css/style.css`; `index.html` eliminado de la raíz. Formulario de suscripción real (`public/suscribir.php`, POST + CSRF + validación). Scripts `check-db`, `migrate`, `seed`, `self-test`. Reescritos `AGENTS.md` y `MEMORY.md`. Pruebas: 44/44 aserciones de BD, lint OK en 16 ficheros PHP, flujo HTTP probado (200/303/403). |
| 2026-09-30 | Agente IA | **BackOffice CRUD creado**: `backoffice/app.php` (front controller con enrutado `?accion=`) + `backoffice/helpers.php` (login/CSRF/whitelists/validación/inferencia de tipos), vistas en `templates/backoffice/` (panel, lista con buscador, detalle, formulario Campos/JSON, confirmación de borrado), entry point `public/backoffice/index.php`, estilos `public/assets/css/backoffice.css` y JS `public/assets/js/backoffice.js` (alternancia de modo, formatear JSON, aviso de cambios sin guardar). Migración `002_usuarios_backoffice` + `seeds/usuarios.json` (admin con BCRYPT). Pruebas: CRUD completo crear→ver→editar→eliminar en navegador, modo JSON, borrado de campos, validación de JSON incorrecto, buscador, sin sesión → 303, POST sin CSRF → nada escrito, path traversal → 400, throttle de login, `check-db` + `self-test` 44/44. |
