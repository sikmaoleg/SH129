<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$me = requireLogin();

$panelSection = 'cabinet';
$panelTitle   = 'Обзор';
$activeItem   = 'index';

$lvl = levelFor((int)$me['points']);

$myPlace = (int)fetchValue(
    "SELECT COUNT(*) + 1 FROM users
     WHERE status='approved' AND role='volunteer' AND points > ?", [(int)$me['points']]
);
$totalVolunteers = (int)fetchValue("SELECT COUNT(*) FROM users WHERE status='approved' AND role='volunteer'");

$attended = (int)fetchValue(
    "SELECT COUNT(*) FROM event_registrations WHERE user_id = ? AND status = 'attended'", [(int)$me['id']]
);

$upcoming = fetchAll(
    "SELECT e.id, e.title, e.starts_at, e.location
     FROM event_registrations r JOIN events e ON e.id = r.event_id
     WHERE r.user_id = ? AND r.status = 'registered' AND e.starts_at >= NOW()
     ORDER BY e.starts_at ASC LIMIT 5", [(int)$me['id']]
);

$openEvents = fetchAll(
    "SELECT e.id, e.title, e.starts_at, e.location
     FROM events e
     WHERE e.status='published' AND e.starts_at >= NOW()
       AND e.id NOT IN (SELECT event_id FROM event_registrations WHERE user_id = ? AND status <> 'cancelled')
     ORDER BY e.starts_at ASC LIMIT 4", [(int)$me['id']]
);

$history = fetchAll(
    "SELECT points, reason, created_at FROM point_transactions
     WHERE user_id = ? ORDER BY created_at DESC LIMIT 6", [(int)$me['id']]
);

$badgesEarned = (int)fetchValue('SELECT COUNT(*) FROM user_badges WHERE user_id = ?', [(int)$me['id']]);

require __DIR__ . '/../includes/panel_header.php';
?>

<div class="grid-2" style="margin-bottom:22px;">
  <div class="level-box">
    <h2><?= icon('medal') ?>Уровень: <?= e($lvl['current']['name']) ?></h2>
    <div class="pts"><?= (int)$me['points'] ?> <span style="font-size:1rem;font-weight:600;"><?= plural((int)$me['points'], 'балл', 'балла', 'баллов') ?></span></div>
    <?php if ($lvl['next']): ?>
      <div class="progress"><i style="width:<?= $lvl['progress'] ?>%"></i></div>
      <div class="sub">До уровня «<?= e($lvl['next']['name']) ?>» осталось <?= $lvl['to_next'] ?> <?= plural($lvl['to_next'], 'балл', 'балла', 'баллов') ?></div>
    <?php else: ?>
      <div class="progress"><i style="width:100%"></i></div>
      <div class="sub">Максимальный уровень достигнут</div>
    <?php endif; ?>
  </div>

  <div class="kpi-grid" style="grid-template-columns:1fr 1fr;margin-bottom:0;">
    <div class="kpi"><div class="kpi-top"><span>Место в рейтинге</span><?= icon('medal') ?></div><b><?= $myPlace ?><span style="font-size:.9rem;font-weight:600;color:var(--muted);"> из <?= $totalVolunteers ?></span></b><a href="<?= url('cabinet/rating.php') ?>"><?= icon('arrow-right') ?>Открыть рейтинг</a></div>
    <div class="kpi"><div class="kpi-top"><span>Мероприятий посещено</span><?= icon('calendar') ?></div><b><?= $attended ?></b><a href="<?= url('cabinet/events.php') ?>"><?= icon('arrow-right') ?>Мои мероприятия</a></div>
    <div class="kpi"><div class="kpi-top"><span>Бейджей получено</span><?= icon('flag') ?></div><b><?= $badgesEarned ?></b><a href="<?= url('cabinet/badges.php') ?>"><?= icon('arrow-right') ?>Мои достижения</a></div>
    <div class="kpi"><div class="kpi-top"><span>В движении с</span><?= icon('badge-check') ?></div><b style="font-size:1.15rem;"><?= e(ruDate($me['created_at'])) ?></b></div>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-head">
      <div><h2>Записан на мероприятия</h2></div>
      <a href="<?= url('cabinet/events.php') ?>" class="btn btn-outline btn-sm">Все</a>
    </div>
    <?php if ($upcoming): ?>
      <div class="card-body card-body-flush table-wrap">
        <table class="data">
          <tbody>
            <?php foreach ($upcoming as $ev): ?>
              <tr>
                <td style="width:1%;white-space:nowrap;color:var(--muted);"><?= e(ruDate($ev['starts_at'], true)) ?></td>
                <td><a href="<?= url('event.php?id='.(int)$ev['id']) ?>"><?= e($ev['title']) ?></a><?php if ($ev['location']): ?><div style="font-size:.83rem;color:var(--muted);"><?= e($ev['location']) ?></div><?php endif; ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="empty"><b>Пока никуда не записаны</b>Выберите мероприятие из афиши ниже.</div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head"><div><h2>История начислений</h2></div></div>
    <?php if ($history): ?>
      <div class="card-body card-body-flush table-wrap">
        <table class="data">
          <tbody>
            <?php foreach ($history as $h): ?>
              <tr>
                <td class="num" style="width:1%;color:<?= (int)$h['points'] >= 0 ? 'var(--ok)' : 'var(--accent)' ?>;"><?= (int)$h['points'] > 0 ? '+' : '' ?><?= (int)$h['points'] ?></td>
                <td><?= e($h['reason']) ?><div style="font-size:.8rem;color:var(--muted);"><?= e(ruDate($h['created_at'])) ?></div></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="empty"><b>Начислений пока нет</b>Баллы появятся после первого мероприятия.</div>
    <?php endif; ?>
  </div>
</div>

<?php if ($openEvents): ?>
<div class="card">
  <div class="card-head">
    <div><h2>Открыта запись</h2><p>Мероприятия, на которые вы ещё не записаны</p></div>
    <a href="<?= url('events.php') ?>" class="btn btn-outline btn-sm">Вся афиша</a>
  </div>
  <div class="card-body card-body-flush table-wrap">
    <table class="data">
      <thead><tr><th>Дата</th><th>Мероприятие</th><th>Место</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($openEvents as $ev): ?>
          <tr>
            <td style="white-space:nowrap;"><?= e(ruDate($ev['starts_at'], true)) ?></td>
            <td><?= e($ev['title']) ?></td>
            <td style="color:var(--muted);"><?= e($ev['location'] ?: '—') ?></td>
            <td><a href="<?= url('event.php?id='.(int)$ev['id']) ?>" class="btn btn-primary btn-sm">Записаться</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/panel_footer.php'; ?>
