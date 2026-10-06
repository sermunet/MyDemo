# AGENTS.md

Guía para agentes de IA y desarrolladores que trabajen en este proyecto.

## 1. Visión del proyecto

Web dinámica en **PHP** con base de datos **NoSQL** (almacén documental JSON en
disco), incluida un **BackOffice** desde el que se gestionan los datos mediante
formularios con operaciones CRUD (crear, leer, actualizar, eliminar).

- **Stack:** PHP 8.x (vanilla, sin framework) + base de datos **NoSQL documental JSON** en `/data`.
  **No usa MySQL, PDO, SQL ni ningún servidor de base de datos**: cada documento es un fichero JSON.
- **Público:** usuarios finales en la web pública; administradores en el BackOffice.
- **Objetivo:** que toda la información se actualice desde formularios del BackOffice, sin tocar la base de datos ni los archivos a mano.

> Si en algún momento se sustituye el almacén JSON por un servidor NoSQL real
> (MongoDB, CouchDB, Redis…) o se adopta un framework (Laravel, Symfony…),
> actualizar esta sección y las secciones 3, 5 y 6.

## 2. Estructura de carpetas

```
/public        → punto de entrada (index.php, suscribir.php, backoffice/, assets css/js/img)
/src           → lógica (controllers, models, services, helpers)
/templates     → vistas PHP (HTML con <?php echo ?>)
/backoffice    → panel CRUD: front controller (app.php) + helpers, protegido por sesión
/config        → configuración (config.example.php → copiar a config.php)
/data          → BASE DE DATOS: colecciones y documentos JSON (no se versiona)
/seeds         → documentos de ejemplo (JSON) para inicializar la BD
/migrations    → migraciones PHP (NNN_descripcion.php)
/scripts       → utilidades de consola (check-db, migrate, seed, self-test)
```

- Nada fuera de `/public` se sirve directamente al navegador.
- El DocumentRoot del servidor apunta a `/public`.
- **`/data` es la base de datos**: está en `.gitignore`; nunca se versiona ni se edita a mano.

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
públicos que escriben en la BD, como `public/suscribir.php`):

- **Autenticación:** todas las rutas pasan por `require_login()`; sin sesión → redirect a login.
- **Contraseñas:** solo `password_hash()` (BCRYPT) y `password_verify()`. Nunca MD5/SHA1 ni contraseñas en claro.
- **CSRF:** token generado por sesión y validado en **todos** los POST (crear, editar, eliminar).
- **Sesiones:** cookies con `HttpOnly`, `SameSite=Lax` y `secure` en producción; `session_regenerate_id()` al hacer login.
- **Salida:** todo dato escapado con `htmlspecialchars($v, ENT_QUOTES, 'UTF-8')` (helper `e()`).
- **Métodos:** `DELETE` solo por POST (o DELETE con CSRF), nunca por enlace GET.
- **Permisos:** comprobar rol antes de acciones destructivas; el usuario no validado no ve ni prueba el BackOffice.
- **Subida de archivos:** validar MIME, tamaño y renombrar el archivo; nunca dejar el nombre original decidir la ruta.
- **Identificadores:** el `id` de un documento forma parte de la ruta del fichero,
  por eso se valida con una whitelist (`[A-Za-z0-9._-]`, máx. 64). Esto bloquea
  cualquier intento de *path traversal* (`../../`) hacia fuera de `/data`.

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

> **Rutas por query string.** El DocumentRoot es `/public` y el servidor de
> desarrollo (`php -S … -t public`) no lleva router, así que las rutas con
> subcarpetas (`/backoffice/entidad/edit`) no existen: todo pasa por el front
> controller único `public/backoffice/index.php`. Ver §8.

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
  tiene que ser escribible por el usuario que lanza PHP.
- **Credenciales nunca en el repositorio:** usar `config.example.php` como
  plantilla y crear `config/config.php` local (que va en `.gitignore`).

## 7. Cómo ejecutarlo en local

```bash
# 1) Comprueba la conexión y los permisos de la BD NoSQL
php scripts/check-db.php

# 2) Crea las colecciones (migraciones) y carga los datos de ejemplo
php scripts/migrate.php
php scripts/seed.php

# 3) Arranca el servidor con DocumentRoot en /public
php -S localhost:8000 -t public

# Auto-test de la capa de datos (44 aserciones; usa un dir temporal, no toca /data)
php scripts/self-test.php
```

- **URL web pública:** `http://localhost:8000/`
- **URL BackOffice:** `http://localhost:8000/backoffice/` (login con el usuario
  sembrado en `seeds/usuarios.json`; ver `MEMORY.md` §3)
- Si la BD no está inicializada, la web se muestra con un aviso rojo en lugar de romperse.

## 8. Reglas para agentes

- **No** incluir credenciales reales, API keys ni datos de producción en el código ni en `/seeds`.
- **No** ejecutar `deleteWhere()`/`truncate()` ni borrar `/data` contra datos que no sean de desarrollo.
- Todo cambio de estructura de datos debe acompañarse de su migración en
  `/migrations` (y de actualizar `seeds/` si los datos de ejemplo cambian).
- Nunca escribir ficheros de `/data` directamente: siempre con `db()` (§3).
- Mantener el patrón CRUD (sección 5) **idéntico** en todas las entidades nuevas.
- Si se rompe un patrón por un motivo concreto, documentarlo aquí mismo.

  > **Desviación documentada (rutas del BackOffice):** §5 usa rutas por query
  > string (`?accion=…&c=…&id=…`) en lugar de `/backoffice/entidad/edit`.
  > Motivo: el DocumentRoot es `/public` y `php -S … -t public` no acepta un
  > router, así que una URL con subcarpetas sin extensión devolvería 404. Las
  > pantallas, métodos, CSRF y validaciones son exactamente los de §5; solo
  > cambia la forma de la URL. Si se adopta Apache/nginx con reescrituras (o un
  > router en `php -S`), se puede pasar a rutas limpias sin tocar la lógica.

- Antes de terminar una tarea: probar el flujo completo crear → ver → editar → eliminar (y `php scripts/check-db.php` + `php scripts/self-test.php`).
- **Actualizar `MEMORY.md`** con el estado resultante.
