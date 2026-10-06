<?php
/**
 * Panel principal: colecciones disponibles en /data con su nº de documentos.
 *
 * Variables: $colecciones (array<string>), $estadisticas (array<string,int>).
 */
?>
<section>
  <div class="bo-page-head">
    <div>
      <h1>Panel</h1>
      <p class="bo-muted">Cada colección es una carpeta de <code>/data</code> y cada documento un fichero JSON.</p>
    </div>
  </div>

  <?php if ($colecciones === []): ?>
    <div class="bo-empty">
      <p><strong>No hay ninguna colección todavía.</strong></p>
      <p>Inicializa la base de datos con <code>php scripts/migrate.php</code> y <code>php scripts/seed.php</code>.</p>
    </div>
  <?php else: ?>
    <div class="bo-grid">
      <?php foreach ($colecciones as $coleccion): ?>
        <?php $n = (int) ($estadisticas[$coleccion] ?? 0); ?>
        <article class="bo-card bo-card-item">
          <h2><?= e($coleccion) ?></h2>
          <p class="bo-count">
            <?= $n === 1 ? '1 documento' : $n . ' documentos' ?>
          </p>
          <div class="bo-card-actions">
            <a class="bo-btn" href="<?= e(boUrl(['accion' => 'listar', 'c' => $coleccion])) ?>">Ver documentos</a>
            <a class="bo-btn bo-btn-primary" href="<?= e(boUrl(['accion' => 'crear', 'c' => $coleccion])) ?>">Crear</a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <p class="bo-muted bo-small">
      Las colecciones internas (las que empiezan por <code>_</code>, como
      <code>_migraciones</code>) no se muestran aquí: se gestionan con
      <code>php scripts/migrate.php</code>.
    </p>
  <?php endif; ?>
</section>
