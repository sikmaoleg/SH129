<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireDev();

$panelSection = 'dev';
$panelTitle   = 'Состояние';
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
$checks[] = ['Режим отладки', $debugOff ? 'выключен' : 'включён', $debugOff, 'На боевом сайте debug должен быть false'];

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
    'Без своей папки сайт пишет сессии в общую папку хостинга, и вход может слетать. Нужны права 755 на storage/sessions',
];

$installerGone = !is_dir(APP_ROOT . '/install');
$checks[] = ['Установщик удалён', $installerGone ? 'да' : 'нет, папка /install на месте', $installerGone,
             'После установки папку /install нужно удалить с сервера'];

$legalOk = operatorInfo()['filled'];
$checks[] = ['Реквизиты оператора персональных данных', $legalOk ? 'заполнены' : 'не заполнены', $legalOk,
             'Укажите наименование и ИНН организации в «Настройки → Персональные данные»: они нужны в Политике и согласиях'];

$defaultKey = ($config['dev_key'] ?? '') === 'change-me-please';
$checks[] = ['Ключ dev_key изменён', $defaultKey ? 'нет, стоит значение по умолчанию' : 'да', !$defaultKey, ''];

// --- Данные базы ---
$dbVersion = fetchValue('SELECT VERSION()');
$tables = fetchAll('SELECT TABLE_NAME AS t, TABLE_ROWS AS r FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME', [$config['db']['name']]);

$counts = [
    'Пользователей'      => [(int)fetchValue('SELECT COUNT(*) FROM users'), 'users'],
    'Ждут одобрения'     => [(int)fetchValue("SELECT COUNT(*) FROM users WHERE status='pending'"), 'hourglass'],
    'Мероприятий'        => [(int)fetchValue('SELECT COUNT(*) FROM events'), 'calendar-check'],
    'Записей на события' => [(int)fetchValue('SELECT COUNT(*) FROM event_registrations'), 'clipboard-text'],
    'Начислений баллов'  => [(int)fetchValue('SELECT COUNT(*) FROM point_transactions'), 'star'],
    'Записей в журнале'  => [(int)fetchValue('SELECT COUNT(*) FROM audit_log'), 'list-checks'],
];

$problems = array_values(array_filter($checks, fn($c) => !$c[2]));
$okChecks = array_values(array_filter($checks, fn($c) => $c[2]));
$panelLead = $problems
    ? 'Есть что поправить: ' . count($problems) . ' ' . plural(count($problems), 'проверка', 'проверки', 'проверок') . ' не пройдено. Проблемы показаны сверху.'
    : 'Все проверки пройдены, сайт работает штатно.';

require __DIR__ . '/../includes/panel_header.php';
?>

<div class="kpis">
  <?php foreach (array_slice($counts, 0, 4, true) as $label => [$value, $ic]): ?>
    <div class="kpi"><div class="kpi-l"><span><?= e($label) ?></span><?= icon($ic) ?></div><div class="kpi-v num"><?= number_format($value, 0, ',', ' ') ?></div></div>
  <?php endforeach; ?>
</div>

<div class="grid g-main">
  <section class="card">
    <div class="card-h"><div><h2>Проверки</h2><p>Что важно для боевого сайта</p></div><?= $problems ? '<span class="chip warn">' . icon('alert') . count($problems) . ' ' . plural(count($problems), 'проблема', 'проблемы', 'проблем') . '</span>' : '<span class="chip ok">' . icon('check') . 'Всё в порядке</span>' ?></div>
    <ul class="checks">
      <?php foreach (array_merge($problems, $okChecks) as [$label, $value, $ok, $note]): ?>
        <li>
          <span class="st <?= $ok ? 'ok' : 'warn' ?>"><?= $ok ? icon('check') : icon('alert') ?></span>
          <div><b><?= e($label) ?></b><?php if (!$ok && $note): ?><small><?= e($note) ?></small><?php endif; ?></div>
          <span class="<?= $ok ? 'muted' : 'chip warn' ?>" style="font-size:13.5px"><?= e($value) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>

  <div class="stack">
    <section class="card">
      <div class="card-h"><div><h2>Окружение</h2></div></div>
      <dl class="env">
        <div><dt>PHP</dt><dd><?= e(PHP_VERSION) ?></dd></div>
        <div><dt>База данных</dt><dd><?= e((string)$dbVersion) ?></dd></div>
        <div><dt>Сервер</dt><dd><?= e($_SERVER['SERVER_SOFTWARE'] ?? 'неизвестно') ?></dd></div>
        <div><dt>Имя базы</dt><dd><?= e($config['db']['name']) ?></dd></div>
        <div><dt>Часовой пояс</dt><dd><?= e(date_default_timezone_get()) ?>, <?= date('d.m.Y H:i') ?></dd></div>
        <div><dt>Лимит памяти</dt><dd><?= e(ini_get('memory_limit')) ?></dd></div>
        <div><dt>Размер загрузки</dt><dd>до <?= e(ini_get('upload_max_filesize')) ?></dd></div>
        <div><dt>Корень сайта</dt><dd class="mono"><?= e(APP_ROOT) ?></dd></div>
      </dl>
    </section>
    <section class="card">
      <div class="card-h"><div><h2>Ещё</h2></div></div>
      <ul class="list">
        <?php foreach (array_slice($counts, 4, null, true) as $label => [$value, $ic]): ?>
          <li><span class="dot"><?= icon($ic) ?></span><div class="grow"><b><?= e($label) ?></b></div><span class="num"><?= number_format($value, 0, ',', ' ') ?></span></li>
        <?php endforeach; ?>
      </ul>
    </section>
  </div>
</div>

<section class="card" style="margin-top:18px">
  <div class="card-h"><div><h2>Таблицы базы</h2><p>Количество строк приблизительное, так его отдаёт MySQL</p></div><a class="btn btn-line btn-sm" href="<?= url('dev/database.php') ?>"><?= icon('database') ?>Подробнее</a></div>
  <div class="table-wrap">
    <table class="data cards">
      <thead><tr><th>Таблица</th><th style="text-align:right">Строк</th></tr></thead>
      <tbody>
        <?php foreach ($tables as $t): ?>
          <tr><td class="mono"><?= e($t['t']) ?></td><td data-label="Строк" class="num" style="text-align:right"><?= number_format((int)$t['r'], 0, ',', ' ') ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<?php require __DIR__ . '/../includes/panel_footer.php'; ?>
