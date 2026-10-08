<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Команда: Молодая Гвардия Щёлково';
$activeNav = 'team';

// Показываем руководство, местный штаб и аппарат. Активисты на этой странице не выводятся.
$order = ['leader', 'local_staff', 'staff'];
$placeholders = implode(',', array_fill(0, count($order), '?'));
$members = fetchAll(
    "SELECT id, last_name, first_name, middle_name, position, avatar
     FROM users WHERE status = 'approved' AND public_consent = 1 AND position IN ($placeholders)
     ORDER BY FIELD(position, $placeholders), last_name ASC",
    array_merge($order, $order)
);
$groups = [];
foreach ($members as $m) {
    $groups[$m['position']][] = $m;
}
$groupTitles = ['local_staff' => 'Местный штаб', 'staff' => 'Члены Аппарата'];
$leaderPhone = setting('org_leader_phone');

require __DIR__ . '/includes/header.php';
?>
<header class="phead">
  <div class="wrap">
    <nav class="crumbs" aria-label="Навигация"><a href="<?= url('index.php') ?>">Главная</a><span>/</span><span>Команда</span></nav>
    <h1 class="phead-title display">Команда</h1>
    <p class="phead-lead">Люди, которые ведут отделение и&nbsp;отвечают за&nbsp;его дела.</p>
  </div>
</header>

<?php if (!$members): ?>
  <section class="sec"><div class="wrap"><div class="empty"><b>Состав пока не опубликован</b>Участники команды появляются здесь после того, как дали согласие на<b>Состав пока не опубликован</b>Команда появится здесь, как только администратор укажет позиции в&nbsp;панели управления.nbsp;публикацию имени и<b>Состав пока не опубликован</b>Команда появится здесь, как только администратор укажет позиции в&nbsp;панели управления.nbsp;фото.</div></div></section>
<?php endif; ?>

<?php foreach ($groups['leader'] ?? [] as $i => $l): $name = $l['first_name'] . ' ' . $l['last_name']; ?>
<section class="sec">
  <div class="wrap split split-lead">
    <div class="split-media" data-reveal>
      <div class="frame-shadow"></div>
      <div class="frame">
        <?php if ($l['avatar']): ?>
          <img class="is-static" src="<?= url('uploads/avatars/' . $l['avatar']) ?>" alt="<?= e($name) ?>">
        <?php else: ?>
          <span class="initials" aria-hidden="true"><?= e(mb_substr($l['first_name'], 0, 1) . mb_substr($l['last_name'], 0, 1)) ?></span>
        <?php endif; ?>
      </div>
    </div>
    <div data-reveal>
      <p class="kicker">Руководитель местного отделения</p>
      <h2 class="leader-name display"><?= e($name) ?></h2>
      <p class="body-l">По&nbsp;вопросам вступления, совместных проектов и&nbsp;помощи отделению можно обращаться напрямую.</p>
      <?php if ($i === 0 && $leaderPhone): ?>
        <div class="row-ctas"><a class="btn btn-accent btn-lg" href="tel:<?= e(preg_replace('/[^\d+]/', '', $leaderPhone)) ?>"><?= icon('phone') ?><?= e($leaderPhone) ?></a></div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php endforeach; ?>

<?php foreach ($groupTitles as $key => $title): if (empty($groups[$key])) continue; ?>
<section class="sec">
  <div class="wrap">
    <h2 class="h2 display" data-reveal><?= e($title) ?></h2>
    <div class="staff" data-reveal>
      <?php foreach ($groups[$key] as $m): $name = $m['first_name'] . ' ' . $m['last_name']; ?>
        <figure>
          <div class="staff-img">
            <?php if ($m['avatar']): ?>
              <img src="<?= url('uploads/avatars/' . $m['avatar']) ?>" alt="<?= e($name) ?>" loading="lazy">
            <?php else: ?>
              <span class="initials" aria-hidden="true"><?= e(mb_substr($m['first_name'], 0, 1) . mb_substr($m['last_name'], 0, 1)) ?></span>
            <?php endif; ?>
          </div>
          <figcaption><b><?= e($name) ?></b><span><?= e(positionLabel($m['position'])) ?></span></figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endforeach; ?>

<?php if (setting('registration_open', '1') === '1'): ?>
<section class="sec">
  <div class="wrap">
    <div class="note-band" data-reveal>
      <h2 class="display">Хочешь в&nbsp;команду?</h2>
      <p>В&nbsp;штаб приходят те, кто давно и&nbsp;стабильно участвует в&nbsp;жизни отделения. Начать можно с&nbsp;малого: подай заявку и&nbsp;приходи на&nbsp;первое дело.</p>
      <a class="btn btn-light btn-lg" href="<?= url('register.php') ?>">Стать волонтёром<?= icon('arrow-right') ?></a>
    </div>
  </div>
</section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
