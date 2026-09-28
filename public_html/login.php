<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (isLoggedIn()) {
    redirect(homeForRole(userRole()));
}

$pageTitle = 'Вход: Молодая Гвардия Щёлково';
$activeNav = '';
$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $email    = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Заполните оба поля.';
    } else {
        $result = attemptLogin($email, $password);
        if ($result['ok']) {
            $target = $_SESSION['redirect_after_login'] ?? '';
            unset($_SESSION['redirect_after_login']);
            flash('success', 'Здравствуйте, ' . $result['user']['first_name'] . '!');
            redirect($target !== '' ? $target : homeForRole($result['user']['role']));
        }
        $error = $result['error'];
    }
}

require __DIR__ . '/includes/header.php';
?>
<section class="sec form-page">
  <div class="wrap login">
    <nav class="crumbs" aria-label="Навигация"><a href="<?= url('index.php') ?>">Главная</a><span>/</span><span>Вход</span></nav>
    <h1 class="phead-title display">Вход</h1>
    <p class="phead-lead">Личный кабинет волонтёра, панель администратора и&nbsp;разработчика.</p>
    <form class="vform login-form" method="post" novalidate data-validate>
      <?= csrfField() ?>
      <?php if ($error): ?>
        <div class="alert alert-error" role="alert"><?= e($error) ?></div>
      <?php endif; ?>
      <div class="field">
        <label for="email">Электронная почта</label>
        <input type="email" id="email" name="email" value="<?= e($email) ?>" required autofocus
               inputmode="email" autocomplete="username" autocapitalize="off" spellcheck="false">
        <p class="err">Укажи почту</p>
      </div>
      <div class="field">
        <label for="password">Пароль</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
        <p class="err">Укажи пароль</p>
      </div>
      <button type="submit" class="btn btn-accent btn-lg btn-block">Войти<?= icon('arrow-right') ?></button>
      <?php if (setting('registration_open', '1') === '1'): ?>
        <p class="form-foot">Ещё не с&nbsp;нами? <a href="<?= url('register.php') ?>">Заполнить анкету</a></p>
      <?php endif; ?>
    </form>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
