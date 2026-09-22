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
    $action = $_POST['action'] ?? 'notes';

    if ($action === 'joined') {
        $joined = trim((string)($_POST['mger_joined_at'] ?? ''));
        if ($joined !== '' && !DateTime::createFromFormat('Y-m-d', $joined)) {
            flash('error', 'Некорректная дата вступления.');
        } else {
            q('UPDATE users SET mger_joined_at = ? WHERE id = ?', [$joined !== '' ? $joined : null, $userId]);
            logAction('mger_joined_update', 'user', $userId, $joined);
            flash('success', 'Дата вступления сохранена.');
        }
    } elseif ($action === 'badge_award') {
        $badgeId = (int)($_POST['badge_id'] ?? 0);
        if ($badgeId && fetchValue('SELECT id FROM badges WHERE id = ?', [$badgeId])) {
            q('INSERT IGNORE INTO user_badges (user_id, badge_id, awarded_by) VALUES (?,?,?)',
              [$userId, $badgeId, (int)$me['id']]);
            logAction('badge_award', 'user', $userId, (string)$badgeId);
            flash('success', 'Достижение выдано.');
        } else {
            flash('error', 'Достижение не найдено.');
        }
    } elseif ($action === 'badge_revoke') {
        $badgeId = (int)($_POST['badge_id'] ?? 0);
        q('DELETE FROM user_badges WHERE user_id = ? AND badge_id = ?', [$userId, $badgeId]);
        logAction('badge_revoke', 'user', $userId, (string)$badgeId);
        flash('info', 'Достижение снято.');
    } elseif ($action === 'avatar_upload') {
        $result = handleAvatarUpload($_FILES['avatar'] ?? []);
        if (isset($result['error'])) {
            flash('error', $result['error']);
        } else {
            deleteAvatarFile($v['avatar']);
            q('UPDATE users SET avatar = ? WHERE id = ?', [$result['filename'], $userId]);
            logAction('avatar_update', 'user', $userId);
            flash('success', 'Фото профиля обновлено.');
        }
    } elseif ($action === 'avatar_remove') {
        if ($v['avatar']) {
            deleteAvatarFile($v['avatar']);
            q('UPDATE users SET avatar = NULL WHERE id = ?', [$userId]);
            logAction('avatar_remove', 'user', $userId);
            flash('info', 'Фото профиля удалено.');
        }
    } elseif ($action === 'profile') {
        $fields = [
            'last_name'   => trim((string)($_POST['last_name'] ?? '')),
            'first_name'  => trim((string)($_POST['first_name'] ?? '')),
            'middle_name' => trim((string)($_POST['middle_name'] ?? '')),
            'email'       => mb_strtolower(trim((string)($_POST['email'] ?? ''))),
            'phone'       => trim((string)($_POST['phone'] ?? '')),
            'birth_date'  => trim((string)($_POST['birth_date'] ?? '')),
            'vk'          => trim((string)($_POST['vk'] ?? '')),
            'telegram'    => trim((string)($_POST['telegram'] ?? '')),
            'school'      => trim((string)($_POST['school'] ?? '')),
            'about'       => trim((string)($_POST['about'] ?? '')),
        ];
        $errors = [];
        if ($fields['last_name'] === '' || $fields['first_name'] === '') {
            $errors[] = 'Фамилия и имя обязательны.';
        }
        if ($fields['email'] === '' || !filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Электронная почта указана в неверном формате.';
        } elseif (fetchValue('SELECT id FROM users WHERE email = ? AND id <> ?', [$fields['email'], $userId])) {
            $errors[] = 'Этот адрес уже используется другим пользователем.';
        }
        if ($fields['birth_date'] !== '' && !DateTime::createFromFormat('Y-m-d', $fields['birth_date'])) {
            $errors[] = 'Некорректная дата рождения.';
        }
        if ($errors) {
            foreach ($errors as $er) { flash('error', $er); }
        } else {
            q('UPDATE users SET last_name=?, first_name=?, middle_name=?, email=?, phone=?, birth_date=?, vk=?, telegram=?, school=?, about=? WHERE id=?', [
                $fields['last_name'], $fields['first_name'], $fields['middle_name'] ?: null,
                $fields['email'], $fields['phone'] ?: null, $fields['birth_date'] !== '' ? $fields['birth_date'] : null,
                $fields['vk'] ?: null, $fields['telegram'] ?: null, $fields['school'] ?: null, $fields['about'] ?: null,
                $userId,
            ]);
            logAction('profile_update_by_admin', 'user', $userId);
            flash('success', 'Данные волонтёра обновлены.');
        }
    } else {
        $notes = trim((string)($_POST['coordinator_notes'] ?? ''));
        q('UPDATE users SET coordinator_notes = ? WHERE id = ?', [$notes !== '' ? $notes : null, $userId]);
        logAction('coordinator_notes_update', 'user', $userId);
        flash('success', 'Заметка сохранена.');
    }
    redirect('admin/volunteer.php?id=' . $userId);
}

$history = fetchAll(
    'SELECT t.*, a.last_name AS by_last, a.first_name AS by_first
     FROM point_transactions t LEFT JOIN users a ON a.id = t.created_by
     WHERE t.user_id = ? ORDER BY t.created_at DESC LIMIT 20', [$userId]
);

$allBadges = fetchAll('SELECT * FROM badges ORDER BY title ASC');
$myBadgeIds = fetchAll('SELECT badge_id, awarded_at FROM user_badges WHERE user_id = ?', [$userId]);
$earnedBadges = [];
foreach ($myBadgeIds as $r) {
    $earnedBadges[(int)$r['badge_id']] = $r['awarded_at'];
}
$availableBadges = array_filter($allBadges, fn($b) => !isset($earnedBadges[(int)$b['id']]));

require __DIR__ . '/../includes/panel_header.php';
?>

<div class="card">
  <div class="card-head">
    <div>
      <h2><?= e($v['last_name'] . ' ' . $v['first_name'] . ' ' . $v['middle_name']) ?></h2>
      <p><?= e(positionLabel($v['position'])) ?> · <?= (int)$v['points'] ?> <?= plural((int)$v['points'], 'балл', 'балла', 'баллов') ?> · в организации с <?= e(ruDate(membershipDate($v))) ?></p>
    </div>
    <a href="<?= url('admin/points.php?user_id=' . $userId) ?>" class="btn btn-outline btn-sm">Начислить баллы</a>
  </div>
  <div class="card-body">
    <form method="post">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="profile">
      <div class="field-row">
        <div class="field"><label for="last_name">Фамилия</label><input type="text" id="last_name" name="last_name" value="<?= e($v['last_name']) ?>" required></div>
        <div class="field"><label for="first_name">Имя</label><input type="text" id="first_name" name="first_name" value="<?= e($v['first_name']) ?>" required></div>
      </div>
      <div class="field-row">
        <div class="field"><label for="middle_name">Отчество</label><input type="text" id="middle_name" name="middle_name" value="<?= e($v['middle_name']) ?>"></div>
        <div class="field"><label for="birth_date">Дата рождения</label><input type="date" id="birth_date" name="birth_date" value="<?= e($v['birth_date'] ?? '') ?>"></div>
      </div>
      <div class="field-row">
        <div class="field"><label for="email">Электронная почта</label><input type="email" id="email" name="email" value="<?= e($v['email']) ?>" required></div>
        <div class="field"><label for="phone">Телефон</label><input type="tel" id="phone" name="phone" value="<?= e($v['phone']) ?>"></div>
      </div>
      <div class="field-row">
        <div class="field"><label for="vk">ВКонтакте</label><input type="text" id="vk" name="vk" value="<?= e($v['vk']) ?>"></div>
        <div class="field"><label for="telegram">Telegram</label><input type="text" id="telegram" name="telegram" value="<?= e($v['telegram']) ?>"></div>
      </div>
      <div class="field"><label for="school">Школа, колледж или работа</label><input type="text" id="school" name="school" value="<?= e($v['school']) ?>"></div>
      <div class="field"><label for="about">О себе</label><textarea id="about" name="about"><?= e($v['about']) ?></textarea></div>
      <button type="submit" class="btn btn-primary">Сохранить данные</button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-head"><div><h2>Фото профиля</h2><p>Загрузить или заменить фото может администратор или разработчик.</p></div></div>
  <div class="card-body" style="display:flex;gap:22px;align-items:center;flex-wrap:wrap;">
    <div class="vk-avatar" style="margin:0;flex:none;">
      <?php if ($v['avatar']): ?>
        <img src="<?= url('uploads/avatars/' . $v['avatar']) ?>" alt="">
      <?php else: ?>
        <span><?= e(mb_substr($v['first_name'], 0, 1) . mb_substr($v['last_name'], 0, 1)) ?></span>
      <?php endif; ?>
    </div>
    <div style="flex:1;min-width:220px;display:flex;flex-direction:column;gap:10px;">
      <form method="post" enctype="multipart/form-data" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="avatar_upload">
        <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" required>
        <button type="submit" class="btn btn-primary btn-sm">Загрузить</button>
      </form>
      <?php if ($v['avatar']): ?>
        <form method="post" style="margin:0;">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="avatar_remove">
          <button type="submit" class="btn btn-outline btn-sm" data-confirm="Удалить фото профиля волонтёра?">Удалить фото</button>
        </form>
      <?php endif; ?>
      <div class="hint">JPG, PNG или WEBP, до 50 МБ. Фото обрежется по центру до квадрата.</div>
    </div>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-head"><div><h2>Дата вступления в МГЕР</h2><p>Видна и редактируется только администратором и разработчиком.</p></div></div>
    <div class="card-body">
      <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="joined">
        <div class="field">
          <label for="mger_joined_at">Дата вступления</label>
          <input type="date" id="mger_joined_at" name="mger_joined_at" value="<?= e($v['mger_joined_at'] ?? '') ?>">
          <div class="hint">Оставьте пустым, если дата неизвестна.</div>
        </div>
        <button type="submit" class="btn btn-primary">Сохранить дату</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><div><h2>Заметка администратора</h2><p>Видна только администраторам и разработчику — сам волонтёр её не видит.</p></div></div>
    <div class="card-body">
      <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="notes">
        <div class="field">
          <textarea name="coordinator_notes" rows="6" placeholder="Например: отлично справляется с координацией, можно предлагать более крупные задачи"><?= e($v['coordinator_notes'] ?? '') ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Сохранить заметку</button>
      </form>
    </div>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-head">
      <div><h2>Достижения</h2><p>Выдайте существующее достижение или создайте новое.</p></div>
      <a href="<?= url('admin/badges.php') ?>" class="btn btn-outline btn-sm">Все достижения</a>
    </div>
    <div class="card-body">
      <?php if ($allBadges): ?>
        <?php if ($earnedBadges): ?>
          <div class="badge-grid" style="margin-bottom:18px;">
            <?php foreach ($allBadges as $b): if (!isset($earnedBadges[(int)$b['id']])) continue; ?>
              <div class="badge-item is-earned">
                <div class="ico"><?= $b['icon'] !== '' && $b['icon'] !== null ? e($b['icon']) : icon('badge-check') ?></div>
                <b><?= e($b['title']) ?></b>
                <span><?= e($b['description']) ?></span>
                <div style="margin-top:8px;font-size:.75rem;color:var(--ok);font-weight:700;"><?= e(ruDate($earnedBadges[(int)$b['id']])) ?></div>
                <form method="post" style="margin-top:8px;">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="badge_revoke">
                  <input type="hidden" name="badge_id" value="<?= (int)$b['id'] ?>">
                  <button type="submit" class="btn btn-outline btn-sm" data-confirm="Снять это достижение?">Снять</button>
                </form>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php if ($availableBadges): ?>
          <form method="post" class="inline-form">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="badge_award">
            <select name="badge_id" required>
              <option value="">— выберите достижение —</option>
              <?php foreach ($availableBadges as $b): ?>
                <option value="<?= (int)$b['id'] ?>"><?= e($b['title']) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Выдать</button>
          </form>
        <?php else: ?>
          <p style="color:var(--muted);font-size:.88rem;">Все существующие достижения уже выданы.</p>
        <?php endif; ?>
      <?php else: ?>
        <div class="empty"><b>Достижения ещё не созданы</b><a href="<?= url('admin/badges.php') ?>">Создайте первое</a> на странице «Достижения».</div>
      <?php endif; ?>
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
