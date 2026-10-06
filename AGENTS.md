# AGENTS.md

Guía para agentes de IA y desarrolladores que trabajen en este proyecto.

## 1. Visión del proyecto

Web dinámica en **PHP** con base de datos **NoSQL** (almacén documental JSON en
disco), incluida un **BackOffice** desde el que se gestionan los datos mediante
formularios con operaciones CRUD (crear, leer, actualizar, eliminar).

- **Stack:** PHP 8.x (vanilla, sin framework) + base de datos **NoSQL documental JSON** en `/data`.
  **No usa MySQL, PDO, SQL ni ningún servidor de base de datos**: cada documento es un fichero JSON.
- **Hosting:** Vercel, con el runtime de la comunidad `vercel-php` (§8).
- **Público:** usuarios finales en la web pública; administradores en el BackOffice.
- **Objetivo:** que toda la información se actualice desde formularios del BackOffice, sin tocar la base de datos ni los archivos a mano.

> **En Vercel el despliegue es de solo lectura** (allí el disco es de solo
> lectura): se consulta y se autentica igual, pero no hay escrituras. El CRUD
> completo sigue funcionando en local. Ver §6 y §8.

> Si en algún momento se sustituye el almacén JSON por un servidor NoSQL real
> (MongoDB, CouchDB, Redis…) o se adopta un framework (Laravel, Symfony…),
> actualizar esta sección y las secciones 3, 5 y 6.

## 2. Estructura de carpetas

```
/api            → front controller ÚNICO de PHP (Vercel y local): index.php
/web            → entry points de la web pública (index.php, suscribir.php)
/public         → SOLO assets estáticos (css/js/img). NUNCA PHP dentro
/src            → lógica (helpers, services, controllers)
/templates      → vistas PHP (HTML con <?php echo ?>)
/backoffice     → panel CRUD: front controller (app.php) + helpers, protegido por sesión
/config         → configuración (config.example.php versionada → config.php local en .gitignore)
/data           → BASE DE DATOS: colecciones y documentos JSON (se versiona: es el dataset que despliega Vercel)
/seeds          → documentos de ejemplo (JSON) para regenerar la BD
/migrations     → migraciones PHP (NNN_descripcion.php)
/scripts       → utilidades de consola (check-db, migrate, seed, self-test)
vercel.json     → configuración del despliegue en Vercel
```

- **`/api/index.php` es el único punto de entrada** de PHP, en los dos entornos:
  - Vercel: `vercel.json` enruta todo a `/api/index.php` (runtime `vercel-php`).
  - Local: `php -S localhost:8000 -t public api/index.php`.
- **Nada fuera de `/api` se sirve directamente al navegador.** En Vercel todo lo
  que hay dentro de `/public` se entrega tal cual, así que `/public` contiene
  **únicamente assets estáticos**: un `.php` dentro se publicaría como código
  fuente en vez de ejecutarse. Los entry points viven en `/web` y `/backoffice`.
- El **DocumentRoot** del servidor de desarrollo es `/public` (por eso el comando
  local lleva `-t public`): sirve `/assets/…` y, para el resto, ejecuta el
  front controller.
- **`/data` es la base de datos.** Aquí sí se versiona, porque en el despliegue
  de Vercel es el dataset que se sirve (el modo es de solo lectura, §8). Para
  regenerarlo desde cero están `seeds/` + `migrations/`.

## 3. Convenciones PHP

- Estilo **PSR-12** (sangría de 4 espacios, llaves en la misma línea).
- Nombres:
  - Colecciones y campos en `snake_case` (`especies`, `created_at`). La colección va en plural.
  - Clases en `PascalCase` (`JsonDatabase`, `UsuarioController`).
  - Funciones y variables en `camelCase` (`getUsuarioById`).
- **Todas las lecturas y escrituras pasan por la capa de datos** (`db()` →
  `JsonDatabase` / `JsonCollection`). Nunca leer, escribir, `glob` ni `unlink`
  ficheros de `/data` a mano desde controllers o plantillas.
- Nunca confiar en los nombres de campo que llegan de `$_GET`/`$_POST` para
  `filter`, `sort` o `fields`: usar siempre una **whitelist** de campos permitidos.
- Separación de responsabilidades:
  - Lógica de negocio y acceso de datos → `/src`
  - Solo presentación HTML → `/templates`
- Salida siempre escapada: `htmlspecialchars` vía el helper `e()` (nunca `echo $dato` a pelo).
- Evitar código muerto: si se elimina una función, eliminar también sus llamadas.

## 4. Seguridad del BackOffice

Obligatorio en **toda** operación del BackOffice (también en los formularios
públicos que escriben en la BD, como `web/suscribir.php`):

- **Autenticación:** todas las rutas pasan por `boRequireLogin()`; sin sesión → redirect a login.
- **Contraseñas:** solo `password_hash()` (BCRYPT) y `password_verify()`. Nunca MD5/SHA1 ni contraseñas en claro.
- **CSRF:** token generado por sesión y validado en **todos** los POST (crear, editar, eliminar).
- **Salida:** todo dato escapado con `htmlspecialchars($v, ENT_QUOTES, 'UTF-8')` (helper `e()`).
- **Métodos:** `DELETE` solo por POST (o DELETE con CSRF), nunca por enlace GET.
- **Permisos:** comprobar rol antes de acciones destructivas; el usuario no validado no ve ni prueba el BackOffice.
- **Subida de archivos:** validar MIME, tamaño y renombrar el archivo; nunca dejar el nombre original decidir la ruta.
- **Identificadores:** el `id` de un documento forma parte de la ruta del fichero,
  por eso se valida con una whitelist (`[A-Za-z0-9._-]`, máx. 64). Esto bloquea
  cualquier intento de *path traversal* (`../../`) hacia fuera de `/data`.

### Sesiones: cookie firmada, no `session_start()`

Las sesiones **no** usan los ficheros de PHP. Los implementa
`src/helpers/session.php`: los datos viajan dentro de una cookie
(`mydemo_sid`) firmada con HMAC-SHA256 y el secreto `APP_KEY`.

Motivo (no es capricho): en Vercel el disco es de solo lectura y `/tmp` no se
comparte entre instancias, así que una sesión en fichero se perdería en cuanto
la petición landingase en otra función y el login fallaría "a ratos".

- Cookies con `HttpOnly`, `SameSite=Lax` y `Secure` en producción; vigencia de
  12 h en el servidor (cookie de sesión en el navegador).
- `sessionRegenerate()` al hacer login (evita el secuestro de sesión por fijar
  el identificador).
- **Obligatorio en Vercel:** definir `APP_KEY`. Sin ella, cada instancia firma
  con un secreto distinto y el panel no mantiene la sesión.
- Contrapartida conocida: al no haber estado en servidor, el contador de
  intentos de login se reinicia si el cliente borra la cookie. Es la contrapartida
  habitual de las sesiones sin estado en serverless.

## 5. Operaciones CRUD

Patrón de pantallas del BackOffice para **cada** colección (usuarios, productos,
noticias…). El panel es **genérico**: al no haber esquema en la BD, cualquier
colección de `/data` se lista y se edita sin escribir código nuevo.

| Acción   | Método | Ruta (ejemplo)                                  | Descripción                             |
|----------|--------|-------------------------------------------------|-----------------------------------------|
| Panel    | GET    | `/backoffice/`                                  | Colecciones y nº de documentos          |
| Listar   | GET    | `/backoffice/?accion=listar&c=especies`         | Lista con buscador (`&q=`)              |
| Ver      | GET    | `/backoffice/?accion=ver&c=especies&id=esp-verde` | Detalle: campos + JSON               |
| Crear    | GET/POST | `/backoffice/?accion=crear&c=especies`        | Formulario / guardar (POST `accion=guardar`) |
| Editar   | GET/POST | `/backoffice/?accion=editar&c=especies&id=…`  | Formulario precargado / actualizar      |
| Eliminar | GET+POST | `/backoffice/?accion=eliminar&c=especies&id=…` | GET = confirmación; POST = borra (nunca por GET) |

> **Rutas por query string.** El front controller es único (`/api/index.php`) y
> el servidor de desarrollo se lanza con `-t public` y un router, así que las
> rutas con subcarpetas (`/backoffice/entidad/edit`) siguen sin existir: todo
> pasa por `?accion=…`. Ver §8.

### API de datos (única válida, `src/services/JsonCollection.php`)

| Acción | Método | Ejemplo |
|--------|--------|---------|
| Listar | `find()` | `$db->collection('especies')->find([], ['sort' => ['orden' => 1], 'limit' => 20, 'skip' => 0])` |
| Buscar uno | `findOne()` / `findById()` | `$db->collection('especies')->findOne(['slug' => 'verde'])` |
| Contar | `count()` | `$db->collection('suscripciones')->count()` |
| Crear | `insert()` / `insertMany()` | `$db->collection('suscripciones')->insert(['email' => $email])` |
| Actualizar | `update()` (fusión) / `upsert()` (reemplazo) | `$db->collection('usuarios')->update($id, ['nombre' => $nombre])` |
| Eliminar | `delete()` / `deleteWhere()` / `truncate()` | `$db->collection('usuarios')->delete($id)` |
| Transacción | `transaction()` | `db()->transaction(fn (JsonDatabase $db) => …)` |

Filtros: igualdad directa (`['estado' => 'ok']`), campos anidados con puntos
(`['autor.nombre' => 'Ana']`) y operadores `$eq $ne $gt $gte $lt $lte $in $nin
$exists $contains $startsWith` (`['visitas' => ['$gte' => 10]]`).

Reglas:

- **Validación en servidor siempre** (el JavaScript del cliente es solo experiencia de uso, nunca seguridad): comprobar obligatoriedad, tipo, longitud y formato.
- Transacciones (`db()->transaction()`) cuando una operación toque varios
  documentos y deba ser todo-o-nada: si el cierre lanza una excepción, la BD
  revierte lo escrito (rollback).
- Los formularios reenvían los datos validados en caso de error, con mensajes claros junto al campo.
- Timestamps `created_at` / `updated_at` los gestiona `JsonCollection`; no se meten desde el formulario.

## 6. Base de datos

**Motor:** almacén documental JSON en disco, escrito en PHP puro
(`src/services/JsonDatabase.php` y `JsonCollection.php`). Sin MySQL, sin PDO,
sin servidor externo y sin dependencias de Composer.

- **Formato:** `data/<coleccion>/<id>.json`, un documento por fichero, en UTF-8.
- **Identificador:** campo `id` único (se autogenera si no viene). Mismas reglas que §4.
- **Esquema:** no hay definición de columnas: cada colección admite los campos
  que se necesiten. Los campos importantes de cada colección se documentan en
  `MEMORY.md` y se materializan en `seeds/`.
- **Relaciones:** por referencia (`id` o `slug` de otra colección). No hay
  *foreign keys*: la integridad se comprueba en código antes de insertar.
- **Concurrencia:** bloqueo `flock` (compartido en lecturas, exclusivo en
  escrituras) + escritura atómica (fichero temporal + `rename`) + transacciones
  con diario de cambios y rollback. Válido para un servidor PHP; si el proyecto
  crece, migrar a un servidor NoSQL real.
- **Consulta:** los filtros/órdenes se construyen en código con la whitelist de
  campos (§3); nunca se concatena lo que llega del navegador.
- **Semillas:** `seeds/<coleccion>.json` (lista de documentos con `id` fijo) →
  `php scripts/seed.php`. Es **idempotente**: se puede repetir sin duplicar datos.
- **Migraciones:** `migrations/NNN_descripcion.php` devuelve
  `['id' => …, 'descripcion' => …, 'up' => function (JsonDatabase $db): void]`
  → `php scripts/migrate.php`. Cada migración se ejecuta una vez y queda
  registrada en la colección `_migraciones`. **Nunca** cambiar la BD a mano sin dejar constancia.
- **Datos locales:** por defecto en `data/` (raíz del proyecto). El directorio
  tiene que ser escribible por el usuario que lanza PHP… **salvo en modo de solo
  lectura**, que solo exige poder leer.
- **Modo de solo lectura** (`readOnly`): la BD se abre sin exigir escritura y
  cualquier modificación falla con `ReadOnlyDatabaseException` en vez de con un
  error de permisos. Se activa automáticamente si detecta `VERCEL_ENV`, y se
  puede forzar con `APP_READONLY=1|0`. En modo lectura el BackOffice oculta los
  botones de escritura (responde `503` si se pulsarían) y el formulario de
  suscripción no guarda nada. En local está **desactivado**: el CRUD funciona
  entero.
- **Credenciales nunca en el repositorio:** usar `config.example.php` (que sí se
  versiona y es la base de la configuración) y crear `config/config.php` local
  (en `.gitignore`). Las variables de entorno se ajustan por encima de los
  ficheros: `APP_ENV`, `APP_READONLY`, `APP_KEY`, `APP_DATA_DIR` (ver
  `src/helpers/app.php`).

## 7. Cómo ejecutarlo en local

```bash
# 1) Comprueba la conexión y los permisos de la BD NoSQL
php scripts/check-db.php

# 2) Crea las colecciones (migraciones) y carga los datos de ejemplo
php scripts/migrate.php
php scripts/seed.php

# 3) Arranca el servidor. -t public es OBLIGATORIO: es el DocumentRoot y hace
#    que /assets/... se sirvan como estáticos, igual que en Vercel.
php -S localhost:8000 -t public api/index.php

# Auto-test de la capa de datos (44 aserciones; usa un dir temporal, no toca /data)
php scripts/self-test.php
```

- **URL web pública:** `http://localhost:8000/`
- **URL BackOffice:** `http://localhost:8000/backoffice/` (login con el usuario
  sembrado en `seeds/usuarios.json`; ver `MEMORY.md` §4)
- Si la BD no está inicializada, la web se muestra con un aviso rojo en lugar de romperse.

## 8. Despliegue en Vercel y reglas para agentes

`vercel.json` fija el runtime (`vercel-php@0.8.0` → PHP 8.4) y las rutas: los
assets de `/public` se sirven como estáticos (`{ "handle": "filesystem" }`) y
todo lo demás va a `/api/index.php`.

**Variable de entorno obligatoria: `APP_KEY`** (secreto de las cookies de
sesión; `openssl rand -hex 32`). Sin ella las sesiones no son fiables.

### Reglas

- **No** incluir credenciales reales, API keys ni datos de producción en el código ni en `/seeds`.
- **No** ejecutar `deleteWhere()`/`truncate()` ni borrar `/data` contra datos que no sean de desarrollo.
- Todo cambio de estructura de datos debe acompañarse de su migración en
  `/migrations` (y de actualizar `seeds/` si los datos de ejemplo cambian).
- Nunca escribir ficheros de `/data` directamente: siempre con `db()` (§3).
- **Nunca añadir un `.php` dentro de `/public`**: se serviría como código fuente.
- Mantener el patrón CRUD (sección 5) **idéntico** en todas las entidades nuevas.
- Si se rompe un patrón por un motivo concreto, documentarlo aquí mismo.

### Desviaciones documentadas

  > **Front controller único y `/public` solo con assets.** Vercel sirve
  > literalmente todo lo que hay en `/public`, así que un `.php` dentro se
  > publicaría como código fuente en vez de ejecutarse. Por eso los entry
  > points viven en `/web` y `/backoffice`, y `/api/index.php` enruta por
  > tabla explícita (nunca un `include` con lo que llega del navegador).

  > **Rutas del BackOffice por query string.** §5 usa `?accion=…&c=…&id=…` en
  > lugar de `/backoffice/entidad/edit`. Motivo: son URLs con subcarpetas y sin
  > extensión; el front controller las sirve igualmente, pero el servidor de
  > desarrollo y Vercel resuelven antes el sistema de ficheros. Las pantallas,
  > métodos, CSRF y validaciones son los de §5; solo cambia la forma de la URL.

  > **Sesiones en cookie firmada** en lugar de `session_start()`, y **modo de
  > solo lectura** en Vercel. Los dos cambios vienen del mismo límite: el disco
  > de las funciones es de solo lectura y `/tmp` no se comparte entre
  > instancias. El CRUD completo sigue intacto en local.

- Antes de terminar una tarea: probar el flujo completo crear → ver → editar → eliminar (y `php scripts/check-db.php` + `php scripts/self-test.php`). En despliegue, comprobar además que en modo solo lectura las escrituras responden `503` y no se crea ningún fichero en `/data`.
- **Actualizar `MEMORY.md`** con el estado resultante.
