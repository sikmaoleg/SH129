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
$panelTitle   = 'Участники мероприятия';
$activeItem   = 'events';

// ---------------------------------------------------------------------
// Отметка участия и начисление очков
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $action = $_POST['action'] ?? '';

    if ($action === 'mark') {
        $marks   = $_POST['status']  ?? [];   // [registration_id => status]
        $hoursIn = $_POST['hours']   ?? [];
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

                $hours = round((float)($hoursIn[$regId] ?? 0), 1);
                $hours = max(0, min(24, $hours));

                // Очки начисляем один раз: при переходе в статус "участие принято"
                if ($newStatus === 'attended' && $reg['status'] !== 'attended') {
                    $pts = (int)$event['points_reward'];
                    awardPoints((int)$reg['user_id'], $pts, 'Участие: ' . $event['title'], $eventId, $hours);
                    q("UPDATE event_registrations SET status='attended', hours=?, points_awarded=? WHERE id=?",
                      [$hours, $pts, $regId]);

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
                // Снятие отметки — очки списываем обратно
                elseif ($newStatus !== 'attended' && $reg['status'] === 'attended') {
                    awardPoints((int)$reg['user_id'], -(int)$reg['points_awarded'],
                                'Отмена участия: ' . $event['title'], $eventId, -(float)$reg['hours']);
                    q('UPDATE event_registrations SET status=?, hours=0, points_awarded=0 WHERE id=?',
                      [$newStatus, $regId]);
                    $changed++;
                }
                // Прочие смены статуса без пересчёта очков
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
            ? 'Отметки сохранены. Очки начислены участникам.'
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

require __DIR__ . '/../includes/panel_header.php';
?>

<div class="card">
  <div class="card-head">
    <div>
      <h2><?= e($event['title']) ?></h2>
      <p><?= e(ruDate($event['starts_at'], true)) ?><?= $event['location'] ? ' · ' . e($event['location']) : '' ?>
         · за участие <?= (int)$event['points_reward'] ?> <?= plural((int)$event['points_reward'], 'очко', 'очка', 'очков') ?></p>
    </div>
    <div style="display:flex;gap:8px;">
      <a href="<?= url('admin/events.php?edit='.$eventId) ?>" class="btn btn-outline btn-sm">Править</a>
      <?php if ($event['status'] !== 'finished'): ?>
        <form method="post" style="margin:0;">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="finish">
          <button class="btn btn-outline btn-sm" data-confirm="Отметить мероприятие как завершённое?">Завершить</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!$isPast): ?>
    <div class="card-body" style="padding-bottom:0;">
      <div class="alert alert-info">Мероприятие ещё не состоялось. Отметить участие и начислить очки можно будет после его проведения.</div>
    </div>
  <?php endif; ?>

  <?php if ($regs): ?>
    <form method="post">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="mark">
      <div class="card-body card-body-flush table-wrap">
        <table class="data cards">
          <thead>
            <tr>
              <th>Волонтёр</th>
              <th>Телефон</th>
              <th style="width:180px;">Отметка</th>
              <th style="width:110px;">Часы</th>
              <th style="width:110px;">Начислено</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($regs as $r): ?>
              <tr>
                <td><b><?= e($r['last_name'] . ' ' . $r['first_name']) ?></b>
                    <div style="font-size:.79rem;color:var(--muted);">всего очков: <?= (int)$r['points'] ?></div></td>
                <td data-label="Телефон" style="color:var(--muted);font-size:.86rem;"><?= e($r['phone'] ?: '—') ?></td>
                <td data-label="Отметка">
                  <select name="status[<?= (int)$r['id'] ?>]">
                    <option value="registered" <?= $r['status'] === 'registered' ? 'selected' : '' ?>>записан</option>
                    <option value="attended"   <?= $r['status'] === 'attended' ? 'selected' : '' ?>>участие принято</option>
                    <option value="no_show"    <?= $r['status'] === 'no_show' ? 'selected' : '' ?>>не пришёл</option>
                    <option value="cancelled"  <?= $r['status'] === 'cancelled' ? 'selected' : '' ?>>отменено</option>
                  </select>
                </td>
                <td data-label="Часы">
                  <input type="number" name="hours[<?= (int)$r['id'] ?>]" step="0.5" min="0" max="24"
                         value="<?= rtrim(rtrim(number_format((float)$r['hours'], 1, '.', ''), '0'), '.') ?>">
                </td>
                <td class="num" data-label="Начислено" style="color:<?= (int)$r['points_awarded'] > 0 ? 'var(--ok)' : 'var(--muted)' ?>;">
                  <?= (int)$r['points_awarded'] > 0 ? '+' . (int)$r['points_awarded'] : '—' ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="card-body" style="border-top:1px solid var(--line);">
        <button type="submit" class="btn btn-primary" <?= $isPast ? '' : 'disabled' ?>>Сохранить отметки и начислить очки</button>
        <p style="font-size:.83rem;color:var(--muted);margin-top:10px;">
          Очки начисляются один раз при переводе в статус «участие принято». Если снять отметку — очки и часы вернутся обратно.
        </p>
      </div>
    </form>
  <?php else: ?>
    <div class="empty"><b>На мероприятие пока никто не записан</b>Записи появятся здесь автоматически.</div>
  <?php endif; ?>
</div>

<p><a href="<?= url('admin/events.php') ?>" class="btn btn-outline">← К списку мероприятий</a></p>

<?php require __DIR__ . '/../includes/panel_footer.php'; ?>
