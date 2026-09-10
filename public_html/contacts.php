<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Контакты — Молодая Гвардия Щёлково';
$activeNav = 'contacts';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div class="container">
    <div class="breadcrumbs"><a href="<?= url('index.php') ?>">Главная</a> / Контакты</div>
    <h1>Контакты</h1>
  </div>
</div>
<section class="section">
  <div class="container">
    <div class="dir-grid">
      <div class="dir-card">
        <div class="num"><?= icon('map-pin') ?></div>
        <h3>Адрес</h3>
        <p><?= e(setting('org_address', 'Московская область, г. Щёлково')) ?></p>
      </div>
      <div class="dir-card">
        <div class="num"><?= icon('mail') ?></div>
        <h3>Электронная почта</h3>
        <p><a href="mailto:<?= e(setting('org_email')) ?>"><?= e(setting('org_email', 'info@example.ru')) ?></a></p>
      </div>
      <div class="dir-card">
        <div class="num"><?= icon('users') ?></div>
        <h3>Социальные сети</h3>
        <p>
          <?php if (setting('org_vk')): ?><a href="<?= e(setting('org_vk')) ?>" target="_blank" rel="noopener">ВКонтакте</a><br><?php endif; ?>
          <?php if (setting('org_tg')): ?><a href="<?= e(setting('org_tg')) ?>" target="_blank" rel="noopener">Telegram</a><?php endif; ?>
          <?php if (!setting('org_vk') && !setting('org_tg')): ?>Ссылки добавляются в панели управления.<?php endif; ?>
        </p>
      </div>
    </div>
    <div class="article" style="margin-top:40px;">
      <h2>Хотите присоединиться?</h2>
      <p>Подайте заявку через сайт — координатор свяжется с вами по указанному телефону и подскажет, с какого мероприятия начать.</p>
      <p><a href="<?= url('register.php') ?>" class="btn btn-accent">Стать волонтёром</a></p>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
