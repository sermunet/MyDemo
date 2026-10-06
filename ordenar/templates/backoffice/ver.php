<?php
/**
 * Detalle de un documento: campos legibles + JSON tal y como se guarda.
 *
 * Variables: $coleccion, $doc (array).
 */
$id = (string) ($doc['id'] ?? '');
?>
<section>
  <div class="bo-page-head">
    <div>
      <p class="bo-breadcrumb">
        <a href="<?= e(boUrl()) ?>">Panel</a>
        <span aria-hidden="true">/</span>
        <a href="<?= e(boUrl(['accion' => 'listar', 'c' => $coleccion])) ?>"><?= e($coleccion) ?></a>
        <span aria-hidden="true">/</span> <?= e($id) ?>
      </p>
      <h1><?= e($id) ?></h1>
    </div>

    <div class="bo-page-actions">
      <a class="bo-btn" href="<?= e(boUrl(['accion' => 'listar', 'c' => $coleccion])) ?>">← Volver</a>
      <a class="bo-btn bo-btn-primary" href="<?= e(boUrl(['accion' => 'editar', 'c' => $coleccion, 'id' => $id])) ?>">Editar</a>
      <a class="bo-btn bo-btn-danger" href="<?= e(boUrl(['accion' => 'eliminar', 'c' => $coleccion, 'id' => $id])) ?>">Eliminar</a>
    </div>
  </div>

  <div class="bo-card">
    <h2>Campos</h2>
    <dl class="bo-dl">
      <?php foreach ($doc as $campo => $valor): ?>
        <dt><?= e($campo) ?></dt>
        <dd class="<?= is_array($valor) ? 'bo-mono' : '' ?>"><?= e(boResumen($valor)) ?></dd>
      <?php endforeach; ?>
    </dl>
  </div>

  <div class="bo-card">
    <h2>JSON</h2>
    <p class="bo-muted bo-small">Así se guarda en <code>/data/<?= e($coleccion) ?>/<?= e($id) ?>.json</code>.</p>
    <pre class="bo-code"><?= e(boJsonFormateado($doc)) ?></pre>
  </div>
</section>
