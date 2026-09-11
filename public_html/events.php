<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Мероприятия — Молодая Гвардия Щёлково';
$activeNav = 'events';

$view = ($_GET['view'] ?? 'list') === 'calendar' ? 'calendar' : 'list';

if ($view === 'calendar') {
    // --- Календарь: сетка на месяц ---
    $monthParam = (string)($_GET['month'] ?? date('Y-m'));
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthParam)) {
        $monthParam = date('Y-m');
    }
    $monthStart = DateTime::createFromFormat('Y-m-d', $monthParam . '-01');
    $monthStart->setTime(0, 0);
    $monthEnd   = (clone $monthStart)->modify('first day of next month');

    $prevMonth = (clone $monthStart)->modify('-1 month')->format('Y-m');
    $nextMonth = (clone $monthStart)->modify('+1 month')->format('Y-m');

    $monthEvents = fetchAll(
        "SELECT e.id, e.title, e.starts_at, e.status
         FROM events e
         WHERE e.status IN ('published','finished') AND e.starts_at >= ? AND e.starts_at < ?
         ORDER BY e.starts_at ASC",
        [$monthStart->format('Y-m-d H:i:s'), $monthEnd->format('Y-m-d H:i:s')]
    );
    $byDay = [];
    foreach ($monthEvents as $ev) {
        $day = (int)date('j', strtotime($ev['starts_at']));
        $byDay[$day][] = $ev;
    }

    // Сетка: понедельник — первый день недели
    $firstWeekday = (int)$monthStart->format('N'); // 1..7, Пн=1
    $daysInMonth  = (int)$monthStart->format('t');
    $todayKey     = date('Y-m-') . '';
    $isCurrentMonth = date('Y-m') === $monthParam;
    $todayDay     = $isCurrentMonth ? (int)date('j') : 0;
}

if ($view === 'list') {
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
}
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
        <h2><?= $view === 'calendar' ? 'Календарь мероприятий' : (($filter ?? 'upcoming') === 'past' ? 'Прошедшие' : 'Ближайшие') . ' мероприятия' ?></h2>
        <p>Записаться может волонтёр отделения из личного кабинета.</p>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <?php if ($view === 'list'): ?>
          <a href="<?= url('events.php') ?>" class="btn btn-sm <?= ($filter ?? 'upcoming') !== 'past' ? 'btn-primary' : 'btn-outline' ?>">Ближайшие</a>
          <a href="<?= url('events.php?filter=past') ?>" class="btn btn-sm <?= ($filter ?? '') === 'past' ? 'btn-primary' : 'btn-outline' ?>">Прошедшие</a>
        <?php endif; ?>
        <a href="<?= url('events.php') ?>" class="btn btn-sm <?= $view === 'list' ? 'btn-primary' : 'btn-outline' ?>"><?= icon('clipboard') ?>Список</a>
        <a href="<?= url('events.php?view=calendar') ?>" class="btn btn-sm <?= $view === 'calendar' ? 'btn-primary' : 'btn-outline' ?>"><?= icon('calendar') ?>Календарь</a>
      </div>
    </div>

    <?php if ($view === 'calendar'): ?>

      <div class="cal-nav">
        <a href="<?= url('events.php?view=calendar&month=' . $prevMonth) ?>" class="btn btn-outline btn-sm"><?= icon('arrow-right', 'icon icon-flip') ?>Раньше</a>
        <h3><?= e(mb_convert_case(RU_MONTHS[(int)$monthStart->format('n')], MB_CASE_TITLE, 'UTF-8')) ?> <?= e($monthStart->format('Y')) ?></h3>
        <a href="<?= url('events.php?view=calendar&month=' . $nextMonth) ?>" class="btn btn-outline btn-sm">Позже<?= icon('arrow-right') ?></a>
      </div>

      <div class="cal-grid">
        <?php foreach (['Пн','Вт','Ср','Чт','Пт','Сб','Вс'] as $wd): ?>
          <div class="cal-weekday"><?= $wd ?></div>
        <?php endforeach; ?>

        <?php for ($i = 1; $i < $firstWeekday; $i++): ?>
          <div class="cal-cell cal-cell-empty"></div>
        <?php endfor; ?>

        <?php for ($d = 1; $d <= $daysInMonth; $d++): $dayEvents = $byDay[$d] ?? []; $isToday = $d === $todayDay; ?>
          <div class="cal-cell <?= $isToday ? 'is-today' : '' ?> <?= $dayEvents ? 'has-events' : '' ?>">
            <span class="cal-daynum"><?= $d ?></span>
            <?php foreach (array_slice($dayEvents, 0, 3) as $ev): ?>
              <a href="<?= url('event.php?id=' . (int)$ev['id']) ?>" class="cal-chip <?= $ev['status'] === 'finished' ? 'is-past' : '' ?>">
                <?= e(mb_strimwidth($ev['title'], 0, 22, '…')) ?>
              </a>
            <?php endforeach; ?>
            <?php if (count($dayEvents) > 3): ?>
              <span class="cal-more">+<?= count($dayEvents) - 3 ?> ещё</span>
            <?php endif; ?>
          </div>
        <?php endfor; ?>
      </div>

    <?php elseif ($events): ?>
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
      <div class="empty"><b>Мероприятий нет</b><?= ($filter ?? '') === 'past' ? 'Здесь появится история проведённых мероприятий.' : 'Афиша пока пуста — загляните позже.' ?></div>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
