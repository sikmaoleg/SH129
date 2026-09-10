<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireDev();

$panelSection = 'dev';
$panelTitle   = 'Состояние системы';
$activeItem   = 'index';

global $config;

// --- Проверки окружения ---
$checks = [];
$phpOk  = version_compare(PHP_VERSION, '8.0.0', '>=');
$checks[] = ['Версия PHP', PHP_VERSION, $phpOk, $phpOk ? '' : 'Требуется PHP 8.0 или новее'];

$pdoOk = extension_loaded('pdo_mysql');
$checks[] = ['Расширение pdo_mysql', $pdoOk ? 'подключено' : 'отсутствует', $pdoOk, ''];

$mbOk = extension_loaded('mbstring');
$checks[] = ['Расширение mbstring', $mbOk ? 'подключено' : 'отсутствует', $mbOk, 'Нужно для корректной работы с кириллицей'];

$httpsOk = !empty($_SERVER['HTTPS']);
$checks[] = ['HTTPS', $httpsOk ? 'включён' : 'выключен', $httpsOk, 'Без HTTPS пароли передаются открыто'];

$debugOff = empty($config['debug']);
$checks[] = ['Режим отладки', $debugOff ? 'выключен' : 'ВКЛЮЧЁН', $debugOff, 'На боевом сайте debug должен быть false'];

$uploadsDir = APP_ROOT . '/uploads';
$writable   = is_dir($uploadsDir) && is_writable($uploadsDir);
$checks[] = ['Папка uploads', $writable ? 'доступна для записи' : 'нет доступа на запись', $writable, 'Права 755 на папку uploads'];

$sessionDir  = APP_ROOT . '/storage/sessions';
$sessionOk   = is_dir($sessionDir) && is_writable($sessionDir);
$sessionUsed = session_save_path() === $sessionDir;
$checks[] = [
    'Хранилище сессий',
    $sessionOk ? ($sessionUsed ? 'своя папка, используется' : 'своя папка есть, но не используется') : 'нет доступа на запись',
    $sessionOk && $sessionUsed,
    'Без этого сайт может использовать общую папку сессий хостинга — вход будет постоянно слетать, если она недоступна для записи. Права 755 на storage/sessions',
];

$installerGone = !is_dir(APP_ROOT . '/install');
$checks[] = ['Установщик удалён', $installerGone ? 'да' : 'НЕТ — папка /install на месте', $installerGone,
             'После установки папку /install нужно удалить с сервера'];

$defaultKey = ($config['dev_key'] ?? '') === 'change-me-please';
$checks[] = ['Ключ dev_key изменён', $defaultKey ? 'НЕТ — стоит значение по умолчанию' : 'да', !$defaultKey, ''];

// --- Данные базы ---
$dbVersion = fetchValue('SELECT VERSION()');
$tables = fetchAll('SELECT TABLE_NAME AS t, TABLE_ROWS AS r FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME', [$config['db']['name']]);

$counts = [
    'Пользователей'      => [(int)fetchValue('SELECT COUNT(*) FROM users'), 'users'],
    'Ждут одобрения'     => [(int)fetchValue("SELECT COUNT(*) FROM users WHERE status='pending'"), 'user-plus'],
    'Мероприятий'        => [(int)fetchValue('SELECT COUNT(*) FROM events'), 'calendar'],
    'Записей на события' => [(int)fetchValue('SELECT COUNT(*) FROM event_registrations'), 'clipboard'],
    'Начислений очков'   => [(int)fetchValue('SELECT COUNT(*) FROM point_transactions'), 'medal'],
    'Записей в журнале'  => [(int)fetchValue('SELECT COUNT(*) FROM audit_log'), 'target'],
];

require __DIR__ . '/../includes/panel_header.php';
?>

<div class="kpi-grid">
  <?php foreach (array_slice($counts, 0, 4, true) as $label => [$value, $ic]): ?>
    <div class="kpi"><div class="kpi-top"><span><?= e($label) ?></span><?= icon($ic) ?></div><b><?= $value ?></b></div>
  <?php endforeach; ?>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-head"><div><h2>Проверка окружения</h2><p>Что важно поправить перед публикацией</p></div></div>
    <div class="card-body card-body-flush table-wrap">
      <table class="data">
        <tbody>
          <?php foreach ($checks as [$label, $value, $ok, $note]): ?>
            <tr>
              <td style="width:44%;"><?= e($label) ?>
                <?php if (!$ok && $note): ?><div style="font-size:.79rem;color:var(--muted);"><?= e($note) ?></div><?php endif; ?></td>
              <td class="mono"><?= e($value) ?></td>
              <td style="width:1%;"><span class="<?= $ok ? 'status-ok' : 'status-bad' ?>"><?= $ok ? icon('check') : icon('alert') ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><div><h2>Окружение</h2></div></div>
    <div class="card-body">
      <table class="kv">
        <tr><th>PHP</th><td><?= e(PHP_VERSION) ?></td></tr>
        <tr><th>Сервер</th><td><?= e($_SERVER['SERVER_SOFTWARE'] ?? 'неизвестно') ?></td></tr>
        <tr><th>MySQL</th><td><?= e((string)$dbVersion) ?></td></tr>
        <tr><th>База</th><td><?= e($config['db']['name']) ?></td></tr>
        <tr><th>Часовой пояс</th><td><?= e(date_default_timezone_get()) ?> (<?= date('d.m.Y H:i') ?>)</td></tr>
        <tr><th>Корень сайта</th><td><?= e(APP_ROOT) ?></td></tr>
        <tr><th>Лимит памяти</th><td><?= e(ini_get('memory_limit')) ?></td></tr>
        <tr><th>Макс. размер загрузки</th><td><?= e(ini_get('upload_max_filesize')) ?></td></tr>
      </table>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-head"><div><h2>Таблицы базы данных</h2><p>Количество строк приблизительное — так его отдаёт MySQL</p></div></div>
  <div class="card-body card-body-flush table-wrap">
    <table class="data">
      <thead><tr><th>Таблица</th><th>Строк (оценка)</th></tr></thead>
      <tbody>
        <?php foreach ($tables as $t): ?>
          <tr><td class="mono"><?= e($t['t']) ?></td><td class="num"><?= (int)$t['r'] ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../includes/panel_footer.php'; ?>
