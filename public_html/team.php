<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Наша команда — Молодая Гвардия Щёлково';
$activeNav = 'team';

$order = ['leader', 'local_staff', 'staff', 'activist'];
$placeholders = implode(',', array_fill(0, count($order), '?'));
$members = fetchAll(
    "SELECT id, last_name, first_name, middle_name, position, avatar, vk, telegram
     FROM users WHERE status = 'approved' AND position IN ($placeholders)
     ORDER BY FIELD(position, $placeholders), last_name ASC",
    array_merge($order, $order)
);
$groups = [];
foreach ($members as $m) {
    $groups[$m['position']][] = $m;
}

require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div class="container">
    <div class="breadcrumbs"><a href="<?= url('index.php') ?>">Главная</a> / Наша команда</div>
    <h1>Наша команда</h1>
  </div>
</div>

<section class="section">
  <div class="container">
    <?php if (!$members): ?>
      <div class="empty">
        <b>Состав пока не опубликован</b>
        Актив отделения появится здесь, как только координатор укажет позиции волонтёров в панели управления.
      </div>
    <?php else: ?>
      <?php foreach ($order as $posKey): if (empty($groups[$posKey])) continue; ?>
        <div class="team-group">
          <h2><?= e(positionLabel($posKey)) ?></h2>
          <div class="team-grid">
            <?php foreach ($groups[$posKey] as $m): ?>
              <div class="team-card">
                <div class="team-avatar">
                  <?php if ($m['avatar']): ?>
                    <img src="<?= url('uploads/avatars/' . $m['avatar']) ?>" alt="<?= e($m['first_name']) ?>">
                  <?php else: ?>
                    <span><?= e(mb_substr($m['first_name'], 0, 1) . mb_substr($m['last_name'], 0, 1)) ?></span>
                  <?php endif; ?>
                </div>
                <h3><?= e($m['last_name'] . ' ' . $m['first_name']) ?></h3>
                <span class="team-role"><?= e(positionLabel($m['position'])) ?></span>
                <?php if ($m['vk'] || $m['telegram']): ?>
                  <div class="team-links">
                    <?php if ($m['vk']): ?><a href="<?= e($m['vk']) ?>" target="_blank" rel="noopener" aria-label="ВКонтакте"><?= icon('users') ?></a><?php endif; ?>
                    <?php if ($m['telegram']): ?><a href="<?= e(str_starts_with($m['telegram'], 'http') ? $m['telegram'] : 'https://t.me/' . ltrim($m['telegram'], '@')) ?>" target="_blank" rel="noopener" aria-label="Telegram"><?= icon('telegram') ?></a><?php endif; ?>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <div class="article" style="margin-top:44px;">
      <h2>Хотите в команду?</h2>
      <p>Позиции в активе получают волонтёры, которые давно и стабильно участвуют в жизни отделения. Начните с малого — запишитесь на ближайшее мероприятие.</p>
      <p><a href="<?= url('register.php') ?>" class="btn btn-accent">Стать волонтёром</a></p>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
