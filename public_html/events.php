<?php
require_once __DIR__ . '/includes/bootstrap.php';
requireLogin();
$pageTitle = 'Мероприятия: Молодая Гвардия Щёлково';
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
$filter = $filter ?? 'upcoming';
?>
<header class="phead">
  <div class="wrap">
    <nav class="crumbs" aria-label="Навигация"><a href="<?= url('index.php') ?>">Главная</a><span>/</span><span>Мероприятия</span></nav>
    <h1 class="phead-title display">Мероприятия</h1>
    <p class="phead-lead">Афиша отделения. Записаться можно на&nbsp;странице мероприятия.</p>
  </div>
</header>

<section class="sec">
  <div class="wrap">
    <div class="toolbar-row">
      <?php if ($view === 'list'): ?>
        <div class="seg" aria-label="Период">
          <a href="<?= url('events.php') ?>" class="<?= $filter !== 'past' ? 'on' : '' ?>">Ближайшие</a>
          <a href="<?= url('events.php?filter=past') ?>" class="<?= $filter === 'past' ? 'on' : '' ?>">Прошедшие</a>
        </div>
      <?php endif; ?>
      <div class="seg" aria-label="Вид">
        <a href="<?= url('events.php') ?>" class="<?= $view === 'list' ? 'on' : '' ?>"><?= icon('list-checks') ?>Список</a>
        <a href="<?= url('events.php?view=calendar') ?>" class="<?= $view === 'calendar' ? 'on' : '' ?>"><?= icon('calendar') ?>Календарь</a>
      </div>
    </div>

    <?php if ($view === 'calendar'): ?>
      <div class="cal-nav">
        <a href="<?= url('events.php?view=calendar&month=' . $prevMonth) ?>" class="btn btn-outline btn-sm"><?= icon('arrow-left') ?>Раньше</a>
        <h2 class="display"><?= e(['', 'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'][(int)$monthStart->format('n')]) ?> <?= e($monthStart->format('Y')) ?></h2>
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
              <a href="<?= url('event.php?id=' . (int)$ev['id']) ?>" class="cal-chip <?= $ev['status'] === 'finished' ? 'is-past' : '' ?>" title="<?= e($ev['title']) ?>"><?= e($ev['title']) ?></a>
            <?php endforeach; ?>
            <?php if (count($dayEvents) > 3): ?>
              <span class="cal-more">+<?= count($dayEvents) - 3 ?> ещё</span>
            <?php endif; ?>
          </div>
        <?php endfor; ?>
      </div>
    <?php elseif ($events): ?>
      <div class="agenda">
        <?php foreach ($events as $ev): $ts = strtotime($ev['starts_at']); ?>
          <article class="ev">
            <div class="ev-date"><b><?= date('j', $ts) ?></b><span><?= mb_strtolower(RU_MONTHS_SHORT[(int)date('n', $ts)]) ?></span></div>
            <div>
              <h3><a href="<?= url('event.php?id=' . (int)$ev['id']) ?>"><?= e($ev['title']) ?></a></h3>
              <div class="ev-meta">
                <span><?= icon('clock') ?><?= date('H:i', $ts) ?></span>
                <?php if ($ev['location']): ?><span><?= icon('map-pin') ?><?= e($ev['location']) ?></span><?php endif; ?>
                <?php if ($ev['direction_title']): ?><span><?= icon('target') ?><?= e($ev['direction_title']) ?></span><?php endif; ?>
                <?php if ((int)$ev['capacity'] > 0): ?><span><?= icon('users') ?>свободно <?= max(0, (int)$ev['capacity'] - (int)$ev['taken']) ?> из <?= (int)$ev['capacity'] ?></span><?php endif; ?>
              </div>
            </div>
            <a href="<?= url('event.php?id=' . (int)$ev['id']) ?>" class="btn btn-outline btn-sm">Подробнее<?= icon('arrow-right') ?></a>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty"><b>Мероприятий нет</b><?= $filter === 'past' ? 'Здесь появится история проведённых мероприятий.' : 'Афиша пока пуста, загляни позже.' ?></div>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
