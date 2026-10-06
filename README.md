# MyDemo · Tortugas Vivas

Web dinámica en **PHP 8** (vanilla, sin framework ni dependencias) con base de
datos **NoSQL documental** (un fichero JSON por documento) y un **BackOffice**
CRUD genérico. Preparada para desplegar en **Vercel** con el runtime
[`vercel-php`](https://github.com/vercel-community/php).

> Documentación completa del proyecto en [`AGENTS.md`](AGENTS.md) (reglas) y
> [`MEMORY.md`](MEMORY.md) (estado y decisiones).

---

## Cómo ejecutar en local

```bash
# 1) Prepara la base de datos documental (migraciones + datos de ejemplo)
php scripts/check-db.php
php scripts/migrate.php
php scripts/seed.php

# 2) Arranca el servidor. OJO al DocumentRoot: ha de ser /public
php -S localhost:8000 -t public api/index.php
```

- **Web pública:** <http://localhost:8000/>
- **BackOffice:** <http://localhost:8000/backoffice/> — `admin@localhost.test` / `admin1234`
- **Auto-test de la capa de datos:** `php scripts/self-test.php` (44 aserciones)

`-t public` es obligatorio: es lo que hace que `/assets/...` se sirvan como
ficheros estáticos (igual que en Vercel).

## Desplegar en Vercel

```bash
npm i -g vercel
vercel          # preview
vercel --prod   # producción
```

El repositorio ya trae `vercel.json` con el runtime y las rutas, así que no hay
que configurar nada en el panel salvo la variable de entorno de abajo.

### Variable de entorno obligatoria

| Variable | Para qué | Obligatoria |
|----------|----------|-------------|
| `APP_KEY` | Secreto para firmar las cookies de sesión (HMAC-SHA256) | **Sí en Vercel** |

Genera un valor aleatorio largo, por ejemplo:

```bash
openssl rand -hex 32
```

Defínelo en *Project Settings → Environment Variables*. Sin ella las sesiones
no son fiables: cada instancia firmaría con un secreto distinto y el login del
BackOffice fallaría de forma intermitente.

### Qué esperar del despliegue

El despliegue es una **demo de solo lectura** (ver más abajo):

- La web pública y el BackOffice se consultan igual que en local.
- No se puede crear, editar ni eliminar: las pantallas de escritura responden
  `503` con un aviso y los botones desaparecen.
- El formulario de suscripción no guarda nada.

## Modo de solo lectura

En Vercel el sistema de ficheros es **de solo lectura** (solo `/tmp` es
escribible, y no se comparte entre instancias), así que un almacén documental
en disco no puede aceptar escrituras. Por eso la base de datos se abre en modo
de solo lectura cuando detecta `VERCEL_ENV`, y **en local sigue siendo
plenamente funcional**: el CRUD completo se puede seguir probando en tu máquina.

El interruptor es `readOnly` en la configuración, ajustable con la variable de
entorno `APP_READONLY` (`1` para forzarlo, `0` para desactivarlo):

| Entorno | Modo | Por defecto |
|---------|------|-------------|
| Local | con escritura | `false` |
| Vercel | solo lectura | `true` (automático por `VERCEL_ENV`) |

Para habilitar el CRUD en producción hace falta un backend de escritura real
(Atlas, Vercel KV, Neon…) detrás de la misma API de `JsonCollection`.

## Cómo está organizado

```
api/index.php      front controller ÚNICO (Vercel y local)
web/               entry points de la web pública
backoffice/        front controller del panel
public/            SOLO assets estáticos (nunca PHP: se serviría como fuente)
src/               helpers y capa de datos
templates/         vistas PHP
data/              la base de datos documental (un JSON por documento)
seeds/ migrations/ scripts/
```

Detalle en [`AGENTS.md`](AGENTS.md) §2.
