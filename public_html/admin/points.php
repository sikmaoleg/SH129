<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$me = requireAdmin();

$panelSection = 'admin';
$panelTitle   = 'Начисление очков';
$activeItem   = 'points';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $userId = (int)($_POST['user_id'] ?? 0);
    $points = (int)($_POST['points'] ?? 0);
    $reason = trim((string)($_POST['reason'] ?? ''));
    $hours  = round((float)($_POST['hours'] ?? 0), 1);

    $target = $userId ? fetchOne("SELECT * FROM users WHERE id=? AND status='approved'", [$userId]) : null;

    if (!$target) {
        flash('error', 'Волонтёр не найден или его учётная запись не одобрена.');
    } elseif ($points === 0 && $hours == 0) {
        flash('error', 'Укажите количество очков или часов.');
    } elseif ($reason === '') {
        flash('error', 'Укажите причину начисления — она видна волонтёру в истории.');
    } elseif (abs($points) > 1000) {
        flash('error', 'За одно начисление можно дать не больше 1000 очков.');
    } else {
        awardPoints($userId, $points, $reason, null, $hours);
        logAction('points_manual', 'user', $userId, $points . ' — ' . $reason);
        flash('success', 'Начисление сохранено: ' . ($points > 0 ? '+' : '') . $points . ' для ' . $target['last_name'] . ' ' . $target['first_name'] . '.');
    }
    redirect('admin/points.php');
}

$preselect = (int)($_GET['user_id'] ?? 0);
$volunteers = fetchAll("SELECT id, last_name, first_name, points FROM users WHERE status='approved' ORDER BY last_name ASC");

$recent = fetchAll(
    "SELECT t.*, u.last_name, u.first_name, a.last_name AS by_last, a.first_name AS by_first
     FROM point_transactions t
     JOIN users u ON u.id = t.user_id
     LEFT JOIN users a ON a.id = t.created_by
     ORDER BY t.created_at DESC LIMIT 40"
);

require __DIR__ . '/../includes/panel_header.php';
?>

<div class="grid-2">
  <div class="card">
    <div class="card-head"><div><h2>Ручное начисление</h2><p>Для случаев вне мероприятий: обучение, координация, помощь в срочной задаче.</p></div></div>
    <div class="card-body">
      <form method="post">
        <?= csrfField() ?>
        <div class="field">
          <label for="user_id">Волонтёр</label>
          <select id="user_id" name="user_id" required>
            <option value="">— выберите —</option>
            <?php foreach ($volunteers as $v): ?>
              <option value="<?= (int)$v['id'] ?>" <?= $preselect === (int)$v['id'] ? 'selected' : '' ?>>
                <?= e($v['last_name'] . ' ' . $v['first_name']) ?> (<?= (int)$v['points'] ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field-row">
          <div class="field">
            <label for="points">Очки</label>
            <input type="number" id="points" name="points" value="0" min="-1000" max="1000" required>
            <div class="hint">Отрицательное число — списание.</div>
          </div>
          <div class="field">
            <label for="hours">Часы</label>
            <input type="number" id="hours" name="hours" value="0" step="0.5" min="-24" max="24">
          </div>
        </div>
        <div class="field">
          <label for="reason">Причина</label>
          <input type="text" id="reason" name="reason" maxlength="190" required
                 placeholder="Например: координация субботника 12 сентября">
          <div class="hint">Волонтёр увидит эту формулировку в своей истории.</div>
        </div>
        <button type="submit" class="btn btn-primary">Начислить</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><div><h2>Как начисляются очки</h2></div></div>
    <div class="card-body">
      <table class="kv">
        <tr><th>За участие в мероприятии</th><td style="font-family:inherit;">Значение указывается в карточке мероприятия. Начисляется при отметке «участие принято».</td></tr>
        <tr><th>За координацию</th><td style="font-family:inherit;">Вручную, рекомендуемое значение — <?= e(setting('points_coordinator', '50')) ?>.</td></tr>
        <tr><th>За час работы</th><td style="font-family:inherit;">Ориентир — <?= e(setting('points_per_hour', '10')) ?> очков.</td></tr>
        <tr><th>Списание</th><td style="font-family:inherit;">Введите отрицательное число и укажите причину.</td></tr>
      </table>
      <p style="font-size:.85rem;color:var(--muted);margin-top:14px;">
        Пороги уровней настраиваются в панели разработчика.
      </p>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-head"><div><h2>История начислений</h2><p>Последние 40 операций</p></div></div>
  <?php if ($recent): ?>
    <div class="card-body card-body-flush table-wrap">
      <table class="data">
        <thead><tr><th>Дата</th><th>Волонтёр</th><th>Очки</th><th>Причина</th><th>Кто начислил</th></tr></thead>
        <tbody>
          <?php foreach ($recent as $t): ?>
            <tr>
              <td style="white-space:nowrap;color:var(--muted);font-size:.85rem;"><?= e(ruDate($t['created_at'], true)) ?></td>
              <td><?= e($t['last_name'] . ' ' . $t['first_name']) ?></td>
              <td class="num" style="color:<?= (int)$t['points'] >= 0 ? 'var(--ok)' : 'var(--accent)' ?>;">
                <?= (int)$t['points'] > 0 ? '+' : '' ?><?= (int)$t['points'] ?>
              </td>
              <td><?= e($t['reason']) ?></td>
              <td style="color:var(--muted);"><?= e(trim(($t['by_last'] ?? '') . ' ' . ($t['by_first'] ?? '')) ?: 'система') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="empty"><b>Начислений пока не было</b>Первые записи появятся после отметки участия.</div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/panel_footer.php'; ?>
