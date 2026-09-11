<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$me = requireLogin();

$panelSection = 'cabinet';
$panelTitle   = 'Рейтинг волонтёров';
$activeItem   = 'rating';

$period = $_GET['period'] ?? 'all';

if ($period === 'month') {
    // Баллы, начисленные за текущий месяц
    $rows = fetchAll(
        "SELECT u.id, u.last_name, u.first_name, u.avatar,
                COALESCE(SUM(t.points),0) AS pts
         FROM users u
         LEFT JOIN point_transactions t
                ON t.user_id = u.id
               AND t.created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
         WHERE u.status='approved' AND u.role='volunteer'
         GROUP BY u.id, u.last_name, u.first_name, u.avatar
         ORDER BY pts DESC, u.last_name ASC
         LIMIT 100"
    );
} else {
    $rows = fetchAll(
        "SELECT id, last_name, first_name, avatar, points AS pts
         FROM users
         WHERE status='approved' AND role='volunteer'
         ORDER BY points DESC, last_name ASC
         LIMIT 100"
    );
}

$levelsList = levels();
require __DIR__ . '/../includes/panel_header.php';
?>

<div class="card">
  <div class="card-head">
    <div>
      <h2>Таблица рейтинга</h2>
      <p>Баллы начисляются координаторами после подтверждения участия в мероприятии.</p>
    </div>
    <div style="display:flex;gap:8px;">
      <a href="<?= url('cabinet/rating.php') ?>" class="btn btn-sm <?= $period !== 'month' ? 'btn-primary' : 'btn-outline' ?>">За всё время</a>
      <a href="<?= url('cabinet/rating.php?period=month') ?>" class="btn btn-sm <?= $period === 'month' ? 'btn-primary' : 'btn-outline' ?>">За месяц</a>
    </div>
  </div>

  <?php if ($rows): ?>
    <div class="card-body card-body-flush table-wrap">
      <table class="data">
        <thead>
          <tr>
            <th style="width:70px;">Место</th>
            <th>Волонтёр</th>
            <th>Уровень</th>
            <th style="width:110px;">Баллы</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $i => $r): $place = $i + 1; $isMe = (int)$r['id'] === (int)$me['id']; ?>
            <tr class="<?= $isMe ? 'row-me' : '' ?>">
              <td class="rank-cell <?= $place <= 3 ? 'rank-'.$place : '' ?>"><?= $place <= 3 ? icon('medal') : '' ?><?= $place ?></td>
              <td>
                <?= e($r['last_name'] . ' ' . $r['first_name']) ?>
                <?php if ($isMe): ?><span class="tag tag-blue" style="margin-left:8px;">это вы</span><?php endif; ?>
              </td>
              <td style="color:var(--muted);"><?= e(levelFor((int)$r['pts'])['current']['name']) ?></td>
              <td class="num"><?= (int)$r['pts'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="empty"><b>Рейтинг пока пуст</b>Он заполнится, когда волонтёры начнут участвовать в мероприятиях.</div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-head"><div><h2>Как устроены уровни</h2><p>Уровень зависит от накопленных баллов за всё время.</p></div></div>
  <div class="card-body card-body-flush table-wrap">
    <table class="data">
      <thead><tr><th>Уровень</th><th>Название</th><th>Баллы</th></tr></thead>
      <tbody>
        <?php foreach ($levelsList as $l): ?>
          <tr class="<?= levelFor((int)$me['points'])['current']['index'] === $l['index'] ? 'row-me' : '' ?>">
            <td class="num"><?= $l['index'] ?></td>
            <td><?= e($l['name']) ?></td>
            <td><?= $l['min'] ?><?= $l['max'] !== null ? '–' . $l['max'] : '+' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../includes/panel_footer.php'; ?>
