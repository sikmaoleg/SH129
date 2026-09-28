<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$me = requireAdmin();

$eventId = (int)($_GET['id'] ?? 0);
$event   = $eventId ? fetchOne(
    'SELECT e.*, d.title AS direction_title FROM events e
     LEFT JOIN directions d ON d.id = e.direction_id WHERE e.id = ?', [$eventId]
) : null;

if (!$event) {
    flash('error', 'Мероприятие не найдено.');
    redirect('admin/events.php');
}

$panelSection = 'admin';
$panelTitle   = 'Отметки участия';
$panelCrumb   = ['Мероприятия', 'admin/events.php'];
$activeItem   = 'events';

// ---------------------------------------------------------------------
// Отметка участия и начисление баллов
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $action = $_POST['action'] ?? '';

    if ($action === 'mark') {
        $marks   = $_POST['status']  ?? [];   // [registration_id => status]
        $changed = 0;

        db()->beginTransaction();
        try {
            foreach ($marks as $regId => $newStatus) {
                $regId = (int)$regId;
                if (!in_array($newStatus, ['registered','attended','no_show','cancelled'], true)) {
                    continue;
                }
                $reg = fetchOne('SELECT * FROM event_registrations WHERE id = ? AND event_id = ?', [$regId, $eventId]);
                if (!$reg) {
                    continue;
                }

                // Баллы начисляем один раз: при переходе в статус "участие принято"
                if ($newStatus === 'attended' && $reg['status'] !== 'attended') {
                    $pts = (int)$event['points_reward'];
                    awardPoints((int)$reg['user_id'], $pts, 'Участие: ' . $event['title'], $eventId);
                    q("UPDATE event_registrations SET status='attended', points_awarded=? WHERE id=?",
                      [$pts, $regId]);

                    // Первое подтверждённое участие — бейдж «Первый шаг»
                    $attendedCount = (int)fetchValue(
                        "SELECT COUNT(*) FROM event_registrations WHERE user_id=? AND status='attended'",
                        [(int)$reg['user_id']]
                    );
                    if ($attendedCount === 1) {
                        awardBadge((int)$reg['user_id'], 'first_step');
                    }
                    $changed++;
                }
                // Снятие отметки — баллы списываем обратно
                elseif ($newStatus !== 'attended' && $reg['status'] === 'attended') {
                    awardPoints((int)$reg['user_id'], -(int)$reg['points_awarded'],
                                'Отмена участия: ' . $event['title'], $eventId);
                    q('UPDATE event_registrations SET status=?, points_awarded=0 WHERE id=?',
                      [$newStatus, $regId]);
                    $changed++;
                }
                // Прочие смены статуса без пересчёта баллов
                elseif ($newStatus !== $reg['status']) {
                    q('UPDATE event_registrations SET status=? WHERE id=?', [$newStatus, $regId]);
                    $changed++;
                }
            }
            db()->commit();
        } catch (Throwable $ex) {
            db()->rollBack();
            flash('error', 'Не удалось сохранить отметки. Повторите попытку.');
            redirect('admin/attendance.php?id=' . $eventId);
        }

        logAction('attendance_mark', 'event', $eventId, 'изменено записей: ' . $changed);
        flash('success', $changed > 0
            ? 'Отметки сохранены. Баллы начислены участникам.'
            : 'Изменений не было.');
        redirect('admin/attendance.php?id=' . $eventId);
    }

    if ($action === 'finish') {
        q("UPDATE events SET status='finished' WHERE id=?", [$eventId]);
        logAction('event_finish', 'event', $eventId);
        flash('success', 'Мероприятие отмечено как завершённое.');
        redirect('admin/attendance.php?id=' . $eventId);
    }
}

$regs = fetchAll(
    "SELECT r.*, u.last_name, u.first_name, u.phone, u.points
     FROM event_registrations r JOIN users u ON u.id = r.user_id
     WHERE r.event_id = ? ORDER BY u.last_name ASC", [$eventId]
);

$isPast = strtotime($event['starts_at']) < time();

$panelLead = e($event['title']) . ' · ' . e(ruDate($event['starts_at'], true)) . ($event['location'] ? ' · ' . e($event['location']) : '');
$panelActions = '<a class="btn btn-line" href="' . url('admin/export.php?type=event&event_id=' . $eventId) . '">' . icon('download') . 'Экспорт CSV</a>'
              . '<a class="btn btn-line" href="' . url('admin/events.php?edit=' . $eventId) . '">' . icon('pencil') . 'Править</a>';
$cnt = ['attended' => 0, 'no_show' => 0, 'cancelled' => 0, 'registered' => 0];
foreach ($regs as $r) { $cnt[$r['status']]++; }
$reward = (int)$event['points_reward'];

require __DIR__ . '/../includes/panel_header.php';
?>

<?php if (!$isPast): ?>
  <div class="alert info" style="margin-bottom:16px"><?= icon('info') ?><div><b>Мероприятие ещё не состоялось</b><p>Отметить участие и начислить баллы можно будет после его проведения. Пока здесь список записавшихся.</p></div></div>
<?php endif; ?>

<section class="card">
  <?php if ($regs): ?>
    <form method="post" id="attForm" data-reward="<?= $reward ?>">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="mark">
      <div class="toolbar">
        <div class="att-stats" id="attStats">
          <span class="chip"><?= icon('users') ?>Записались: <?= count($regs) - $cnt['cancelled'] ?></span>
          <span class="chip ok"><?= icon('check') ?>Пришли: <b data-c="attended"><?= $cnt['attended'] ?></b></span>
          <span class="chip dark"><?= icon('close') ?>Не пришли: <b data-c="no_show"><?= $cnt['no_show'] ?></b></span>
          <span class="chip warn"><?= icon('hourglass') ?>Без отметки: <b data-c="registered"><?= $cnt['registered'] ?></b></span>
        </div>
        <span class="spacer"></span>
        <?php if ($isPast): ?>
          <button type="button" class="btn btn-line btn-sm" data-bulk-status="attended"><?= icon('check') ?>Все пришли</button>
          <button type="button" class="btn btn-line btn-sm" data-bulk-status="no_show">Никто не пришёл</button>
        <?php endif; ?>
      </div>
      <?php foreach ($regs as $r): $name = $r['first_name'] . ' ' . $r['last_name']; ?>
        <div class="att-row">
          <div class="who">
            <span class="ava"><?= e(mb_substr($r['first_name'], 0, 1) . mb_substr($r['last_name'], 0, 1)) ?></span>
            <div><b><?= e($name) ?></b><small><?= e($r['phone'] ?: 'телефон не указан') ?><?= (int)$r['points_awarded'] > 0 ? ' · начислено +' . (int)$r['points_awarded'] : '' ?></small></div>
          </div>
          <fieldset class="att-seg" <?= $isPast ? '' : 'disabled' ?>>
            <legend class="sr">Участие: <?= e($name) ?></legend>
            <?php foreach (['attended' => ['Пришёл', 'check'], 'no_show' => ['Не пришёл', 'close'], 'cancelled' => ['Отменил', 'undo'], 'registered' => ['Записан', 'hourglass']] as $v => [$l, $ic]): ?>
              <label class="att-opt"><input type="radio" name="status[<?= (int)$r['id'] ?>]" value="<?= $v ?>" <?= $r['status'] === $v ? 'checked' : '' ?>><span data-v="<?= $v ?>"><?= icon($ic) ?><?= $l ?></span></label>
            <?php endforeach; ?>
          </fieldset>
        </div>
      <?php endforeach; ?>
      <div class="savebar">
        <p id="attSum">Отметь, кто пришёл. Баллы начислятся при сохранении.</p>
        <?php if ($isPast && $event['status'] !== 'finished'): ?>
          <button class="btn btn-line" type="submit" form="finishForm" data-confirm="Отметить мероприятие как завершённое?">Завершить</button>
        <?php endif; ?>
        <button class="btn btn-accent" type="submit" <?= $isPast ? '' : 'disabled' ?>>Сохранить и начислить баллы</button>
      </div>
    </form>
    <p class="muted" style="font-size:13px;padding:12px 20px">Баллы начисляются один раз при отметке «Пришёл». Если отметку снять, баллы вернутся обратно.</p>
  <?php else: ?>
    <div class="empty"><?= icon('users') ?><b>Пока никто не записан</b>Записи волонтёров появятся здесь автоматически.</div>
  <?php endif; ?>
</section>
<form method="post" id="finishForm" hidden><?= csrfField() ?><input type="hidden" name="action" value="finish"></form>

<?php require __DIR__ . '/../includes/panel_footer.php'; ?>
