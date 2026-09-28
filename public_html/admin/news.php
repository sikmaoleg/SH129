<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$me = requireAdmin();

$panelSection = 'admin';
$panelTitle   = 'Новости';
$activeItem   = 'news';

$editId = (int)($_GET['edit'] ?? 0);
$edit   = $editId ? fetchOne('SELECT * FROM news WHERE id = ?', [$editId]) : null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $images = fetchAll('SELECT image FROM news_images WHERE news_id = ?', [$id]);
        foreach ($images as $img) {
            $path = __DIR__ . '/../' . $img['image'];
            if (is_file($path)) {
                @unlink($path);
            }
        }
        q('DELETE FROM news WHERE id = ?', [$id]); // news_images удаляются каскадом
        logAction('news_delete', 'news', $id);
        flash('info', 'Новость удалена.');
        redirect('admin/news.php');
    }

    if ($action === 'save') {
        $id      = (int)($_POST['id'] ?? 0);
        $title   = trim((string)($_POST['title'] ?? ''));
        $excerpt = trim((string)($_POST['excerpt'] ?? ''));
        $body    = trim((string)($_POST['body'] ?? ''));
        $status  = ($_POST['status'] ?? 'published') === 'draft' ? 'draft' : 'published';
        $date    = trim((string)($_POST['published_at'] ?? '')) ?: date('Y-m-d');

        if ($title === '') $errors[] = 'Укажите заголовок.';
        if ($body === '')  $errors[] = 'Напишите текст новости.';

        if (!$errors) {
            $publishedAt = $date . ' ' . date('H:i:s');
            if ($id) {
                q('UPDATE news SET title=?, excerpt=?, body=?, status=?, published_at=? WHERE id=?',
                  [$title, $excerpt, $body, $status, $publishedAt, $id]);
                logAction('news_update', 'news', $id, $title);
                flash('success', 'Новость обновлена.');
            } else {
                q('INSERT INTO news (title, excerpt, body, status, published_at, author_id) VALUES (?,?,?,?,?,?)',
                  [$title, $excerpt, $body, $status, $publishedAt, (int)$me['id']]);
                logAction('news_create', 'news', (int)db()->lastInsertId(), $title);
                flash('success', 'Новость опубликована.');
            }
            redirect('admin/news.php');
        }
    }
}

$items = fetchAll('SELECT n.*, u.last_name, u.first_name FROM news n LEFT JOIN users u ON u.id = n.author_id ORDER BY n.published_at DESC LIMIT 100');

$drawerOpen = $edit || $errors || isset($_GET['new']);
$cntPub = count(array_filter($items, fn($n) => $n['status'] === 'published'));
$panelLead = 'Посты из Telegram-канала попадают сюда автоматически. Проверь заголовок и при необходимости поправь текст.';
$panelActions = '<button class="btn btn-accent" type="button" data-drawer-open="newsDrawer">' . icon('plus') . 'Новая новость</button>';
$formTitle = $edit ? cleanNewsTitle((string)$edit['title'], (string)$edit['body']) : ($_POST['title'] ?? '');

require __DIR__ . '/../includes/panel_header.php';
?>

<section class="card">
  <div class="toolbar">
    <label class="input-ico"><?= icon('search') ?><span class="sr">Поиск</span><input class="input" type="search" placeholder="Поиск по заголовку" data-filter-input="newsBody" data-filter-empty="newsEmpty" autocomplete="off"></label>
    <div class="seg" role="group" aria-label="Статус">
      <button type="button" class="on" data-role-filter="all">Все <em><?= count($items) ?></em></button>
      <button type="button" data-role-filter="published">Опубликованы <em><?= $cntPub ?></em></button>
      <button type="button" data-role-filter="draft">Черновики <em><?= count($items) - $cntPub ?></em></button>
    </div>
  </div>
  <?php if ($items): ?>
    <div class="table-wrap">
      <table class="data cards">
        <thead><tr><th></th><th>Заголовок</th><th>Дата</th><th>Источник</th><th>Статус</th><th></th></tr></thead>
        <tbody id="newsBody" data-role="all">
          <?php foreach ($items as $n):
            $shown = cleanNewsTitle((string)$n['title'], (string)$n['body']);
            $emojiOnly = !preg_match('/\p{L}/u', (string)$n['title']); ?>
            <tr class="click" data-href="<?= url('admin/news.php?edit=' . (int)$n['id']) ?>" data-role="<?= e($n['status']) ?>" data-filter-text="<?= e(mb_strtolower($shown . ' ' . $n['title'])) ?>">
              <td class="w1">
                <?php if ($n['cover']): ?><img class="nthumb" src="<?= url('uploads/news/' . $n['cover']) ?>" alt="" loading="lazy">
                <?php else: ?><span class="nthumb" style="display:grid;place-items:center;color:var(--ink-3)"><?= icon('image') ?></span><?php endif; ?>
              </td>
              <td data-label="">
                <b style="font-weight:600"><?= e($shown) ?></b>
                <?php if ($emojiOnly): ?><span class="warn-line"><?= icon('alert') ?>В посте заголовок из одних эмодзи, на сайте показываем первую строку текста</span><?php endif; ?>
              </td>
              <td data-label="Дата" class="num" style="white-space:nowrap;font-weight:400"><?= e(ruDate($n['published_at'])) ?></td>
              <td data-label="Источник"><?= $n['tg_message_id'] ? '<span class="tag">' . icon('telegram') . 'Telegram</span>' : '<span class="tag">' . icon('pencil') . 'Вручную</span>' ?></td>
              <td data-label="Статус"><?= $n['status'] === 'published' ? '<span class="chip ok">' . icon('check-circle') . 'Опубликовано</span>' : '<span class="chip">' . icon('pencil') . 'Черновик</span>' ?></td>
              <td data-label="">
                <div class="actions">
                  <?php if ($n['status'] === 'published'): ?><a class="btn btn-line btn-sm" href="<?= url('news-item.php?id=' . (int)$n['id']) ?>" target="_blank" rel="noopener" aria-label="Открыть на сайте"><?= icon('external') ?></a><?php endif; ?>
                  <a class="btn btn-line btn-sm" href="<?= url('admin/news.php?edit=' . (int)$n['id']) ?>" aria-label="Править"><?= icon('pencil') ?></a>
                  <form method="post" class="inline-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                    <button class="btn btn-ghost btn-sm" data-confirm="Удалить новость вместе с фото?" aria-label="Удалить"><?= icon('trash') ?></button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="empty" id="newsEmpty" hidden><?= icon('newspaper') ?><b>Ничего не нашли</b>Измени запрос или фильтр.</div>
  <?php else: ?>
    <div class="empty"><?= icon('newspaper') ?><b>Новостей пока нет</b>Они появятся после синхронизации с Telegram или когда ты напишешь первую.</div>
  <?php endif; ?>
</section>

<aside class="drawer" id="newsDrawer" role="dialog" aria-modal="true" aria-labelledby="newsDrawerTitle" <?= $drawerOpen ? '' : 'hidden' ?> <?= $edit || isset($_GET['new']) ? 'data-close-url="' . e(url('admin/news.php')) . '"' : '' ?>>
  <form class="drawer-panel" method="post">
    <header class="drawer-head">
      <div><p class="drawer-kicker"><?= $edit ? 'Редактирование новости' : 'Новая новость' ?></p><h2 class="drawer-title" id="newsDrawerTitle"><?= $edit ? e(cleanNewsTitle((string)$edit['title'], (string)$edit['body'])) : 'Новость' ?></h2></div>
      <button class="icon-btn" type="button" data-close-drawer aria-label="Закрыть"><?= icon('close') ?></button>
    </header>
    <div class="drawer-body">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
      <?php if ($edit && !preg_match('/\p{L}/u', $edit['title'])): ?>
        <div class="alert warn"><?= icon('alert') ?><div><b>Заголовок подставлен из текста</b><p>В посте первая строка была из одних эмодзи. Проверь заголовок и сохрани.</p></div></div>
      <?php endif; ?>
      <div class="field"><label for="title">Заголовок</label>
        <input type="text" id="title" name="title" maxlength="190" value="<?= e($formTitle) ?>" required></div>
      <div class="field"><label for="excerpt">Краткое описание</label>
        <textarea id="excerpt" name="excerpt" style="min-height:80px" maxlength="400" placeholder="Необязательно: если оставить пустым, анонс соберётся из текста"><?= e($edit['excerpt'] ?? ($_POST['excerpt'] ?? '')) ?></textarea></div>
      <div class="field"><label for="body">Текст</label>
        <textarea id="body" name="body" style="min-height:220px" required><?= e($edit['body'] ?? ($_POST['body'] ?? '')) ?></textarea>
        <span class="hint">Абзацы разделяй пустой строкой. Хэштеги в конце покажутся отдельными метками.</span></div>
      <div class="frow">
        <div class="field"><label for="published_at">Дата публикации</label>
          <input type="date" id="published_at" name="published_at" value="<?= e($edit ? date('Y-m-d', strtotime($edit['published_at'])) : ($_POST['published_at'] ?? date('Y-m-d'))) ?>"></div>
        <div class="field"><label for="status">Статус</label>
          <select id="status" name="status">
            <option value="published" <?= ($edit['status'] ?? ($_POST['status'] ?? 'published')) === 'published' ? 'selected' : '' ?>>Опубликовано</option>
            <option value="draft" <?= ($edit['status'] ?? ($_POST['status'] ?? '')) === 'draft' ? 'selected' : '' ?>>Черновик</option>
          </select></div>
      </div>
      <?php if ($edit && $edit['cover']): ?>
        <div><p class="lbl" style="margin-bottom:8px">Так карточка выглядит на сайте</p>
          <div class="preview-card" style="max-width:320px"><img src="<?= url('uploads/news/' . $edit['cover']) ?>" alt=""><div><time><?= e(ruDate($edit['published_at'])) ?></time><h4 id="pvT"><?= e($formTitle) ?></h4></div></div></div>
      <?php endif; ?>
    </div>
    <footer class="drawer-foot">
      <button class="btn btn-ghost" type="button" data-close-drawer>Отмена</button>
      <button class="btn btn-accent" type="submit"><?= $edit ? 'Сохранить' : 'Опубликовать' ?></button>
    </footer>
  </form>
</aside>

<?php require __DIR__ . '/../includes/panel_footer.php'; ?>
