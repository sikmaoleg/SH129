<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$me = requireAdmin();

$panelSection = 'admin';
$panelTitle   = 'Заявки на вступление';
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

require __DIR__ . '/../includes/panel_header.php';
?>

<div class="card">
  <div class="card-head">
    <div>
      <h2>Ожидают решения — <?= count($pending) ?></h2>
      <p>Пока заявка не одобрена, человек не может войти в личный кабинет.</p>
    </div>
  </div>

  <?php if ($pending): ?>
    <div class="card-body">
      <?php foreach ($pending as $u): $age = $u['birth_date'] ? (new DateTime($u['birth_date']))->diff(new DateTime())->y : null; ?>
        <div class="card" style="margin-bottom:16px;" id="user-<?= (int)$u['id'] ?>">
          <div class="card-head">
            <div>
              <h2><?= e($u['last_name'] . ' ' . $u['first_name'] . ' ' . $u['middle_name']) ?></h2>
              <p>Заявка от <?= e(ruDate($u['created_at'], true)) ?></p>
            </div>
            <span class="tag tag-pending">на рассмотрении</span>
          </div>
          <div class="card-body">
            <table class="kv" style="margin-bottom:18px;">
              <tr><th>Электронная почта</th><td><?= e($u['email']) ?></td></tr>
              <tr><th>Телефон</th><td><?= e($u['phone'] ?: '—') ?></td></tr>
              <tr><th>Дата рождения</th><td><?= e(ruDate($u['birth_date'])) ?><?= $age !== null ? ' (' . $age . ' ' . plural($age, 'год', 'года', 'лет') . ')' : '' ?></td></tr>
              <tr><th>ВКонтакте</th><td><?= e($u['vk'] ?: '—') ?></td></tr>
              <tr><th>Telegram</th><td><?= e($u['telegram'] ?: '—') ?></td></tr>
              <tr><th>Учёба или работа</th><td><?= e($u['school'] ?: '—') ?></td></tr>
              <tr><th>Чем хочет заниматься</th><td style="font-family:inherit;"><?= nl2br(e($u['about'] ?: '—')) ?></td></tr>
            </table>

            <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-start;">
              <form method="post" style="margin:0;">
                <?= csrfField() ?>
                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                <input type="hidden" name="action" value="approve">
                <button type="submit" class="btn btn-ok" data-confirm="Одобрить заявку? Человек сможет войти в личный кабинет.">Одобрить заявку</button>
              </form>

              <form method="post" class="inline-form" style="margin:0;flex:1;min-width:280px;">
                <?= csrfField() ?>
                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                <input type="hidden" name="action" value="reject">
                <input type="text" name="reason" placeholder="Причина отказа (необязательно)" maxlength="255" style="flex:1;">
                <button type="submit" class="btn btn-danger" data-confirm="Отклонить заявку?">Отклонить</button>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="empty"><b>Новых заявок нет</b>Все анкеты обработаны.</div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-head"><div><h2>Последние решения</h2><p>История одобренных и отклонённых заявок</p></div></div>
  <?php if ($handled): ?>
    <div class="card-body card-body-flush table-wrap">
      <table class="data">
        <thead><tr><th>Волонтёр</th><th>Почта</th><th>Решение</th><th>Кто обработал</th><th>Когда</th></tr></thead>
        <tbody>
          <?php foreach ($handled as $u): ?>
            <tr>
              <td><?= e($u['last_name'] . ' ' . $u['first_name']) ?></td>
              <td style="color:var(--muted);"><?= e($u['email']) ?></td>
              <td>
                <?php if ($u['status'] === 'approved'): ?>
                  <span class="tag tag-approved">одобрена</span>
                <?php else: ?>
                  <span class="tag tag-rejected">отклонена</span>
                  <?php if ($u['reject_reason']): ?><div style="font-size:.8rem;color:var(--muted);margin-top:4px;"><?= e($u['reject_reason']) ?></div><?php endif; ?>
                <?php endif; ?>
              </td>
              <td style="color:var(--muted);"><?= e(trim(($u['admin_last'] ?? '') . ' ' . ($u['admin_first'] ?? '')) ?: '—') ?></td>
              <td style="white-space:nowrap;color:var(--muted);font-size:.85rem;"><?= e(ruDate($u['approved_at'], true)) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="empty"><b>История пуста</b>Здесь появятся обработанные заявки.</div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/panel_footer.php'; ?>
