<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$me = requireAdmin();

$panelSection = 'admin';
$panelTitle   = 'Баллы';
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
        flash('error', 'Выбери хотя бы одного волонтёра.');
    } elseif ($points === 0) {
        flash('error', 'Укажи, сколько баллов начислить.');
    } elseif ($reason === '') {
        flash('error', 'Укажи причину: волонтёр увидит её в своей истории.');
    } elseif (abs($points) > 1000) {
        flash('error', 'За одно начисление можно дать не больше 1000 баллов.');
    } else {
        foreach ($targets as $target) {
            awardPoints((int)$target['id'], $points, $reason);
            logAction('points_manual', 'user', (int)$target['id'], ($points > 0 ? '+' : '') . $points . ' · ' . $reason);
        }
        flash('success', 'Начисление сохранено: ' . ($points > 0 ? '+' : '') . $points
            . ' для ' . count($targets) . ' ' . plural(count($targets), 'волонтёра', 'волонтёров', 'волонтёров') . '.');
    }
    redirect('admin/points.php');
}

$preselect = (int)($_GET['user_id'] ?? 0);
$volunteers = fetchAll("SELECT id, last_name, first_name, points, avatar FROM users WHERE status='approved' ORDER BY last_name ASC, first_name ASC");

$recent = fetchAll(
    "SELECT t.*, u.last_name, u.first_name, a.last_name AS by_last, a.first_name AS by_first
     FROM point_transactions t
     JOIN users u ON u.id = t.user_id
     LEFT JOIN users a ON a.id = t.created_by
     ORDER BY t.created_at DESC LIMIT 40"
);

$panelLead = 'Начисляй за координацию и помощь вне мероприятий. За участие баллы приходят сами, когда ставишь отметку.';
$coordPts  = (int)setting('points_coordinator', '50');
$reasonTpl = ['Координация мероприятия', 'Помощь в штабе', 'Съёмка и монтаж', 'Организация акции'];

require __DIR__ . '/../includes/panel_header.php';
?>

<div class="grid g-main">
  <section class="card">
    <div class="card-h"><div><h2>Начисление</h2><p>Можно выбрать сразу несколько человек</p></div></div>
    <form method="post" class="card-b stack" id="pointsForm">
      <?= csrfField() ?>
      <div class="field">
        <div class="row between"><span class="lbl">Кому</span><span class="hint" id="pickedCount"></span></div>
        <div class="input-ico"><?= icon('search') ?><input class="input" type="search" placeholder="Найти по имени" aria-label="Найти волонтёра" data-filter-input="pickList" data-filter-empty="pickEmpty" autocomplete="off"></div>
        <div class="check-list" id="pickList">
          <?php foreach ($volunteers as $v): $vn = $v['first_name'] . ' ' . $v['last_name']; ?>
            <label class="check-list-item" data-filter-text="<?= e(mb_strtolower($vn . ' ' . $v['last_name'])) ?>">
              <input type="checkbox" name="user_ids[]" value="<?= (int)$v['id'] ?>" data-name="<?= e($vn) ?>" <?= $preselect === (int)$v['id'] ? 'checked' : '' ?>>
              <span class="ava xs"><?php if ($v['avatar']): ?><img src="<?= url('uploads/avatars/' . $v['avatar']) ?>" alt=""><?php else: ?><?= e(mb_substr($v['first_name'], 0, 1) . mb_substr($v['last_name'], 0, 1)) ?><?php endif; ?></span>
              <span class="grow"><?= e($v['last_name'] . ' ' . $v['first_name']) ?></span>
              <small class="num"><?= (int)$v['points'] ?> б.</small>
            </label>
          <?php endforeach; ?>
          <p class="empty-line" id="pickEmpty" hidden>Никого не нашли</p>
        </div>
      </div>
      <div class="field">
        <label for="points">Сколько</label>
        <div class="amounts">
          <?php foreach ([10, 25, 50, 100, -10] as $a): ?>
            <button type="button" data-amount="<?= $a ?>"><?= $a > 0 ? '+' . $a : '−' . abs($a) ?></button>
          <?php endforeach; ?>
          <input class="input num" type="number" id="points" name="points" value="<?= $coordPts ?>" min="-1000" max="1000" step="1" required style="width:120px">
        </div>
        <span class="hint">Отрицательное число списывает баллы. За раз не больше 1 000.</span>
      </div>
      <div class="field">
        <label for="reason">За что</label>
        <input class="input" type="text" id="reason" name="reason" maxlength="190" required placeholder="Причина видна волонтёру в истории">
        <div class="reasons">
          <?php foreach ($reasonTpl as $r): ?><button type="button" data-fill="<?= e($r) ?>" data-fill-target="reason"><?= e($r) ?></button><?php endforeach; ?>
        </div>
      </div>
      <p class="summary" id="pointsSummary" aria-live="polite">Выбери, кому и сколько начислить.</p>
      <button type="submit" class="btn btn-accent btn-block" id="pointsGo"><?= icon('star') ?>Начислить</button>
    </form>
  </section>

  <section class="card">
    <div class="card-h"><div><h2>Как начисляются</h2></div></div>
    <ul class="list">
      <li><span class="dot ok"><?= icon('calendar-check') ?></span><div class="grow"><b>За участие</b><small>Автоматически при отметке «Пришёл». Сумма задаётся в карточке мероприятия.</small></div></li>
      <li><span class="dot info"><?= icon('users') ?></span><div class="grow"><b>За координацию</b><small>Вручную на этой странице. Рекомендуем <?= $coordPts ?> <?= plural($coordPts, 'балл', 'балла', 'баллов') ?>.</small></div></li>
      <li><span class="dot"><?= icon('undo') ?></span><div class="grow"><b>Списание</b><small>Отрицательное число и обязательная причина.</small></div></li>
      <li><span class="dot"><?= icon('trophy') ?></span><div class="grow"><b>Уровни</b><small>Пороги уровней настраивает разработчик в разделе «Настройки».</small></div></li>
    </ul>
  </section>
</div>

<section class="card" style="margin-top:18px">
  <div class="card-h"><div><h2>История начислений</h2><p>Последние 40 операций</p></div></div>
  <?php if ($recent): ?>
    <div class="table-wrap">
      <table class="data cards">
        <thead><tr><th>Волонтёр</th><th>Дата</th><th style="text-align:right">Баллы</th><th>Причина</th><th>Кто начислил</th></tr></thead>
        <tbody>
          <?php foreach ($recent as $t): $pv = (int)$t['points']; ?>
            <tr>
              <td><b style="font-weight:600"><?= e($t['first_name'] . ' ' . $t['last_name']) ?></b></td>
              <td data-label="Дата" class="muted" style="white-space:nowrap"><?= e(ruDate($t['created_at'], true)) ?></td>
              <td data-label="Баллы" style="text-align:right"><span class="num <?= $pv >= 0 ? 'pos' : 'neg' ?>"><?= $pv > 0 ? '+' . $pv : '−' . abs($pv) ?></span></td>
              <td data-label="Причина"><?= e($t['reason']) ?></td>
              <td data-label="Кто" class="muted"><?= e(trim(($t['by_first'] ?? '') . ' ' . ($t['by_last'] ?? '')) ?: 'система') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="empty"><?= icon('star') ?><b>Начислений пока не было</b>Первые записи появятся после отметки участия.</div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/panel_footer.php'; ?>
