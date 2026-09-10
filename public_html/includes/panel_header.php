<?php
/**
 * Общий каркас всех панелей.
 * Перед подключением задайте: $pageTitle, $panelTitle, $panelSection ('cabinet'|'admin'|'dev'), $activeItem
 */
require_once __DIR__ . '/icons.php';
$me           = currentUser();
$panelTitle   = $panelTitle ?? 'Панель';
$panelSection = $panelSection ?? 'cabinet';
$activeItem   = $activeItem ?? '';
$pageTitle    = $pageTitle ?? ($panelTitle . ' — Молодая Гвардия Щёлково');

// Количество заявок, ожидающих проверки — показывается администратору
$pendingCount = 0;
if (isAdmin()) {
    $pendingCount = (int)fetchValue("SELECT COUNT(*) FROM users WHERE status = 'pending'");
}

$menus = [
    'cabinet' => [
        'title' => 'Личный кабинет',
        'items' => [
            'index'   => ['Обзор',            'cabinet/index.php',   'grid'],
            'events'  => ['Мои мероприятия',  'cabinet/events.php',  'calendar'],
            'rating'  => ['Рейтинг',          'cabinet/rating.php',  'medal'],
            'badges'  => ['Достижения',       'cabinet/badges.php',  'badge-check'],
            'profile' => ['Мои данные',       'cabinet/profile.php', 'users'],
        ],
    ],
    'admin' => [
        'title' => 'Администрирование',
        'items' => [
            'index'        => ['Обзор',               'admin/index.php',        'grid'],
            'applications' => ['Заявки на вступление', 'admin/applications.php', 'user-plus'],
            'users'        => ['Волонтёры',            'admin/users.php',        'users'],
            'events'       => ['Мероприятия',          'admin/events.php',       'calendar'],
            'news'         => ['Новости',               'admin/news.php',         'clipboard'],
            'points'       => ['Начисление очков',      'admin/points.php',       'medal'],
        ],
    ],
    'dev' => [
        'title' => 'Разработчик',
        'items' => [
            'index'    => ['Состояние системы', 'dev/index.php',    'shield-check'],
            'database' => ['База данных',       'dev/database.php', 'grid'],
            'settings' => ['Настройки сайта',   'dev/settings.php', 'target'],
            'logs'     => ['Журнал действий',   'dev/logs.php',     'clipboard'],
        ],
    ],
];
$menu = $menus[$panelSection];
?><!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#0A2A5E">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('assets/css/panel.css') ?>">
<link rel="icon" href="<?= url('favicon.ico') ?>" sizes="any">
<link rel="icon" href="<?= url('assets/img/favicon/favicon-32.png') ?>" type="image/png" sizes="32x32">
<link rel="apple-touch-icon" href="<?= url('assets/img/favicon/apple-touch-icon.png') ?>">
</head>
<body>
<a class="skip-link" href="#panel-main">Перейти к содержимому</a>
<div class="panel">

  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <img src="<?= url('assets/img/logo.webp') ?>" alt="Молодая Гвардия Щёлково">
      <span><?= e($menu['title']) ?></span>
    </div>
    <button class="panel-menu-btn" id="panelMenuBtn" aria-expanded="false"><?= icon('menu') ?> Меню</button>

    <nav>
      <div class="sidebar-role"><?= e($menu['title']) ?></div>
      <?php foreach ($menu['items'] as $key => [$label, $href, $ic]): ?>
        <a href="<?= url($href) ?>" class="<?= $activeItem === $key ? 'is-active' : '' ?>">
          <?= icon($ic) ?>
          <span><?= e($label) ?></span>
          <?php if ($key === 'applications' && $pendingCount > 0): ?>
            <span class="count"><?= $pendingCount ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>

      <?php // Переходы между панелями для тех, у кого есть права ?>
      <?php if (isAdmin() && $panelSection !== 'admin'): ?>
        <div class="sidebar-role">Ещё</div>
        <a href="<?= url('admin/index.php') ?>">
          <?= icon('shield-check') ?>
          <span>Панель администратора</span>
          <?php if ($pendingCount > 0): ?><span class="count"><?= $pendingCount ?></span><?php endif; ?>
        </a>
      <?php endif; ?>
      <?php if (isDev() && $panelSection !== 'dev'): ?>
        <?php if (!isAdmin() || $panelSection === 'admin'): ?><div class="sidebar-role">Ещё</div><?php endif; ?>
        <a href="<?= url('dev/index.php') ?>"><?= icon('target') ?><span>Панель разработчика</span></a>
      <?php endif; ?>
      <?php if ($panelSection !== 'cabinet'): ?>
        <a href="<?= url('cabinet/index.php') ?>"><?= icon('grid') ?><span>Мой личный кабинет</span></a>
      <?php endif; ?>
    </nav>

    <div class="sidebar-foot">
      <a href="<?= url('index.php') ?>"><?= icon('arrow-right', 'icon icon-flip') ?>На сайт</a>
      <a href="<?= url('logout.php') ?>"><?= icon('close') ?>Выйти</a>
    </div>
  </aside>

  <div class="panel-main">
    <div class="panel-top">
      <h1><?= e($panelTitle) ?></h1>
      <div class="who">
        <b><?= e($me['last_name'] . ' ' . $me['first_name']) ?></b>
        · <?= e(match ($me['role']) { 'dev' => 'разработчик', 'admin' => 'администратор', default => 'волонтёр' }) ?>
      </div>
    </div>

    <div class="panel-body" id="panel-main">
      <?php foreach (takeFlash() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
      <?php endforeach; ?>
