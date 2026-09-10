<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$me = requireLogin();

$panelSection = 'cabinet';
$panelTitle   = 'Мои мероприятия';
$activeItem   = 'events';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $eventId = (int)($_POST['event_id'] ?? 0);
    if ($_POST['action'] === 'cancel' && $eventId) {
        $ev = fetchOne('SELECT starts_at FROM events WHERE id = ?', [$eventId]);
        if ($ev && strtotime($ev['starts_at']) > time()) {
            q("UPDATE event_registrations SET status='cancelled' WHERE event_id=? AND user_id=? AND status='registered'",
              [$eventId, (int)$me['id']]);
            logAction('event_cancel', 'event', $eventId);
            flash('info', 'Запись отменена.');
        } else {
            flash('error', 'Мероприятие уже прошло — отменить запись нельзя.');
        }
    }
    redirect('cabinet/events.php');
}

$rows = fetchAll(
    "SELECT r.status, r.hours, r.points_awarded, r.comment,
            e.id, e.title, e.starts_at, e.location, d.title AS direction_title
     FROM event_registrations r
     JOIN events e ON e.id = r.event_id
     LEFT JOIN directions d ON d.id = e.direction_id
     WHERE r.user_id = ?
     ORDER BY e.starts_at DESC", [(int)$me['id']]
);

$upcoming = array_filter($rows, fn($r) => strtotime($r['starts_at']) >= time() && $r['status'] === 'registered');
$past     = array_filter($rows, fn($r) => strtotime($r['starts_at']) < time() || $r['status'] !== 'registered');

$statusLabels = [
    'registered' => ['Записан',        'tag-blue'],
    'attended'   => ['Участие принято','tag-approved'],
    'no_show'    => ['Не пришёл',      'tag-rejected'],
    'cancelled'  => ['Отменено',       'tag-blocked'],
];

require __DIR__ . '/../includes/panel_header.php';
?>

<div class="card">
  <div class="card-head">
    <div><h2>Предстоящие</h2><p>Мероприятия, на которые вы записаны.</p></div>
    <a href="<?= url('events.php') ?>" class="btn btn-primary btn-sm">Записаться ещё</a>
  </div>
  <?php if ($upcoming): ?>
    <div class="card-body card-body-flush table-wrap">
      <table class="data">
        <thead><tr><th>Дата</th><th>Мероприятие</th><th>Место</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($upcoming as $r): ?>
            <tr>
              <td style="white-space:nowrap;"><?= e(ruDate($r['starts_at'], true)) ?></td>
              <td><a href="<?= url('event.php?id='.(int)$r['id']) ?>"><?= e($r['title']) ?></a>
                  <?php if ($r['direction_title']): ?><div style="font-size:.8rem;color:var(--muted);"><?= e($r['direction_title']) ?></div><?php endif; ?></td>
              <td style="color:var(--muted);"><?= e($r['location'] ?: '—') ?></td>
              <td>
                <form method="post" style="margin:0;">
                  <?= csrfField() ?>
                  <input type="hidden" name="event_id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="action" value="cancel">
                  <button type="submit" class="btn btn-outline btn-sm" data-confirm="Отменить запись на это мероприятие?">Отменить</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="empty"><b>Вы никуда не записаны</b>Загляните в афишу и выберите ближайшее мероприятие.</div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-head"><div><h2>История участия</h2><p>Прошедшие и отменённые мероприятия.</p></div></div>
  <?php if ($past): ?>
    <div class="card-body card-body-flush table-wrap">
      <table class="data">
        <thead><tr><th>Дата</th><th>Мероприятие</th><th>Статус</th><th>Часы</th><th>Очки</th></tr></thead>
        <tbody>
          <?php foreach ($past as $r): [$label, $cls] = $statusLabels[$r['status']]; ?>
            <tr>
              <td style="white-space:nowrap;"><?= e(ruDate($r['starts_at'])) ?></td>
              <td><?= e($r['title']) ?>
                  <?php if ($r['comment']): ?><div style="font-size:.8rem;color:var(--muted);"><?= e($r['comment']) ?></div><?php endif; ?></td>
              <td><span class="tag <?= $cls ?>"><?= $label ?></span></td>
              <td class="num"><?= $r['hours'] > 0 ? rtrim(rtrim(number_format((float)$r['hours'], 1, ',', ''), '0'), ',') : '—' ?></td>
              <td class="num" style="color:var(--ok);"><?= (int)$r['points_awarded'] > 0 ? '+'.(int)$r['points_awarded'] : '—' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="empty"><b>История пуста</b>Здесь появятся мероприятия, в которых вы участвовали.</div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/panel_footer.php'; ?>
