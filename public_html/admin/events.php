<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$me = requireAdmin();

$panelSection = 'admin';
$panelTitle   = 'Мероприятия';
$activeItem   = 'events';

$editId = (int)($_GET['edit'] ?? 0);
$edit   = $editId ? fetchOne('SELECT * FROM events WHERE id = ?', [$editId]) : null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        q('DELETE FROM events WHERE id = ?', [$id]);
        logAction('event_delete', 'event', $id);
        flash('info', 'Мероприятие удалено.');
        redirect('admin/events.php');
    }

    if ($action === 'save') {
        $id           = (int)($_POST['id'] ?? 0);
        $title        = trim((string)($_POST['title'] ?? ''));
        $description  = trim((string)($_POST['description'] ?? ''));
        $directionId  = (int)($_POST['direction_id'] ?? 0) ?: null;
        $date         = trim((string)($_POST['date'] ?? ''));
        $time         = trim((string)($_POST['time'] ?? '')) ?: '10:00';
        $location     = trim((string)($_POST['location'] ?? ''));
        $capacity     = max(0, (int)($_POST['capacity'] ?? 0));
        $pointsReward = max(0, (int)($_POST['points_reward'] ?? 10));
        $status       = $_POST['status'] ?? 'published';

        if ($title === '')  $errors[] = 'Укажите название мероприятия.';
        if ($date === '')   $errors[] = 'Укажите дату проведения.';
        if (!in_array($status, ['draft','published','finished','cancelled'], true)) $status = 'published';

        if (!$errors) {
            $startsAt = $date . ' ' . $time . ':00';
            if ($id) {
                q('UPDATE events SET title=?, description=?, direction_id=?, starts_at=?, location=?, capacity=?, points_reward=?, status=? WHERE id=?',
                  [$title, $description, $directionId, $startsAt, $location, $capacity, $pointsReward, $status, $id]);
                logAction('event_update', 'event', $id, $title);
                flash('success', 'Мероприятие обновлено.');
            } else {
                q('INSERT INTO events (title, description, direction_id, starts_at, location, capacity, points_reward, status, created_by)
                   VALUES (?,?,?,?,?,?,?,?,?)',
                  [$title, $description, $directionId, $startsAt, $location, $capacity, $pointsReward, $status, (int)$me['id']]);
                logAction('event_create', 'event', (int)db()->lastInsertId(), $title);
                flash('success', 'Мероприятие создано.');
            }
            redirect('admin/events.php');
        }
    }
}

$filter = $_GET['filter'] ?? 'upcoming';
$where  = $filter === 'past' ? 'starts_at < NOW()' : 'starts_at >= NOW()';
$order  = $filter === 'past' ? 'DESC' : 'ASC';

$events = fetchAll(
    "SELECT e.*, d.title AS direction_title,
            (SELECT COUNT(*) FROM event_registrations r WHERE r.event_id=e.id AND r.status<>'cancelled') AS taken,
            (SELECT COUNT(*) FROM event_registrations r WHERE r.event_id=e.id AND r.status='attended') AS attended
     FROM events e LEFT JOIN directions d ON d.id = e.direction_id
     WHERE $where ORDER BY e.starts_at $order LIMIT 100"
);
$directions = fetchAll('SELECT * FROM directions ORDER BY sort ASC');

$statusLabels = ['draft'=>['черновик','tag-blocked'],'published'=>['опубликовано','tag-approved'],
                 'finished'=>['завершено','tag-blue'],'cancelled'=>['отменено','tag-rejected']];

require __DIR__ . '/../includes/panel_header.php';
?>

<?php if ($errors): ?>
  <div class="alert alert-error"><ul><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="card">
  <div class="card-head"><div><h2><?= $edit ? 'Редактирование мероприятия' : 'Новое мероприятие' ?></h2></div>
    <?php if ($edit): ?><a href="<?= url('admin/events.php') ?>" class="btn btn-outline btn-sm">Отменить правку</a><?php endif; ?>
  </div>
  <div class="card-body">
    <form method="post">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">

      <div class="field"><label for="title">Название</label>
        <input type="text" id="title" name="title" value="<?= e($edit['title'] ?? '') ?>" required></div>

      <div class="field-row-3">
        <div class="field"><label for="date">Дата</label>
          <input type="date" id="date" name="date" value="<?= e($edit ? date('Y-m-d', strtotime($edit['starts_at'])) : '') ?>" required></div>
        <div class="field"><label for="time">Время</label>
          <input type="time" id="time" name="time" value="<?= e($edit ? date('H:i', strtotime($edit['starts_at'])) : '10:00') ?>"></div>
        <div class="field"><label for="direction_id">Направление</label>
          <select id="direction_id" name="direction_id">
            <option value="">— не выбрано —</option>
            <?php foreach ($directions as $d): ?>
              <option value="<?= (int)$d['id'] ?>" <?= (int)($edit['direction_id'] ?? 0) === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['title']) ?></option>
            <?php endforeach; ?>
          </select></div>
      </div>

      <div class="field"><label for="location">Место проведения</label>
        <input type="text" id="location" name="location" value="<?= e($edit['location'] ?? '') ?>" placeholder="Например: городской парк, ул. Центральная"></div>

      <div class="field-row-3">
        <div class="field"><label for="capacity">Количество мест</label>
          <input type="number" id="capacity" name="capacity" min="0" value="<?= (int)($edit['capacity'] ?? 0) ?>">
          <div class="hint">0 — без ограничения.</div></div>
        <div class="field"><label for="points_reward">Очки за участие</label>
          <input type="number" id="points_reward" name="points_reward" min="0" value="<?= (int)($edit['points_reward'] ?? 10) ?>"></div>
        <div class="field"><label for="status">Статус</label>
          <select id="status" name="status">
            <?php foreach ($statusLabels as $k => [$lbl, $_]): ?>
              <option value="<?= $k ?>" <?= ($edit['status'] ?? 'published') === $k ? 'selected' : '' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
          </select></div>
      </div>

      <div class="field"><label for="description">Описание</label>
        <textarea id="description" name="description" placeholder="Что будем делать, что взять с собой, как добраться"><?= e($edit['description'] ?? '') ?></textarea></div>

      <button type="submit" class="btn btn-primary"><?= $edit ? 'Сохранить изменения' : 'Создать мероприятие' ?></button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <div><h2><?= $filter === 'past' ? 'Прошедшие' : 'Предстоящие' ?> мероприятия</h2>
      <p><?= $filter === 'past' ? 'Отметьте участие, чтобы начислить очки волонтёрам.' : 'Открыта запись волонтёров.' ?></p></div>
    <div style="display:flex;gap:8px;">
      <a href="<?= url('admin/events.php') ?>" class="btn btn-sm <?= $filter !== 'past' ? 'btn-primary' : 'btn-outline' ?>">Предстоящие</a>
      <a href="<?= url('admin/events.php?filter=past') ?>" class="btn btn-sm <?= $filter === 'past' ? 'btn-primary' : 'btn-outline' ?>">Прошедшие</a>
    </div>
  </div>

  <?php if ($events): ?>
    <div class="card-body card-body-flush table-wrap">
      <table class="data">
        <thead><tr><th>Дата</th><th>Мероприятие</th><th>Записей</th><th>Отмечено</th><th>Статус</th><th>Действия</th></tr></thead>
        <tbody>
          <?php foreach ($events as $ev): [$lbl, $cls] = $statusLabels[$ev['status']]; ?>
            <tr>
              <td style="white-space:nowrap;"><?= e(ruDate($ev['starts_at'], true)) ?></td>
              <td><b><?= e($ev['title']) ?></b>
                <div style="font-size:.8rem;color:var(--muted);"><?= e($ev['direction_title'] ?: 'без направления') ?><?= $ev['location'] ? ' · ' . e($ev['location']) : '' ?></div></td>
              <td class="num"><?= (int)$ev['taken'] ?><?= (int)$ev['capacity'] > 0 ? ' / '.(int)$ev['capacity'] : '' ?></td>
              <td class="num"><?= (int)$ev['attended'] ?></td>
              <td><span class="tag <?= $cls ?>"><?= $lbl ?></span></td>
              <td>
                <div class="actions">
                  <a href="<?= url('admin/attendance.php?id='.(int)$ev['id']) ?>" class="btn btn-primary btn-sm">Участники</a>
                  <a href="<?= url('admin/events.php?edit='.(int)$ev['id']) ?>" class="btn btn-outline btn-sm">Править</a>
                  <form method="post" style="margin:0;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$ev['id'] ?>">
                    <button class="btn btn-outline btn-sm" data-confirm="Удалить мероприятие вместе со всеми записями?">Удалить</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="empty"><b>Мероприятий нет</b>Создайте первое в форме выше.</div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/panel_footer.php'; ?>
