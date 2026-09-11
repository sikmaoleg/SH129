<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();

$panelSection = 'admin';
$panelTitle   = 'Аналитика';
$activeItem   = 'analytics';

// --- Активность по месяцам: мероприятия и записи, последние 6 месяцев ---
$months = [];
for ($i = 5; $i >= 0; $i--) {
    $months[] = date('Y-m', strtotime("-$i months"));
}
$monthLabels = array_map(
    fn($m) => mb_convert_case(RU_MONTHS_SHORT[(int)date('n', strtotime($m . '-01'))], MB_CASE_TITLE, 'UTF-8'),
    $months
);

$eventsByMonth = [];
foreach (fetchAll(
    "SELECT DATE_FORMAT(starts_at, '%Y-%m') ym, COUNT(*) c FROM events
     WHERE status IN ('published','finished') AND starts_at >= ? GROUP BY ym",
    [$months[0] . '-01']
) as $r) {
    $eventsByMonth[$r['ym']] = (int)$r['c'];
}

// --- Рост числа волонтёров: новых одобренных по месяцам ---
$volunteersByMonth = [];
foreach (fetchAll(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') ym, COUNT(*) c FROM users
     WHERE status = 'approved' AND role = 'volunteer' AND created_at >= ? GROUP BY ym",
    [$months[0] . '-01']
) as $r) {
    $volunteersByMonth[$r['ym']] = (int)$r['c'];
}

// --- Топ направлений по количеству мероприятий (за всё время) ---
$topDirections = fetchAll(
    "SELECT d.title, COUNT(e.id) c FROM directions d
     LEFT JOIN events e ON e.direction_id = d.id AND e.status IN ('published','finished')
     GROUP BY d.id ORDER BY c DESC, d.sort ASC"
);

$maxEvents     = max(1, ...array_values($eventsByMonth ?: [0]));
$maxVolunteers = max(1, ...array_values($volunteersByMonth ?: [0]));
$maxDirection  = max(1, ...array_column($topDirections, 'c'));

require __DIR__ . '/../includes/panel_header.php';
?>

<div class="grid-2">
  <div class="card">
    <div class="card-head"><div><h2>Мероприятия по месяцам</h2><p>Последние 6 месяцев</p></div></div>
    <div class="card-body">
      <div class="bar-chart">
        <?php foreach ($months as $i => $m): $v = $eventsByMonth[$m] ?? 0; ?>
          <div class="bar-col">
            <div class="bar-track"><div class="bar-fill" style="height:<?= $v > 0 ? max(6, round($v / $maxEvents * 100)) : 0 ?>%;"><?= $v > 0 ? $v : '' ?></div></div>
            <span><?= e($monthLabels[$i]) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><div><h2>Рост числа волонтёров</h2><p>Новых одобренных по месяцам</p></div></div>
    <div class="card-body">
      <div class="bar-chart">
        <?php foreach ($months as $i => $m): $v = $volunteersByMonth[$m] ?? 0; ?>
          <div class="bar-col">
            <div class="bar-track"><div class="bar-fill bar-fill-accent" style="height:<?= $v > 0 ? max(6, round($v / $maxVolunteers * 100)) : 0 ?>%;"><?= $v > 0 ? $v : '' ?></div></div>
            <span><?= e($monthLabels[$i]) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-head"><div><h2>Топ направлений</h2><p>По количеству проведённых мероприятий за всё время</p></div></div>
  <div class="card-body">
    <?php if (array_sum(array_column($topDirections, 'c')) === 0): ?>
      <div class="empty"><b>Мероприятий пока не было</b></div>
    <?php else: ?>
      <div class="hbar-chart">
        <?php foreach ($topDirections as $d): ?>
          <div class="hbar-row">
            <span class="hbar-label"><?= e($d['title']) ?></span>
            <div class="hbar-track"><div class="hbar-fill" style="width:<?= max(3, round((int)$d['c'] / $maxDirection * 100)) ?>%;"></div></div>
            <span class="hbar-value"><?= (int)$d['c'] ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-head"><div><h2>Сводка за период</h2><p>Выгрузка в CSV: новые волонтёры, мероприятия, очки, часы</p></div></div>
  <div class="card-body">
    <form method="get" action="<?= url('admin/export.php') ?>" class="inline-form">
      <input type="hidden" name="type" value="summary">
      <label style="display:flex;flex-direction:column;gap:4px;font-size:.82rem;color:var(--muted);">С
        <input type="date" name="from" value="<?= e(date('Y-m-01')) ?>">
      </label>
      <label style="display:flex;flex-direction:column;gap:4px;font-size:.82rem;color:var(--muted);">По
        <input type="date" name="to" value="<?= e(date('Y-m-d')) ?>">
      </label>
      <button type="submit" class="btn btn-primary"><?= icon('download') ?>Скачать CSV</button>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../includes/panel_footer.php'; ?>
