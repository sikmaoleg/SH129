<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireDev();

$panelSection = 'dev';
$panelTitle   = 'Журнал';
$activeItem   = 'logs';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    if (($_POST['action'] ?? '') === 'clear_old') {
        $deleted = q('DELETE FROM audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)')->rowCount();
        logAction('logs_cleanup', 'audit_log', null, 'удалено записей: ' . $deleted);
        flash('success', 'Удалено записей старше 90 дней: ' . $deleted . '.');
    }
    redirect('dev/logs.php');
}

$filterAction = trim((string)($_GET['action_filter'] ?? ''));
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset  = ($page - 1) * $perPage;

$where  = '1=1';
$params = [];
if ($filterAction !== '') {
    $where .= ' AND a.action = ?';
    $params[] = $filterAction;
}

$total = (int)fetchValue("SELECT COUNT(*) FROM audit_log a WHERE $where", $params);
$pages = max(1, (int)ceil($total / $perPage));

$rows = fetchAll(
    "SELECT a.*, u.last_name, u.first_name, u.role
     FROM audit_log a LEFT JOIN users u ON u.id = a.user_id
     WHERE $where ORDER BY a.created_at DESC LIMIT $perPage OFFSET $offset", $params
);

$actions = fetchAll('SELECT action, COUNT(*) AS c FROM audit_log GROUP BY action ORDER BY c DESC');

$panelLead = 'Входы, решения по заявкам, баллы и правки данных. Всего записей: ' . number_format($total, 0, ',', ' ') . '.';
$panelActions = '<form method="post" style="margin:0">' . csrfField() . '<input type="hidden" name="action" value="clear_old"><button class="btn btn-line" type="submit" data-confirm="Удалить записи журнала старше 90 дней?">' . icon('trash') . 'Удалить старше 90 дней</button></form>';
$pageUrl = fn(int $p) => url('dev/logs.php?page=' . $p . ($filterAction !== '' ? '&action_filter=' . urlencode($filterAction) : ''));

require __DIR__ . '/../includes/panel_header.php';
?>

<section class="card">
  <div class="toolbar">
    <form method="get" class="row" style="flex-wrap:wrap">
      <label class="sr" for="lf">Тип действия</label>
      <select class="select" id="lf" name="action_filter" onchange="this.form.submit()" style="min-width:260px">
        <option value="">Все действия</option>
        <?php foreach ($actions as $a): ?>
          <option value="<?= e($a['action']) ?>" <?= $filterAction === $a['action'] ? 'selected' : '' ?>><?= e(actionLabel($a['action'])[0]) ?> (<?= (int)$a['c'] ?>)</option>
        <?php endforeach; ?>
      </select>
      <?php if ($filterAction !== ''): ?><a class="btn btn-ghost btn-sm" href="<?= url('dev/logs.php') ?>"><?= icon('x') ?>Сбросить</a><?php endif; ?>
    </form>
  </div>

  <?php if ($rows): ?>
    <ul class="feed">
      <?php foreach ($rows as $r): [$label, $ic, $kind] = actionLabel($r['action']); $who = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')); ?>
        <li>
          <span class="dot <?= e($kind) ?>"><?= icon($ic) ?></span>
          <div style="min-width:0">
            <p><b style="font-weight:600"><?= e($who ?: 'Гость') ?></b> <span><?= e(mb_strtolower($label)) ?></span><?= $r['meta'] ? ': ' . e($r['meta']) : '' ?></p>
            <span class="log-code"><?= e($r['action']) ?><?= $r['entity'] ? ' · ' . e($r['entity']) . ($r['entity_id'] ? ' #' . (int)$r['entity_id'] : '') : '' ?><?= $r['ip'] ? ' · ' . e($r['ip']) : '' ?></span>
          </div>
          <time datetime="<?= e(date('c', strtotime($r['created_at']))) ?>"><?= e(date('d.m.Y H:i', strtotime($r['created_at']))) ?></time>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php if ($pages > 1): ?>
      <div class="card-f" style="justify-content:center">
        <div class="pagination" style="margin:0">
          <?php if ($page > 1): ?><a href="<?= $pageUrl($page - 1) ?>" aria-label="Назад"><?= icon('caret-left') ?></a><?php endif; ?>
          <?php for ($p = max(1, $page - 3); $p <= min($pages, $page + 3); $p++): ?>
            <?php if ($p === $page): ?><span class="is-current"><?= $p ?></span><?php else: ?><a href="<?= $pageUrl($p) ?>"><?= $p ?></a><?php endif; ?>
          <?php endfor; ?>
          <?php if ($page < $pages): ?><a href="<?= $pageUrl($page + 1) ?>" aria-label="Вперёд"><?= icon('caret-right') ?></a><?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  <?php else: ?>
    <div class="empty"><?= icon('list-checks') ?><b>Записей нет</b>Журнал заполнится по мере работы с сайтом.</div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/panel_footer.php'; ?>
