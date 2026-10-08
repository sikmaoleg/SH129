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
    'guardian_name' => '', 'guardian_phone' => '',
];
$checks = ['agree_pd' => false, 'agree_public' => false, 'agree_guardian' => false];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($closed)) {
    csrfCheck();
    foreach ($old as $k => $_) {
        $old[$k] = trim((string)($_POST[$k] ?? ''));
    }
    foreach ($checks as $k => $_) {
        $checks[$k] = !empty($_POST[$k]);
    }
    $result = registerVolunteer($old + $checks + [
        'password'  => (string)($_POST['password'] ?? ''),
        'password2' => (string)($_POST['password2'] ?? ''),
    ], 'site');
    if (isset($result['errors'])) {
        $errors = $result['errors'];
    } else {
        $done = true;
    }
}

// Блок родителя показываем сразу, если по дате рождения участнику нет 18
$age = ageFromBirthDate($old['birth_date'] ?: null);
$isMinor = $age !== null && $age < 18;

require __DIR__ . '/includes/header.php';

/** Поле анкеты: подпись сверху, подсказка и текст ошибки снизу. */
$field = function (string $name, string $label, array $opt = []) use ($old, $errors): string {
    $req  = !empty($opt['required']);
    $type = $opt['type'] ?? 'text';
    $attrs = '';
    foreach (($opt['attrs'] ?? []) as $k => $v) {
        $attrs .= ' ' . $k . '="' . e($v) . '"';
    }
    $value = in_array($type, ['password'], true) ? '' : ' value="' . e($old[$name] ?? '') . '"';
    return '<div class="field' . (isset($errors[$name]) ? ' bad' : '') . '"><label for="' . $name . '">' . e($label) . ($req ? ' <i>*</i>' : '') . '</label>'
         . '<input type="' . $type . '" id="' . $name . '" name="' . $name . '"' . $value . ($req ? ' required' : '') . $attrs . '>'
         . (!empty($opt['hint']) ? '<p class="hint">' . e($opt['hint']) . '</p>' : '')
         . (!empty($opt['err']) || isset($errors[$name]) ? '<p class="err">' . e($errors[$name] ?? $opt['err']) . '</p>' : '')
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
          <div class="alert alert-error" role="alert"><b>Проверь анкету:</b><ul><?php foreach (array_unique(array_values($errors)) as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
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
        <fieldset class="guardian" id="guardianBox" <?= $isMinor || isset($errors['guardian_name']) ? '' : 'hidden' ?>>
          <legend>Родитель или законный представитель</legend>
          <p class="guardian-note"><?= icon('info') ?><span>Тебе меньше 18 лет, поэтому анкету подаём с&nbsp;согласия родителя или законного представителя. Покажи ему <a href="<?= url('consent.php') ?>" target="_blank" rel="noopener">согласие</a> и&nbsp;укажи его контакты: администратор может позвонить для подтверждения.</span></p>
          <div class="frow">
            <?= $field('guardian_name', 'ФИО родителя или представителя', ['required' => $isMinor, 'err' => 'Укажи ФИО полностью', 'attrs' => ['autocomplete' => 'off', 'data-guardian' => '1']]) ?>
            <?= $field('guardian_phone', 'Его телефон', ['type' => 'tel', 'required' => $isMinor, 'err' => 'Укажи телефон', 'attrs' => ['inputmode' => 'tel', 'placeholder' => '+7 900 000-00-00', 'data-guardian' => '1']]) ?>
          </div>
          <label class="check"><input type="checkbox" name="agree_guardian" value="1" data-guardian="1" <?= $checks['agree_guardian'] ? 'checked' : '' ?> <?= $isMinor ? 'required' : '' ?>><span>Мой родитель или законный представитель ознакомлен с&nbsp;согласием и&nbsp;Политикой и&nbsp;согласен на&nbsp;обработку моих персональных данных <i>*</i></span></label>
          <p class="err err-check <?= isset($errors['agree_guardian']) ? 'show' : '' ?>" data-for="agree_guardian">Нужно подтверждение родителя или представителя</p>
        </fieldset>

        <fieldset class="consents">
          <legend>Согласия</legend>
          <label class="check"><input type="checkbox" name="agree_pd" value="1" required <?= $checks['agree_pd'] ? 'checked' : '' ?>><span>Даю <a href="<?= url('consent.php') ?>" target="_blank" rel="noopener">согласие на&nbsp;обработку персональных данных</a> и&nbsp;подтверждаю, что ознакомлен(а) с&nbsp;<a href="<?= url('privacy.php') ?>" target="_blank" rel="noopener">Политикой обработки персональных данных</a> <i>*</i></span></label>
          <p class="err err-check <?= isset($errors['agree_pd']) ? 'show' : '' ?>" data-for="agree_pd">Без этого согласия мы не можем принять анкету</p>
          <label class="check"><input type="checkbox" name="agree_public" value="1" <?= $checks['agree_public'] ? 'checked' : '' ?>><span>Разрешаю показывать моё имя и&nbsp;фото на&nbsp;сайте, в&nbsp;команде и&nbsp;на&nbsp;доске почёта (<a href="<?= url('consent-public.php') ?>" target="_blank" rel="noopener">согласие на&nbsp;распространение</a>). Необязательно, можно изменить в&nbsp;личном кабинете.</span></label>
        </fieldset>
        <button class="btn btn-accent btn-lg btn-block" type="submit">Отправить заявку<?= icon('arrow-right') ?></button>
        <p class="form-foot">Уже в&nbsp;организации? <a href="<?= url('login.php') ?>">Войти</a></p>
      </form>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
