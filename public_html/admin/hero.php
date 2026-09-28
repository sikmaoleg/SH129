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
            flash('error', 'Файл слишком большой: можно до 50 МБ.');
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
    } elseif ($action === 'reorder') {
        // Новый порядок после перетаскивания: список id сверху вниз
        $order = array_map('intval', (array)($_POST['order'] ?? []));
        foreach (array_values($order) as $i => $id) {
            q('UPDATE hero_slides SET sort = ? WHERE id = ?', [($i + 1) * 10, $id]);
        }
        logAction('hero_slide_reorder', 'hero_slide');
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch') {
            header('Content-Type: application/json; charset=utf-8');
            exit('{"ok":true}');
        }
        flash('success', 'Порядок фото сохранён.');
    } elseif ($action === 'caption') {
        $id = (int)($_POST['id'] ?? 0);
        $caption = trim((string)($_POST['caption'] ?? ''));
        q('UPDATE hero_slides SET caption = ? WHERE id = ?', [$caption !== '' ? mb_substr($caption, 0, 190) : null, $id]);
        flash('success', 'Подпись сохранена.');
    } elseif ($action === 'move') {
        $id  = (int)($_POST['id'] ?? 0);
        $dir = $_POST['dir'] ?? '';
        $slides = fetchAll('SELECT id, sort FROM hero_slides ORDER BY sort ASC, id ASC');
        $ids = array_column($slides, 'id');
        $idx = array_search($id, $ids, true);
        if ($idx !== false) {
            $swapIdx = $dir === 'up' ? $idx - 1 : $idx + 1;
            if (isset($slides[$swapIdx])) {
                [$ids[$idx], $ids[$swapIdx]] = [$ids[$swapIdx], $ids[$idx]];
                foreach ($ids as $i => $sid) {
                    q('UPDATE hero_slides SET sort = ? WHERE id = ?', [($i + 1) * 10, $sid]);
                }
            }
        }
    }
    redirect('admin/hero.php');
}

$slides = fetchAll('SELECT * FROM hero_slides ORDER BY sort ASC, id ASC');

$panelLead = 'Снимки сменяют друг друга на первом экране сайта, а четвёртый показывается в блоке «Часть большой команды». Перетащи карточку или используй стрелки, чтобы поменять порядок.';

require __DIR__ . '/../includes/panel_header.php';
?>

<form method="post" enctype="multipart/form-data" class="upload-row">
  <?= csrfField() ?>
  <input type="hidden" name="action" value="create">
  <label class="drop" id="drop">
    <?= icon('upload') ?><b>Выбери фото или перетащи его сюда</b><span class="muted">JPG, PNG или WEBP до 50 МБ. Лучше горизонтальные снимки.</span>
    <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required id="heroFile">
    <span class="file-name" id="heroFileName"></span>
  </label>
  <div class="upload-side">
    <div class="field"><label for="caption">Подпись</label><input type="text" id="caption" name="caption" maxlength="190" placeholder="Например: субботник в парке"><span class="hint">Видна как описание фото для незрячих и в галерее.</span></div>
    <button type="submit" class="btn btn-accent btn-block"><?= icon('plus') ?>Добавить фото</button>
  </div>
</form>

<?php if ($slides): ?>
  <div class="slides" id="slides" data-reorder-url="<?= url('admin/hero.php') ?>" data-csrf="<?= e(csrfToken()) ?>">
    <?php foreach ($slides as $i => $sl): ?>
      <article class="slide" draggable="true" data-id="<?= (int)$sl['id'] ?>">
        <div class="slide-img"><img src="<?= url($sl['image']) ?>" alt="<?= e($sl['caption'] ?? '') ?>" loading="lazy"><span class="slide-n"><?= $i + 1 ?></span></div>
        <div class="slide-b">
          <span class="drag" title="Перетащить" aria-hidden="true"><?= icon('grip') ?></span>
          <form method="post" class="slide-cap">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="caption">
            <input type="hidden" name="id" value="<?= (int)$sl['id'] ?>">
            <label class="sr" for="cap<?= (int)$sl['id'] ?>">Подпись</label>
            <input class="input" id="cap<?= (int)$sl['id'] ?>" name="caption" value="<?= e($sl['caption'] ?? '') ?>" placeholder="Без подписи" onchange="this.form.submit()">
          </form>
        </div>
        <div class="slide-act">
          <form method="post" class="inline-form"><?= csrfField() ?><input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?= (int)$sl['id'] ?>"><input type="hidden" name="dir" value="up"><button class="menu-dots" <?= $i === 0 ? 'disabled' : '' ?> aria-label="Выше"><?= icon('arrow-up') ?></button></form>
          <form method="post" class="inline-form"><?= csrfField() ?><input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?= (int)$sl['id'] ?>"><input type="hidden" name="dir" value="down"><button class="menu-dots" <?= $i === count($slides) - 1 ? 'disabled' : '' ?> aria-label="Ниже"><?= icon('arrow-down') ?></button></form>
          <span class="spacer"></span>
          <form method="post" class="inline-form"><?= csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$sl['id'] ?>"><button class="menu-dots" data-confirm="Убрать это фото с главной?" aria-label="Удалить фото"><?= icon('trash') ?></button></form>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <section class="card"><div class="empty"><?= icon('image') ?><b>Фото пока нет</b>Пока здесь пусто, на главной показывается стандартный снимок команды.</div></section>
<?php endif; ?>

<?php require __DIR__ . '/../includes/panel_footer.php'; ?>
