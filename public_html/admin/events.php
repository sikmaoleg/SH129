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
                // Повтор доступен только при создании — набор новых мероприятий по одному шаблону
                $repeatOn    = !empty($_POST['repeat_enable']);
                $repeatFreq  = $_POST['repeat_freq'] ?? 'weekly';
                $repeatCount = $repeatOn ? max(1, min(26, (int)($_POST['repeat_count'] ?? 1))) : 1;
                $interval    = match ($repeatFreq) {
                    'biweekly' => '+2 weeks',
                    'monthly'  => '+1 month',
                    default    => '+1 week',
                };

                $created = 0;
                $ts = new DateTime($startsAt);
                for ($i = 0; $i < $repeatCount; $i++) {
                    q('INSERT INTO events (title, description, direction_id, starts_at, location, capacity, points_reward, status, created_by)
                       VALUES (?,?,?,?,?,?,?,?,?)',
                      [$title, $description, $directionId, $ts->format('Y-m-d H:i:s'), $location, $capacity, $pointsReward, $status, (int)$me['id']]);
                    $created++;
                    if ($i === 0) {
                        logAction('event_create', 'event', (int)db()->lastInsertId(), $title);
                    }
                    $ts = (clone $ts)->modify($interval);
                }
                flash('success', $created > 1
                    ? "Создана серия: {$created} " . plural($created, 'мероприятие', 'мероприятия', 'мероприятий') . ', ' . match ($repeatFreq) { 'biweekly' => 'раз в 2 недели', 'monthly' => 'каждый месяц', default => 'каждую неделю' } . '.'
                    : 'Мероприятие создано.');
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

$drawerOpen = $edit || $errors || isset($_GET['new']);
$needMarks = fetchAll("SELECT id, title, starts_at FROM events WHERE status='published' AND starts_at < NOW() ORDER BY starts_at ASC LIMIT 3");
$wd = ['вс', 'пн', 'вт', 'ср', 'чт', 'пт', 'сб'];
$panelLead = 'Афиша видна только волонтёрам отделения, в личном кабинете.';
$panelActions = '<button class="btn btn-accent" type="button" data-drawer-open="eventDrawer">' . icon('plus') . 'Новое мероприятие</button>';

require __DIR__ . '/../includes/panel_header.php';
?>

<?php foreach ($needMarks as $nm): ?>
  <div class="alert warn" style="margin-bottom:12px"><?= icon('alert') ?><div><b>Отметь участие в&nbsp;«<?= e($nm['title']) ?>»</b><p>Прошло <?= e(ruDate($nm['starts_at'])) ?>. Баллы начислятся после сохранения отметок.</p></div><a class="btn btn-accent btn-sm" href="<?= url('admin/attendance.php?id=' . (int)$nm['id']) ?>">Отметить</a></div>
<?php endforeach; ?>

<div class="row" style="margin:6px 0 14px">
  <div class="seg" role="group" aria-label="Период">
    <a href="<?= url('admin/events.php') ?>" class="<?= $filter !== 'past' ? 'on' : '' ?>">Предстоящие</a>
    <a href="<?= url('admin/events.php?filter=past') ?>" class="<?= $filter === 'past' ? 'on' : '' ?>">Прошедшие</a>
  </div>
</div>

<?php if ($events): ?>
  <div class="agenda">
    <?php foreach ($events as $ev):
      $ts = strtotime($ev['starts_at']);
      $isPast = $ts < time();
      $attn = $ev['status'] === 'published' && $isPast;
      $cap = (int)$ev['capacity']; $taken = (int)$ev['taken']; ?>
      <article class="ev <?= $attn ? 'attn' : '' ?>">
        <div class="ev-date"><b><?= date('j', $ts) ?></b><span><?= mb_strtolower(RU_MONTHS_SHORT[(int)date('n', $ts)]) ?>, <?= $wd[(int)date('w', $ts)] ?></span></div>
        <div>
          <h3><?= e($ev['title']) ?></h3>
          <div class="ev-meta">
            <span><?= icon('clock') ?><?= date('H:i', $ts) ?></span>
            <?php if ($ev['location']): ?><span><?= icon('map-pin') ?><?= e($ev['location']) ?></span><?php endif; ?>
            <span><?= icon('star') ?>+<?= (int)$ev['points_reward'] ?> за участие</span>
          </div>
          <div class="row" style="margin-top:10px;flex-wrap:wrap;gap:8px">
            <?php if ($ev['status'] === 'finished'): ?><span class="chip ok"><?= icon('check-circle') ?>Завершено</span>
            <?php elseif ($ev['status'] === 'draft'): ?><span class="chip"><?= icon('pencil') ?>Черновик</span>
            <?php elseif ($ev['status'] === 'cancelled'): ?><span class="chip"><?= icon('prohibit') ?>Отменено</span>
            <?php elseif ($attn): ?><span class="chip warn"><?= icon('alert') ?>Ждёт отметки</span>
            <?php else: ?><span class="chip info"><?= icon('calendar-check') ?>Опубликовано</span><?php endif; ?>
            <?php if ($ev['direction_title']): ?><span class="tag"><?= e($ev['direction_title']) ?></span><?php endif; ?>
          </div>
        </div>
        <?php if ($isPast && (int)$ev['attended'] > 0): ?>
          <div class="meter"><div class="meter-t"><span>Пришли</span><b class="num"><?= (int)$ev['attended'] ?> из <?= $taken ?></b></div><div class="meter-bar"><i style="width:<?= $taken ? min(100, (int)round($ev['attended'] / $taken * 100)) : 0 ?>%"></i></div></div>
        <?php elseif ($cap > 0): ?>
          <div class="meter <?= $taken >= $cap ? 'full' : '' ?>"><div class="meter-t"><span><?= $taken >= $cap ? 'Мест нет' : 'Записались' ?></span><b class="num"><?= $taken ?> из <?= $cap ?></b></div><div class="meter-bar"><i style="width:<?= min(100, (int)round($taken / $cap * 100)) ?>%"></i></div></div>
        <?php else: ?>
          <span class="tag"><?= icon('users') ?><?= $taken ?> <?= plural($taken, 'запись', 'записи', 'записей') ?></span>
        <?php endif; ?>
        <div class="ev-act">
          <a class="btn <?= $attn ? 'btn-accent' : 'btn-line' ?> btn-sm" href="<?= url('admin/attendance.php?id=' . (int)$ev['id']) ?>"><?= icon('users') ?>Участники</a>
          <button class="btn btn-line btn-sm" type="button" data-copy="<?= e(eventLink((int)$ev['id'])) ?>" title="<?= e(eventLink((int)$ev['id'])) ?>" aria-label="Скопировать ссылку на «<?= e($ev['title']) ?>»"><?= icon('link') ?>Ссылка</button>
          <a class="btn btn-line btn-sm" href="<?= url('admin/events.php?edit=' . (int)$ev['id'] . ($filter === 'past' ? '&filter=past' : '')) ?>" aria-label="Править «<?= e($ev['title']) ?>»"><?= icon('pencil') ?></a>
          <form method="post" class="inline-form">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$ev['id'] ?>">
            <button class="btn btn-ghost btn-sm" data-confirm="Удалить мероприятие вместе со всеми записями?" aria-label="Удалить «<?= e($ev['title']) ?>»"><?= icon('trash') ?></button>
          </form>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <section class="card"><div class="empty"><?= icon('calendar-check') ?><b>Здесь пусто</b><?= $filter === 'past' ? 'Прошедших мероприятий пока нет.' : 'Создай мероприятие, и оно появится в афише волонтёров.' ?><button class="btn btn-accent btn-sm" type="button" data-drawer-open="eventDrawer"><?= icon('plus') ?>Новое мероприятие</button></div></section>
<?php endif; ?>

<aside class="drawer" id="eventDrawer" role="dialog" aria-modal="true" aria-labelledby="eventDrawerTitle" <?= $drawerOpen ? '' : 'hidden' ?> <?= $edit || isset($_GET['new']) ? 'data-close-url="' . e(url('admin/events.php' . ($filter === 'past' ? '?filter=past' : ''))) . '"' : '' ?>>
  <form class="drawer-panel" method="post">
    <header class="drawer-head">
      <div><p class="drawer-kicker"><?= $edit ? 'Редактирование' : 'Новое мероприятие' ?></p><h2 class="drawer-title" id="eventDrawerTitle"><?= $edit ? e($edit['title']) : 'Мероприятие' ?></h2></div>
      <button class="icon-btn" type="button" data-close-drawer aria-label="Закрыть"><?= icon('close') ?></button>
    </header>
    <div class="drawer-body">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
      <div class="field"><label for="title">Название</label>
        <input type="text" id="title" name="title" value="<?= e($edit['title'] ?? ($_POST['title'] ?? '')) ?>" placeholder="Например: субботник в городском парке" required></div>
      <div class="frow">
        <div class="field"><label for="date">Дата</label>
          <input type="date" id="date" name="date" value="<?= e($edit ? date('Y-m-d', strtotime($edit['starts_at'])) : ($_POST['date'] ?? '')) ?>" required></div>
        <div class="field"><label for="time">Время</label>
          <input type="time" id="time" name="time" value="<?= e($edit ? date('H:i', strtotime($edit['starts_at'])) : ($_POST['time'] ?? '10:00')) ?>"></div>
      </div>
      <div class="frow">
        <div class="field"><label for="direction_id">Направление</label>
          <select id="direction_id" name="direction_id">
            <option value="">Не выбрано</option>
            <?php foreach ($directions as $d): ?>
              <option value="<?= (int)$d['id'] ?>" <?= (int)($edit['direction_id'] ?? ($_POST['direction_id'] ?? 0)) === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['title']) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="field"><label for="location">Место проведения</label>
          <input type="text" id="location" name="location" value="<?= e($edit['location'] ?? ($_POST['location'] ?? '')) ?>" placeholder="Адрес или ориентир"></div>
      </div>
      <div class="frow3">
        <div class="field"><label for="capacity">Мест</label>
          <input type="number" id="capacity" name="capacity" min="0" value="<?= (int)($edit['capacity'] ?? ($_POST['capacity'] ?? 0)) ?>">
          <span class="hint">0 = без ограничений</span></div>
        <div class="field"><label for="points_reward">Баллы за участие</label>
          <input type="number" id="points_reward" name="points_reward" min="0" value="<?= (int)($edit['points_reward'] ?? ($_POST['points_reward'] ?? 10)) ?>"></div>
        <div class="field"><label for="status">Статус</label>
          <select id="status" name="status">
            <?php foreach ($statusLabels as $k => [$lbl, $_]): ?>
              <option value="<?= $k ?>" <?= ($edit['status'] ?? ($_POST['status'] ?? 'published')) === $k ? 'selected' : '' ?>><?= mb_convert_case($lbl, MB_CASE_TITLE, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select></div>
      </div>
      <div class="field"><label for="description">Описание</label>
        <textarea id="description" name="description" placeholder="Что будем делать, что взять с собой, как добраться"><?= e($edit['description'] ?? ($_POST['description'] ?? '')) ?></textarea></div>
      <?php if (!$edit): ?>
        <label class="switchbox"><span><b>Повторять</b><small>Создать серию одинаковых мероприятий</small></span><input type="checkbox" id="repeat_enable" name="repeat_enable" value="1"><span class="toggle"></span></label>
        <div class="frow" id="repeatFields" hidden>
          <div class="field"><label for="repeat_freq">Как часто</label>
            <select id="repeat_freq" name="repeat_freq">
              <option value="weekly">Каждую неделю</option>
              <option value="biweekly">Раз в 2 недели</option>
              <option value="monthly">Каждый месяц</option>
            </select></div>
          <div class="field"><label for="repeat_count">Сколько раз, включая первое</label>
            <input type="number" id="repeat_count" name="repeat_count" min="2" max="26" value="4"></div>
        </div>
        <p class="repeat-note" id="repeatNote" hidden></p>
      <?php endif; ?>
    </div>
    <footer class="drawer-foot">
      <button class="btn btn-ghost" type="button" data-close-drawer>Отмена</button>
      <button class="btn btn-accent" type="submit"><?= $edit ? 'Сохранить' : 'Создать мероприятие' ?></button>
    </footer>
  </form>
</aside>

<?php require __DIR__ . '/../includes/panel_footer.php'; ?>
