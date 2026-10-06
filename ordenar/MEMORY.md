# MEMORY.md · Memoria del proyecto

Archivo de memoria para agentes de IA y desarrolladores. Complementa a `AGENTS.md`
(allí están las **reglas**; aquí está el **estado**). Se actualiza al final de cada
tarea relevante: lo que no se escriba aquí, en la próxima sesión no existirá.

---

## 1. Estado actual (snapshot)

| Área | Estado | Notas |
|------|--------|-------|
| Estructura de carpetas (`/public`, `/src`, `/templates`…) | ✅ Creada | `/config`, `/data`, `/seeds`, `/migrations`, `/scripts`. **Falta `/backoffice`** |
| Configuración (`/config`) | ✅ Creada | `config.example.php` + `config.php` local (gitignored) con `driver: json` y `dataDir` |
| Base de datos **NoSQL** (JSON en disco) | ✅ Operativa | `src/services/JsonDatabase.php` + `JsonCollection.php`; helper `db()` en `src/helpers/db.php`. **Ya no hay MySQL, PDO ni SQL** |
| Esquema / migraciones | ✅ Creadas | `migrations/001_esquema_inicial.php` + runner `scripts/migrate.php` (registro en `_migraciones`) |
| Semillas de datos | ✅ Cargadas | `seeds/*.json` → `scripts/seed.php` (idempotente) |
| Web pública | ✅ Funciona | `public/index.php` → `templates/home.php`, todo el contenido sale de la BD |
| Formulario de suscripción | ✅ Funciona | `public/suscribir.php`: POST + CSRF + validación → colección `suscripciones` |
| Comprobación de conexión | ✅ | `php scripts/check-db.php` (salida 0/1) + auto-test `php scripts/self-test.php` |
| BackOffice CRUD | ✅ Creado | `/backoffice` (front controller + helpers) + `public/backoffice/` + `templates/backoffice/`. Login con sesión, CSRF, y CRUD genérico sobre **cualquier** colección (modo *Campos* y modo *JSON*) |
| Landing original `index.html` | 🔄 Migrada y **eliminada** de la raíz | Contenido en `templates/home.php` + `public/assets/css/style.css` + `seeds/` (verificado: 47 frases conservadas) |

**Contenido real del repositorio hoy:** `AGENTS.md`, `MEMORY.md`, `.gitignore`,
`config/` (2), `src/helpers/` (5), `src/services/` (2), `backoffice/` (2),
`templates/` (`home.php` + `backoffice/` 6), `public/` (`index.php`,
`suscribir.php`, `backoffice/index.php`, `assets/css/style.css` +
`backoffice.css`, `assets/js/backoffice.js`), `seeds/` (6), `migrations/` (2),
`scripts/` (4) y `/data` (BD local, no versionada).

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

## 3. Decisiones ya tomadas

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
  en un solo front controller: el DocumentRoot es `/public` y `php -S -t public`
  no lleva router, así que las rutas `/backoffice/entidad/editar` (con
  subcarpetas y sin `.php`) darían 404. Decisión anotada en `AGENTS.md` §5 y §8.
- **El editor de documentos es genérico** (no hay esquema en la BD): dos modos,
  *Campos* (inputs generados a partir de los tipos reales de los datos) y
  *JSON* (texto libre). La validación siempre es en servidor y es la misma en
  ambos modos.
- **Usuario inicial del BackOffice:** `admin@localhost.test` /
  `admin1234`, sembrado con `password_hash()` (BCRYPT) en `seeds/usuarios.json`.
  Es una contraseña de desarrollo: cambiarla antes de exponer el panel.

## 4. Pendientes / TODO

- [x] **BackOffice**: `require_login()`, sesión de administrador, contraseñas
      con `password_hash()`, y el patrón CRUD de `AGENTS.md` §5 sobre las
      colecciones existentes. *(Hecho: crear → ver → editar → eliminar probado.)*
- [x] Conectar `templates/home.php` a un CRUD de BackOffice. *(Hecho: el
      contenido de la landing se edita desde el panel.)*
- [ ] Cambiar la contraseña por defecto del admin (`admin1234`) y el correo si
      el panel se despliega fuera de local. Añadir un aviso en la pantalla de
      login cuando siga siendo la contraseña sembrada.
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

## 5. Aprendizajes y trampas detectadas

- Arranque real hoy: `php scripts/check-db.php && php scripts/migrate.php &&
  php scripts/seed.php && php -S localhost:8000 -t public`. **Sin esos pasos la
  web no muestra datos** (sale un banner rojo, no rompe).
- `/data` debe ser escribible por quien lanza PHP; si no lo es, `db()` lanza un
  `RuntimeException` con el motivo.
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

## 6. Cómo trabajar aquí (recordatorio rápido)

1. Leer `AGENTS.md` antes de escribir código (reglas de seguridad, CRUD, BD NoSQL).
2. Acceso a datos solo vía `db()` + `JsonCollection` (nunca ficheros de `/data` a mano).
3. CSRF + validación en servidor + `e()` (escape) en cualquier operación.
4. Cambio de estructura → migración PHP en `/migrations` + `seeds/` si aplica.
5. Probar crear → ver → editar → eliminar (y `php scripts/self-test.php`) antes
   de dar una tarea por terminada.
6. **Actualizar este archivo** con el estado resultante.

## 7. Log de cambios

| Fecha | Agente/persona | Cambio |
|-------|----------------|--------|
| 2026-09-30 | — | Creación inicial de `MEMORY.md` con el estado del proyecto. |
| 2026-09-30 | Agente IA | **Migración completa MySQL → BD NoSQL JSON en disco**: servicios `JsonDatabase`/`JsonCollection` (CRUD, filtros con operadores, orden/paginación, bloqueo `flock`, escritura atómica y transacciones con rollback), `db()` en helpers, config con `driver: json`, `.gitignore` con `/data/`. Estructura nueva `/public`, `/templates`, `/seeds`, `/migrations`, `/scripts`. Landing migrada de `index.html` a `templates/home.php` con datos desde la BD (se añade la 7ª especie) y CSS a `public/assets/css/style.css`; `index.html` eliminado de la raíz. Formulario de suscripción real (`public/suscribir.php`, POST + CSRF + validación). Scripts `check-db`, `migrate`, `seed`, `self-test`. Reescritos `AGENTS.md` y `MEMORY.md`. Pruebas: 44/44 aserciones de BD, lint OK en 16 ficheros PHP, flujo HTTP probado (200/303/403). |
| 2026-09-30 | Agente IA | **BackOffice CRUD creado**: `backoffice/app.php` (front controller con enrutado `?accion=`) + `backoffice/helpers.php` (login/CSRF/whitelists/validación/inferencia de tipos), vistas en `templates/backoffice/` (panel, lista con buscador, detalle, formulario Campos/JSON, confirmación de borrado), entry point `public/backoffice/index.php`, estilos `public/assets/css/backoffice.css` y JS `public/assets/js/backoffice.js` (alternancia de modo, formatear JSON, aviso de cambios sin guardar). Migración `002_usuarios_backoffice` + `seeds/usuarios.json` (admin con BCRYPT). Pruebas: CRUD completo crear→ver→editar→eliminar en navegador, modo JSON, borrado de campos, validación de JSON incorrecto, buscador, sin sesión → 303, POST sin CSRF → nada escrito, path traversal → 400, throttle de login, `check-db` + `self-test` 44/44. |
