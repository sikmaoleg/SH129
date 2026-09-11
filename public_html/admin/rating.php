<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$me = requireAdmin();

$panelSection = 'admin';
$panelTitle   = 'Рейтинг волонтёров';
$activeItem   = 'rating';

$period = $_GET['period'] ?? 'all';

if ($period === 'month') {
    $rows = fetchAll(
        "SELECT u.id, u.last_name, u.first_name, u.avatar, u.status,
                COALESCE(SUM(t.points),0) AS pts
         FROM users u
         LEFT JOIN point_transactions t
                ON t.user_id = u.id
               AND t.created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
         WHERE u.status='approved' AND u.role='volunteer'
         GROUP BY u.id, u.last_name, u.first_name, u.avatar, u.status
         ORDER BY pts DESC, u.last_name ASC
         LIMIT 200"
    );
} else {
    $rows = fetchAll(
        "SELECT id, last_name, first_name, avatar, status, points AS pts
         FROM users
         WHERE status='approved' AND role='volunteer'
         ORDER BY points DESC, last_name ASC
         LIMIT 200"
    );
}

require __DIR__ . '/../includes/panel_header.php';
?>

<div class="card">
  <div class="card-head">
    <div>
      <h2>Таблица рейтинга</h2>
      <p>Все волонтёры отделения, упорядоченные по баллам.</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <a href="<?= url('admin/rating.php') ?>" class="btn btn-sm <?= $period !== 'month' ? 'btn-primary' : 'btn-outline' ?>">За всё время</a>
      <a href="<?= url('admin/rating.php?period=month') ?>" class="btn btn-sm <?= $period === 'month' ? 'btn-primary' : 'btn-outline' ?>">За месяц</a>
      <a href="<?= url('admin/export.php?type=users') ?>" class="btn btn-outline btn-sm"><?= icon('download') ?>Экспорт CSV</a>
    </div>
  </div>

  <?php if ($rows): ?>
    <div class="card-body card-body-flush table-wrap">
      <table class="data cards">
        <thead>
          <tr>
            <th style="width:70px;">Место</th>
            <th>Волонтёр</th>
            <th style="width:110px;">Баллы</th>
            <th style="width:160px;"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $i => $r): $place = $i + 1; ?>
            <tr>
              <td class="rank-cell <?= $place <= 3 ? 'rank-'.$place : '' ?>" data-label="Место"><?= $place <= 3 ? icon('medal') : '' ?><?= $place ?></td>
              <td data-label="Волонтёр"><?= e($r['last_name'] . ' ' . $r['first_name']) ?></td>
              <td class="num" data-label="Баллы"><?= (int)$r['pts'] ?></td>
              <td data-label="">
                <a href="<?= url('admin/volunteer.php?id=' . (int)$r['id']) ?>" class="btn btn-outline btn-sm">Карточка</a>
                <a href="<?= url('admin/points.php?user_id=' . (int)$r['id']) ?>" class="btn btn-outline btn-sm">Начислить</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="empty"><b>Рейтинг пока пуст</b>Он заполнится, когда волонтёры начнут получать баллы.</div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/panel_footer.php'; ?>
