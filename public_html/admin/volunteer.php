<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$me = requireAdmin();

$userId = (int)($_GET['id'] ?? 0);
$v = $userId ? fetchOne("SELECT * FROM users WHERE id = ? AND status IN ('approved','blocked')", [$userId]) : null;
if (!$v) {
    flash('error', 'Волонтёр не найден.');
    redirect('admin/users.php');
}

$panelSection = 'admin';
$panelTitle   = 'Карточка волонтёра';
$activeItem   = 'users';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $notes = trim((string)($_POST['coordinator_notes'] ?? ''));
    q('UPDATE users SET coordinator_notes = ? WHERE id = ?', [$notes !== '' ? $notes : null, $userId]);
    logAction('coordinator_notes_update', 'user', $userId);
    flash('success', 'Заметка сохранена.');
    redirect('admin/volunteer.php?id=' . $userId);
}

$history = fetchAll(
    'SELECT t.*, a.last_name AS by_last, a.first_name AS by_first
     FROM point_transactions t LEFT JOIN users a ON a.id = t.created_by
     WHERE t.user_id = ? ORDER BY t.created_at DESC LIMIT 20', [$userId]
);

require __DIR__ . '/../includes/panel_header.php';
?>

<div class="card">
  <div class="card-head">
    <div>
      <h2><?= e($v['last_name'] . ' ' . $v['first_name'] . ' ' . $v['middle_name']) ?></h2>
      <p><?= e(positionLabel($v['position'])) ?> · в движении с <?= e(ruDate($v['created_at'])) ?></p>
    </div>
    <a href="<?= url('admin/points.php?user_id=' . $userId) ?>" class="btn btn-outline btn-sm">Начислить очки</a>
  </div>
  <div class="card-body">
    <table class="kv">
      <tr><th>Почта</th><td style="font-family:inherit;"><?= e($v['email']) ?></td></tr>
      <tr><th>Телефон</th><td style="font-family:inherit;"><?= e($v['phone'] ?: '—') ?></td></tr>
      <tr><th>Дата рождения</th><td style="font-family:inherit;"><?= e(ruDate($v['birth_date'])) ?></td></tr>
      <tr><th>Школа / работа</th><td style="font-family:inherit;"><?= e($v['school'] ?: '—') ?></td></tr>
      <tr><th>ВКонтакте</th><td style="font-family:inherit;"><?= e($v['vk'] ?: '—') ?></td></tr>
      <tr><th>Telegram</th><td style="font-family:inherit;"><?= e($v['telegram'] ?: '—') ?></td></tr>
      <tr><th>Очки / часы</th><td style="font-family:inherit;"><?= (int)$v['points'] ?> очков · <?= rtrim(rtrim(number_format((float)$v['hours'], 1, ',', ''), '0'), ',') ?> ч</td></tr>
    </table>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-head"><div><h2>Заметка координатора</h2><p>Видна только администраторам и разработчику — сам волонтёр её не видит.</p></div></div>
    <div class="card-body">
      <form method="post">
        <?= csrfField() ?>
        <div class="field">
          <textarea name="coordinator_notes" rows="6" placeholder="Например: отлично справляется с координацией, можно предлагать более крупные задачи"><?= e($v['coordinator_notes'] ?? '') ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Сохранить заметку</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><div><h2>История начислений</h2></div></div>
    <?php if ($history): ?>
      <div class="card-body card-body-flush table-wrap">
        <table class="data">
          <tbody>
            <?php foreach ($history as $t): ?>
              <tr>
                <td style="white-space:nowrap;color:var(--muted);font-size:.83rem;"><?= e(ruDate($t['created_at'], true)) ?></td>
                <td class="num" style="color:<?= (int)$t['points'] >= 0 ? 'var(--ok)' : 'var(--accent)' ?>;"><?= (int)$t['points'] > 0 ? '+' : '' ?><?= (int)$t['points'] ?></td>
                <td><?= e($t['reason']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="empty"><b>Начислений ещё не было</b></div>
    <?php endif; ?>
  </div>
</div>

<p><a href="<?= url('admin/users.php') ?>" class="btn btn-outline">← К списку волонтёров</a></p>

<?php require __DIR__ . '/../includes/panel_footer.php'; ?>
