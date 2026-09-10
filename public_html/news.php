<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Новости — Молодая Гвардия Щёлково';
$activeNav = 'news';

$perPage = 9;
$page    = max(1, (int)($_GET['page'] ?? 1));
$total   = (int)fetchValue("SELECT COUNT(*) FROM news WHERE status='published' AND published_at <= NOW()");
$pages   = max(1, (int)ceil($total / $perPage));
$page    = min($page, $pages);
$offset  = ($page - 1) * $perPage;

$items = fetchAll(
    "SELECT id, title, excerpt, cover, published_at FROM news
     WHERE status='published' AND published_at <= NOW()
     ORDER BY published_at DESC LIMIT $perPage OFFSET $offset"
);
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div class="container">
    <div class="breadcrumbs"><a href="<?= url('index.php') ?>">Главная</a> / Новости</div>
    <h1>Новости отделения</h1>
  </div>
</div>

<section class="section">
  <div class="container">
    <?php if ($items): ?>
      <div class="news-grid">
        <?php foreach ($items as $n): ?>
          <article class="news-card">
            <div class="news-card-img">
              <img src="<?= e($n['cover'] ? url('uploads/news/'.$n['cover']) : url('assets/img/march.webp')) ?>" alt="" loading="lazy">
            </div>
            <div class="news-card-body">
              <span class="news-date"><?= e(ruDate($n['published_at'])) ?></span>
              <h3><a href="<?= url('news-item.php?id='.(int)$n['id']) ?>"><?= e($n['title']) ?></a></h3>
              <p><?= e(mb_strimwidth((string)$n['excerpt'], 0, 150, '…')) ?></p>
            </div>
          </article>
        <?php endforeach; ?>
      </div>

      <?php if ($pages > 1): ?>
        <div class="pagination">
          <?php for ($p = 1; $p <= $pages; $p++): ?>
            <?php if ($p === $page): ?>
              <span class="is-current"><?= $p ?></span>
            <?php else: ?>
              <a href="<?= url('news.php?page='.$p) ?>"><?= $p ?></a>
            <?php endif; ?>
          <?php endfor; ?>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <div class="empty"><b>Новостей пока нет</b>Публикации появятся здесь после добавления в панели управления.</div>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
