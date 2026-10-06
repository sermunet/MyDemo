<?php
/**
 * Plantilla común del BackOffice (AGENTS.md §4): barra superior con sesión,
 * mensajes flash con whitelist y contenido renderizado por cada pantalla.
 *
 * Variables: $titulo, $usuario (?array), $contenido (HTML ya renderizado).
 */
$usuario = $usuario ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow" />
  <title><?= e($titulo ?? 'BackOffice') ?> · BackOffice</title>
  <link rel="stylesheet" href="/assets/css/backoffice.css" />
  <script src="/assets/js/backoffice.js" defer></script>
</head>
<body class="bo">

  <header class="bo-topbar">
    <a class="bo-brand" href="<?= e(boUrl()) ?>"><span aria-hidden="true">🐢</span> BackOffice</a>

    <nav class="bo-nav">
      <a href="<?= e(boUrl()) ?>">Panel</a>
      <a href="/" target="_blank" rel="noopener">Ver la web ↗</a>
    </nav>

    <div class="bo-user">
      <?php if ($usuario !== null): ?>
        <span class="bo-user-name" title="<?= e($usuario['email'] ?? '') ?>"><?= e($usuario['nombre'] ?? $usuario['email'] ?? '') ?></span>
        <form method="post" action="<?= e(boUrl(['accion' => 'logout'])) ?>">
          <?= csrfField() ?>
          <button class="bo-btn bo-btn-ghost" type="submit">Cerrar sesión</button>
        </form>
      <?php else: ?>
        <a class="bo-btn bo-btn-ghost" href="<?= e(boUrl(['accion' => 'login'])) ?>">Entrar</a>
      <?php endif; ?>
    </div>
  </header>

  <?php $flash = boFlash(); ?>
  <?php if ($flash !== null): ?>
    <div class="bo-flash" role="status"><?= e($flash) ?></div>
  <?php endif; ?>

  <?php if (boSoloLectura()): ?>
    <div class="bo-flash bo-flash--readonly" role="status">
      🔒 Modo de solo lectura: este despliegue muestra los datos pero no permite
      crear, editar ni eliminar documentos.
    </div>
  <?php endif; ?>

  <main class="bo-main">
    <?= $contenido ?>
  </main>

  <footer class="bo-foot">
    BackOffice · base de datos NoSQL documental en <code>/data</code> ·
    ninguna pantalla escribe ficheros a mano (AGENTS.md §3)
  </footer>

</body>
</html>
