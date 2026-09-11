<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();

$panelSection = 'admin';
$panelTitle   = 'Доска почёта';
$activeItem   = 'honor';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $userId = (int)($_POST['user_id'] ?? 0);
    $note   = trim((string)($_POST['note'] ?? ''));

    if ($userId > 0) {
        $target = fetchOne("SELECT id FROM users WHERE id = ? AND status = 'approved'", [$userId]);
        if (!$target) {
            flash('error', 'Волонтёр не найден.');
            redirect('admin/honor.php');
        }
    }

    setSetting('honor_user_id', (string)$userId);
    setSetting('honor_note', $note);
    logAction('honor_update', 'settings', null, (string)$userId);
    flash('success', $userId > 0 ? 'Волонтёр месяца обновлён.' : 'Карточка волонтёра месяца скрыта с сайта.');
    redirect('admin/honor.php');
}

$volunteers = fetchAll("SELECT id, last_name, first_name, position FROM users WHERE status = 'approved' ORDER BY last_name ASC");
$currentId  = (int)setting('honor_user_id');
$currentNote = setting('honor_note');

require __DIR__ . '/../includes/panel_header.php';
?>

<div class="card">
  <div class="card-head">
    <div><h2>Волонтёр месяца</h2><p>Показывается карточкой на главной странице сайта. Нет автоматики по часам — выбирайте вручную.</p></div>
  </div>
  <div class="card-body">
    <form method="post">
      <?= csrfField() ?>
      <div class="field">
        <label for="user_id">Волонтёр</label>
        <select id="user_id" name="user_id">
          <option value="0">— не показывать на сайте —</option>
          <?php foreach ($volunteers as $v): ?>
            <option value="<?= (int)$v['id'] ?>" <?= $currentId === (int)$v['id'] ? 'selected' : '' ?>>
              <?= e($v['last_name'] . ' ' . $v['first_name']) ?> · <?= e(positionLabel($v['position'])) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="note">За что отмечен (коротко)</label>
        <input type="text" id="note" name="note" maxlength="160" value="<?= e($currentNote) ?>"
               placeholder="Например: провёл три мероприятия за месяц и помог с координацией волонтёров">
      </div>
      <button type="submit" class="btn btn-primary">Сохранить</button>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../includes/panel_footer.php'; ?>
