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
    } elseif ($userId === (int)$me['id'] && $action !== 'role') {
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

$counts = ['all' => count($users), 'volunteer' => 0, 'admin' => 0, 'dev' => 0, 'blocked' => 0];
foreach ($users as $u) {
    $counts[$u['role']]++;
    if ($u['status'] === 'blocked') { $counts['blocked']++; }
}
$panelLead = 'Все одобренные учётные записи отделения. Нажми на строку, чтобы открыть карточку волонтёра.';
$panelActions = '<a class="btn btn-line" href="' . url('admin/export.php?type=users' . ($search !== '' ? '&q=' . urlencode($search) : '')) . '">' . icon('download') . 'Экспорт CSV</a>';

require __DIR__ . '/../includes/panel_header.php';
?>

<section class="card">
  <div class="toolbar">
    <form method="get" class="input-ico" role="search">
      <?= icon('search') ?>
      <label class="sr" for="uq">Поиск</label>
      <input class="input" id="uq" type="search" name="q" value="<?= e($search) ?>" placeholder="Имя, почта или телефон" data-filter-input="usersBody" data-filter-empty="usersEmpty" autocomplete="off">
    </form>
    <div class="seg" role="group" aria-label="Фильтр по роли">
      <?php foreach (['all' => 'Все', 'volunteer' => 'Волонтёры', 'admin' => 'Администраторы', 'dev' => 'Разработчики', 'blocked' => 'Заблокированы'] as $k => $l): ?>
        <?php if ($k !== 'all' && $counts[$k] === 0 && $k !== 'blocked') continue; ?>
        <button type="button" class="<?= $k === 'all' ? 'on' : '' ?>" data-role-filter="<?= $k ?>"><?= $l ?> <em><?= $counts[$k] ?></em></button>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if ($users): ?>
    <div class="table-wrap">
      <table class="data cards">
        <thead>
          <tr><th>Волонтёр</th><th>Телефон</th><th>Роль</th><th>Позиция</th><th class="r">Баллы</th><th class="r">Часы</th><th>Уровень</th><th>Статус</th><th></th></tr>
        </thead>
        <tbody id="usersBody" data-role="all">
          <?php foreach ($users as $u): $isMe = (int)$u['id'] === (int)$me['id']; ?>
            <tr class="click" data-href="<?= url('admin/volunteer.php?id=' . (int)$u['id']) ?>"
                data-role="<?= e($u['role'] . ($u['status'] === 'blocked' ? ' blocked' : '')) ?>"
                data-filter-text="<?= e(mb_strtolower($u['last_name'] . ' ' . $u['first_name'] . ' ' . $u['email'] . ' ' . $u['phone'])) ?>">
              <td>
                <div class="who">
                  <span class="ava"><?php if ($u['avatar']): ?><img src="<?= url('uploads/avatars/' . $u['avatar']) ?>" alt=""><?php else: ?><?= e(mb_substr($u['first_name'], 0, 1) . mb_substr($u['last_name'], 0, 1)) ?><?php endif; ?></span>
                  <div style="min-width:0"><a href="<?= url('admin/volunteer.php?id=' . (int)$u['id']) ?>"><b><?= e($u['first_name'] . ' ' . $u['last_name']) ?></b></a><small><?= e($u['email']) ?></small></div>
                </div>
              </td>
              <td data-label="Телефон" class="num" style="white-space:nowrap;font-weight:400"><?= e($u['phone'] ?: 'не указан') ?></td>
              <td data-label="Роль">
                <form method="post" class="inline-form">
                  <?= csrfField() ?>
                  <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                  <input type="hidden" name="action" value="role">
                  <input type="hidden" name="q" value="<?= e($search) ?>">
                  <select name="role" onchange="this.form.submit()" aria-label="Роль: <?= e($u['first_name'] . ' ' . $u['last_name']) ?>">
                    <option value="volunteer" <?= $u['role'] === 'volunteer' ? 'selected' : '' ?>>волонтёр</option>
                    <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>администратор</option>
                    <?php if (isDev() || $u['role'] === 'dev'): ?><option value="dev" <?= $u['role'] === 'dev' ? 'selected' : '' ?>>разработчик</option><?php endif; ?>
                  </select>
                </form>
                <?php if ($isMe): ?><div class="hint">Это вы: смена роли применится сразу.</div><?php endif; ?>
              </td>
              <td data-label="Позиция">
                <form method="post" class="inline-form">
                  <?= csrfField() ?>
                  <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                  <input type="hidden" name="action" value="position">
                  <input type="hidden" name="q" value="<?= e($search) ?>">
                  <select name="position" onchange="this.form.submit()" <?= $isMe ? 'disabled' : '' ?> aria-label="Позиция: <?= e($u['first_name'] . ' ' . $u['last_name']) ?>">
                    <?php foreach (POSITION_LABELS as $pv => $pl): ?>
                      <option value="<?= e($pv) ?>" <?= ($u['position'] ?? 'volunteer') === $pv ? 'selected' : '' ?>><?= e($pl) ?></option>
                    <?php endforeach; ?>
                  </select>
                </form>
              </td>
              <td class="num r" data-label="Баллы"><?= number_format((int)$u['points'], 0, '.', ' ') ?></td>
              <td class="num r" data-label="Часы" style="font-weight:400"><?= rtrim(rtrim(number_format((float)$u['hours'], 1, ',', ''), '0'), ',') ?></td>
              <td data-label="Уровень"><?= e(levelFor((int)$u['points'])['current']['name']) ?></td>
              <td data-label="Статус">
                <?php if ($u['status'] === 'blocked'): ?>
                  <span class="chip"><?= icon('prohibit') ?>заблокирован</span>
                <?php else: ?>
                  <span class="chip ok"><?= icon('check') ?>активен</span>
                <?php endif; ?>
              </td>
              <td data-label="">
                <div class="actions">
                  <a href="<?= url('admin/points.php?user_id=' . (int)$u['id']) ?>" class="btn btn-line btn-sm"><?= icon('star') ?>Баллы</a>
                  <?php if (!$isMe): ?>
                    <form method="post" class="inline-form">
                      <?= csrfField() ?>
                      <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                      <input type="hidden" name="q" value="<?= e($search) ?>">
                      <?php if ($u['status'] === 'blocked'): ?>
                        <input type="hidden" name="action" value="unblock">
                        <button class="btn btn-ok btn-sm">Разблокировать</button>
                      <?php else: ?>
                        <input type="hidden" name="action" value="block">
                        <button class="btn btn-ghost btn-sm" data-confirm="Закрыть доступ этому волонтёру?" aria-label="Заблокировать"><?= icon('prohibit') ?></button>
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
    <div class="empty" id="usersEmpty" hidden><?= icon('users') ?><b>Никого не нашли</b>Измени запрос или фильтр.</div>
  <?php else: ?>
    <div class="empty"><?= icon('users') ?><b>Ничего не найдено</b><?= $search !== '' ? 'Попробуй изменить запрос.' : 'Одобренных волонтёров пока нет.' ?></div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/panel_footer.php'; ?>
