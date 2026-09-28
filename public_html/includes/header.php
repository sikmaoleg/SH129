<?php
/**
 * Шапка публичной части. Перед подключением задайте $pageTitle и $activeNav.
 * $navCta: 'home' — кнопка «Стать волонтёром» в шапке появляется после первого экрана,
 *          'none' — не показывать (страница анкеты), иначе показывается всегда.
 */
require_once __DIR__ . '/icons.php';
$pageTitle = $pageTitle ?? 'Молодая Гвардия Щёлково';
$activeNav = $activeNav ?? '';
$navCta    = $navCta ?? 'always';
$me        = currentUser();
$navItems  = [
    'about'    => ['О нас',       'about.php'],
    'team'     => ['Команда',     'team.php'],
    'news'     => ['Новости',     'news.php'],
];
if ($me) {
    $navItems['events'] = ['Мероприятия', 'events.php'];
}
$navItems['gallery']  = ['Фотогалерея', 'gallery.php'];
$navItems['contacts'] = ['Контакты',    'contacts.php'];
$metaDescription = $metaDescription ?? 'Местное отделение «Молодой Гвардии Единой России» в Щёлковском городском округе: люди, идеи и дела молодёжи округа.';
$showCta = !$me && $navCta !== 'none' && setting('registration_open', '1') === '1';
?><!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($metaDescription) ?>">
<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="#F1F3F9" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#070B1F" media="(prefers-color-scheme: dark)">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Молодая Гвардия Щёлково">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e($metaDescription) ?>">
<meta property="og:image" content="<?= url('assets/img/favicon/icon-512.png') ?>">
<meta name="twitter:card" content="summary">
<script>
  document.documentElement.classList.add('js');
  try { var t = localStorage.getItem('mg-theme'); if (t === 'light' || t === 'dark') document.documentElement.dataset.theme = t; } catch (e) {}
</script>
<link rel="preload" href="<?= url('assets/fonts/alumni-sans-cyrillic.woff2') ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= url('assets/fonts/onest-cyrillic.woff2') ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= assetUrl('assets/css/fonts.css') ?>">
<link rel="stylesheet" href="<?= assetUrl('assets/css/site.css') ?>">
<link rel="icon" href="<?= url('favicon.ico') ?>" sizes="any">
<link rel="icon" href="<?= url('assets/img/favicon/favicon-32.png') ?>" type="image/png" sizes="32x32">
<link rel="apple-touch-icon" href="<?= url('assets/img/favicon/apple-touch-icon.png') ?>">
</head>
<body class="page-<?= e($activeNav !== '' ? $activeNav : ($navCta === 'home' ? 'home' : 'other')) ?>">
<a class="skip-link" href="#main">Перейти к содержимому</a>

<header class="nav <?= $navCta === 'home' ? '' : 'inner' ?>" id="nav">
  <div class="wrap nav-row">
    <a class="brand" href="<?= url('index.php') ?>" aria-label="Молодая Гвардия Щёлково, на главную"><span class="logo"></span></a>
    <nav class="nav-links" aria-label="Основное меню">
      <?php foreach ($navItems as $key => [$label, $href]): ?>
        <a href="<?= url($href) ?>" class="<?= $activeNav === $key ? 'is-active' : '' ?>" <?= $activeNav === $key ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="nav-actions">
      <button class="icon-btn" id="themeBtn" type="button" aria-label="Сменить тему оформления"><span class="ico-moon"><?= icon('moon') ?></span><span class="ico-sun"><?= icon('sun') ?></span></button>
      <?php if ($me): ?>
        <a class="nav-login" href="<?= url(homeForRole($me['role'])) ?>"><?= icon('user-circle') ?><span>Кабинет</span></a>
        <a class="icon-btn" href="<?= url('logout.php') ?>" aria-label="Выйти"><?= icon('sign-out') ?></a>
      <?php else: ?>
        <a class="nav-login" href="<?= url('login.php') ?>"><?= icon('sign-in') ?><span>Войти</span></a>
      <?php endif; ?>
      <?php if ($showCta): ?>
        <div class="nav-cta-wrap"><div><a class="btn btn-accent nav-cta" href="<?= url('register.php') ?>">Стать волонтёром</a></div></div>
      <?php endif; ?>
      <button class="icon-btn menu-btn" type="button" id="menuBtn" aria-expanded="false" aria-controls="menu" aria-label="Открыть меню"><span class="ico-list"><?= icon('menu') ?></span><span class="ico-x"><?= icon('close') ?></span></button>
    </div>
  </div>
</header>

<div class="menu" id="menu">
  <?php foreach ($navItems as $key => [$label, $href]): ?>
    <a class="big <?= $activeNav === $key ? 'is-active' : '' ?>" href="<?= url($href) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
  <?php if ($me): ?>
    <div class="menu-extra">
      <a href="<?= url(homeForRole($me['role'])) ?>"><?= icon('user-circle') ?>Личный кабинет</a>
      <a href="<?= url('logout.php') ?>"><?= icon('sign-out') ?>Выйти</a>
    </div>
  <?php elseif ($showCta): ?>
    <a class="btn btn-accent btn-lg" href="<?= url('register.php') ?>">Стать волонтёром<?= icon('arrow-right') ?></a>
  <?php endif; ?>
</div>

<?php $flashes = takeFlash(); ?>
<?php if ($flashes): ?>
  <div class="wrap flash-wrap" role="status">
    <?php foreach ($flashes as $f): ?>
      <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<main id="main">
