<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$me = requireAdmin();

$panelSection = 'admin';
$panelTitle   = 'Рейтинг';
$activeItem   = 'rating';

$period = ($_GET['period'] ?? 'all') === 'month' ? 'month' : 'all';

if ($period === 'month') {
    $rows = fetchAll(
        "SELECT u.id, u.last_name, u.first_name, u.avatar, u.position, u.points AS total,
                COALESCE(SUM(t.points),0) AS pts,
                (SELECT COUNT(*) FROM event_registrations r JOIN events e ON e.id = r.event_id
                  WHERE r.user_id = u.id AND r.status = 'attended'
                    AND e.starts_at >= DATE_FORMAT(NOW(), '%Y-%m-01')) AS ev
         FROM users u
         LEFT JOIN point_transactions t
                ON t.user_id = u.id
               AND t.created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
         WHERE u.status='approved' AND u.role='volunteer'
         GROUP BY u.id, u.last_name, u.first_name, u.avatar, u.position, u.points
         ORDER BY pts DESC, u.last_name ASC
         LIMIT 200"
    );
} else {
    $rows = fetchAll(
        "SELECT u.id, u.last_name, u.first_name, u.avatar, u.position, u.points AS total, u.points AS pts,
                (SELECT COUNT(*) FROM event_registrations r WHERE r.user_id = u.id AND r.status = 'attended') AS ev
         FROM users u
         WHERE u.status='approved' AND u.role='volunteer'
         ORDER BY u.points DESC, u.last_name ASC
         LIMIT 200"
    );
}

$months = ['январь', 'февраль', 'март', 'апрель', 'май', 'июнь', 'июль', 'август', 'сентябрь', 'октябрь', 'ноябрь', 'декабрь'];
$monthName = $months[(int)date('n') - 1];
$top = array_values(array_filter(array_slice($rows, 0, 3), fn($r) => (int)$r['pts'] > 0));
$nf = fn($n) => number_format((int)$n, 0, ',', ' ');
$ini = fn($r) => mb_substr($r['first_name'], 0, 1) . mb_substr($r['last_name'], 0, 1);

$panelLead = 'Волонтёры отделения по сумме баллов. Администраторы и разработчики в рейтинг не входят.';
$panelActions = '<div class="seg" role="group" aria-label="Период">'
    . '<a href="' . url('admin/rating.php') . '" class="' . ($period === 'all' ? 'on' : '') . '">За всё время</a>'
    . '<a href="' . url('admin/rating.php?period=month') . '" class="' . ($period === 'month' ? 'on' : '') . '">За ' . $monthName . '</a>'
    . '</div>'
    . '<a class="btn btn-line" href="' . url('admin/export.php?type=users') . '">' . icon('download') . 'Экспорт CSV</a>';

require __DIR__ . '/../includes/panel_header.php';
?>

<?php if (count($top) === 3): ?>
  <div class="podium">
    <?php foreach ([1, 0, 2] as $k): $r = $top[$k]; $place = $k + 1; ?>
      <a class="pod p<?= $place ?>" href="<?= url('admin/volunteer.php?id=' . (int)$r['id']) ?>">
        <span class="pod-place"><?= $place ?></span>
        <div class="pod-who">
          <span class="ava"><?php if ($r['avatar']): ?><img src="<?= url('uploads/avatars/' . $r['avatar']) ?>" alt=""><?php else: ?><?= e($ini($r)) ?><?php endif; ?></span>
          <div><b><?= e($r['first_name'] . ' ' . $r['last_name']) ?></b><small><?= e(levelFor((int)$r['total'])['current']['name']) ?> · <?= (int)$r['ev'] ?> <?= plural((int)$r['ev'], 'мероприятие', 'мероприятия', 'мероприятий') ?></small></div>
        </div>
        <span class="pts num"><?= $nf($r['pts']) ?> <small><?= plural((int)$r['pts'], 'балл', 'балла', 'баллов') ?></small></span>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<section class="card">
  <?php if ($rows): ?>
    <div class="table-wrap">
      <table class="data cards">
        <thead><tr><th>Волонтёр</th><th>Уровень</th><th style="text-align:right">Мероприятий</th><th style="text-align:right"><?= $period === 'month' ? 'Баллы за ' . $monthName : 'Баллы' ?></th><th></th></tr></thead>
        <tbody>
          <?php foreach ($rows as $i => $r): $place = $i + 1; ?>
            <tr class="click" data-href="<?= url('admin/volunteer.php?id=' . (int)$r['id']) ?>">
              <td>
                <div class="who">
                  <span class="rank-cell place <?= $place <= 3 && (int)$r['pts'] > 0 ? 'rank-' . $place : '' ?>" title="Место <?= $place ?>"><?= $place <= 3 && (int)$r['pts'] > 0 ? icon('medal') : '' ?><?= $place ?></span>
                  <span class="ava"><?php if ($r['avatar']): ?><img src="<?= url('uploads/avatars/' . $r['avatar']) ?>" alt=""><?php else: ?><?= e($ini($r)) ?><?php endif; ?></span>
                  <div style="min-width:0"><a href="<?= url('admin/volunteer.php?id=' . (int)$r['id']) ?>"><b><?= e($r['first_name'] . ' ' . $r['last_name']) ?></b></a><small><?= e(positionLabel($r['position'])) ?></small></div>
                </div>
              </td>
              <td data-label="Уровень"><?= e(levelFor((int)$r['total'])['current']['name']) ?></td>
              <td data-label="Мероприятий" class="num" style="text-align:right"><?= (int)$r['ev'] ?></td>
              <td data-label="Баллы" class="num" style="text-align:right"><b><?= $nf($r['pts']) ?></b></td>
              <td data-label=""><div class="actions" style="justify-content:flex-end"><a class="btn btn-line btn-sm" href="<?= url('admin/points.php?user_id=' . (int)$r['id']) ?>"><?= icon('star') ?>Начислить</a></div></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="empty"><?= icon('trophy') ?><b>Рейтинг пока пуст</b>Он заполнится, когда волонтёры начнут получать баллы.</div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/panel_footer.php'; ?>
