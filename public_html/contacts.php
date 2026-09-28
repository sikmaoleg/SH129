<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Контакты: Молодая Гвардия Щёлково';
$activeNav = 'contacts';

$address = setting('org_address', 'Московская область, г. Щёлково');
// Короткий адрес крупно, область и город — мелкой строкой под ним
$addrParts = array_map('trim', explode(',', $address));
$addrMain  = count($addrParts) > 2 ? implode(', ', array_slice($addrParts, 2)) : $address;
$addrSub   = count($addrParts) > 2 ? implode(', ', array_slice($addrParts, 0, 2)) : '';
$phone     = setting('org_leader_phone');

$mapCoords = array_map('trim', explode(',', setting('org_map_coords')));
$mapLat = $mapCoords[0] ?? '';
$mapLon = $mapCoords[1] ?? '';

require __DIR__ . '/includes/header.php';
?>
<header class="phead">
  <div class="wrap">
    <nav class="crumbs" aria-label="Навигация"><a href="<?= url('index.php') ?>">Главная</a><span>/</span><span>Контакты</span></nav>
    <h1 class="phead-title display">Контакты</h1>
    <p class="phead-lead">Приходи знакомиться лично, звони или пиши.</p>
  </div>
</header>

<section class="sec">
  <div class="wrap contacts">
    <dl class="clist" data-reveal>
      <div><dt>Штаб отделения</dt><dd class="display"><?= e($addrMain) ?></dd><?php if ($addrSub): ?><dd class="sub"><?= e($addrSub) ?></dd><?php endif; ?></div>
      <?php if ($phone): ?>
        <div><dt>Руководитель</dt><dd class="display"><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $phone)) ?>"><?= e($phone) ?></a></dd><?php if (setting('org_leader_name')): ?><dd class="sub"><?= e(setting('org_leader_name')) ?></dd><?php endif; ?></div>
      <?php endif; ?>
      <?php if (setting('org_email')): ?>
        <div><dt>Почта</dt><dd class="display lower"><a href="mailto:<?= e(setting('org_email')) ?>"><?= e(setting('org_email')) ?></a></dd></div>
      <?php endif; ?>
      <?php if (setting('org_vk') || setting('org_tg')): ?>
        <div><dt>Мы в&nbsp;соцсетях</dt><dd class="soc-row">
          <?php if (setting('org_vk')): ?><a class="soc-big" href="<?= e(setting('org_vk')) ?>" target="_blank" rel="noopener"><?= icon('vk') ?>ВКонтакте</a><?php endif; ?>
          <?php if (setting('org_tg')): ?><a class="soc-big" href="<?= e(setting('org_tg')) ?>" target="_blank" rel="noopener"><?= icon('telegram-brand') ?>Telegram</a><?php endif; ?>
        </dd></div>
      <?php endif; ?>
    </dl>
    <?php if (is_numeric($mapLat) && is_numeric($mapLon)): ?>
      <div class="cmap" data-reveal>
        <iframe src="https://yandex.ru/map-widget/v1/?ll=<?= e($mapLon) ?>%2C<?= e($mapLat) ?>&amp;z=16&amp;pt=<?= e($mapLon) ?>,<?= e($mapLat) ?>,pm2blm"
                title="Штаб отделения на карте: <?= e($address) ?>" loading="lazy" allowfullscreen></iframe>
        <a class="map-link" href="https://yandex.ru/maps/?pt=<?= e($mapLon) ?>,<?= e($mapLat) ?>&amp;z=16&amp;l=map" target="_blank" rel="noopener"><?= icon('map-pin') ?>Открыть на Яндекс.Картах</a>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
