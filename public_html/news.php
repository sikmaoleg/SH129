<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Новости: Молодая Гвардия Щёлково';
$activeNav = 'news';

$perPage = 10;
$page    = max(1, (int)($_GET['page'] ?? 1));
$total   = (int)fetchValue("SELECT COUNT(*) FROM news WHERE status='published' AND published_at <= NOW()");
$pages   = max(1, (int)ceil($total / $perPage));
$page    = min($page, $pages);
$offset  = ($page - 1) * $perPage;

$items = fetchAll(
    "SELECT id, title, excerpt, body, cover, published_at FROM news
     WHERE status='published' AND published_at <= NOW()
     ORDER BY published_at DESC LIMIT $perPage OFFSET $offset"
);
$featured = $page === 1 && $items ? array_shift($items) : null;

require __DIR__ . '/includes/header.php';
?>
<header class="phead">
  <div class="wrap">
    <nav class="crumbs" aria-label="Навигация"><a href="<?= url('index.php') ?>">Главная</a><span>/</span><span>Новости</span></nav>
    <h1 class="phead-title display">Новости</h1>
    <p class="phead-lead">Чем живёт отделение. Публикации приходят сюда из&nbsp;нашего Telegram-канала.</p>
  </div>
</header>

<section class="sec">
  <div class="wrap">
    <?php if ($featured): $ft = cleanNewsTitle($featured['title'], (string)$featured['body']); ?>
      <a class="nfeat" href="<?= url('news-item.php?id=' . (int)$featured['id']) ?>" data-reveal>
        <div class="n-img"><img src="<?= e($featured['cover'] ? url('uploads/news/' . $featured['cover']) : url('assets/img/march.webp')) ?>" alt=""></div>
        <div>
          <time datetime="<?= e(substr($featured['published_at'], 0, 10)) ?>"><?= e(ruDate($featured['published_at'])) ?></time>
          <h2 class="display"><?= e($ft) ?></h2>
          <p><?= e(newsExcerpt($featured, 220)) ?></p>
          <span class="link-arrow" style="margin-top:22px">Читать<?= icon('arrow-up-right') ?></span>
        </div>
      </a>
    <?php endif; ?>

    <?php if ($items): ?>
      <div class="nlist">
        <?php foreach ($items as $n): ?>
          <article class="nrow">
            <a href="<?= url('news-item.php?id=' . (int)$n['id']) ?>">
              <time datetime="<?= e(substr($n['published_at'], 0, 10)) ?>"><?= e(ruDate($n['published_at'])) ?></time>
              <div><h3><?= e(cleanNewsTitle($n['title'], (string)$n['body'])) ?></h3><p><?= e(newsExcerpt($n, 170)) ?></p></div>
              <div class="thumb"><img src="<?= e($n['cover'] ? url('uploads/news/' . $n['cover']) : url('assets/img/march.webp')) ?>" alt="" loading="lazy"></div>
            </a>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (!$featured && !$items): ?>
      <div class="empty"><b>Новостей пока нет</b>Публикации появятся здесь, как только выйдут в&nbsp;Telegram-канале отделения.</div>
    <?php endif; ?>

    <?php if ($pages > 1): ?>
      <nav class="pagination" aria-label="Страницы новостей">
        <?php for ($p = 1; $p <= $pages; $p++): ?>
          <?php if ($p === $page): ?>
            <span class="is-current" aria-current="page"><?= $p ?></span>
          <?php else: ?>
            <a href="<?= url('news.php?page=' . $p) ?>"><?= $p ?></a>
          <?php endif; ?>
        <?php endfor; ?>
      </nav>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
