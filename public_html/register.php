<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (isLoggedIn()) {
    redirect(homeForRole(userRole()));
}
if (setting('registration_open', '1') !== '1') {
    $closed = true;
}

$pageTitle = 'Стать волонтёром — Молодая Гвардия Щёлково';
$activeNav = '';

$errors = [];
$done   = false;
$old    = [
    'last_name' => '', 'first_name' => '', 'middle_name' => '', 'email' => '',
    'phone' => '', 'birth_date' => '', 'vk' => '', 'telegram' => '', 'school' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($closed)) {
    csrfCheck();

    foreach ($old as $k => $_) {
        $old[$k] = trim((string)($_POST[$k] ?? ''));
    }
    $password = (string)($_POST['password'] ?? '');
    $password2 = (string)($_POST['password2'] ?? '');
    $agree     = !empty($_POST['agree']);

    // --- Проверки ---
    if ($old['last_name'] === '')  $errors[] = 'Укажите фамилию.';
    if ($old['first_name'] === '') $errors[] = 'Укажите имя.';

    $old['email'] = mb_strtolower($old['email']);
    if ($old['email'] === '') {
        $errors[] = 'Укажите электронную почту.';
    } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Электронная почта указана в неверном формате.';
    } elseif (fetchValue('SELECT id FROM users WHERE email = ?', [$old['email']])) {
        $errors[] = 'Этот адрес уже зарегистрирован. Попробуйте войти.';
    }

    if ($old['phone'] === '') {
        $errors[] = 'Укажите номер телефона — по нему с вами свяжется координатор.';
    }

    if ($old['birth_date'] === '') {
        $errors[] = 'Укажите дату рождения.';
    } else {
        $ts = strtotime($old['birth_date']);
        if (!$ts || $ts > time()) {
            $errors[] = 'Дата рождения указана неверно.';
        } else {
            $age = (int)((new DateTime($old['birth_date']))->diff(new DateTime())->y);
            if ($age < 14) {
                $errors[] = 'Вступить в движение можно с 14 лет.';
            }
            if ($age > 100) {
                $errors[] = 'Проверьте дату рождения.';
            }
        }
    }

    if (mb_strlen($password) < 8) {
        $errors[] = 'Пароль должен быть не короче 8 символов.';
    }
    if ($password !== $password2) {
        $errors[] = 'Пароли не совпадают.';
    }
    if (!$agree) {
        $errors[] = 'Нужно согласие на обработку персональных данных.';
    }

    // --- Сохранение ---
    if (!$errors) {
        q('INSERT INTO users (email, password_hash, last_name, first_name, middle_name,
                              phone, birth_date, vk, telegram, school, role, status)
           VALUES (?,?,?,?,?,?,?,?,?,?,\'volunteer\',\'pending\')', [
            $old['email'],
            password_hash($password, PASSWORD_DEFAULT),
            $old['last_name'],
            $old['first_name'],
            $old['middle_name'] ?: null,
            $old['phone'],
            $old['birth_date'],
            $old['vk'] ?: null,
            $old['telegram'] ?: null,
            $old['school'] ?: null,
        ]);
        $newId = (int)db()->lastInsertId();
        logAction('register', 'user', $newId, $old['email']);
        $done = true;
    }
}

require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <div class="container">
    <div class="breadcrumbs"><a href="<?= url('index.php') ?>">Главная</a> / Стать волонтёром</div>
    <h1>Заявка на вступление в движение</h1>
  </div>
</div>

<section class="section">
  <div class="container">
    <?php if (!empty($closed)): ?>

      <div class="form-card form-narrow">
        <div class="alert alert-warn">Приём заявок временно закрыт. Следите за новостями отделения — мы сообщим, когда он откроется снова.</div>
        <a href="<?= url('index.php') ?>" class="btn btn-outline btn-block">На главную</a>
      </div>

    <?php elseif ($done): ?>

      <div class="form-card form-narrow">
        <h1>Заявка отправлена</h1>
        <div class="alert alert-success">
          Спасибо! Ваша анкета передана координатору отделения.
        </div>
        <p class="form-intro">
          Учётная запись создана, но вход в личный кабинет откроется после того, как администратор одобрит заявку.
          Обычно проверка занимает один-два дня. Координатор может связаться с вами по указанному телефону.
        </p>
        <a href="<?= url('index.php') ?>" class="btn btn-primary btn-block">Вернуться на главную</a>
      </div>

    <?php else: ?>

      <div class="form-card form-wide">
        <h1>Анкета волонтёра</h1>
        <p class="form-intro">Заполните поля со звёздочкой. После отправки анкету проверит координатор отделения — доступ в личный кабинет откроется после одобрения.</p>

        <?php if ($errors): ?>
          <div class="alert alert-error">
            Проверьте заполнение анкеты:
            <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
          </div>
        <?php endif; ?>

        <form method="post" novalidate>
          <?= csrfField() ?>

          <div class="field-row">
            <div class="field">
              <label for="last_name">Фамилия <span class="req">*</span></label>
              <input type="text" id="last_name" name="last_name" value="<?= e($old['last_name']) ?>" required>
            </div>
            <div class="field">
              <label for="first_name">Имя <span class="req">*</span></label>
              <input type="text" id="first_name" name="first_name" value="<?= e($old['first_name']) ?>" required>
            </div>
          </div>

          <div class="field-row">
            <div class="field">
              <label for="middle_name">Отчество</label>
              <input type="text" id="middle_name" name="middle_name" value="<?= e($old['middle_name']) ?>">
            </div>
            <div class="field">
              <label for="birth_date">Дата рождения <span class="req">*</span></label>
              <input type="date" id="birth_date" name="birth_date" value="<?= e($old['birth_date']) ?>" required>
              <div class="hint">Вступить в движение можно с 14 лет.</div>
            </div>
          </div>

          <div class="field-row">
            <div class="field">
              <label for="email">Электронная почта <span class="req">*</span></label>
              <input type="email" id="email" name="email" value="<?= e($old['email']) ?>" required>
              <div class="hint">Он же логин для входа в личный кабинет.</div>
            </div>
            <div class="field">
              <label for="phone">Телефон <span class="req">*</span></label>
              <input type="tel" id="phone" name="phone" value="<?= e($old['phone']) ?>" placeholder="+7 900 000-00-00" required>
            </div>
          </div>

          <div class="field-row">
            <div class="field">
              <label for="vk">Страница ВКонтакте</label>
              <input type="text" id="vk" name="vk" value="<?= e($old['vk']) ?>" placeholder="vk.com/username">
            </div>
            <div class="field">
              <label for="telegram">Telegram</label>
              <input type="text" id="telegram" name="telegram" value="<?= e($old['telegram']) ?>" placeholder="@username">
            </div>
          </div>

          <div class="field">
            <label for="school">Школа, колледж или место работы</label>
            <input type="text" id="school" name="school" value="<?= e($old['school']) ?>">
          </div>

          <div class="field-row">
            <div class="field">
              <label for="password">Пароль <span class="req">*</span></label>
              <input type="password" id="password" name="password" required minlength="8">
              <div class="hint">Не короче 8 символов.</div>
            </div>
            <div class="field">
              <label for="password2">Повторите пароль <span class="req">*</span></label>
              <input type="password" id="password2" name="password2" required minlength="8">
            </div>
          </div>

          <div class="field">
            <label class="field-check">
              <input type="checkbox" name="agree" value="1" required>
              <span>Согласен на обработку персональных данных для целей работы волонтёрского отделения <span class="req">*</span></span>
            </label>
          </div>

          <button type="submit" class="btn btn-accent btn-block">Отправить заявку</button>
        </form>

        <p class="form-foot">Уже состоите в движении? <a href="<?= url('login.php') ?>">Войти в личный кабинет</a></p>
      </div>

    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
