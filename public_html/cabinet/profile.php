<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$me = requireLogin();

$panelSection = 'cabinet';
$panelTitle   = 'Мои данные';
$activeItem   = 'profile';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $fields = [
            'last_name'   => trim((string)($_POST['last_name'] ?? '')),
            'first_name'  => trim((string)($_POST['first_name'] ?? '')),
            'middle_name' => trim((string)($_POST['middle_name'] ?? '')),
            'phone'       => trim((string)($_POST['phone'] ?? '')),
            'vk'          => trim((string)($_POST['vk'] ?? '')),
            'telegram'    => trim((string)($_POST['telegram'] ?? '')),
            'school'      => trim((string)($_POST['school'] ?? '')),
            'about'       => trim((string)($_POST['about'] ?? '')),
        ];
        if ($fields['last_name'] === '' || $fields['first_name'] === '') {
            $errors[] = 'Фамилия и имя обязательны.';
        }
        if (!$errors) {
            q('UPDATE users SET last_name=?, first_name=?, middle_name=?, phone=?, vk=?, telegram=?, school=?, about=? WHERE id=?',
              [...array_values($fields), (int)$me['id']]);
            logAction('profile_update', 'user', (int)$me['id']);
            flash('success', 'Данные сохранены.');
            redirect('cabinet/profile.php');
        }
    }

    if ($action === 'avatar_upload') {
        $result = handleAvatarUpload($_FILES['avatar'] ?? []);
        if (isset($result['error'])) {
            $errors[] = $result['error'];
        } else {
            $old = $me['avatar'];
            q('UPDATE users SET avatar = ? WHERE id = ?', [$result['filename'], (int)$me['id']]);
            deleteAvatarFile($old);
            logAction('avatar_update', 'user', (int)$me['id']);
            flash('success', 'Фото профиля обновлено.');
            redirect('cabinet/profile.php');
        }
    }

    if ($action === 'avatar_remove') {
        if ($me['avatar']) {
            deleteAvatarFile($me['avatar']);
            q('UPDATE users SET avatar = NULL WHERE id = ?', [(int)$me['id']]);
            logAction('avatar_remove', 'user', (int)$me['id']);
            flash('info', 'Фото профиля удалено.');
        }
        redirect('cabinet/profile.php');
    }

    if ($action === 'public_consent') {
        $on = ($_POST['value'] ?? '') === '1';
        setPublicConsent((int)$me['id'], $on, 'site');
        logAction($on ? 'public_consent_on' : 'public_consent_off', 'user', (int)$me['id']);
        flash('success', $on ? 'Готово: имя и фото могут показываться в команде и на доске почёта.' : 'Готово: имя и фото больше не показываются на открытых страницах сайта.');
        redirect('cabinet/profile.php#privacy');
    }

    if ($action === 'export') {
        // Право субъекта знать, какие данные о нём хранятся (ст. 14 152-ФЗ)
        $uid = (int)$me['id'];
        $data = [
            'выгружено' => date('Y-m-d H:i:s'),
            'оператор' => operatorInfo()['name'],
            'анкета' => array_intersect_key($me, array_flip(['email', 'last_name', 'first_name', 'middle_name', 'phone',
                'birth_date', 'vk', 'telegram', 'school', 'about', 'avatar', 'role', 'position', 'status', 'points', 'hours',
                'mger_joined_at', 'created_at', 'approved_at', 'last_login_at', 'pd_consent_at', 'pd_consent_version',
                'public_consent', 'public_consent_at', 'guardian_name', 'guardian_phone', 'guardian_consent_at'])),
            'записи_на_мероприятия' => fetchAll('SELECT e.title, e.starts_at, r.status, r.points_awarded, r.created_at
                FROM event_registrations r JOIN events e ON e.id = r.event_id WHERE r.user_id = ? ORDER BY e.starts_at', [$uid]),
            'начисления_баллов' => fetchAll('SELECT points, reason, created_at FROM point_transactions WHERE user_id = ? ORDER BY created_at', [$uid]),
            'достижения' => fetchAll('SELECT b.title, ub.awarded_at FROM user_badges ub JOIN badges b ON b.id = ub.badge_id WHERE ub.user_id = ?', [$uid]),
            'согласия' => fetchAll('SELECT kind, action, version, source, created_at FROM consent_log WHERE user_id = ? ORDER BY created_at', [$uid]),
            'журнал_входов' => fetchAll("SELECT action, ip, created_at FROM audit_log WHERE user_id = ? AND action IN ('login','logout') ORDER BY created_at DESC LIMIT 100", [$uid]),
        ];
        logAction('data_export', 'user', $uid);
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="moi-dannye.json"');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    if ($action === 'delete_account') {
        if ($me['role'] === 'dev') {
            $errors[] = 'Учётную запись разработчика удалить нельзя, пока на сайте нет другого разработчика.';
        } elseif (!password_verify((string)($_POST['password'] ?? ''), $me['password_hash'])) {
            $errors[] = 'Пароль для удаления указан неверно.';
        } else {
            deleteUserData((int)$me['id'], 'self');
            logout();
            session_start();
            session_regenerate_id(true); // новая сессия, чтобы сообщение дошло после выхода
            flash('info', 'Учётная запись и ваши данные удалены. Спасибо, что были с нами.');
            redirect('index.php');
        }
    }

    if ($action === 'password') {
        $cur  = (string)($_POST['current_password'] ?? '');
        $new  = (string)($_POST['new_password'] ?? '');
        $new2 = (string)($_POST['new_password2'] ?? '');
        if (!password_verify($cur, $me['password_hash'])) {
            $errors[] = 'Текущий пароль указан неверно.';
        }
        if (mb_strlen($new) < 8) {
            $errors[] = 'Новый пароль должен быть не короче 8 символов.';
        }
        if ($new !== $new2) {
            $errors[] = 'Новые пароли не совпадают.';
        }
        if (!$errors) {
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), (int)$me['id']]);
            logAction('password_change', 'user', (int)$me['id']);
            flash('success', 'Пароль изменён.');
            redirect('cabinet/profile.php');
        }
    }
}

require __DIR__ . '/../includes/panel_header.php';
?>

<?php if ($errors): ?>
  <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="card">
  <div class="card-head"><div><h2>Фото профиля</h2><p>Показывается в личном кабинете, на доске почёта и в команде отделения.</p></div></div>
  <div class="card-body" style="display:flex;gap:22px;align-items:center;flex-wrap:wrap;">
    <div class="vk-avatar" style="margin:0;flex:none;">
      <?php if ($me['avatar']): ?>
        <img src="<?= url('uploads/avatars/' . $me['avatar']) ?>" alt="">
      <?php else: ?>
        <span><?= e(mb_substr($me['first_name'], 0, 1) . mb_substr($me['last_name'], 0, 1)) ?></span>
      <?php endif; ?>
    </div>
    <div style="flex:1;min-width:220px;display:flex;flex-direction:column;gap:10px;">
      <form method="post" enctype="multipart/form-data" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="avatar_upload">
        <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" required>
        <button type="submit" class="btn btn-primary btn-sm">Загрузить</button>
      </form>
      <?php if ($me['avatar']): ?>
        <form method="post" style="margin:0;">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="avatar_remove">
          <button type="submit" class="btn btn-outline btn-sm" data-confirm="Удалить фото профиля?">Удалить фото</button>
        </form>
      <?php endif; ?>
      <div class="hint">JPG, PNG или WEBP, до 50 МБ. Фото обрежется по центру до квадрата.</div>
    </div>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-head"><div><h2>Личные данные</h2><p>Эти сведения видит администратор отделения.</p></div></div>
    <div class="card-body">
      <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="profile">
        <div class="field-row">
          <div class="field"><label for="last_name">Фамилия</label><input type="text" id="last_name" name="last_name" value="<?= e($me['last_name']) ?>" required></div>
          <div class="field"><label for="first_name">Имя</label><input type="text" id="first_name" name="first_name" value="<?= e($me['first_name']) ?>" required></div>
        </div>
        <div class="field"><label for="middle_name">Отчество</label><input type="text" id="middle_name" name="middle_name" value="<?= e($me['middle_name']) ?>"></div>
        <div class="field"><label>Электронная почта</label><input type="email" value="<?= e($me['email']) ?>" disabled><div class="hint">Адрес меняет администратор — напишите ему.</div></div>
        <div class="field"><label for="phone">Телефон</label><input type="tel" id="phone" name="phone" value="<?= e($me['phone']) ?>"></div>
        <div class="field-row">
          <div class="field"><label for="vk">ВКонтакте</label><input type="text" id="vk" name="vk" value="<?= e($me['vk']) ?>"></div>
          <div class="field"><label for="telegram">Telegram</label><input type="text" id="telegram" name="telegram" value="<?= e($me['telegram']) ?>"></div>
        </div>
        <div class="field"><label for="school">Школа, колледж или работа</label><input type="text" id="school" name="school" value="<?= e($me['school']) ?>"></div>
        <div class="field"><label for="about">О себе</label><textarea id="about" name="about"><?= e($me['about']) ?></textarea></div>
        <button type="submit" class="btn btn-primary">Сохранить</button>
      </form>
    </div>
  </div>

  <div>
    <div class="card">
      <div class="card-head"><div><h2>Смена пароля</h2></div></div>
      <div class="card-body">
        <form method="post">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="password">
          <div class="field"><label for="current_password">Текущий пароль</label><input type="password" id="current_password" name="current_password" required></div>
          <div class="field"><label for="new_password">Новый пароль</label><input type="password" id="new_password" name="new_password" required minlength="8"><div class="hint">Не короче 8 символов.</div></div>
          <div class="field"><label for="new_password2">Повторите новый пароль</label><input type="password" id="new_password2" name="new_password2" required minlength="8"></div>
          <button type="submit" class="btn btn-primary">Изменить пароль</button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><div><h2>Учётная запись</h2></div></div>
      <div class="card-body">
        <table class="kv">
          <tr><th>Статус</th><td><span class="tag tag-approved">одобрена</span></td></tr>
          <tr><th>Роль</th><td><?= e(match ($me['role']) { 'dev' => 'разработчик', 'admin' => 'администратор', default => 'волонтёр' }) ?></td></tr>
          <tr><th>Позиция</th><td><?= e(positionLabel($me['position'] ?? null)) ?></td></tr>
          <tr><th>Дата регистрации</th><td><?= e(ruDate($me['created_at'])) ?></td></tr>
          <tr><th>Одобрена</th><td><?= e(ruDate($me['approved_at'])) ?></td></tr>
        </table>
      </div>
    </div>

    <div class="card" id="privacy">
      <div class="card-head"><div><h2>Персональные данные</h2><p>Ваши согласия и права по закону о персональных данных</p></div></div>
      <div class="card-body stack">
        <p style="font-size:14px;color:var(--ink-2)">Согласие на обработку дано <?= e(ruDate((string)$me['pd_consent_at'], true)) ?>. Документы: <a href="<?= url('privacy.php') ?>" target="_blank" rel="noopener">Политика</a>, <a href="<?= url('consent.php') ?>" target="_blank" rel="noopener">согласие</a>, <a href="<?= url('consent-public.php') ?>" target="_blank" rel="noopener">согласие на распространение</a>.</p>
        <form method="post" class="switch-form">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="public_consent">
          <input type="hidden" name="value" value="<?= (int)$me['public_consent'] === 1 ? '0' : '1' ?>">
          <div class="switchbox" style="cursor:default">
            <span><b>Показывать имя и фото на сайте</b><small><?= (int)$me['public_consent'] === 1 ? 'Включено: вы можете быть в команде и на доске почёта' : 'Выключено: на открытых страницах вас не видно' ?></small></span>
            <button type="submit" class="btn btn-sm <?= (int)$me['public_consent'] === 1 ? 'btn-outline' : 'btn-primary' ?>"><?= (int)$me['public_consent'] === 1 ? 'Выключить' : 'Включить' ?></button>
          </div>
        </form>
        <form method="post">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="export">
          <button type="submit" class="btn btn-outline btn-sm"><?= icon('download') ?>Скачать мои данные</button>
        </form>
        <details class="danger-details">
          <summary>Отозвать согласие и удалить учётную запись</summary>
          <form method="post" class="stack" style="margin-top:12px">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="delete_account">
            <p style="font-size:14px;color:var(--ink-2)">Удалятся анкета, записи на мероприятия, баллы, достижения и фото. Восстановить их будет нельзя.</p>
            <div class="field"><label for="del_password">Пароль для подтверждения</label><input id="del_password" type="password" name="password" autocomplete="current-password" required></div>
            <div><button type="submit" class="btn btn-danger btn-sm" data-confirm="Удалить учётную запись и все данные безвозвратно?"><?= icon('trash') ?>Удалить учётную запись</button></div>
          </form>
        </details>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/panel_footer.php'; ?>
