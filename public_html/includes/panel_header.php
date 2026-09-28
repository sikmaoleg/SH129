<?php
/**
 * Общий каркас всех панелей.
 * Перед подключением задайте: $panelTitle, $panelSection ('cabinet'|'admin'|'dev'), $activeItem.
 * Необязательно: $panelLead (подзаголовок под названием страницы), $panelActions (HTML кнопок справа),
 * $panelCrumb (['Название', 'ссылка'] — промежуточный пункт в хлебных крошках).
 */
require_once __DIR__ . '/icons.php';
$me           = currentUser();
$panelTitle   = $panelTitle ?? 'Панель';
$panelSection = $panelSection ?? 'cabinet';
$activeItem   = $activeItem ?? '';
$panelLead    = $panelLead ?? '';
$panelActions = $panelActions ?? '';
$panelCrumb   = $panelCrumb ?? null;
$pageTitle    = $pageTitle ?? ($panelTitle . ': Молодая Гвардия Щёлково');

// Счётчики «требует внимания» в меню
$pendingCount = 0;
$needMarksCount = 0;
if (isAdmin()) {
    $pendingCount   = (int)fetchValue("SELECT COUNT(*) FROM users WHERE status = 'pending'");
    $needMarksCount = (int)fetchValue("SELECT COUNT(*) FROM events WHERE status = 'published' AND starts_at < NOW()");
}
$devIssues = 0;
if (isDev()) {
    global $config;
    $devIssues += !empty($config['debug']) ? 1 : 0;
    $devIssues += empty($_SERVER['HTTPS']) ? 1 : 0;
    $devIssues += is_dir(APP_ROOT . '/install') ? 1 : 0;
    $devIssues += (($config['dev_key'] ?? '') === 'change-me-please') ? 1 : 0;
}

$sections = [
    'cabinet' => ['Личный кабинет', 'cabinet/index.php', [
        ['Кабинет', [
            ['index',   'Обзор',           'cabinet/index.php',   'grid', 0],
            ['events',  'Мои мероприятия', 'cabinet/events.php',  'calendar-check', 0],
            ['rating',  'Рейтинг',         'cabinet/rating.php',  'trophy', 0],
            ['badges',  'Достижения',      'cabinet/badges.php',  'medal', 0],
            ['profile', 'Мои данные',      'cabinet/profile.php', 'user-circle', 0],
        ]],
    ]],
    'admin' => ['Администратор', 'admin/index.php', [
        ['Главное', [
            ['index',        'Обзор',     'admin/index.php',        'grid', 0],
            ['applications', 'Заявки',    'admin/applications.php', 'user-plus', $pendingCount],
            ['users',        'Волонтёры', 'admin/users.php',        'users', 0],
        ]],
        ['Работа', [
            ['events', 'Мероприятия',     'admin/events.php', 'calendar-check', $needMarksCount],
            ['news',   'Новости',         'admin/news.php',   'newspaper', 0],
            ['hero',   'Фото на главной', 'admin/hero.php',   'image', 0],
        ]],
        ['Мотивация', [
            ['points', 'Баллы',        'admin/points.php', 'star', 0],
            ['rating', 'Рейтинг',      'admin/rating.php', 'trophy', 0],
            ['badges', 'Достижения',   'admin/badges.php', 'medal', 0],
            ['honor',  'Доска почёта', 'admin/honor.php',  'crown', 0],
        ]],
        ['Отчёты', [
            ['analytics', 'Аналитика', 'admin/analytics.php', 'chart-bar', 0],
        ]],
    ]],
    'dev' => ['Разработчик', 'dev/index.php', [
        ['Система', [
            ['index',    'Состояние системы', 'dev/index.php',    'heartbeat', $devIssues],
            ['database', 'База данных',       'dev/database.php', 'database', 0],
            ['logs',     'Журнал действий',   'dev/logs.php',     'clipboard', 0],
        ]],
        ['Настройка', [
            ['settings', 'Настройки сайта', 'dev/settings.php', 'gear', 0],
            ['telegram', 'Telegram',        'dev/telegram.php', 'telegram', 0],
        ]],
    ]],
];
[$sectionTitle, $sectionHome, $groups] = $sections[$panelSection];

// Переключатель разделов — только если у человека больше одной панели
$switch = [];
if (isAdmin()) { $switch['admin'] = ['Администратор', 'admin/index.php']; }
if (isDev())   { $switch['dev']   = ['Разработчик', 'dev/index.php']; }

// Данные для поиска (Ctrl+K): разделы всех доступных панелей и частые действия
$paletteItems = [];
foreach ($sections as $secKey => [$secTitle, , $secGroups]) {
    if (($secKey === 'admin' && !isAdmin()) || ($secKey === 'dev' && !isDev())) {
        continue;
    }
    foreach ($secGroups as [, $items]) {
        foreach ($items as [, $label, $href, $ic]) {
            $paletteItems[] = ['g' => 'Разделы', 't' => $label, 's' => $secTitle, 'i' => icon($ic), 'u' => url($href)];
        }
    }
}
if (isAdmin()) {
    foreach ([['Создать мероприятие', 'admin/events.php?new=1', 'calendar-check'], ['Написать новость', 'admin/news.php?new=1', 'newspaper'],
              ['Начислить баллы', 'admin/points.php', 'star'], ['Отметить участие', 'admin/events.php?filter=past', 'list-checks']] as [$t, $u, $ic]) {
        $paletteItems[] = ['g' => 'Действия', 't' => $t, 's' => '', 'i' => icon($ic), 'u' => url($u)];
    }
}
if (isDev()) {
    $paletteItems[] = ['g' => 'Действия', 't' => 'Синхронизировать Telegram', 's' => '', 'i' => icon('refresh'), 'u' => url('dev/telegram.php')];
}
$paletteSearch = isAdmin() ? url('admin/users.php') : '';

$meName = $me ? trim($me['first_name'] . ' ' . $me['last_name']) : '';
$meInitials = $me ? mb_substr($me['first_name'], 0, 1) . mb_substr($me['last_name'], 0, 1) : '';
$roleLabel = match ($me['role'] ?? '') { 'dev' => 'разработчик', 'admin' => 'администратор', default => 'волонтёр' };

// Профиль волонтёра над содержимым личного кабинета
$vkProfile = null;
if ($panelSection === 'cabinet' && $me) {
    $vkAttended = (int)fetchValue(
        "SELECT COUNT(*) FROM event_registrations WHERE user_id = ? AND status = 'attended'", [(int)$me['id']]
    );
    $vkProfile = [
        'name'     => trim($me['last_name'] . ' ' . $me['first_name']),
        'position' => positionLabel($me['position'] ?? null),
        'attended' => $vkAttended,
        'points'   => (int)$me['points'],
        'initials' => $meInitials,
    ];
}
?><!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="robots" content="noindex, nofollow">
<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="#0B1233">
<script>
  document.documentElement.classList.add('js');
  try { var t = localStorage.getItem('mg-theme'); if (t === 'light' || t === 'dark') document.documentElement.dataset.theme = t; } catch (e) {}
</script>
<link rel="preload" href="<?= url('assets/fonts/onest-cyrillic.woff2') ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= assetUrl('assets/css/fonts.css') ?>">
<link rel="stylesheet" href="<?= assetUrl('assets/css/panel.css') ?>">
<link rel="icon" href="<?= url('favicon.ico') ?>" sizes="any">
<link rel="icon" href="<?= url('assets/img/favicon/favicon-32.png') ?>" type="image/png" sizes="32x32">
<link rel="apple-touch-icon" href="<?= url('assets/img/favicon/apple-touch-icon.png') ?>">
</head>
<body class="section-<?= e($panelSection) ?>">
<a class="skip-link" href="#panel-main">Перейти к содержимому</a>
<div class="app">

  <aside class="side" id="side" aria-label="Меню панели">
    <div class="side-top">
      <a class="side-logo" href="<?= url($sectionHome) ?>" aria-label="Молодая Гвардия Щёлково: <?= e($sectionTitle) ?>"><span class="logo"></span></a>
      <button class="icon-btn side-close" type="button" id="sideClose" aria-label="Закрыть меню"><?= icon('close') ?></button>
    </div>
    <?php if (count($switch) > 1 && $panelSection !== 'cabinet'): ?>
      <nav class="switch" aria-label="Раздел панели">
        <?php foreach ($switch as $key => [$label, $href]): ?>
          <a href="<?= url($href) ?>" class="<?= $panelSection === $key ? 'on' : '' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
      </nav>
    <?php else: ?>
      <p class="side-title"><?= e($sectionTitle) ?></p>
    <?php endif; ?>
    <nav class="side-nav" aria-label="<?= e($sectionTitle) ?>">
      <?php foreach ($groups as [$groupTitle, $items]): ?>
        <div class="side-group">
          <?php if (count($groups) > 1): ?><p><?= e($groupTitle) ?></p><?php endif; ?>
          <?php foreach ($items as [$key, $label, $href, $ic, $count]): ?>
            <a href="<?= url($href) ?>" class="<?= $activeItem === $key ? 'on' : '' ?>" <?= $activeItem === $key ? 'aria-current="page"' : '' ?>>
              <?= icon($ic) ?><span><?= e($label) ?></span>
              <?php if ($count > 0): ?><span class="count" aria-label="требуют внимания: <?= (int)$count ?>"><?= (int)$count ?></span><?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
      <?php if ($panelSection === 'cabinet' && $switch): ?>
        <div class="side-group"><p>Управление</p>
          <?php foreach ($switch as $key => [$label, $href]): ?>
            <a href="<?= url($href) ?>"><?= icon($key === 'dev' ? 'heartbeat' : 'shield-check') ?><span>Панель: <?= e(mb_strtolower($label)) ?></span></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </nav>
    <div class="side-foot">
      <div class="me">
        <span class="ava ava-me">
          <?php if (!empty($me['avatar'])): ?><img src="<?= url('uploads/avatars/' . $me['avatar']) ?>" alt=""><?php else: ?><?= e($meInitials) ?><?php endif; ?>
        </span>
        <div><b><?= e($meName) ?></b><span><?= e($roleLabel) ?></span></div>
      </div>
      <?php if ($panelSection !== 'cabinet'): ?>
        <a class="side-cabinet" href="<?= url('cabinet/index.php') ?>"><?= icon('user-circle') ?>Мой личный кабинет</a>
      <?php endif; ?>
      <div class="side-links">
        <a href="<?= url('index.php') ?>"><?= icon('external') ?>На сайт</a>
        <a href="<?= url('logout.php') ?>"><?= icon('sign-out') ?>Выйти</a>
      </div>
    </div>
  </aside>
  <div class="scrim" id="scrim" hidden></div>

  <div class="main">
    <header class="top">
      <button class="icon-btn menu-btn" type="button" id="menuBtn" aria-label="Открыть меню" aria-controls="side" aria-expanded="false"><?= icon('menu') ?></button>
      <nav class="crumbs" aria-label="Навигация">
        <a href="<?= url($sectionHome) ?>"><?= e($sectionTitle) ?></a>
        <?php if ($panelCrumb): ?><span class="sep">/</span><a href="<?= url($panelCrumb[1]) ?>"><?= e($panelCrumb[0]) ?></a><?php endif; ?>
        <span class="sep">/</span><b><?= e($panelTitle) ?></b>
      </nav>
      <div class="top-actions">
        <button class="search-btn" type="button" id="searchBtn" aria-label="Поиск по панели"><?= icon('search') ?><span><?= isAdmin() ? 'Найти волонтёра, раздел или действие' : 'Найти раздел' ?></span><kbd>Ctrl K</kbd></button>
        <?php if (isAdmin()): ?>
          <div class="create">
            <button class="btn btn-accent" type="button" id="createBtn" aria-haspopup="true" aria-expanded="false"><?= icon('plus') ?><span>Создать</span></button>
            <div class="menu-pop" id="createMenu" hidden>
              <a href="<?= url('admin/events.php?new=1') ?>"><?= icon('calendar-check') ?>Мероприятие</a>
              <a href="<?= url('admin/news.php?new=1') ?>"><?= icon('newspaper') ?>Новость</a>
              <a href="<?= url('admin/points.php') ?>"><?= icon('star') ?>Начисление баллов</a>
              <a href="<?= url('admin/badges.php?new=1') ?>"><?= icon('medal') ?>Достижение</a>
            </div>
          </div>
        <?php endif; ?>
        <button class="icon-btn" type="button" id="themeBtn" aria-label="Сменить тему оформления"><span class="ico-moon"><?= icon('moon') ?></span><span class="ico-sun"><?= icon('sun') ?></span></button>
      </div>
    </header>

    <main class="view" id="panel-main" tabindex="-1">
      <?php if ($vkProfile): ?>
        <div class="profile-head">
          <div class="profile-ava">
            <?php if (!empty($me['avatar'])): ?><img src="<?= url('uploads/avatars/' . $me['avatar']) ?>" alt=""><?php else: ?><span><?= e($vkProfile['initials']) ?></span><?php endif; ?>
          </div>
          <div>
            <p class="profile-pos"><?= e($vkProfile['position']) ?></p>
            <h1 class="pg-title display"><?= e($vkProfile['name']) ?></h1>
            <p class="profile-stats">
              <a href="<?= url('cabinet/events.php') ?>"><b><?= $vkProfile['attended'] ?></b> <?= plural($vkProfile['attended'], 'мероприятие', 'мероприятия', 'мероприятий') ?></a>
              <a href="<?= url('cabinet/rating.php') ?>"><b><?= $vkProfile['points'] ?></b> <?= plural($vkProfile['points'], 'балл', 'балла', 'баллов') ?></a>
            </p>
          </div>
        </div>
      <?php else: ?>
        <div class="pg-head">
          <div>
            <h1 class="pg-title display"><?= e($panelTitle) ?></h1>
            <?php if ($panelLead !== ''): ?><p class="pg-sub"><?= $panelLead ?></p><?php endif; ?>
          </div>
          <?php if ($panelActions !== ''): ?><div class="pg-actions"><?= $panelActions ?></div><?php endif; ?>
        </div>
      <?php endif; ?>

      <?php foreach (takeFlash() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>" role="status"><?= e($f['message']) ?></div>
      <?php endforeach; ?>
