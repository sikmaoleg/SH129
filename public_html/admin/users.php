<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$me = requireAdmin();

$panelSection = 'admin';
$panelTitle   = 'Волонтёры';
$activeItem   = 'users';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $userId = (int)($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $target = $userId ? fetchOne('SELECT * FROM users WHERE id = ?', [$userId]) : null;

    if (!$target) {
        flash('error', 'Пользователь не найден.');
    } elseif ($userId === (int)$me['id']) {
        flash('error', 'Нельзя менять собственную учётную запись здесь.');
    } elseif ($target['role'] === 'dev' && !isDev()) {
        flash('error', 'Учётную запись разработчика может менять только разработчик.');
    } else {
        switch ($action) {
            case 'block':
                q("UPDATE users SET status='blocked' WHERE id=?", [$userId]);
                logAction('user_block', 'user', $userId);
                flash('info', 'Доступ закрыт.');
                break;
            case 'unblock':
                q("UPDATE users SET status='approved' WHERE id=?", [$userId]);
                logAction('user_unblock', 'user', $userId);
                flash('success', 'Доступ восстановлен.');
                break;
            case 'role':
                $newRole = $_POST['role'] ?? 'volunteer';
                // Роль dev назначает только разработчик
                if ($newRole === 'dev' && !isDev()) {
                    flash('error', 'Назначить роль разработчика может только разработчик.');
                    break;
                }
                if (!in_array($newRole, ['volunteer','admin','dev'], true)) {
                    flash('error', 'Неизвестная роль.');
                    break;
                }
                q('UPDATE users SET role=? WHERE id=?', [$newRole, $userId]);
                logAction('user_role', 'user', $userId, $newRole);
                flash('success', 'Роль изменена.');
                break;
            case 'position':
                $newPosition = $_POST['position'] ?? 'volunteer';
                if (!array_key_exists($newPosition, POSITION_LABELS)) {
                    flash('error', 'Неизвестная позиция.');
                    break;
                }
                q('UPDATE users SET position=? WHERE id=?', [$newPosition, $userId]);
                logAction('user_position', 'user', $userId, $newPosition);
                flash('success', 'Позиция изменена.');
                break;
        }
    }
    redirect('admin/users.php' . (!empty($_POST['q']) ? '?q=' . urlencode((string)$_POST['q']) : ''));
}

$search = trim((string)($_GET['q'] ?? ''));
$params = [];
$where  = "status IN ('approved','blocked')";
if ($search !== '') {
    $where .= " AND (last_name LIKE ? OR first_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $like = '%' . $search . '%';
    $params = [$like, $like, $like, $like];
}
$users = fetchAll("SELECT * FROM users WHERE $where ORDER BY points DESC, last_name ASC LIMIT 200", $params);

require __DIR__ . '/../includes/panel_header.php';
?>

<div class="card">
  <div class="card-head">
    <div><h2>Список волонтёров — <?= count($users) ?></h2><p>Одобренные учётные записи отделения</p></div>
    <form method="get" class="inline-form">
      <input type="text" name="q" value="<?= e($search) ?>" placeholder="Поиск по имени, почте, телефону">
      <button type="submit" class="btn btn-outline btn-sm">Найти</button>
      <?php if ($search !== ''): ?><a href="<?= url('admin/users.php') ?>" class="btn btn-outline btn-sm">Сброс</a><?php endif; ?>
    </form>
  </div>

  <?php if ($users): ?>
    <div class="card-body card-body-flush table-wrap">
      <table class="data">
        <thead>
          <tr><th>Волонтёр</th><th>Контакты</th><th>Роль</th><th>Позиция</th><th>Очки</th><th>Часы</th><th>Статус</th><th>Действия</th></tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <tr>
              <td>
                <b><?= e($u['last_name'] . ' ' . $u['first_name']) ?></b>
                <div style="font-size:.79rem;color:var(--muted);">в движении с <?= e(ruDate($u['created_at'])) ?></div>
              </td>
              <td style="font-size:.85rem;color:var(--muted);"><?= e($u['email']) ?><br><?= e($u['phone'] ?: '—') ?></td>
              <td>
                <form method="post" class="inline-form" style="margin:0;">
                  <?= csrfField() ?>
                  <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                  <input type="hidden" name="action" value="role">
                  <input type="hidden" name="q" value="<?= e($search) ?>">
                  <select name="role" onchange="this.form.submit()" <?= (int)$u['id'] === (int)$me['id'] ? 'disabled' : '' ?>>
                    <option value="volunteer" <?= $u['role'] === 'volunteer' ? 'selected' : '' ?>>волонтёр</option>
                    <option value="admin"     <?= $u['role'] === 'admin' ? 'selected' : '' ?>>администратор</option>
                    <?php if (isDev()): ?><option value="dev" <?= $u['role'] === 'dev' ? 'selected' : '' ?>>разработчик</option><?php endif; ?>
                  </select>
                </form>
              </td>
              <td>
                <form method="post" class="inline-form" style="margin:0;">
                  <?= csrfField() ?>
                  <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                  <input type="hidden" name="action" value="position">
                  <input type="hidden" name="q" value="<?= e($search) ?>">
                  <select name="position" onchange="this.form.submit()" <?= (int)$u['id'] === (int)$me['id'] ? 'disabled' : '' ?>>
                    <?php foreach (POSITION_LABELS as $pv => $pl): ?>
                      <option value="<?= e($pv) ?>" <?= ($u['position'] ?? 'volunteer') === $pv ? 'selected' : '' ?>><?= e($pl) ?></option>
                    <?php endforeach; ?>
                  </select>
                </form>
              </td>
              <td class="num"><?= (int)$u['points'] ?></td>
              <td class="num"><?= rtrim(rtrim(number_format((float)$u['hours'], 1, ',', ''), '0'), ',') ?></td>
              <td>
                <?php if ($u['status'] === 'blocked'): ?>
                  <span class="tag tag-blocked">заблокирован</span>
                <?php else: ?>
                  <span class="tag tag-approved">активен</span>
                <?php endif; ?>
              </td>
              <td>
                <div class="actions">
                  <a href="<?= url('admin/points.php?user_id='.(int)$u['id']) ?>" class="btn btn-outline btn-sm">Очки</a>
                  <?php if ((int)$u['id'] !== (int)$me['id']): ?>
                    <form method="post" style="margin:0;">
                      <?= csrfField() ?>
                      <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                      <input type="hidden" name="q" value="<?= e($search) ?>">
                      <?php if ($u['status'] === 'blocked'): ?>
                        <input type="hidden" name="action" value="unblock">
                        <button class="btn btn-ok btn-sm">Разблокировать</button>
                      <?php else: ?>
                        <input type="hidden" name="action" value="block">
                        <button class="btn btn-outline btn-sm" data-confirm="Закрыть доступ этому волонтёру?">Заблокировать</button>
                      <?php endif; ?>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="empty"><b>Ничего не найдено</b><?= $search !== '' ? 'Попробуйте изменить запрос.' : 'Одобренных волонтёров пока нет.' ?></div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/panel_footer.php'; ?>
