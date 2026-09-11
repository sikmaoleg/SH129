<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$me = requireAdmin();

$panelSection = 'admin';
$panelTitle   = 'Начисление баллов';
$activeItem   = 'points';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $userIds = array_values(array_unique(array_map('intval', (array)($_POST['user_ids'] ?? []))));
    $points  = (int)($_POST['points'] ?? 0);
    $reason  = trim((string)($_POST['reason'] ?? ''));

    $targets = $userIds ? fetchAll(
        'SELECT * FROM users WHERE status=\'approved\' AND id IN (' . implode(',', array_fill(0, count($userIds), '?')) . ')',
        $userIds
    ) : [];

    if (!$targets) {
        flash('error', 'Выберите хотя бы одного волонтёра.');
    } elseif ($points === 0) {
        flash('error', 'Укажите количество баллов.');
    } elseif ($reason === '') {
        flash('error', 'Укажите причину начисления — она видна волонтёру в истории.');
    } elseif (abs($points) > 1000) {
        flash('error', 'За одно начисление можно дать не больше 1000 баллов.');
    } else {
        foreach ($targets as $target) {
            awardPoints((int)$target['id'], $points, $reason);
            logAction('points_manual', 'user', (int)$target['id'], $points . ' — ' . $reason);
        }
        flash('success', 'Начисление сохранено: ' . ($points > 0 ? '+' : '') . $points
            . ' для ' . count($targets) . ' ' . plural(count($targets), 'волонтёра', 'волонтёров', 'волонтёров') . '.');
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
    <div class="card-head"><div><h2>Начисление баллов</h2><p>Можно выбрать несколько волонтёров — баллы начислятся каждому.</p></div></div>
    <div class="card-body">
      <form method="post">
        <?= csrfField() ?>
        <div class="field">
          <label>Волонтёры</label>
          <div class="hint" style="margin-bottom:8px;">Выберите одного или нескольких.</div>
          <div class="check-list">
            <?php foreach ($volunteers as $v): ?>
              <label class="check-list-item">
                <input type="checkbox" name="user_ids[]" value="<?= (int)$v['id'] ?>" <?= $preselect === (int)$v['id'] ? 'checked' : '' ?>>
                <span><?= e($v['last_name'] . ' ' . $v['first_name']) ?> <small>(<?= (int)$v['points'] ?>)</small></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="field">
          <label for="points">Баллы</label>
          <input type="number" id="points" name="points" value="0" min="-1000" max="1000" required>
          <div class="hint">Отрицательное число — списание.</div>
        </div>
        <div class="field">
          <label for="reason">Причина</label>
          <input type="text" id="reason" name="reason" maxlength="190" required
                 placeholder="Например: координация субботника 12 сентября">
          <div class="hint">Волонтёры увидят эту формулировку в своей истории.</div>
        </div>
        <button type="submit" class="btn btn-primary">Начислить</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><div><h2>Как начисляются баллы</h2></div></div>
    <div class="card-body">
      <table class="kv">
        <tr><th>За участие в мероприятии</th><td style="font-family:inherit;">Значение указывается в карточке мероприятия. Начисляется при отметке «участие принято».</td></tr>
        <tr><th>За координацию</th><td style="font-family:inherit;">Вручную, рекомендуемое значение — <?= e(setting('points_coordinator', '50')) ?>.</td></tr>
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
        <thead><tr><th>Дата</th><th>Волонтёр</th><th>Баллы</th><th>Причина</th><th>Кто начислил</th></tr></thead>
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
