<?php
/** Шапка публичной части. Перед подключением задайте $pageTitle и $activeNav. */
require_once __DIR__ . '/icons.php';
$pageTitle = $pageTitle ?? 'Молодая Гвардия · Щёлково';
$activeNav = $activeNav ?? '';
$me        = currentUser();
$navItems  = [
    ''            => ['Главная',      'index.php'],
    'about'       => ['О нас',        'about.php'],
    'news'        => ['Новости',      'news.php'],
    'events'      => ['Мероприятия',  'events.php'],
    'gallery'     => ['Фотогалерея',  'gallery.php'],
    'contacts'    => ['Контакты',     'contacts.php'],
];
$metaDescription = 'Местное отделение «Молодой Гвардии Единой России» в Щёлковском городском округе: волонтёрские проекты, мероприятия, новости и приём в движение.';
?><!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($metaDescription) ?>">
<meta name="theme-color" content="#0A1E45">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Молодая Гвардия · Щёлково">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e($metaDescription) ?>">
<meta property="og:image" content="<?= url('assets/img/favicon/icon-512.png') ?>">
<meta name="twitter:card" content="summary">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('assets/css/site.css') ?>">
<link rel="icon" href="<?= url('favicon.ico') ?>" sizes="any">
<link rel="icon" href="<?= url('assets/img/favicon/favicon-32.png') ?>" type="image/png" sizes="32x32">
<link rel="apple-touch-icon" href="<?= url('assets/img/favicon/apple-touch-icon.png') ?>">
</head>
<body>
<a class="skip-link" href="#main">Перейти к содержимому</a>

<div class="header-utility">
  <div class="container">
    <span class="org-label">Местное отделение · Щёлковский городской округ</span>
    <?php if ($me): ?>
      <a href="<?= url(homeForRole($me['role'])) ?>"><?= e($me['first_name']) ?> — личный кабинет</a>
      <a href="<?= url('logout.php') ?>">Выйти</a>
    <?php else: ?>
      <a href="<?= url('login.php') ?>">Вход</a>
      <a href="<?= url('register.php') ?>">Регистрация</a>
    <?php endif; ?>
  </div>
</div>

<header class="site-header">
  <div class="container">
    <div class="header-shell">
      <div class="header-main">
        <a href="<?= url('index.php') ?>" class="brand">
          <img src="<?= url('assets/img/logo.webp') ?>" alt="Молодая Гвардия Щёлково">
        </a>
        <div class="header-tools">
          <?php if ($me): ?>
            <a href="<?= url(homeForRole($me['role'])) ?>" class="btn-join">Личный кабинет</a>
          <?php else: ?>
            <a href="<?= url('login.php') ?>" class="btn-login">Вход</a>
            <a href="<?= url('register.php') ?>" class="btn-join">Стать волонтёром</a>
          <?php endif; ?>
        </div>
      </div>
      <nav class="main-nav" aria-label="Основное меню">
        <button class="nav-toggle" id="navToggle" aria-expanded="false" aria-controls="mainMenu">
          <?= icon('menu') ?> Меню
        </button>
        <ul id="mainMenu">
          <?php foreach ($navItems as $key => [$label, $href]): ?>
            <li><a href="<?= url($href) ?>" class="<?= $activeNav === $key ? 'is-active' : '' ?>"><?= e($label) ?></a></li>
          <?php endforeach; ?>
          <li><a class="nav-cta" href="<?= url($me ? homeForRole($me['role']) : 'register.php') ?>">
            <?= icon('arrow-right') ?><?= $me ? 'Личный кабинет' : 'Стать волонтёром' ?>
          </a></li>
        </ul>
      </nav>
    </div>
  </div>
</header>

<?php $flashes = takeFlash(); ?>
<?php if ($flashes): ?>
  <div class="container" style="padding-top:20px;">
    <?php foreach ($flashes as $f): ?>
      <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<main id="main">
