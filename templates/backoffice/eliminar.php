<?php
/**
 * Confirmación de borrado: solo se borra con POST + CSRF (AGENTS.md §4),
 * nunca por un enlace GET.
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
      <h1>Eliminar documento</h1>
    </div>
  </div>

  <div class="bo-card bo-card-narrow bo-card-danger">
    <p>
      Vas a eliminar <strong class="bo-mono"><?= e($id) ?></strong> de la
      colección <strong><?= e($coleccion) ?></strong>. Esta acción borra el
      fichero JSON y <strong>no se puede deshacer</strong>.
    </p>

    <pre class="bo-code"><?= e(boCortar(boJsonFormateado($doc), 600)) ?></pre>

    <div class="bo-card-actions">
      <form method="post" action="<?= e(boUrl(['accion' => 'eliminar', 'c' => $coleccion, 'id' => $id])) ?>">
        <?= csrfField() ?>
        <button class="bo-btn bo-btn-danger" type="submit">Sí, eliminar</button>
      </form>
      <a class="bo-btn bo-btn-ghost" href="<?= e(boUrl(['accion' => 'editar', 'c' => $coleccion, 'id' => $id])) ?>">Cancelar, editar en su lugar</a>
      <a class="bo-btn bo-btn-ghost" href="<?= e(boUrl(['accion' => 'listar', 'c' => $coleccion])) ?>">Cancelar, volver a la lista</a>
    </div>
  </div>
</section>
