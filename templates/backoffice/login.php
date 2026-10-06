<?php
/**
 * Login del BackOffice (AGENTS.md §4): solo POST + CSRF + password_verify().
 *
 * Variables: $error (?string), $email (string).
 */
?>
<section class="bo-card bo-card-narrow">
  <h1>Entrar en el BackOffice</h1>
  <p class="bo-muted">Solo administradores. La sesión se regenera al iniciar y caduca al cerrar el navegador.</p>

  <?php if (!empty($error)): ?>
    <p class="bo-error" role="alert"><?= e($error) ?></p>
  <?php endif; ?>

  <form method="post" action="<?= e(boUrl(['accion' => 'login'])) ?>" class="bo-form">
    <?= csrfField() ?>

    <label class="bo-field">
      <span>Correo</span>
      <input type="email" name="email" value="<?= e($email ?? '') ?>" required autocomplete="username" autofocus />
    </label>

    <label class="bo-field">
      <span>Contraseña</span>
      <input type="password" name="password" required autocomplete="current-password" />
    </label>

    <button class="bo-btn bo-btn-primary" type="submit">Iniciar sesión</button>
  </form>

  <p class="bo-muted bo-small">
    ¿Sin acceso? El usuario inicial se carga con
    <code>php scripts/seed.php</code> y la contraseña se cambia editando el
    documento en la colección <code>usuarios</code>.
  </p>
</section>
