<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Фотогалерея: Молодая Гвардия Щёлково';
$activeNav = 'gallery';

// Фото с главной (их подбирает администратор) и снимки из свежих новостей
$photos = [];
foreach (fetchAll('SELECT image, caption FROM hero_slides ORDER BY sort ASC, id ASC') as $s) {
    $photos[] = ['src' => $s['image'], 'caption' => (string)$s['caption'], 'link' => null];
}
$newsPhotos = fetchAll(
    "SELECT i.image, n.id, n.title, n.body FROM news_images i
     JOIN news n ON n.id = i.news_id
     WHERE n.status = 'published' AND n.published_at <= NOW()
     ORDER BY n.published_at DESC, i.sort ASC, i.id ASC LIMIT 60"
);
foreach ($newsPhotos as $p) {
    $photos[] = ['src' => $p['image'], 'caption' => cleanNewsTitle($p['title'], (string)$p['body']), 'link' => 'news-item.php?id=' . (int)$p['id']];
}
if (!$photos) {
    foreach ([['march.webp', 'Городское шествие'], ['cake.webp', 'День рождения отделения'], ['rink.webp', 'Спортивное мероприятие'],
              ['rain.webp', 'Работаем в любую погоду'], ['creative.webp', 'Съёмка команды'], ['team.webp', 'Команда отделения']] as [$f, $c]) {
        $photos[] = ['src' => 'assets/img/' . $f, 'caption' => $c, 'link' => null];
    }
}

require __DIR__ . '/includes/header.php';
?>
<header class="phead">
  <div class="wrap">
    <nav class="crumbs" aria-label="Навигация"><a href="<?= url('index.php') ?>">Главная</a><span>/</span><span>Фотогалерея</span></nav>
    <h1 class="phead-title display">Фото<em class="stamp">галерея</em></h1>
    <p class="phead-lead">Кадры из&nbsp;жизни отделения. Нажми на&nbsp;фото, чтобы рассмотреть поближе.</p>
  </div>
</header>

<section class="sec">
  <div class="wrap">
    <div class="masonry" data-lightbox-group="gallery">
      <?php foreach ($photos as $i => $p): ?>
        <figure>
          <a href="<?= url($p['src']) ?>" class="lightbox-trigger" data-caption="<?= e($p['caption']) ?>">
            <img src="<?= url($p['src']) ?>" alt="<?= e($p['caption']) ?>" <?= $i > 5 ? 'loading="lazy"' : '' ?>>
          </a>
          <?php if ($p['caption'] !== ''): ?>
            <figcaption><?= $p['link'] ? '<a href="' . url($p['link']) . '">' . e($p['caption']) . '</a>' : e($p['caption']) ?></figcaption>
          <?php endif; ?>
        </figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
