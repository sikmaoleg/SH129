<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Фотогалерея — Молодая Гвардия Щёлково';
$activeNav = 'gallery';
$photos = [
    ['march.webp',    'Городское шествие'],
    ['cake.webp',     'День рождения отделения'],
    ['rink.webp',     'Спортивное мероприятие'],
    ['rain.webp',     'Работаем в любую погоду'],
    ['creative.webp', 'Съёмка команды'],
    ['team.webp',     'Команда отделения'],
];
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div class="container">
    <div class="breadcrumbs"><a href="<?= url('index.php') ?>">Главная</a> / Фотогалерея</div>
    <h1>Фотогалерея</h1>
  </div>
</div>
<section class="section">
  <div class="container">
    <div class="gal-grid">
      <?php foreach ($photos as $i => [$file, $caption]): ?>
        <figure class="gal-item <?= $i === 0 ? 'gal-tall' : ($i % 4 === 1 ? 'gal-wide' : '') ?>">
          <img src="<?= url('assets/img/'.$file) ?>" alt="<?= e($caption) ?>" loading="lazy">
          <figcaption><?= e($caption) ?></figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
