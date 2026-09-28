<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();

$panelSection = 'admin';
$panelTitle   = 'Аналитика';
$activeItem   = 'analytics';

$span = (int)($_GET['m'] ?? 6) === 12 ? 12 : 6;

$months = [];
for ($i = $span - 1; $i >= 0; $i--) {
    $months[] = date('Y-m', strtotime(date('Y-m-01') . " -$i months"));
}
$monthsFull = ['январь', 'февраль', 'март', 'апрель', 'май', 'июнь', 'июль', 'август', 'сентябрь', 'октябрь', 'ноябрь', 'декабрь'];
$short  = ['Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн', 'Июл', 'Авг', 'Сен', 'Окт', 'Ноя', 'Дек'];
$labels = array_map(fn($m) => $short[(int)substr($m, 5, 2) - 1], $months);
$full   = array_map(fn($m) => mb_convert_case($monthsFull[(int)substr($m, 5, 2) - 1], MB_CASE_TITLE, 'UTF-8') . ' ' . substr($m, 0, 4), $months);
$from   = $months[0] . '-01';

$byMonth = function (string $sql) use ($months, $from): array {
    $out = array_fill_keys($months, 0);
    foreach (fetchAll($sql, [$from]) as $r) {
        if (isset($out[$r['ym']])) { $out[$r['ym']] = (int)$r['c']; }
    }
    return array_values($out);
};
$events = $byMonth("SELECT DATE_FORMAT(starts_at, '%Y-%m') ym, COUNT(*) c FROM events
                    WHERE status IN ('published','finished') AND starts_at >= ? AND starts_at <= NOW() GROUP BY ym");
$people = $byMonth("SELECT DATE_FORMAT(created_at, '%Y-%m') ym, COUNT(*) c FROM users
                    WHERE status = 'approved' AND role = 'volunteer' AND created_at >= ? GROUP BY ym");
$attendedSum = (int)fetchValue(
    "SELECT COUNT(*) FROM event_registrations r JOIN events e ON e.id = r.event_id
     WHERE r.status = 'attended' AND e.starts_at >= ?", [$from]
);
$pointsSum = (int)fetchValue("SELECT COALESCE(SUM(points),0) FROM point_transactions WHERE points > 0 AND created_at >= ?", [$from]);

$topDirections = fetchAll(
    "SELECT d.title, COUNT(e.id) c FROM directions d
     LEFT JOIN events e ON e.direction_id = d.id AND e.status IN ('published','finished')
     GROUP BY d.id ORDER BY c DESC, d.sort ASC"
);
$maxDirection = max(1, ...array_map('intval', array_column($topDirections, 'c') ?: [0]));

$evSum = array_sum($events);
$pplSum = array_sum($people);
$last = count($months) - 1;
$busiest = $evSum ? array_search(max($events), $events, true) : null;
$nf = fn($n) => number_format((int)$n, 0, ',', ' ');
$chart = fn(array $vals, string $kind, array $unit, string $title) => e(json_encode(
    ['labels' => $labels, 'full' => $full, 'values' => $vals, 'kind' => $kind, 'unit' => $unit, 'title' => $title],
    JSON_UNESCAPED_UNICODE
));

$panelLead = 'Как растёт отделение за последние ' . $span . ' месяцев. Графики можно посмотреть и таблицей.';
$panelActions = '<div class="seg" role="group" aria-label="Период">'
    . '<a href="' . url('admin/analytics.php') . '" class="' . ($span === 6 ? 'on' : '') . '">6 месяцев</a>'
    . '<a href="' . url('admin/analytics.php?m=12') . '" class="' . ($span === 12 ? 'on' : '') . '">12 месяцев</a></div>';

require __DIR__ . '/../includes/panel_header.php';
?>

<div class="kpis">
  <div class="kpi"><div class="kpi-l"><span>Новых волонтёров</span><?= icon('users') ?></div><div class="kpi-v num"><?= $pplSum ?></div><div class="kpi-d"><?= $people[$last] ? '<span class="up">+' . $people[$last] . '</span> в этом месяце' : 'в этом месяце пока нет' ?></div></div>
  <div class="kpi"><div class="kpi-l"><span>Проведено мероприятий</span><?= icon('calendar-check') ?></div><div class="kpi-v num"><?= $evSum ?></div><div class="kpi-d"><?= $busiest !== null ? 'больше всего: ' . e(mb_strtolower($full[$busiest])) : 'за период не было' ?></div></div>
  <div class="kpi"><div class="kpi-l"><span>Участий</span><?= icon('hand-heart') ?></div><div class="kpi-v num"><?= $nf($attendedSum) ?></div><div class="kpi-d">отметки «пришёл»</div></div>
  <div class="kpi"><div class="kpi-l"><span>Начислено баллов</span><?= icon('star') ?></div><div class="kpi-v num"><?= $nf($pointsSum) ?></div><div class="kpi-d">без списаний</div></div>
</div>

<div class="grid g2">
  <section class="card">
    <div class="card-h"><div><h2>Мероприятия</h2><p>Сколько прошло за месяц</p></div><button class="link-btn" type="button" data-toggle-table="t1">Таблицей</button></div>
    <div class="card-b">
      <div class="chart" data-chart="<?= $chart($events, 'bar', ['мероприятие', 'мероприятия', 'мероприятий'], 'Мероприятия по месяцам') ?>"></div>
      <div class="chart-tbl" id="t1" hidden><table class="data"><thead><tr><th>Месяц</th><th style="text-align:right">Мероприятий</th></tr></thead><tbody>
        <?php foreach ($months as $i => $m): ?><tr><td><?= e($full[$i]) ?></td><td class="num" style="text-align:right"><?= $events[$i] ?></td></tr><?php endforeach; ?>
      </tbody></table></div>
    </div>
  </section>
  <section class="card">
    <div class="card-h"><div><h2>Новые волонтёры</h2><p>Одобренные анкеты по месяцам</p></div><button class="link-btn" type="button" data-toggle-table="t2">Таблицей</button></div>
    <div class="card-b">
      <div class="chart" data-chart="<?= $chart($people, 'line', ['новый волонтёр', 'новых волонтёра', 'новых волонтёров'], 'Новые волонтёры по месяцам') ?>"></div>
      <div class="chart-tbl" id="t2" hidden><table class="data"><thead><tr><th>Месяц</th><th style="text-align:right">Новых</th></tr></thead><tbody>
        <?php foreach ($months as $i => $m): ?><tr><td><?= e($full[$i]) ?></td><td class="num" style="text-align:right"><?= $people[$i] ?></td></tr><?php endforeach; ?>
      </tbody></table></div>
    </div>
  </section>
</div>

<div class="grid g-main" style="margin-top:18px">
  <section class="card">
    <div class="card-h"><div><h2>Направления</h2><p>Мероприятий за всё время</p></div></div>
    <div class="card-b">
      <?php if (array_sum(array_map('intval', array_column($topDirections, 'c'))) === 0): ?>
        <div class="empty"><?= icon('chart-line') ?><b>Мероприятий пока не было</b></div>
      <?php else: ?>
        <div class="hbars">
          <?php foreach ($topDirections as $d): $c = (int)$d['c']; ?>
            <div class="hb"><span><?= e($d['title']) ?></span><div class="hb-track"><div class="hb-bar" style="width:<?= $c ? max(2, round($c / $maxDirection * 86)) : 0 ?>%" tabindex="0" data-hb="<?= $c ?> <?= plural($c, 'мероприятие', 'мероприятия', 'мероприятий') ?>" data-hbl="<?= e($d['title']) ?>"></div><span class="hb-v"><?= $c ?></span></div></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>
  <section class="card">
    <div class="card-h"><div><h2>Выгрузка</h2><p>Новые волонтёры, мероприятия и баллы за период</p></div></div>
    <form method="get" action="<?= url('admin/export.php') ?>" class="card-b stack">
      <input type="hidden" name="type" value="summary">
      <div class="frow">
        <div class="field"><label for="an1">С</label><input class="input" id="an1" type="date" name="from" value="<?= e($from) ?>"></div>
        <div class="field"><label for="an2">По</label><input class="input" id="an2" type="date" name="to" value="<?= e(date('Y-m-d')) ?>"></div>
      </div>
      <button type="submit" class="btn btn-accent btn-block"><?= icon('download') ?>Скачать CSV</button>
    </form>
  </section>
</div>

<?php require __DIR__ . '/../includes/panel_footer.php'; ?>
