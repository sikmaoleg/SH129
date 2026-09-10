<?php
require_once __DIR__ . '/includes/bootstrap.php';
$id = (int)($_GET['id'] ?? 0);
$ev = fetchOne(
    "SELECT e.*, d.title AS direction_title,
            (SELECT COUNT(*) FROM event_registrations r WHERE r.event_id = e.id AND r.status <> 'cancelled') AS taken
     FROM events e LEFT JOIN directions d ON d.id = e.direction_id
     WHERE e.id = ? AND e.status IN ('published','finished')", [$id]
);
if (!$ev) {
    http_response_code(404);
    $pageTitle = 'Мероприятие не найдено';
    $activeNav = 'events';
    require __DIR__ . '/includes/header.php';
    echo '<section class="section"><div class="container"><div class="empty"><b>Мероприятие не найдено</b>Возможно, оно отменено или ссылка устарела.</div></div></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$me         = currentUser();
$myReg      = null;
if ($me) {
    $myReg = fetchOne('SELECT * FROM event_registrations WHERE event_id = ? AND user_id = ?', [$id, (int)$me['id']]);
}
$isPast     = strtotime($ev['starts_at']) < time();
$freeSlots  = (int)$ev['capacity'] > 0 ? max(0, (int)$ev['capacity'] - (int)$ev['taken']) : null;

// --- Запись / отмена ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $me = requireLogin();
    $action = $_POST['action'] ?? '';

    if ($action === 'signup') {
        if ($isPast) {
            flash('error', 'Мероприятие уже прошло.');
        } elseif ($freeSlots !== null && $freeSlots <= 0 && (!$myReg || $myReg['status'] === 'cancelled')) {
            flash('error', 'Свободных мест не осталось.');
        } else {
            q("INSERT INTO event_registrations (event_id, user_id, status) VALUES (?,?,'registered')
               ON DUPLICATE KEY UPDATE status = 'registered'", [$id, (int)$me['id']]);
            logAction('event_signup', 'event', $id);
            flash('success', 'Вы записаны на мероприятие. Координатор свяжется с вами при необходимости.');
        }
    } elseif ($action === 'cancel') {
        q("UPDATE event_registrations SET status='cancelled' WHERE event_id = ? AND user_id = ?", [$id, (int)$me['id']]);
        logAction('event_cancel', 'event', $id);
        flash('info', 'Запись отменена.');
    }
    redirect('event.php?id=' . $id);
}

$pageTitle = $ev['title'] . ' — Молодая Гвардия Щёлково';
$activeNav = 'events';
$ts = strtotime($ev['starts_at']);
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div class="container">
    <div class="breadcrumbs"><a href="<?= url('index.php') ?>">Главная</a> / <a href="<?= url('events.php') ?>">Мероприятия</a></div>
    <h1><?= e($ev['title']) ?></h1>
  </div>
</div>

<section class="section">
  <div class="container">
    <div class="article">
      <div class="event-row" style="margin-bottom:28px;">
        <div class="event-date">
          <b><?= date('j', $ts) ?></b>
          <span><?= RU_MONTHS_SHORT[(int)date('n', $ts)] ?></span>
        </div>
        <div class="event-info">
          <div class="event-meta">
            <span><?= icon('calendar') ?><?= e(ruDate($ev['starts_at'], true)) ?></span>
            <?php if ($ev['location']): ?><span><?= icon('map-pin') ?><?= e($ev['location']) ?></span><?php endif; ?>
            <?php if ($ev['direction_title']): ?><span><?= icon('target') ?><?= e($ev['direction_title']) ?></span><?php endif; ?>
            <?php if ($freeSlots !== null): ?><span><?= icon('users') ?><?= $freeSlots ?> из <?= (int)$ev['capacity'] ?></span><?php endif; ?>
          </div>
        </div>
      </div>

      <?php if ($ev['cover']): ?><img src="<?= url('uploads/events/'.$ev['cover']) ?>" alt=""><?php endif; ?>

      <?php foreach (preg_split("/\R{2,}/", (string)$ev['description']) as $para): ?>
        <?php if (trim($para) !== ''): ?><p><?= nl2br(e(trim($para))) ?></p><?php endif; ?>
      <?php endforeach; ?>

      <div style="margin-top:32px;">
        <?php if ($isPast): ?>
          <div class="alert alert-info">Мероприятие уже прошло.</div>
        <?php elseif (!$me): ?>
          <div class="alert alert-info">Записаться на мероприятие могут волонтёры отделения. <a href="<?= url('login.php') ?>">Войдите</a> или <a href="<?= url('register.php') ?>">подайте заявку</a>.</div>
        <?php elseif ($myReg && $myReg['status'] === 'registered'): ?>
          <div class="alert alert-success">Вы записаны на это мероприятие.</div>
          <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="cancel">
            <button type="submit" class="btn btn-outline">Отменить запись</button>
          </form>
        <?php elseif ($freeSlots !== null && $freeSlots <= 0): ?>
          <div class="alert alert-warn">Свободных мест не осталось.</div>
        <?php else: ?>
          <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="signup">
            <button type="submit" class="btn btn-accent">Записаться на мероприятие</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
