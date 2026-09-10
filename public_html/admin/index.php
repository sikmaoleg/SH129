<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();

$panelSection = 'admin';
$panelTitle   = 'Обзор';
$activeItem   = 'index';

$pending    = (int)fetchValue("SELECT COUNT(*) FROM users WHERE status='pending'");
$approved   = (int)fetchValue("SELECT COUNT(*) FROM users WHERE status='approved' AND role='volunteer'");
$upcoming   = (int)fetchValue("SELECT COUNT(*) FROM events WHERE status='published' AND starts_at >= NOW()");
$needReview = (int)fetchValue("SELECT COUNT(*) FROM events WHERE status='published' AND starts_at < NOW()");

$latestApps = fetchAll(
    "SELECT id, last_name, first_name, email, phone, created_at
     FROM users WHERE status='pending' ORDER BY created_at ASC LIMIT 5"
);

$soon = fetchAll(
    "SELECT e.id, e.title, e.starts_at,
            (SELECT COUNT(*) FROM event_registrations r WHERE r.event_id=e.id AND r.status<>'cancelled') AS taken,
            e.capacity
     FROM events e WHERE e.status='published' AND e.starts_at >= NOW()
     ORDER BY e.starts_at ASC LIMIT 5"
);

$recent = fetchAll(
    "SELECT a.action, a.entity, a.created_at, u.last_name, u.first_name
     FROM audit_log a LEFT JOIN users u ON u.id = a.user_id
     ORDER BY a.created_at DESC LIMIT 8"
);

require __DIR__ . '/../includes/panel_header.php';
?>

<div class="kpi-grid">
  <div class="kpi <?= $pending > 0 ? 'is-alert' : '' ?>">
    <div class="kpi-top"><span>Заявки на рассмотрении</span><?= icon('user-plus') ?></div><b><?= $pending ?></b>
    <a href="<?= url('admin/applications.php') ?>"><?= icon('arrow-right') ?>Проверить</a>
  </div>
  <div class="kpi"><div class="kpi-top"><span>Волонтёров в отделении</span><?= icon('users') ?></div><b><?= $approved ?></b><a href="<?= url('admin/users.php') ?>"><?= icon('arrow-right') ?>Список</a></div>
  <div class="kpi"><div class="kpi-top"><span>Предстоящих мероприятий</span><?= icon('calendar') ?></div><b><?= $upcoming ?></b><a href="<?= url('admin/events.php') ?>"><?= icon('arrow-right') ?>Афиша</a></div>
  <div class="kpi <?= $needReview > 0 ? 'is-alert' : '' ?>">
    <div class="kpi-top"><span>Ждут отметки участия</span><?= icon('clipboard') ?></div><b><?= $needReview ?></b>
    <a href="<?= url('admin/events.php?filter=past') ?>"><?= icon('arrow-right') ?>Отметить</a>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-head">
      <div><h2>Новые заявки</h2><p>Ожидают одобрения администратором</p></div>
      <a href="<?= url('admin/applications.php') ?>" class="btn btn-outline btn-sm">Все</a>
    </div>
    <?php if ($latestApps): ?>
      <div class="card-body card-body-flush table-wrap">
        <table class="data">
          <tbody>
            <?php foreach ($latestApps as $a): ?>
              <tr>
                <td><b><?= e($a['last_name'].' '.$a['first_name']) ?></b>
                    <div style="font-size:.8rem;color:var(--muted);"><?= e($a['email']) ?> · <?= e($a['phone']) ?></div></td>
                <td style="white-space:nowrap;color:var(--muted);font-size:.83rem;"><?= e(ruDate($a['created_at'])) ?></td>
                <td><a href="<?= url('admin/applications.php#user-'.(int)$a['id']) ?>" class="btn btn-primary btn-sm">Открыть</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="empty"><b>Новых заявок нет</b>Все анкеты обработаны.</div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head">
      <div><h2>Ближайшие мероприятия</h2></div>
      <a href="<?= url('admin/events.php') ?>" class="btn btn-outline btn-sm">Все</a>
    </div>
    <?php if ($soon): ?>
      <div class="card-body card-body-flush table-wrap">
        <table class="data">
          <tbody>
            <?php foreach ($soon as $ev): ?>
              <tr>
                <td><?= e($ev['title']) ?><div style="font-size:.8rem;color:var(--muted);"><?= e(ruDate($ev['starts_at'], true)) ?></div></td>
                <td class="num" style="white-space:nowrap;"><?= (int)$ev['taken'] ?><?= (int)$ev['capacity'] > 0 ? ' / '.(int)$ev['capacity'] : '' ?></td>
                <td><a href="<?= url('admin/attendance.php?id='.(int)$ev['id']) ?>" class="btn btn-outline btn-sm">Записи</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="empty"><b>Афиша пуста</b>Создайте первое мероприятие.</div>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-head"><div><h2>Последние действия</h2></div></div>
  <div class="card-body card-body-flush table-wrap">
    <table class="data">
      <tbody>
        <?php foreach ($recent as $r): ?>
          <tr>
            <td style="width:1%;white-space:nowrap;color:var(--muted);font-size:.83rem;"><?= e(ruDate($r['created_at'], true)) ?></td>
            <td class="mono"><?= e($r['action']) ?></td>
            <td style="color:var(--muted);"><?= e(trim(($r['last_name'] ?? '').' '.($r['first_name'] ?? '')) ?: 'система') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$recent): ?><tr><td colspan="3" style="text-align:center;color:var(--muted);padding:28px;">Записей пока нет</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../includes/panel_footer.php'; ?>
