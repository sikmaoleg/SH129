<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (isLoggedIn()) {
    redirect(homeForRole(userRole()));
}
if (setting('registration_open', '1') !== '1') {
    $closed = true;
}

$pageTitle = 'Стать волонтёром: Молодая Гвардия Щёлково';
$navCta    = 'none';
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
        $errors[] = 'Укажите номер телефона — по нему с вами свяжется администратор.';
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
                $errors[] = 'Вступить в организацию можно с 14 лет.';
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

/** Поле анкеты: подпись сверху, подсказка и текст ошибки снизу. */
$field = function (string $name, string $label, array $opt = []) use ($old): string {
    $req  = !empty($opt['required']);
    $type = $opt['type'] ?? 'text';
    $attrs = '';
    foreach (($opt['attrs'] ?? []) as $k => $v) {
        $attrs .= ' ' . $k . '="' . e($v) . '"';
    }
    $value = in_array($type, ['password'], true) ? '' : ' value="' . e($old[$name] ?? '') . '"';
    return '<div class="field"><label for="' . $name . '">' . e($label) . ($req ? ' <i>*</i>' : '') . '</label>'
         . '<input type="' . $type . '" id="' . $name . '" name="' . $name . '"' . $value . ($req ? ' required' : '') . $attrs . '>'
         . (!empty($opt['hint']) ? '<p class="hint">' . e($opt['hint']) . '</p>' : '')
         . (!empty($opt['err']) ? '<p class="err">' . e($opt['err']) . '</p>' : '')
         . '</div>';
};
?>
<section class="sec form-page">
  <div class="wrap form-grid">
    <div class="form-side">
      <nav class="crumbs" aria-label="Навигация"><a href="<?= url('index.php') ?>">Главная</a><span>/</span><span>Стать волонтёром</span></nav>
      <h1 class="phead-title display">Анкета <em class="stamp">волонтёра</em></h1>
      <p class="phead-lead">Принимаем жителей Щёлковского округа от&nbsp;14&nbsp;лет. Администратор отделения проверит анкету и&nbsp;свяжется с&nbsp;тобой.</p>
      <p class="side-note"><?= icon('clock') ?>Заполнение займёт пару минут. Поля со&nbsp;звёздочкой обязательны.</p>
    </div>

    <?php if (!empty($closed)): ?>
      <div class="form-done">
        <span class="done-ico"><?= icon('hourglass') ?></span>
        <h2 class="display">Приём заявок закрыт</h2>
        <p>Приём заявок временно приостановлен. Следите за&nbsp;новостями отделения: мы сообщим, когда он откроется снова.</p>
        <a class="link-arrow" href="<?= url('news.php') ?>">Новости отделения<?= icon('arrow-up-right') ?></a>
      </div>
    <?php elseif ($done): ?>
      <div class="form-done" role="status">
        <span class="done-ico"><?= icon('check-circle') ?></span>
        <h2 class="display">Заявка отправлена</h2>
        <p>Спасибо! Анкета передана администратору отделения. Вход в&nbsp;личный кабинет откроется после одобрения, обычно это один-два дня. Администратор может связаться с&nbsp;тобой по&nbsp;указанному телефону.</p>
        <a class="link-arrow" href="<?= url('index.php') ?>">На главную<?= icon('arrow-up-right') ?></a>
      </div>
    <?php else: ?>
      <form class="vform" method="post" novalidate data-validate>
        <?= csrfField() ?>
        <?php if ($errors): ?>
          <div class="alert alert-error" role="alert"><b>Проверь анкету:</b><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <fieldset>
          <legend>О себе</legend>
          <div class="frow">
            <?= $field('last_name', 'Фамилия', ['required' => true, 'err' => 'Укажи фамилию', 'attrs' => ['autocomplete' => 'family-name']]) ?>
            <?= $field('first_name', 'Имя', ['required' => true, 'err' => 'Укажи имя', 'attrs' => ['autocomplete' => 'given-name']]) ?>
          </div>
          <div class="frow">
            <?= $field('middle_name', 'Отчество', ['attrs' => ['autocomplete' => 'additional-name']]) ?>
            <?= $field('birth_date', 'Дата рождения', ['type' => 'date', 'required' => true, 'hint' => 'Вступить можно с 14 лет.', 'err' => 'Укажи дату рождения', 'attrs' => ['max' => date('Y-m-d')]]) ?>
          </div>
        </fieldset>
        <fieldset>
          <legend>Связь</legend>
          <div class="frow">
            <?= $field('email', 'Электронная почта', ['type' => 'email', 'required' => true, 'hint' => 'Она же логин для входа.', 'err' => 'Проверь адрес почты', 'attrs' => ['inputmode' => 'email', 'autocomplete' => 'email', 'autocapitalize' => 'off', 'spellcheck' => 'false']]) ?>
            <?= $field('phone', 'Телефон', ['type' => 'tel', 'required' => true, 'err' => 'Укажи телефон', 'attrs' => ['placeholder' => '+7 900 000-00-00', 'inputmode' => 'tel', 'autocomplete' => 'tel']]) ?>
          </div>
          <div class="frow">
            <?= $field('vk', 'Страница ВКонтакте', ['attrs' => ['placeholder' => 'vk.com/username']]) ?>
            <?= $field('telegram', 'Telegram', ['attrs' => ['placeholder' => '@username']]) ?>
          </div>
          <?= $field('school', 'Школа, колледж или место работы') ?>
        </fieldset>
        <fieldset>
          <legend>Пароль для входа</legend>
          <div class="frow">
            <?= $field('password', 'Пароль', ['type' => 'password', 'required' => true, 'hint' => 'Не короче 8 символов.', 'err' => 'Минимум 8 символов', 'attrs' => ['minlength' => '8', 'autocomplete' => 'new-password']]) ?>
            <?= $field('password2', 'Повтори пароль', ['type' => 'password', 'required' => true, 'err' => 'Пароли не совпадают', 'attrs' => ['minlength' => '8', 'autocomplete' => 'new-password']]) ?>
          </div>
        </fieldset>
        <label class="check"><input type="checkbox" name="agree" value="1" required><span>Согласен на&nbsp;обработку персональных данных для целей работы волонтёрского отделения <i>*</i></span></label>
        <p class="err err-agree">Нужно согласие на&nbsp;обработку данных</p>
        <button class="btn btn-accent btn-lg btn-block" type="submit">Отправить заявку<?= icon('arrow-right') ?></button>
        <p class="form-foot">Уже в&nbsp;организации? <a href="<?= url('login.php') ?>">Войти</a></p>
      </form>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
