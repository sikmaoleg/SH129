<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();

$panelSection = 'admin';
$panelTitle   = 'Доска почёта';
$activeItem   = 'honor';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $userId = (int)($_POST['user_id'] ?? 0);
    $note   = mb_substr(trim((string)($_POST['note'] ?? '')), 0, 160);

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
    flash('success', $userId > 0 ? 'Волонтёр месяца обновлён, карточка уже на главной.' : 'Карточка волонтёра месяца скрыта с сайта.');
    redirect('admin/honor.php');
}

$volunteers = fetchAll(
    "SELECT u.id, u.last_name, u.first_name, u.position, u.avatar,
            COALESCE((SELECT SUM(t.points) FROM point_transactions t
                      WHERE t.user_id = u.id AND t.created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')), 0) AS month_pts
     FROM users u
     WHERE u.status = 'approved'
     ORDER BY month_pts DESC, u.last_name ASC"
);
$currentId   = (int)setting('honor_user_id');
$currentNote = setting('honor_note');
$months = ['январь', 'февраль', 'март', 'апрель', 'май', 'июнь', 'июль', 'август', 'сентябрь', 'октябрь', 'ноябрь', 'декабрь'];
$monthTitle = mb_convert_case($months[(int)date('n') - 1], MB_CASE_TITLE) . ' ' . date('Y');
$candidates = array_slice(array_filter($volunteers, fn($v) => (int)$v['month_pts'] > 0), 0, 3);
$current = null;
foreach ($volunteers as $v) { if ((int)$v['id'] === $currentId) { $current = $v; } }

$panelLead = 'Волонтёр месяца показывается карточкой на главной странице сайта. Выбирай вручную: автоматики по баллам нет.';
$panelActions = '<a class="btn btn-line" href="' . url('index.php') . '" target="_blank" rel="noopener">' . icon('external') . 'Открыть главную</a>';

require __DIR__ . '/../includes/panel_header.php';
?>

<div class="grid g2">
  <section class="card">
    <div class="card-h">
      <div><h2>Волонтёр месяца</h2><p><?= e($monthTitle) ?></p></div>
      <?= $current ? '<span class="chip ok">' . icon('eye') . 'На сайте</span>' : '<span class="chip">' . icon('eye-off') . 'Скрыта</span>' ?>
    </div>
    <form method="post" class="card-b stack">
      <?= csrfField() ?>
      <div class="field">
        <label for="honor_user">Волонтёр</label>
        <select class="select" id="honor_user" name="user_id">
          <option value="0">Не показывать на сайте</option>
          <?php foreach ($volunteers as $v): $name = $v['first_name'] . ' ' . $v['last_name']; ?>
            <option value="<?= (int)$v['id'] ?>" <?= $currentId === (int)$v['id'] ? 'selected' : '' ?>
                    data-name="<?= e($name) ?>"
                    data-ini="<?= e(mb_substr($v['first_name'], 0, 1) . mb_substr($v['last_name'], 0, 1)) ?>"
                    data-ava="<?= $v['avatar'] ? e(url('uploads/avatars/' . $v['avatar'])) : '' ?>">
              <?= e($name) ?> · <?= (int)$v['month_pts'] > 0 ? '+' . (int)$v['month_pts'] . ' за месяц' : e(positionLabel($v['position'])) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <span class="hint">Сверху те, у кого больше баллов в этом месяце.</span>
      </div>
      <?php if ($candidates): ?>
        <div class="field">
          <span class="lbl">Лидеры месяца</span>
          <div class="reasons">
            <?php foreach ($candidates as $c): ?>
              <button type="button" data-fill="<?= (int)$c['id'] ?>" data-fill-target="honor_user"><?= e($c['first_name'] . ' ' . $c['last_name']) ?> <span class="muted">+<?= (int)$c['month_pts'] ?></span></button>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
      <div class="field">
        <label for="honor_note">За что отмечен</label>
        <textarea class="textarea" id="honor_note" name="note" maxlength="160" style="min-height:96px" placeholder="Например: провёл три субботника и привёл в отделение двух новичков"><?= e($currentNote) ?></textarea>
        <span class="hint" id="honorCount"></span>
      </div>
      <div><button type="submit" class="btn btn-accent"><?= icon('crown') ?>Сохранить</button></div>
    </form>
  </section>

  <section class="card">
    <div class="card-h"><div><h2>Так будет на сайте</h2><p>Карточка появляется на главной между новостями и командой</p></div></div>
    <div class="card-b">
      <div class="honor-prev" id="honorShown">
        <span class="ava"><img id="honorAva" alt="" hidden><span id="honorIni"></span></span>
        <div><p class="k"><?= icon('crown') ?> Волонтёр месяца</p><h4 id="honorName"></h4><p class="n" id="honorText"></p></div>
      </div>
      <div class="empty" id="honorHidden" hidden><?= icon('eye-off') ?><b>Карточка скрыта</b>На главной её не будет, пока не выберешь волонтёра.</div>
    </div>
  </section>
</div>

<?php require __DIR__ . '/../includes/panel_footer.php'; ?>
