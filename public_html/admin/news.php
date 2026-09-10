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
        q('DELETE FROM news WHERE id = ?', [$id]);
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

require __DIR__ . '/../includes/panel_header.php';
?>

<?php if ($errors): ?>
  <div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="card">
  <div class="card-head"><div><h2><?= $edit ? 'Редактирование новости' : 'Новая новость' ?></h2></div>
    <?php if ($edit): ?><a href="<?= url('admin/news.php') ?>" class="btn btn-outline btn-sm">Отменить правку</a><?php endif; ?>
  </div>
  <div class="card-body">
    <form method="post">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">

      <div class="field"><label for="title">Заголовок</label>
        <input type="text" id="title" name="title" value="<?= e($edit['title'] ?? '') ?>" required></div>

      <div class="field"><label for="excerpt">Краткое описание</label>
        <input type="text" id="excerpt" name="excerpt" maxlength="400" value="<?= e($edit['excerpt'] ?? '') ?>"
               placeholder="Одно-два предложения — показывается в списке новостей"></div>

      <div class="field"><label for="body">Текст</label>
        <textarea id="body" name="body" style="min-height:200px;" required><?= e($edit['body'] ?? '') ?></textarea>
        <div class="hint">Абзацы разделяйте пустой строкой.</div></div>

      <div class="field-row">
        <div class="field"><label for="published_at">Дата публикации</label>
          <input type="date" id="published_at" name="published_at"
                 value="<?= e($edit ? date('Y-m-d', strtotime($edit['published_at'])) : date('Y-m-d')) ?>"></div>
        <div class="field"><label for="status">Статус</label>
          <select id="status" name="status">
            <option value="published" <?= ($edit['status'] ?? 'published') === 'published' ? 'selected' : '' ?>>опубликовано</option>
            <option value="draft"     <?= ($edit['status'] ?? '') === 'draft' ? 'selected' : '' ?>>черновик</option>
          </select></div>
      </div>

      <button type="submit" class="btn btn-primary"><?= $edit ? 'Сохранить' : 'Опубликовать' ?></button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-head"><div><h2>Все публикации</h2></div></div>
  <?php if ($items): ?>
    <div class="card-body card-body-flush table-wrap">
      <table class="data">
        <thead><tr><th>Дата</th><th>Заголовок</th><th>Статус</th><th>Автор</th><th>Действия</th></tr></thead>
        <tbody>
          <?php foreach ($items as $n): ?>
            <tr>
              <td style="white-space:nowrap;"><?= e(ruDate($n['published_at'])) ?></td>
              <td><b><?= e($n['title']) ?></b></td>
              <td><span class="tag <?= $n['status'] === 'published' ? 'tag-approved' : 'tag-blocked' ?>"><?= $n['status'] === 'published' ? 'опубликовано' : 'черновик' ?></span></td>
              <td style="color:var(--muted);"><?= e(trim(($n['last_name'] ?? '') . ' ' . ($n['first_name'] ?? '')) ?: '—') ?></td>
              <td>
                <div class="actions">
                  <a href="<?= url('news-item.php?id='.(int)$n['id']) ?>" class="btn btn-outline btn-sm" target="_blank">Открыть</a>
                  <a href="<?= url('admin/news.php?edit='.(int)$n['id']) ?>" class="btn btn-outline btn-sm">Править</a>
                  <form method="post" style="margin:0;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                    <button class="btn btn-outline btn-sm" data-confirm="Удалить новость?">Удалить</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="empty"><b>Публикаций нет</b>Создайте первую новость в форме выше.</div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/panel_footer.php'; ?>
