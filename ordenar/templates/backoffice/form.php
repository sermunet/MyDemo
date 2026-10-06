<?php
/**
 * Formulario de creación y edición de documentos.
 *
 * Dos modos (la elección viaja en el campo `modo` y lo decide el servidor):
 *   - "campos": inputs generados a partir de los tipos reales del documento.
 *   - "json":   un editor de texto con el documento completo (AGENTS.md §5
 *               permite el modo crudo, pero la validación siempre es en servidor).
 *
 * Variables: $coleccion, $modo ('crear'|'editar'), $idEdit, $doc, $tipos,
 *            $json, $borrar, $nuevo, $modoActual, $error (?string).
 */

$esEditar  = $modo === 'editar';
$modoActual = $modoActual ?? 'campos';
$tipos     = $tipos ?? [];
$borrar    = $borrar ?? [];
$json      = $json ?? boJsonFormateado($doc);

$tiposPermitidos = ['text' => 'Texto', 'textarea' => 'Texto largo', 'number' => 'Número', 'bool' => 'Sí / No', 'json' => 'JSON'];

/** Valor listo para imprimir en un input de texto. */
$paraTexto = static function (mixed $valor): string {
    if (is_array($valor)) {
        return (string) json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    if (is_bool($valor) || $valor === null) {
        return $valor === null ? '' : ($valor ? 'true' : 'false');
    }

    return (string) $valor;
};

/** Valor listo para imprimir dentro de un <textarea> tipo JSON. */
$paraJson = static function (mixed $valor): string {
    if (is_array($valor)) {
        return boJsonFormateado($valor);
    }

    if (is_string($valor)) {
        return $valor; // JSON ya escrito a mano (se reenvía tal cual tras un error)
    }

    return json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
};

$camposVisibles = [];

foreach (array_keys($doc) as $campo) {
    $campo = (string) $campo;

    if ($campo === 'id' || boEsTimestamp($campo)) {
        continue;
    }

    $camposVisibles[] = $campo;
}

$action = $esEditar
    ? boUrl(['accion' => 'guardar', 'c' => $coleccion, 'id' => $idEdit])
    : boUrl(['accion' => 'guardar', 'c' => $coleccion]);
?>
<section>
  <div class="bo-page-head">
    <div>
      <p class="bo-breadcrumb">
        <a href="<?= e(boUrl()) ?>">Panel</a>
        <span aria-hidden="true">/</span>
        <a href="<?= e(boUrl(['accion' => 'listar', 'c' => $coleccion])) ?>"><?= e($coleccion) ?></a>
        <span aria-hidden="true">/</span>
        <?= $esEditar ? e($idEdit) : 'nuevo' ?>
      </p>
      <h1><?= $esEditar ? 'Editar ' . e($idEdit) : 'Crear documento en ' . e($coleccion) ?></h1>
    </div>

    <div class="bo-page-actions">
      <a class="bo-btn bo-btn-ghost" href="<?= e(boUrl(['accion' => 'listar', 'c' => $coleccion])) ?>">Cancelar</a>
    </div>
  </div>

  <?php if (!empty($error)): ?>
    <p class="bo-error" role="alert"><?= e($error) ?></p>
  <?php endif; ?>

  <form method="post" action="<?= e($action) ?>" class="bo-form" data-bo-form>
    <?= csrfField() ?>

    <div class="bo-modes" data-bo-modes>
      <label class="bo-mode">
        <input type="radio" name="modo" value="campos" data-bo-mode-radio
               <?= $modoActual === 'campos' ? 'checked' : '' ?> />
        Campos
      </label>
      <label class="bo-mode">
        <input type="radio" name="modo" value="json" data-bo-mode-radio
               <?= $modoActual === 'json' ? 'checked' : '' ?> />
        JSON
      </label>
      <span class="bo-muted bo-small">Elige cómo quieres editar; el servidor valida lo que envíes.</span>
    </div>

    <!-- ------------------------------ MODO CAMPOS ------------------------------ -->
    <fieldset class="bo-card" data-bo-mode="campos">
      <legend>Campos del documento</legend>

      <label class="bo-field">
        <span>id <small>(letras, dígitos, punto, guion y guion bajo · máx. 64)</small></span>
        <?php if ($esEditar): ?>
          <input type="text" value="<?= e($idEdit) ?>" readonly disabled
                 aria-describedby="bo-id-nota" />
          <small id="bo-id-nota" class="bo-muted">El id es el nombre del fichero: en edición no se cambia.</small>
        <?php else: ?>
          <input type="text" name="id" value="<?= e($paraTexto($doc['id'] ?? '')) ?>"
                 pattern="[A-Za-z0-9][A-Za-z0-9._-]{0,63}"
                 placeholder="Vacío = id automático" />
        <?php endif; ?>
      </label>

      <?php if ($esEditar && (isset($doc['created_at']) || isset($doc['updated_at']))): ?>
        <p class="bo-muted bo-small">
          created_at: <code><?= e($doc['created_at'] ?? '—') ?></code>
          · updated_at: <code><?= e($doc['updated_at'] ?? '—') ?></code>
          (los gestiona la base de datos)
        </p>
      <?php endif; ?>

      <?php if ($camposVisibles === []): ?>
        <p class="bo-muted">Este documento aún no tiene campos. Añade el primero aquí abajo o edítalo en modo JSON.</p>
      <?php endif; ?>

      <?php foreach ($camposVisibles as $campo): ?>
        <?php
            $tipo   = $tipos[$campo] ?? 'text';
            $tipo   = in_array($tipo, array_keys($tiposPermitidos), true) ? $tipo : 'text';
            $valor  = $doc[$campo] ?? null;
            $marcado = isset($borrar[$campo]);
        ?>
        <div class="bo-field-row" data-bo-field>
          <label class="bo-field bo-field-main">
            <span><?= e($campo) ?></span>

            <?php if ($tipo === 'textarea'): ?>
              <textarea name="campo[<?= e($campo) ?>]" rows="3"><?= e($paraTexto($valor)) ?></textarea>

            <?php elseif ($tipo === 'json'): ?>
              <textarea class="bo-mono" name="campo[<?= e($campo) ?>]" rows="3"><?= e($paraJson($valor)) ?></textarea>

            <?php elseif ($tipo === 'number'): ?>
              <input type="number" step="any" name="campo[<?= e($campo) ?>]"
                     value="<?= e($paraTexto($valor)) ?>" />

            <?php elseif ($tipo === 'bool'): ?>
              <input type="hidden" name="campo[<?= e($campo) ?>]" value="" />
              <input type="checkbox" name="campo[<?= e($campo) ?>]" value="1"
                     <?= $valor ? 'checked' : '' ?> />
              <small class="bo-muted">marcado = true</small>

            <?php else: ?>
              <input type="text" name="campo[<?= e($campo) ?>]" value="<?= e($paraTexto($valor)) ?>" />
            <?php endif; ?>
          </label>

          <label class="bo-field bo-field-type">
            <span class="bo-sr-only">Tipo de <?= e($campo) ?></span>
            <select name="tipo[<?= e($campo) ?>]" aria-label="Tipo de <?= e($campo) ?>">
              <?php foreach ($tiposPermitidos as $valorTipo => $etiquetaTipo): ?>
                <option value="<?= e($valorTipo) ?>" <?= $tipo === $valorTipo ? 'selected' : '' ?>>
                  <?= e($etiquetaTipo) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>

          <label class="bo-field bo-field-drop" title="Quitar este campo al guardar">
            <input type="checkbox" name="borrar[<?= e($campo) ?>]" value="1" <?= $marcado ? 'checked' : '' ?> />
            <span>quitar</span>
          </label>
        </div>
      <?php endforeach; ?>

      <div class="bo-field-row bo-field-new">
        <label class="bo-field bo-field-main">
          <span>＋ Nuevo campo <small>(nombre)</small></span>
          <input type="text" name="nuevo_nombre" value="<?= e($nuevo['nombre'] ?? '') ?>"
                 pattern="[A-Za-z0-9_][A-Za-z0-9_-]{0,63}" placeholder="nombre_nuevo" />
        </label>

        <label class="bo-field bo-field-type">
          <span class="bo-sr-only">Tipo del nuevo campo</span>
          <select name="nuevo_tipo" aria-label="Tipo del nuevo campo">
            <?php foreach ($tiposPermitidos as $valorTipo => $etiquetaTipo): ?>
              <option value="<?= e($valorTipo) ?>" <?= ($nuevo['tipo'] ?? 'text') === $valorTipo ? 'selected' : '' ?>>
                <?= e($etiquetaTipo) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>

        <label class="bo-field bo-field-value">
          <span class="bo-sr-only">Valor del nuevo campo</span>
          <textarea rows="1" name="nuevo_valor" placeholder="valor (para Sí/No: true; para JSON: {…} o […])"><?= e($nuevo['valor'] ?? '') ?></textarea>
        </label>
      </div>
    </fieldset>

    <!-- ------------------------------- MODO JSON ------------------------------- -->
    <fieldset class="bo-card" data-bo-mode="json">
      <legend>Documento JSON</legend>

      <p class="bo-muted bo-small">
        Objeto JSON completo. El <code>id</code> y los timestamps
        (<code>created_at</code> / <code>updated_at</code>) los controla el
        servidor: no los cambies aquí.
      </p>

      <textarea class="bo-mono bo-json" name="json" rows="18" spellcheck="false"
                aria-label="Documento JSON"><?= e($json) ?></textarea>

      <p class="bo-small">
        <button type="button" class="bo-btn bo-btn-sm" data-bo-format>Formatear JSON</button>
        <span class="bo-muted" data-bo-format-msg></span>
      </p>
    </fieldset>

    <div class="bo-form-actions">
      <button class="bo-btn bo-btn-primary" type="submit">
        <?= $esEditar ? 'Guardar cambios' : 'Crear documento' ?>
      </button>
      <a class="bo-btn bo-btn-ghost" href="<?= e(boUrl(['accion' => 'listar', 'c' => $coleccion])) ?>">Cancelar</a>
    </div>

    <p class="bo-muted bo-small">
      Todo se valida en el servidor antes de escribir en <code>/data/<?= e($coleccion) ?></code>
      (AGENTS.md §5): tipos, nombres de campo, tamaño máximo y id.
    </p>
  </form>
</section>
