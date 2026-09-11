<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireDev();

$panelSection = 'dev';
$panelTitle   = 'Журнал действий';
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

require __DIR__ . '/../includes/panel_header.php';
?>

<div class="card">
  <div class="card-head">
    <div><h2>Записей: <?= $total ?></h2><p>Фиксируются входы, одобрения заявок, начисления баллов и правки данных.</p></div>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
      <form method="get" class="inline-form">
        <select name="action_filter" onchange="this.form.submit()">
          <option value="">все действия</option>
          <?php foreach ($actions as $a): ?>
            <option value="<?= e($a['action']) ?>" <?= $filterAction === $a['action'] ? 'selected' : '' ?>>
              <?= e($a['action']) ?> (<?= (int)$a['c'] ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </form>
      <form method="post" style="margin:0;">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="clear_old">
        <button class="btn btn-outline btn-sm" data-confirm="Удалить записи журнала старше 90 дней?">Очистить старые</button>
      </form>
    </div>
  </div>

  <?php if ($rows): ?>
    <div class="card-body card-body-flush table-wrap">
      <table class="data">
        <thead><tr><th>Время</th><th>Действие</th><th>Объект</th><th>Кто</th><th>IP</th><th>Подробности</th></tr></thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td style="white-space:nowrap;color:var(--muted);font-size:.83rem;"><?= e(date('d.m.Y H:i:s', strtotime($r['created_at']))) ?></td>
              <td class="mono"><?= e($r['action']) ?></td>
              <td class="mono" style="color:var(--muted);"><?= e($r['entity'] ?: '—') ?><?= $r['entity_id'] ? ' #' . (int)$r['entity_id'] : '' ?></td>
              <td><?= e(trim(($r['last_name'] ?? '') . ' ' . ($r['first_name'] ?? '')) ?: 'гость') ?></td>
              <td class="mono" style="color:var(--muted);"><?= e($r['ip'] ?: '—') ?></td>
              <td style="font-size:.84rem;color:var(--muted);"><?= e($r['meta'] ?: '') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($pages > 1): ?>
      <div class="card-body">
        <div class="pagination">
          <?php for ($p = max(1, $page - 4); $p <= min($pages, $page + 4); $p++): ?>
            <?php if ($p === $page): ?><span class="is-current"><?= $p ?></span>
            <?php else: ?><a href="<?= url('dev/logs.php?page='.$p . ($filterAction !== '' ? '&action_filter='.urlencode($filterAction) : '')) ?>"><?= $p ?></a><?php endif; ?>
          <?php endfor; ?>
        </div>
      </div>
    <?php endif; ?>
  <?php else: ?>
    <div class="empty"><b>Записей нет</b>Журнал заполнится по мере работы с сайтом.</div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/panel_footer.php'; ?>
