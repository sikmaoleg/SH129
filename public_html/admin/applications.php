<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$me = requireAdmin();

$panelSection = 'admin';
$panelTitle   = 'Заявки';
$activeItem   = 'applications';

// ---------------------------------------------------------------------
// Обработка решения администратора
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $userId = (int)($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $target = $userId ? fetchOne('SELECT * FROM users WHERE id = ?', [$userId]) : null;

    if (!$target) {
        flash('error', 'Заявка не найдена.');
    } elseif ($target['status'] !== 'pending') {
        flash('error', 'Эта заявка уже обработана.');
    } elseif ($action === 'approve') {
        q("UPDATE users SET status='approved', approved_by=?, approved_at=NOW(), reject_reason=NULL WHERE id=?",
          [(int)$me['id'], $userId]);
        logAction('user_approve', 'user', $userId, $target['email']);
        flash('success', 'Заявка одобрена: ' . $target['last_name'] . ' ' . $target['first_name'] . ' теперь может войти в личный кабинет.');
    } elseif ($action === 'reject') {
        $reason = trim((string)($_POST['reason'] ?? ''));
        q("UPDATE users SET status='rejected', reject_reason=?, approved_by=?, approved_at=NOW() WHERE id=?",
          [$reason !== '' ? mb_substr($reason, 0, 255) : null, (int)$me['id'], $userId]);
        logAction('user_reject', 'user', $userId, $reason);
        flash('info', 'Заявка отклонена.');
    }
    redirect('admin/applications.php');
}


$pending  = fetchAll("SELECT * FROM users WHERE status='pending' ORDER BY created_at ASC");
$handled  = fetchAll(
    "SELECT u.*, a.last_name AS admin_last, a.first_name AS admin_first
     FROM users u LEFT JOIN users a ON a.id = u.approved_by
     WHERE u.status IN ('approved','rejected') AND u.approved_at IS NOT NULL
     ORDER BY u.approved_at DESC LIMIT 20"
);

$selId = (int)($_GET['id'] ?? 0);
$sel   = null;
foreach ($pending as $p) {
    if ($p['id'] == $selId) { $sel = $p; }
}
$sel = $sel ?? ($pending[0] ?? null);
$ageOf = function (?string $birth): ?int {
    return $birth ? (int)(new DateTime($birth))->diff(new DateTime())->y : null;
};
$agoOf = function (string $dt): string {
    $days = (int)(new DateTime(substr($dt, 0, 10)))->diff(new DateTime(date('Y-m-d')))->days;
    return $days === 0 ? 'сегодня' : ($days === 1 ? 'вчера' : $days . ' ' . plural($days, 'день', 'дня', 'дней') . ' назад');
};
$panelLead = 'Анкеты людей, которые хотят вступить в отделение. Одобренные сразу получают доступ в личный кабинет.';
$panelActions = '<div class="seg"><a class="on" href="' . url('admin/applications.php') . '">Ожидают <em>' . count($pending) . '</em></a><a href="#history">История</a></div>';

require __DIR__ . '/../includes/panel_header.php';
?>

<?php if ($pending): ?>
  <div class="queue">
    <div class="q-list">
      <?php foreach ($pending as $p): $age = $ageOf($p['birth_date']); ?>
        <a class="q-item <?= $sel && $p['id'] == $sel['id'] ? 'on' : '' ?>" href="<?= url('admin/applications.php?id=' . (int)$p['id']) ?>" <?= $sel && $p['id'] == $sel['id'] ? 'aria-current="true"' : '' ?>>
          <span class="ava"><?= e(mb_substr($p['first_name'], 0, 1) . mb_substr($p['last_name'], 0, 1)) ?></span>
          <span><b><?= e($p['first_name'] . ' ' . $p['last_name']) ?></b><small><?= $age !== null ? $age . ' ' . plural($age, 'год', 'года', 'лет') . ' · ' : '' ?>подана <?= e($agoOf($p['created_at'])) ?></small></span>
        </a>
      <?php endforeach; ?>
    </div>

    <?php $u = $sel; $age = $ageOf($u['birth_date']); ?>
    <section class="card q-detail" id="user-<?= (int)$u['id'] ?>">
      <div class="card-b">
        <div>
          <div class="row" style="flex-wrap:wrap;gap:8px">
            <span class="chip warn"><?= icon('hourglass') ?>Ждёт решения</span>
            <span class="tag"><?= icon('calendar') ?>Подана <?= e(ruDate($u['created_at'], true)) ?></span>
          </div>
          <h2 class="q-name display" style="margin-top:14px"><?= e($u['first_name'] . ' ' . $u['last_name']) ?></h2>
          <p class="muted" style="margin-top:6px"><?= e(trim(($u['middle_name'] ?? '') . ($age !== null ? ($u['middle_name'] ? ' · ' : '') . $age . ' ' . plural($age, 'год', 'года', 'лет') : ''))) ?></p>
        </div>
        <dl class="kv-grid">
          <div><dt>Электронная почта</dt><dd><?= e($u['email']) ?></dd></div>
          <div><dt>Телефон</dt><dd><?= e($u['phone'] ?: 'не указан') ?></dd></div>
          <div><dt>Дата рождения</dt><dd><?= e(ruDate($u['birth_date'])) ?></dd></div>
          <div><dt>Учёба или работа</dt><dd><?= e($u['school'] ?: 'не указано') ?></dd></div>
          <div><dt>ВКонтакте</dt><dd><?= $u['vk'] ? e($u['vk']) : '<span class="muted">не указан</span>' ?></dd></div>
          <div><dt>Telegram</dt><dd><?= $u['telegram'] ? e($u['telegram']) : '<span class="muted">не указан</span>' ?></dd></div>
        </dl>
        <?php if (trim((string)$u['about']) !== ''): ?>
          <div><p class="lbl" style="margin-bottom:8px">Чем хочет заниматься</p><p class="quote"><?= nl2br(e($u['about'])) ?></p></div>
        <?php endif; ?>
        <div class="contact-row">
          <?php if ($u['phone']): ?><a class="btn btn-line btn-sm" href="tel:<?= e(preg_replace('/[^\d+]/', '', $u['phone'])) ?>"><?= icon('phone') ?>Позвонить</a><?php endif; ?>
          <?php if ($u['telegram'] && preg_match('/^@?[A-Za-z0-9_]{4,}$/', $u['telegram'])): ?><a class="btn btn-line btn-sm" href="https://t.me/<?= e(ltrim($u['telegram'], '@')) ?>" target="_blank" rel="noopener"><?= icon('telegram') ?>Написать в Telegram</a><?php endif; ?>
          <button class="btn btn-line btn-sm" type="button" data-copy="<?= e($u['email']) ?>"><?= icon('copy') ?>Скопировать почту</button>
        </div>
        <form method="post" class="decide">
          <?= csrfField() ?>
          <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
          <input type="hidden" name="action" value="approve">
          <button type="submit" class="btn btn-ok"><?= icon('check') ?>Одобрить заявку</button>
          <span class="muted" style="font-size:13.5px">Человек сразу сможет войти в&nbsp;личный кабинет</span>
        </form>
        <form method="post">
          <?= csrfField() ?>
          <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
          <input type="hidden" name="action" value="reject">
          <p class="lbl" style="margin-bottom:8px">Или отклонить с&nbsp;причиной</p>
          <div class="reasons">
            <?php foreach (['Не проживает в округе', 'Младше 14 лет', 'Повторная заявка', 'Не удалось связаться'] as $r): ?>
              <button type="button" data-fill="<?= e($r) ?>" data-fill-target="reason<?= (int)$u['id'] ?>"><?= e($r) ?></button>
            <?php endforeach; ?>
          </div>
          <div class="row" style="margin-top:10px;flex-wrap:wrap">
            <label class="sr" for="reason<?= (int)$u['id'] ?>">Причина отказа</label>
            <input class="input" id="reason<?= (int)$u['id'] ?>" name="reason" maxlength="255" placeholder="Своя причина, её видит только администратор" style="flex:1 1 260px">
            <button type="submit" class="btn btn-line" data-confirm="Отклонить заявку?"><?= icon('prohibit') ?>Отклонить</button>
          </div>
        </form>
      </div>
    </section>
  </div>
<?php else: ?>
  <section class="card"><div class="empty"><?= icon('check-circle') ?><b>Новых заявок нет</b>Все анкеты разобраны. Новые появятся здесь и в счётчике в меню.</div></section>
<?php endif; ?>

<section class="card" id="history" style="margin-top:18px">
  <div class="card-head"><div><h2>Последние решения</h2><p>История одобренных и отклонённых заявок</p></div></div>
  <?php if ($handled): ?>
    <div class="table-wrap">
      <table class="data cards">
        <thead><tr><th>Волонтёр</th><th>Почта</th><th>Решение</th><th>Кто обработал</th><th>Когда</th></tr></thead>
        <tbody>
          <?php foreach ($handled as $u): ?>
            <tr>
              <td><b style="font-weight:600"><?= e($u['first_name'] . ' ' . $u['last_name']) ?></b></td>
              <td data-label="Почта" class="muted"><?= e($u['email']) ?></td>
              <td data-label="Решение">
                <?php if ($u['status'] === 'approved'): ?>
                  <span class="chip ok"><?= icon('check') ?>одобрена</span>
                <?php else: ?>
                  <span class="chip"><?= icon('prohibit') ?>отклонена</span>
                  <?php if ($u['reject_reason']): ?><div class="muted" style="font-size:13px;margin-top:4px"><?= e($u['reject_reason']) ?></div><?php endif; ?>
                <?php endif; ?>
              </td>
              <td data-label="Кто обработал" class="muted"><?= e(trim(($u['admin_first'] ?? '') . ' ' . ($u['admin_last'] ?? '')) ?: 'неизвестно') ?></td>
              <td data-label="Когда" class="muted" style="white-space:nowrap"><?= e(ruDate($u['approved_at'], true)) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="empty"><b>История пуста</b>Здесь появятся обработанные заявки.</div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/panel_footer.php'; ?>
