<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireDev();

$panelSection = 'dev';
$panelTitle   = 'База данных';
$activeItem   = 'database';

global $config;
$dbName = $config['db']['name'];

$tables = fetchAll(
    'SELECT TABLE_NAME AS name, TABLE_ROWS AS rows_est, DATA_LENGTH AS data_len, INDEX_LENGTH AS idx_len, ENGINE AS engine
     FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME', [$dbName]
);

$inspect = trim((string)($_GET['table'] ?? ''));
$columns = [];
$exactCount = null;

if ($inspect !== '') {
    // Имя таблицы приходит из адресной строки — сверяем со списком реальных таблиц,
    // чтобы подставить его в запрос было безопасно.
    $allowed = array_column($tables, 'name');
    if (in_array($inspect, $allowed, true)) {
        $columns = fetchAll(
            'SELECT COLUMN_NAME AS name, COLUMN_TYPE AS type, IS_NULLABLE AS nullable,
                    COLUMN_KEY AS keytype, COLUMN_DEFAULT AS def, EXTRA AS extra
             FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
             ORDER BY ORDINAL_POSITION', [$dbName, $inspect]
        );
        $exactCount = (int)fetchValue("SELECT COUNT(*) FROM `$inspect`");
    } else {
        flash('error', 'Такой таблицы нет в базе.');
        $inspect = '';
    }
}

$fmt = fn(int $bytes) => $bytes > 1048576
    ? round($bytes / 1048576, 1) . ' МБ'
    : round($bytes / 1024) . ' КБ';

require __DIR__ . '/../includes/panel_header.php';
?>

<div class="card">
  <div class="card-head"><div><h2>Таблицы базы <span class="mono"><?= e($dbName) ?></span></h2></div></div>
  <div class="card-body card-body-flush table-wrap">
    <table class="data">
      <thead><tr><th>Таблица</th><th>Движок</th><th>Строк (оценка)</th><th>Данные</th><th>Индексы</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($tables as $t): ?>
          <tr>
            <td class="mono"><?= e($t['name']) ?></td>
            <td style="color:var(--muted);"><?= e($t['engine']) ?></td>
            <td class="num"><?= (int)$t['rows_est'] ?></td>
            <td class="mono" style="color:var(--muted);"><?= $fmt((int)$t['data_len']) ?></td>
            <td class="mono" style="color:var(--muted);"><?= $fmt((int)$t['idx_len']) ?></td>
            <td><a href="<?= url('dev/database.php?table='.urlencode($t['name'])) ?>" class="btn btn-outline btn-sm">Структура</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($inspect !== ''): ?>
  <div class="card">
    <div class="card-head">
      <div><h2>Структура <span class="mono"><?= e($inspect) ?></span></h2>
        <p>Точное количество строк: <?= (int)$exactCount ?></p></div>
      <a href="<?= url('dev/database.php') ?>" class="btn btn-outline btn-sm">Закрыть</a>
    </div>
    <div class="card-body card-body-flush table-wrap">
      <table class="data">
        <thead><tr><th>Поле</th><th>Тип</th><th>NULL</th><th>Ключ</th><th>По умолчанию</th><th>Дополнительно</th></tr></thead>
        <tbody>
          <?php foreach ($columns as $c): ?>
            <tr>
              <td class="mono"><b><?= e($c['name']) ?></b></td>
              <td class="mono" style="color:var(--muted);"><?= e($c['type']) ?></td>
              <td><?= $c['nullable'] === 'YES' ? 'да' : 'нет' ?></td>
              <td><?= $c['keytype'] ? '<span class="tag tag-blue">' . e($c['keytype']) . '</span>' : '' ?></td>
              <td class="mono" style="color:var(--muted);"><?= e($c['def'] ?? '—') ?></td>
              <td class="mono" style="color:var(--muted);"><?= e($c['extra'] ?: '') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<div class="card">
  <div class="card-head"><div><h2>Резервное копирование</h2></div></div>
  <div class="card-body">
    <p style="font-size:.92rem;color:var(--ink-2);margin-bottom:14px;">
      Выгружать базу лучше средствами хостинга — в панели управления SprintHost есть phpMyAdmin и автоматические резервные копии.
      Ручная выгрузка через SSH:
    </p>
    <pre class="code">mysqldump -u <?= e($config['db']['user']) ?> -p <?= e($dbName) ?> > backup_$(date +%F).sql</pre>
    <p style="font-size:.85rem;color:var(--muted);margin-top:12px;">
      Восстановление: <span class="mono">mysql -u пользователь -p база &lt; backup.sql</span>
    </p>
  </div>
</div>

<?php require __DIR__ . '/../includes/panel_footer.php'; ?>
