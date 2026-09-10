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

<div class="grid-2">
  <div class="card">
    <div class="card-head"><div><h2>Личные данные</h2><p>Эти сведения видит координатор отделения.</p></div></div>
    <div class="card-body">
      <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="profile">
        <div class="field-row">
          <div class="field"><label for="last_name">Фамилия</label><input type="text" id="last_name" name="last_name" value="<?= e($me['last_name']) ?>" required></div>
          <div class="field"><label for="first_name">Имя</label><input type="text" id="first_name" name="first_name" value="<?= e($me['first_name']) ?>" required></div>
        </div>
        <div class="field"><label for="middle_name">Отчество</label><input type="text" id="middle_name" name="middle_name" value="<?= e($me['middle_name']) ?>"></div>
        <div class="field"><label>Электронная почта</label><input type="email" value="<?= e($me['email']) ?>" disabled><div class="hint">Адрес меняет администратор — напишите координатору.</div></div>
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
  </div>
</div>

<?php require __DIR__ . '/../includes/panel_footer.php'; ?>
