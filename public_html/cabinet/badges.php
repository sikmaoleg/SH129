<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$me = requireLogin();

$panelSection = 'cabinet';
$panelTitle   = 'Достижения';
$activeItem   = 'badges';

$all = fetchAll('SELECT * FROM badges ORDER BY id ASC');
$mine = [];
foreach (fetchAll('SELECT badge_id, awarded_at FROM user_badges WHERE user_id = ?', [(int)$me['id']]) as $r) {
    $mine[(int)$r['badge_id']] = $r['awarded_at'];
}
require __DIR__ . '/../includes/panel_header.php';
?>

<div class="card">
  <div class="card-head">
    <div>
      <h2>Мои бейджи</h2>
      <p>Получено <?= count($mine) ?> из <?= count($all) ?>. Бейджи выдаёт координатор за конкретные достижения.</p>
    </div>
  </div>
  <div class="card-body">
    <?php if ($all): ?>
      <div class="badge-grid">
        <?php foreach ($all as $b): $earned = isset($mine[(int)$b['id']]); ?>
          <div class="badge-item <?= $earned ? 'is-earned' : 'is-locked' ?>">
            <div class="ico"><?= $b['icon'] !== '' && $b['icon'] !== null ? e($b['icon']) : icon('badge-check') ?></div>
            <b><?= e($b['title']) ?></b>
            <span><?= e($b['description']) ?></span>
            <?php if ($earned): ?>
              <div style="margin-top:8px;font-size:.75rem;color:var(--ok);font-weight:700;"><?= e(ruDate($mine[(int)$b['id']])) ?></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty"><b>Бейджи не настроены</b>Их список задаётся в базе данных.</div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../includes/panel_footer.php'; ?>
