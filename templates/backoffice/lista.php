<?php
/**
 * Lista de documentos de una colección con buscador.
 *
 * Variables: $coleccion, $documentos (array), $total (int),
 *            $columnas (array<string>), $consulta (string).
 */
?>
<section>
  <div class="bo-page-head">
    <div>
      <p class="bo-breadcrumb">
        <a href="<?= e(boUrl()) ?>">Panel</a> <span aria-hidden="true">/</span> <?= e($coleccion) ?>
      </p>
      <h1><?= e($coleccion) ?></h1>
      <p class="bo-muted">
        <?= count($documentos) === $total
            ? ($total === 1 ? '1 documento' : $total . ' documentos')
            : count($documentos) . ' de ' . $total . ' documentos' ?>
      </p>
    </div>

    <div class="bo-page-actions">
      <?php if (!boSoloLectura()): ?>
        <a class="bo-btn bo-btn-primary" href="<?= e(boUrl(['accion' => 'crear', 'c' => $coleccion])) ?>">＋ Crear documento</a>
      <?php endif; ?>
    </div>
  </div>

  <form method="get" action="/backoffice/" class="bo-search">
    <input type="hidden" name="accion" value="listar" />
    <input type="hidden" name="c" value="<?= e($coleccion) ?>" />
    <input type="search" name="q" value="<?= e($consulta) ?>" placeholder="Buscar en id y campos de texto…" aria-label="Buscar" />
    <button class="bo-btn" type="submit">Buscar</button>
    <?php if ($consulta !== ''): ?>
      <a class="bo-btn bo-btn-ghost" href="<?= e(boUrl(['accion' => 'listar', 'c' => $coleccion])) ?>">Limpiar</a>
    <?php endif; ?>
  </form>

  <?php if ($documentos === []): ?>
    <div class="bo-empty">
      <?php if ($consulta !== ''): ?>
        <p>Ningún documento coincide con «<?= e($consulta) ?>».</p>
      <?php else: ?>
        <p><strong>Esta colección está vacía.</strong></p>
        <?php if (!boSoloLectura()): ?>
          <p><a href="<?= e(boUrl(['accion' => 'crear', 'c' => $coleccion])) ?>">Crear el primer documento →</a></p>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="bo-table-wrap">
      <table class="bo-table">
        <thead>
          <tr>
            <th scope="col">id</th>
            <?php foreach ($columnas as $columna): ?>
              <th scope="col"><?= e($columna) ?></th>
            <?php endforeach; ?>
            <th scope="col">actualizado</th>
            <th scope="col" class="bo-col-actions">acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($documentos as $doc): ?>
            <tr>
              <td class="bo-mono">
                <a href="<?= e(boUrl(['accion' => 'ver', 'c' => $coleccion, 'id' => $doc['id'] ?? ''])) ?>">
                  <?= e($doc['id'] ?? '(sin id)') ?>
                </a>
              </td>
              <?php foreach ($columnas as $columna): ?>
                <td><?= e(boResumen($doc[$columna] ?? null)) ?></td>
              <?php endforeach; ?>
              <td class="bo-muted bo-nowrap"><?= e($doc['updated_at'] ?? '—') ?></td>
              <td class="bo-col-actions">
                <?php if (!boSoloLectura()): ?>
                  <a class="bo-btn bo-btn-sm" href="<?= e(boUrl(['accion' => 'editar', 'c' => $coleccion, 'id' => $doc['id'] ?? ''])) ?>">Editar</a>
                  <a class="bo-btn bo-btn-sm bo-btn-danger" href="<?= e(boUrl(['accion' => 'eliminar', 'c' => $coleccion, 'id' => $doc['id'] ?? ''])) ?>">Eliminar</a>
                <?php else: ?>
                  <a class="bo-btn bo-btn-sm" href="<?= e(boUrl(['accion' => 'ver', 'c' => $coleccion, 'id' => $doc['id'] ?? ''])) ?>">Ver</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
