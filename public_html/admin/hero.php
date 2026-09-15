<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$me = requireAdmin();

$panelSection = 'admin';
$panelTitle   = 'Фото на главной';
$activeItem   = 'hero';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $caption = trim((string)($_POST['caption'] ?? ''));
        $file = $_FILES['image'] ?? null;

        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            flash('error', 'Выберите файл фотографии.');
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            flash('error', $file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE
                ? 'Файл слишком большой.' : 'Не удалось загрузить файл. Попробуйте ещё раз.');
        } elseif ($file['size'] > 50 * 1024 * 1024) {
            flash('error', 'Файл слишком большой — до 50 МБ.');
        } else {
            $filename = resizeAndSaveImage($file['tmp_name'], 'hero', 1800);
            if (!$filename) {
                flash('error', 'Поддерживаются только изображения JPG, PNG или WEBP.');
            } else {
                $maxSort = (int)fetchValue('SELECT COALESCE(MAX(sort),0) FROM hero_slides');
                q('INSERT INTO hero_slides (image, caption, sort) VALUES (?,?,?)',
                  ['uploads/hero/' . $filename, $caption !== '' ? $caption : null, $maxSort + 10]);
                logAction('hero_slide_create', 'hero_slide', (int)db()->lastInsertId(), $caption);
                flash('success', 'Фото добавлено в слайдер.');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $slide = fetchOne('SELECT * FROM hero_slides WHERE id = ?', [$id]);
        if ($slide) {
            // Дефолтные фото лежат в assets/img — их с диска не удаляем, только свои загрузки.
            if (str_starts_with($slide['image'], 'uploads/hero/')) {
                $path = __DIR__ . '/../' . $slide['image'];
                if (is_file($path)) {
                    @unlink($path);
                }
            }
            q('DELETE FROM hero_slides WHERE id = ?', [$id]);
            logAction('hero_slide_delete', 'hero_slide', $id);
            flash('info', 'Фото удалено.');
        }
    } elseif ($action === 'move') {
        $id  = (int)($_POST['id'] ?? 0);
        $dir = $_POST['dir'] ?? '';
        $slides = fetchAll('SELECT id, sort FROM hero_slides ORDER BY sort ASC, id ASC');
        $ids = array_column($slides, 'id');
        $idx = array_search($id, $ids, true);
        if ($idx !== false) {
            $swapIdx = $dir === 'up' ? $idx - 1 : $idx + 1;
            if (isset($slides[$swapIdx])) {
                q('UPDATE hero_slides SET sort = ? WHERE id = ?', [$slides[$swapIdx]['sort'], $slides[$idx]['id']]);
                q('UPDATE hero_slides SET sort = ? WHERE id = ?', [$slides[$idx]['sort'], $slides[$swapIdx]['id']]);
            }
        }
    }
    redirect('admin/hero.php');
}

$slides = fetchAll('SELECT * FROM hero_slides ORDER BY sort ASC, id ASC');

require __DIR__ . '/../includes/panel_header.php';
?>

<div class="card">
  <div class="card-head"><div><h2>Добавить фото</h2><p>Показывается в слайдере на главной странице сайта.</p></div></div>
  <div class="card-body">
    <form method="post" enctype="multipart/form-data" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="create">
      <div class="field" style="flex:1;min-width:220px;margin:0;">
        <label for="image">Файл</label>
        <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp" required>
      </div>
      <div class="field" style="flex:1;min-width:220px;margin:0;">
        <label for="caption">Подпись (необязательно)</label>
        <input type="text" id="caption" name="caption" maxlength="190" placeholder="Например: Городское шествие">
      </div>
      <button type="submit" class="btn btn-primary">Добавить</button>
    </form>
    <div class="hint" style="margin-top:10px;">JPG, PNG или WEBP, до 50 МБ. Пропорции не обрезаются — слишком большие фото только уменьшаются.</div>
  </div>
</div>

<div class="card">
  <div class="card-head"><div><h2>Слайды — <?= count($slides) ?></h2><p>Порядок показа сверху вниз, начиная с первого.</p></div></div>
  <?php if ($slides): ?>
    <div class="card-body card-body-flush table-wrap">
      <table class="data cards">
        <thead><tr><th style="width:120px;">Фото</th><th>Подпись</th><th style="width:140px;">Порядок</th><th style="width:100px;">Действия</th></tr></thead>
        <tbody>
          <?php foreach ($slides as $i => $s): ?>
            <tr>
              <td data-label="Фото"><img src="<?= url($s['image']) ?>" alt="" style="width:96px;height:64px;object-fit:cover;border-radius:8px;"></td>
              <td data-label="Подпись"><?= e($s['caption'] ?: '—') ?></td>
              <td data-label="Порядок">
                <div style="display:flex;gap:6px;">
                  <form method="post" style="margin:0;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="move">
                    <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                    <input type="hidden" name="dir" value="up">
                    <button type="submit" class="btn btn-outline btn-sm" <?= $i === 0 ? 'disabled' : '' ?>>↑</button>
                  </form>
                  <form method="post" style="margin:0;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="move">
                    <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                    <input type="hidden" name="dir" value="down">
                    <button type="submit" class="btn btn-outline btn-sm" <?= $i === count($slides) - 1 ? 'disabled' : '' ?>>↓</button>
                  </form>
                </div>
              </td>
              <td data-label="Действия">
                <form method="post" style="margin:0;">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                  <button type="submit" class="btn btn-outline btn-sm" data-confirm="Убрать это фото из слайдера?">Удалить</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="empty"><b>Слайдер пуст</b>Добавьте хотя бы одно фото — иначе на главной не будет фотоблока.</div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/panel_footer.php'; ?>
