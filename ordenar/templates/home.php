<?php
/**
 * Landing "Tortugas Vivas".
 *
 * Todo el contenido visible (estadísticas, especies, curiosidades y medidas
 * de protección) se lee de la base de datos NoSQL (AGENTS.md §5 y §6).
 *
 * Variables que recibe desde public/index.php:
 *   $dbOk (bool), $dbMessage (string), $flashMessage (?string),
 *   $estadisticas, $especies, $curiosidades, $protecciones (arrays)
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Tortugas · Conoce a las guardianas del mar</title>
  <meta name="description" content="Landing page sobre tortugas: especies, curiosidades y cómo ayudar a protegerlas." />
  <link rel="stylesheet" href="/assets/css/style.css" />
</head>
<body>

<?php if (!$dbOk): ?>
  <div class="db-banner" role="alert">
    ⚠️ Sin conexión con la base de datos NoSQL: <?= e($dbMessage) ?>
    <br />
    Inicialízala con <code>php scripts/migrate.php</code> y <code>php scripts/seed.php</code>.
  </div>
<?php endif; ?>

  <header>
    <div class="container nav">
      <a class="brand" href="#inicio"><span class="logo">🐢</span> Tortugas Vivas</a>
      <nav>
        <ul class="nav-links">
          <li><a href="#especies">Especies</a></li>
          <li><a href="#curiosidades">Curiosidades</a></li>
          <li><a href="#proteccion">Protección</a></li>
          <li><a href="#contacto">Participa</a></li>
        </ul>
      </nav>
    </div>
  </header>

  <main id="inicio">

    <section class="hero">
      <div class="container">
        <span class="hero-art">🐢</span>
        <h1>Las guardianas del mar</h1>
        <p class="lead">
          Han recorrido los océanos durante más de 100 millones de años.
          Conoce a las tortugas, descubre su mundo y aprende cómo puedes ayudarlas.
        </p>
        <a class="btn btn-primary" href="#especies">Descubre las especies</a>
        <a class="btn btn-ghost" href="#proteccion">Cómo ayudar</a>
      </div>
    </section>

    <section id="datos">
      <div class="container">
        <h2 class="section-title">Tortugas en números</h2>
        <p class="section-sub">Datos que ayudan a entender por qué su conservación es urgente.</p>
        <div class="stats">
          <?php foreach ($estadisticas as $stat): ?>
            <div class="stat">
              <b><?= e($stat['valor'] ?? '') ?></b>
              <span><?= e($stat['etiqueta'] ?? '') ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section id="especies">
      <div class="container">
        <h2 class="section-title">Conoce sus especies</h2>
        <p class="section-sub">Cada tortuga marina tiene un carácter, un tamaño y un hogar distintos.</p>
        <div class="cards">
          <?php foreach ($especies as $especie): ?>
            <article class="card">
              <div class="card-art"><?= e($especie['emoji'] ?? '🐢') ?></div>
              <div class="card-body">
                <span class="tag"><?= e($especie['etiqueta'] ?? '') ?></span>
                <h3><?= e($especie['nombre'] ?? '') ?></h3>
                <p><?= e($especie['descripcion'] ?? '') ?></p>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="facts" id="curiosidades">
      <div class="container">
        <h2 class="section-title">Curiosidades</h2>
        <p class="section-sub">Datos curiosos que quizá no conocías sobre las tortugas.</p>
        <ul class="fact-list">
          <?php foreach ($curiosidades as $curiosidad): ?>
            <li>
              <span><?= e($curiosidad['icono'] ?? '') ?></span>
              <div>
                <b><?= e($curiosidad['titulo'] ?? '') ?></b>
                <?= e($curiosidad['texto'] ?? '') ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </section>

    <section id="proteccion">
      <div class="container">
        <h2 class="section-title">Cómo puedes ayudar</h2>
        <p class="section-sub">Pequeñas acciones cotidianas que marcan la diferencia para su supervivencia.</p>
        <div class="protect-grid">
          <?php foreach ($protecciones as $proteccion): ?>
            <div class="protect-item">
              <span class="icon"><?= e($proteccion['icono'] ?? '') ?></span>
              <h3><?= e($proteccion['titulo'] ?? '') ?></h3>
              <p><?= e($proteccion['texto'] ?? '') ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section id="contacto">
      <div class="container">
        <div class="cta">
          <h2>Únete a Tortugas Vivas</h2>
          <p>Recibe novedades, invitaciones a limpiezas de playa y consejos para convivir respetuosamente con el mar.</p>
          <form class="subscribe-form" action="/suscribir.php" method="post">
            <?= csrfField() ?>
            <input type="email" name="email" required maxlength="254" placeholder="Tu correo electrónico" />
            <button class="btn btn-primary" type="submit">Suscribirme</button>
          </form>
          <?php if ($flashMessage !== null): ?>
            <p class="flash <?= str_starts_with($flashMessage, '✕') ? 'flash--error' : '' ?>" role="status">
              <?= e($flashMessage) ?>
            </p>
          <?php endif; ?>
        </div>
      </div>
    </section>

  </main>

  <footer>
    <span class="brand">🐢 Tortugas Vivas</span>
    <div>Hecho con cariño para quienes cuidan el mar · © 2026</div>
  </footer>

</body>
</html>
