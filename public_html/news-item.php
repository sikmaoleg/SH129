<?php
require_once __DIR__ . '/includes/bootstrap.php';

$id = (int)($_GET['id'] ?? 0);
$n  = fetchOne("SELECT * FROM news WHERE id = ? AND status='published' AND published_at <= NOW()", [$id]);

if (!$n) {
    http_response_code(404);
    $pageTitle = 'Новость не найдена';
    $activeNav = 'news';
    require __DIR__ . '/includes/header.php';
    echo '<section class="phead"><div class="wrap"><h1 class="phead-title display">Не найдено</h1>'
       . '<p class="phead-lead">Возможно, публикацию удалили или ссылка устарела.</p>'
       . '<div class="row-ctas"><a class="btn btn-accent btn-lg" href="' . url('news.php') . '">Все новости' . icon('arrow-right') . '</a></div></div></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$title = cleanNewsTitle($n['title'], (string)$n['body']);
$pageTitle = $title . ': Молодая Гвардия Щёлково';
$metaDescription = newsExcerpt($n, 180);
$activeNav = 'news';
$images = fetchAll('SELECT image FROM news_images WHERE news_id = ? ORDER BY sort ASC, id ASC', [$n['id']]);
if (!$images && $n['cover']) {
    $images = [['image' => 'uploads/news/' . $n['cover']]];
}
$parts = newsBodyParts((string)$n['body'], (string)$n['title']);
if (!$parts['paras'] && $n['excerpt']) {
    $parts['paras'] = [(string)$n['excerpt']];
}

// Соседние публикации для навигации внизу
$older = fetchOne("SELECT id, title, body FROM news WHERE status='published' AND published_at <= NOW()
                   AND (published_at < ? OR (published_at = ? AND id < ?)) ORDER BY published_at DESC, id DESC LIMIT 1",
                  [$n['published_at'], $n['published_at'], $n['id']]);
$newer = fetchOne("SELECT id, title, body FROM news WHERE status='published' AND published_at <= NOW()
                   AND (published_at > ? OR (published_at = ? AND id > ?)) ORDER BY published_at ASC, id ASC LIMIT 1",
                  [$n['published_at'], $n['published_at'], $n['id']]);

require __DIR__ . '/includes/header.php';
?>
<article class="art">
  <div class="wrap">
    <header class="art-head">
      <nav class="crumbs" aria-label="Навигация"><a href="<?= url('index.php') ?>">Главная</a><span>/</span><a href="<?= url('news.php') ?>">Новости</a></nav>
      <h1 class="art-title display"><?= e($title) ?></h1>
      <div class="art-meta">
        <span><?= icon('calendar') ?><?= e(ruDate($n['published_at'], true)) ?></span>
        <?php if (count($images) > 1): ?><span><?= icon('images') ?><?= count($images) ?> фото</span><?php endif; ?>
      </div>
    </header>

    <?php if ($images): ?>
      <?php $count = count($images) <= 4 ? (string)count($images) : 'many'; ?>
      <div class="mosaic" data-count="<?= $count ?>" data-lightbox-group="news<?= (int)$n['id'] ?>">
        <?php foreach ($images as $j => $img): ?>
          <a href="<?= url($img['image']) ?>" class="lightbox-trigger" data-caption="<?= e($title) ?>">
            <img src="<?= url($img['image']) ?>" alt="Фото <?= $j + 1 ?> к новости «<?= e($title) ?>»" <?= $j ? 'loading="lazy"' : '' ?>>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="art-body">
      <?php foreach ($parts['paras'] as $para): ?>
        <p><?= nl2br(e($para)) ?></p>
      <?php endforeach; ?>
      <?php if ($parts['tags']): ?>
        <div class="art-tags"><?php foreach ($parts['tags'] as $tag): ?><span>#<?= e($tag) ?></span><?php endforeach; ?></div>
      <?php endif; ?>
      <?= shareButtons(url('news-item.php?id=' . (int)$n['id']), $title) ?>
    </div>

    <?php if ($older || $newer): ?>
      <nav class="art-nav" aria-label="Другие новости">
        <?php if ($older): ?>
          <a href="<?= url('news-item.php?id=' . (int)$older['id']) ?>"><small><?= icon('arrow-left') ?>Предыдущая</small><b><?= e(cleanNewsTitle($older['title'], (string)$older['body'])) ?></b></a>
        <?php else: ?><span></span><?php endif; ?>
        <?php if ($newer): ?>
          <a class="next" href="<?= url('news-item.php?id=' . (int)$newer['id']) ?>"><small>Следующая<?= icon('arrow-right') ?></small><b><?= e(cleanNewsTitle($newer['title'], (string)$newer['body'])) ?></b></a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>
  </div>
</article>
<?php require __DIR__ . '/includes/footer.php'; ?>
