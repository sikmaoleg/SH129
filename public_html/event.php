<?php
require_once __DIR__ . '/includes/bootstrap.php';
requireLogin();
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
    echo '<section class="phead"><div class="wrap"><h1 class="phead-title display">Не найдено</h1>'
       . '<p class="phead-lead">Возможно, мероприятие отменено или ссылка устарела.</p>'
       . '<div class="row-ctas"><a class="btn btn-accent btn-lg" href="' . url('events.php') . '">Все мероприятия' . icon('arrow-right') . '</a></div></div></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$me         = currentUser();
$myReg      = fetchOne('SELECT * FROM event_registrations WHERE event_id = ? AND user_id = ?', [$id, (int)$me['id']]);
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
            flash('success', 'Вы записаны на мероприятие. Администратор свяжется с вами при необходимости.');
        }
    } elseif ($action === 'cancel') {
        q("UPDATE event_registrations SET status='cancelled' WHERE event_id = ? AND user_id = ?", [$id, (int)$me['id']]);
        logAction('event_cancel', 'event', $id);
        flash('info', 'Запись отменена.');
    }
    redirect('event.php?id=' . $id);
}

$pageTitle = $ev['title'] . ': Молодая Гвардия Щёлково';
$activeNav = 'events';
$ts = strtotime($ev['starts_at']);
require __DIR__ . '/includes/header.php';
?>
<article class="art">
  <div class="wrap">
    <header class="art-head">
      <nav class="crumbs" aria-label="Навигация"><a href="<?= url('index.php') ?>">Главная</a><span>/</span><a href="<?= url('events.php') ?>">Мероприятия</a></nav>
      <h1 class="art-title display"><?= e($ev['title']) ?></h1>
    </header>
    <dl class="ev-facts" style="margin-top:clamp(28px,3vw,44px)">
      <div><dt>Когда</dt><dd><?= e(ruDate($ev['starts_at'], true)) ?></dd></div>
      <?php if ($ev['location']): ?><div><dt>Где</dt><dd><?= e($ev['location']) ?></dd></div><?php endif; ?>
      <?php if ($ev['direction_title']): ?><div><dt>Направление</dt><dd><?= e($ev['direction_title']) ?></dd></div><?php endif; ?>
      <?php if ($freeSlots !== null): ?><div><dt>Свободных мест</dt><dd><?= $freeSlots ?> из <?= (int)$ev['capacity'] ?></dd></div><?php endif; ?>
    </dl>
    <?php if ($ev['cover']): ?><img class="ev-cover" src="<?= url('uploads/events/' . $ev['cover']) ?>" alt=""><?php endif; ?>
    <div class="art-body">
      <?php foreach (preg_split("/\R{2,}/", (string)$ev['description']) as $para): ?>
        <?php if (trim($para) !== ''): ?><p><?= nl2br(e(trim($para))) ?></p><?php endif; ?>
      <?php endforeach; ?>

      <div class="ev-actions">
        <?php if ($isPast): ?>
          <div class="alert alert-info">Мероприятие уже прошло.</div>
        <?php elseif ($myReg && $myReg['status'] === 'registered'): ?>
          <div class="alert alert-success">Ты записан на&nbsp;это мероприятие.</div>
          <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="cancel">
            <button type="submit" class="btn btn-outline" data-confirm="Отменить запись на мероприятие?">Отменить запись</button>
          </form>
        <?php elseif ($freeSlots !== null && $freeSlots <= 0): ?>
          <div class="alert alert-warn">Свободных мест не осталось.</div>
        <?php else: ?>
          <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="signup">
            <button type="submit" class="btn btn-accent btn-lg">Записаться<?= icon('arrow-right') ?></button>
          </form>
        <?php endif; ?>
        <?php if (!$isPast): ?>
          <a href="<?= url('event-ics.php?id=' . $id) ?>" class="link-arrow"><?= icon('calendar-plus') ?>Добавить в&nbsp;календарь</a>
        <?php endif; ?>
      </div>
      <?= shareButtons(eventLink($id), $ev['title']) ?>
    </div>
  </div>
</article>
<?php require __DIR__ . '/includes/footer.php'; ?>
