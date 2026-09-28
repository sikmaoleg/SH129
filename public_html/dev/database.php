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

$totalSize = array_sum(array_map(fn($t) => (int)$t['data_len'] + (int)$t['idx_len'], $tables));
$panelLead = 'База ' . e($dbName) . ': ' . count($tables) . ' ' . plural(count($tables), 'таблица', 'таблицы', 'таблиц') . ', около ' . $fmt($totalSize) . '. Нажми на таблицу, чтобы увидеть её поля.';
$dumpCmd = 'mysqldump -u ' . $config['db']['user'] . ' -p ' . $dbName . ' > backup_$(date +%F).sql';

require __DIR__ . '/../includes/panel_header.php';
?>

<section class="card">
  <div class="table-wrap">
    <table class="data cards">
      <thead><tr><th>Таблица</th><th>Движок</th><th style="text-align:right">Строк</th><th style="text-align:right">Данные</th><th style="text-align:right">Индексы</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($tables as $t): $href = url('dev/database.php?table=' . urlencode($t['name'])); ?>
          <tr class="click" data-href="<?= $href ?>">
            <td class="mono"><b><?= e($t['name']) ?></b></td>
            <td data-label="Движок" class="muted"><?= e($t['engine']) ?></td>
            <td data-label="Строк" class="num" style="text-align:right"><?= number_format((int)$t['rows_est'], 0, ',', ' ') ?></td>
            <td data-label="Данные" class="muted" style="text-align:right"><?= $fmt((int)$t['data_len']) ?></td>
            <td data-label="Индексы" class="muted" style="text-align:right"><?= $fmt((int)$t['idx_len']) ?></td>
            <td data-label=""><a class="btn btn-line btn-sm" href="<?= $href ?>">Поля</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="card" style="margin-top:18px">
  <div class="card-h"><div><h2>Резервная копия</h2><p>Проще всего через панель SprintHost: там есть phpMyAdmin и автоматические копии</p></div></div>
  <div class="card-b stack">
    <div class="field"><span class="lbl">Выгрузка по SSH</span><div class="codebox"><span><?= e($dumpCmd) ?></span><button class="btn btn-line btn-sm" type="button" data-copy="<?= e($dumpCmd) ?>"><?= icon('copy') ?>Копировать</button></div></div>
    <div class="field"><span class="lbl">Восстановление</span><div class="codebox"><span>mysql -u пользователь -p база &lt; backup.sql</span></div></div>
  </div>
</section>

<?php if ($inspect !== ''): ?>
  <div class="drawer" id="tableDrawer" data-close-url="<?= url('dev/database.php') ?>">
    <div class="drawer-panel" role="dialog" aria-modal="true" aria-labelledby="tdT">
      <div class="drawer-head"><div><p class="drawer-kicker">Структура таблицы</p><h2 class="drawer-title mono" id="tdT" style="text-transform:none;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:26px"><?= e($inspect) ?></h2><p class="muted" style="margin-top:6px;font-size:14px">Точное количество строк: <?= number_format((int)$exactCount, 0, ',', ' ') ?></p></div><button class="icon-btn" type="button" data-close-drawer aria-label="Закрыть"><?= icon('x') ?></button></div>
      <div class="drawer-body" style="padding:0">
        <ul class="list">
          <?php foreach ($columns as $c): ?>
            <li>
              <div class="grow"><b class="mono" style="font-size:14px"><?= e($c['name']) ?></b><small class="mono"><?= e($c['type']) ?><?= $c['nullable'] === 'YES' ? ', может быть пустым' : '' ?><?= $c['def'] !== null ? ', по умолчанию ' . e($c['def']) : '' ?><?= $c['extra'] ? ', ' . e($c['extra']) : '' ?></small></div>
              <?php if ($c['keytype']): ?><span class="chip info"><?= e($c['keytype'] === 'PRI' ? 'ключ' : ($c['keytype'] === 'UNI' ? 'уникальное' : 'индекс')) ?></span><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/panel_footer.php'; ?>
