<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();

$panelSection = 'admin';
$panelTitle   = 'Обзор';
$activeItem   = 'index';

$pending    = (int)fetchValue("SELECT COUNT(*) FROM users WHERE status='pending'");
$approved   = (int)fetchValue("SELECT COUNT(*) FROM users WHERE status='approved' AND role='volunteer'");
$upcoming   = (int)fetchValue("SELECT COUNT(*) FROM events WHERE status='published' AND starts_at >= NOW()");
$needReview = (int)fetchValue("SELECT COUNT(*) FROM events WHERE status='published' AND starts_at < NOW()");

$latestApps = fetchAll(
    "SELECT id, last_name, first_name, email, phone, created_at
     , birth_date, school FROM users WHERE status='pending' ORDER BY created_at ASC LIMIT 5"
);

$soon = fetchAll(
    "SELECT e.id, e.title, e.starts_at, e.location,
            (SELECT COUNT(*) FROM event_registrations r WHERE r.event_id=e.id AND r.status<>'cancelled') AS taken,
            e.capacity
     FROM events e WHERE e.status='published' AND e.starts_at >= NOW()
     ORDER BY e.starts_at ASC LIMIT 5"
);

$recent = fetchAll(
    "SELECT a.action, a.entity, a.entity_id, a.meta, a.created_at, u.last_name, u.first_name
     FROM audit_log a LEFT JOIN users u ON u.id = a.user_id
     ORDER BY a.created_at DESC LIMIT 8"
);

$firstReview = $needReview > 0 ? fetchOne("SELECT id, title, starts_at FROM events WHERE status='published' AND starts_at < NOW() ORDER BY starts_at ASC LIMIT 1") : null;
$newThisMonth = (int)fetchValue("SELECT COUNT(*) FROM users WHERE status='approved' AND role='volunteer' AND approved_at >= DATE_FORMAT(NOW(), '%Y-%m-01')");
$wd = ['вс', 'пн', 'вт', 'ср', 'чт', 'пт', 'сб'];
$weekdays  = ['Воскресенье', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота'];
$panelLead = e($weekdays[(int)date('w')] . ', ' . ruDate(date('Y-m-d'))) . '. Вот что ждёт решения.';

require __DIR__ . '/../includes/panel_header.php';
?>

<div class="kpis">
  <a class="kpi <?= $pending > 0 ? 'attn' : '' ?>" href="<?= url('admin/applications.php') ?>">
    <div class="kpi-l"><span>Заявки на рассмотрении</span><?= icon('user-plus') ?></div>
    <div class="kpi-v"><?= $pending ?></div>
    <div class="kpi-d"><?= $pending ? 'нажми, чтобы разобрать' : 'все заявки разобраны' ?></div>
  </a>
  <a class="kpi" href="<?= url('admin/users.php') ?>">
    <div class="kpi-l"><span>Волонтёров в отделении</span><?= icon('users') ?></div>
    <div class="kpi-v"><?= $approved ?></div>
    <div class="kpi-d"><?php if ($newThisMonth): ?><span class="up">+<?= $newThisMonth ?></span> за этот месяц<?php else: ?>одобренные учётные записи<?php endif; ?></div>
  </a>
  <a class="kpi" href="<?= url('admin/events.php') ?>">
    <div class="kpi-l"><span>Предстоящих мероприятий</span><?= icon('calendar-check') ?></div>
    <div class="kpi-v"><?= $upcoming ?></div>
    <div class="kpi-d"><?= $soon ? 'ближайшее ' . e(ruDate($soon[0]['starts_at'])) : 'афиша пуста' ?></div>
  </a>
  <a class="kpi <?= $needReview > 0 ? 'attn' : '' ?>" href="<?= url($firstReview ? 'admin/attendance.php?id=' . (int)$firstReview['id'] : 'admin/events.php?filter=past') ?>">
    <div class="kpi-l"><span>Ждут отметки участия</span><?= icon('list-checks') ?></div>
    <div class="kpi-v"><?= $needReview ?></div>
    <div class="kpi-d"><?= $firstReview ? '«' . e(mb_strimwidth($firstReview['title'], 0, 34, '…')) . '»' : 'всё отмечено' ?></div>
  </a>
</div>

<div class="grid g-main">
  <section class="card">
    <div class="card-h"><div><h2>Ближайшие мероприятия</h2><p>Кто записался и сколько осталось мест</p></div><a class="btn btn-line btn-sm" href="<?= url('admin/events.php') ?>">Все</a></div>
    <?php if ($soon): ?>
      <ul class="list">
        <?php foreach ($soon as $ev): $ts = strtotime($ev['starts_at']); $cap = (int)$ev['capacity']; $taken = (int)$ev['taken']; ?>
          <li>
            <div class="ev-date" style="border:0;padding:0;min-width:48px"><b style="font-size:34px"><?= date('j', $ts) ?></b><span><?= mb_strtolower(RU_MONTHS_SHORT[(int)date('n', $ts)]) ?>, <?= $wd[(int)date('w', $ts)] ?></span></div>
            <div class="grow"><a href="<?= url('admin/attendance.php?id=' . (int)$ev['id']) ?>"><b><?= e($ev['title']) ?></b></a><small><?= date('H:i', $ts) ?><?= $ev['location'] ? ' · ' . e($ev['location']) : '' ?></small></div>
            <?php if ($cap > 0): ?>
              <div class="meter <?= $taken >= $cap ? 'full' : '' ?>"><div class="meter-t"><span><?= $taken >= $cap ? 'Мест нет' : 'Записались' ?></span><b class="num"><?= $taken ?> из <?= $cap ?></b></div><div class="meter-bar"><i style="width:<?= min(100, (int)round($taken / $cap * 100)) ?>%"></i></div></div>
            <?php else: ?>
              <span class="tag"><?= icon('users') ?><?= $taken ?> <?= plural($taken, 'запись', 'записи', 'записей') ?></span>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <div class="empty"><?= icon('calendar-check') ?><b>Афиша пуста</b>Создай первое мероприятие.<a class="btn btn-accent btn-sm" href="<?= url('admin/events.php?new=1') ?>"><?= icon('plus') ?>Новое мероприятие</a></div>
    <?php endif; ?>
  </section>

  <section class="card">
    <div class="card-h"><div><h2>Новые заявки</h2><p>Ожидают одобрения</p></div><a class="btn btn-line btn-sm" href="<?= url('admin/applications.php') ?>">Разобрать</a></div>
    <?php if ($latestApps): ?>
      <ul class="list">
        <?php foreach ($latestApps as $a): $age = $a['birth_date'] ? (int)(new DateTime($a['birth_date']))->diff(new DateTime())->y : null; ?>
          <li>
            <span class="ava"><?= e(mb_substr($a['first_name'], 0, 1) . mb_substr($a['last_name'], 0, 1)) ?></span>
            <div class="grow"><a href="<?= url('admin/applications.php?id=' . (int)$a['id']) ?>"><b><?= e($a['first_name'] . ' ' . $a['last_name']) ?></b></a><small><?= $age !== null ? $age . ' ' . plural($age, 'год', 'года', 'лет') : '' ?><?= $a['school'] ? ', ' . e($a['school']) : '' ?></small></div>
            <small style="white-space:nowrap"><?= e(ruDate($a['created_at'])) ?></small>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <div class="empty"><?= icon('check-circle') ?><b>Новых заявок нет</b>Все анкеты разобраны.</div>
    <?php endif; ?>
  </section>
</div>

<section class="card" style="margin-top:18px">
  <div class="card-h"><div><h2>Последние действия</h2><p>Кто и что менял в отделении</p></div><?php if (isDev()): ?><a class="btn btn-line btn-sm" href="<?= url('dev/logs.php') ?>">Весь журнал</a><?php endif; ?></div>
  <?php if ($recent): ?>
    <ul class="feed">
      <?php foreach ($recent as $r): [$label, $ic, $kind] = actionLabel($r['action']); $who = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')); ?>
        <li><span class="dot <?= e($kind) ?>"><?= icon($ic) ?></span><p><b><?= e($who !== '' ? $who : 'Система') ?></b> <span><?= e(mb_strtolower(mb_substr($label, 0, 1)) . mb_substr($label, 1)) ?></span></p><time><?= e(ruDate($r['created_at'], true)) ?></time></li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <div class="empty"><b>Записей пока нет</b>Здесь появятся действия администраторов и волонтёров.</div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/panel_footer.php'; ?>
