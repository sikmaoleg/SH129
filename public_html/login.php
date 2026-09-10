<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (isLoggedIn()) {
    redirect(homeForRole(userRole()));
}

$pageTitle = 'Вход — Молодая Гвардия Щёлково';
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

<div class="page-head">
  <div class="container">
    <div class="breadcrumbs"><a href="<?= url('index.php') ?>">Главная</a> / Вход</div>
    <h1>Вход в личный кабинет</h1>
  </div>
</div>

<section class="section">
  <div class="container">
    <div class="form-card form-narrow">
      <h1>Здравствуйте</h1>
      <p class="form-intro">Введите почту и пароль, указанные при регистрации.</p>

      <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post" novalidate>
        <?= csrfField() ?>
        <div class="field">
          <label for="email">Электронная почта</label>
          <input type="email" id="email" name="email" value="<?= e($email) ?>" required autofocus>
        </div>
        <div class="field">
          <label for="password">Пароль</label>
          <input type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Войти</button>
      </form>

      <p class="form-foot">Ещё не в движении? <a href="<?= url('register.php') ?>">Подать заявку</a></p>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
