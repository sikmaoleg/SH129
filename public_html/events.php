<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Мероприятия — Молодая Гвардия Щёлково';
$activeNav = 'events';

$filter = $_GET['filter'] ?? 'upcoming';
$where  = $filter === 'past'
    ? "e.status IN ('published','finished') AND e.starts_at < NOW()"
    : "e.status = 'published' AND e.starts_at >= NOW()";
$order  = $filter === 'past' ? 'DESC' : 'ASC';

$events = fetchAll(
    "SELECT e.*, d.title AS direction_title,
            (SELECT COUNT(*) FROM event_registrations r WHERE r.event_id = e.id AND r.status <> 'cancelled') AS taken
     FROM events e
     LEFT JOIN directions d ON d.id = e.direction_id
     WHERE $where
     ORDER BY e.starts_at $order LIMIT 60"
);
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div class="container">
    <div class="breadcrumbs"><a href="<?= url('index.php') ?>">Главная</a> / Мероприятия</div>
    <h1>Мероприятия отделения</h1>
  </div>
</div>

<section class="section">
  <div class="container">
    <div class="section-head">
      <div>
        <h2><?= $filter === 'past' ? 'Прошедшие' : 'Ближайшие' ?> мероприятия</h2>
        <p>Записаться может волонтёр отделения из личного кабинета.</p>
      </div>
      <div style="display:flex;gap:10px;">
        <a href="<?= url('events.php') ?>" class="btn btn-sm <?= $filter !== 'past' ? 'btn-primary' : 'btn-outline' ?>">Ближайшие</a>
        <a href="<?= url('events.php?filter=past') ?>" class="btn btn-sm <?= $filter === 'past' ? 'btn-primary' : 'btn-outline' ?>">Прошедшие</a>
      </div>
    </div>

    <?php if ($events): ?>
      <div class="event-list">
        <?php foreach ($events as $ev): $ts = strtotime($ev['starts_at']); ?>
          <div class="event-row">
            <div class="event-date">
              <b><?= date('j', $ts) ?></b>
              <span><?= RU_MONTHS_SHORT[(int)date('n', $ts)] ?></span>
            </div>
            <div class="event-info">
              <h3><?= e($ev['title']) ?></h3>
              <div class="event-meta">
                <span><?= icon('clock') ?><?= date('H:i', $ts) ?></span>
                <?php if ($ev['location']): ?><span><?= icon('map-pin') ?><?= e($ev['location']) ?></span><?php endif; ?>
                <?php if ($ev['direction_title']): ?><span><?= icon('target') ?><?= e($ev['direction_title']) ?></span><?php endif; ?>
                <?php if ((int)$ev['capacity'] > 0): ?><span><?= icon('users') ?><?= max(0, (int)$ev['capacity'] - (int)$ev['taken']) ?> из <?= (int)$ev['capacity'] ?></span><?php endif; ?>
              </div>
            </div>
            <div class="event-actions">
              <a href="<?= url('event.php?id='.(int)$ev['id']) ?>" class="btn btn-outline btn-sm">Подробнее</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty"><b>Мероприятий нет</b><?= $filter === 'past' ? 'Здесь появится история проведённых мероприятий.' : 'Афиша пока пуста — загляните позже.' ?></div>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
