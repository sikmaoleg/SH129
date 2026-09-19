<?php
require_once __DIR__ . '/includes/bootstrap.php';
$id = (int)($_GET['id'] ?? 0);
$n  = fetchOne("SELECT * FROM news WHERE id = ? AND status='published' AND published_at <= NOW()", [$id]);
if (!$n) {
    http_response_code(404);
    $pageTitle = 'Новость не найдена';
    $activeNav = 'news';
    require __DIR__ . '/includes/header.php';
    echo '<section class="section"><div class="container"><div class="empty"><b>Новость не найдена</b>Возможно, публикацию удалили или ссылка устарела.</div></div></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}
$pageTitle = $n['title'] . ' — Молодая Гвардия Щёлково';
$activeNav = 'news';
$images = fetchAll('SELECT image FROM news_images WHERE news_id = ? ORDER BY sort ASC, id ASC', [$n['id']]);
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div class="container">
    <div class="breadcrumbs"><a href="<?= url('index.php') ?>">Главная</a> / <a href="<?= url('news.php') ?>">Новости</a></div>
    <h1><?= e($n['title']) ?></h1>
  </div>
</div>

<section class="section">
  <div class="container">
    <div class="article">
      <p class="news-date"><?= e(ruDate($n['published_at'], true)) ?></p>
      <?php if ($images): ?>
        <div class="article-gallery <?= count($images) === 1 ? 'article-gallery-single' : '' ?>" data-lightbox-group="news<?= (int)$n['id'] ?>">
          <?php foreach ($images as $img): ?>
            <a href="<?= url($img['image']) ?>" class="lightbox-trigger"><img src="<?= url($img['image']) ?>" alt="" loading="lazy"></a>
          <?php endforeach; ?>
        </div>
      <?php elseif ($n['cover']): ?>
        <img src="<?= url('uploads/news/'.$n['cover']) ?>" alt="">
      <?php endif; ?>
      <?php if ($n['excerpt']): ?><p><strong><?= e($n['excerpt']) ?></strong></p><?php endif; ?>
      <?php foreach (preg_split("/\R{2,}/", (string)$n['body']) as $para): ?>
        <?php if (trim($para) !== ''): ?><p><?= nl2br(e(trim($para))) ?></p><?php endif; ?>
      <?php endforeach; ?>
      <?= shareButtons(url('news-item.php?id=' . (int)$n['id']), $n['title']) ?>
      <p style="margin-top:22px;"><a href="<?= url('news.php') ?>" class="btn btn-outline">← Все новости</a></p>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
