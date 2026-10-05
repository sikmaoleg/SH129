</main>
<?php
  $mapCoords = array_map('trim', explode(',', setting('org_map_coords')));
  $mapLat = $mapCoords[0] ?? '';
  $mapLon = $mapCoords[1] ?? '';
  $hasMap = is_numeric($mapLat) && is_numeric($mapLon);
  $orgAddress = setting('org_address', 'Московская область, г. Щёлково');
?>
<footer class="foot" id="contacts">
  <div class="wrap">
    <div class="foot-grid">
      <div class="foot-info">
        <div class="foot-brand">
          <span class="logo logo-lg" role="img" aria-label="Молодая Гвардия Щёлково"></span>
          <p>Местное отделение Всероссийской общественной организации «Молодая Гвардия Единой России» в&nbsp;Щёлковском городском округе Московской области.</p>
        </div>
        <div class="foot-contacts">
          <p><?= icon('map-pin') ?><span><?= e($orgAddress) ?></span></p>
          <?php if (setting('org_email')): ?>
            <a href="mailto:<?= e(setting('org_email')) ?>"><?= icon('mail') ?><span><?= e(setting('org_email')) ?></span></a>
          <?php endif; ?>
        </div>
        <nav class="foot-cols" aria-label="Разделы сайта">
          <div>
            <h4>Об организации</h4>
            <a href="<?= url('about.php') ?>">О нас</a>
            <a href="<?= url('team.php') ?>">Команда</a>
            <a href="<?= url('news.php') ?>">Новости</a>
            <a href="<?= url('gallery.php') ?>">Фотогалерея</a>
            <a href="<?= url('contacts.php') ?>">Контакты</a>
          </div>
          <div>
            <h4>Волонтёрам</h4>
            <a href="<?= url('register.php') ?>">Стать волонтёром</a>
            <a href="<?= url('events.php') ?>">Мероприятия</a>
            <a href="<?= url('login.php') ?>">Личный кабинет</a>
          </div>
        </nav>
      </div>
      <?php if ($hasMap): ?>
        <div class="foot-map">
          <iframe src="https://yandex.ru/map-widget/v1/?ll=<?= e($mapLon) ?>%2C<?= e($mapLat) ?>&amp;z=16&amp;pt=<?= e($mapLon) ?>,<?= e($mapLat) ?>,pm2blm"
                  title="Штаб отделения на карте: <?= e($orgAddress) ?>" loading="lazy" allowfullscreen></iframe>
          <a class="map-link" href="https://yandex.ru/maps/?pt=<?= e($mapLon) ?>,<?= e($mapLat) ?>&amp;z=16&amp;l=map" target="_blank" rel="noopener"><?= icon('map-pin') ?>Открыть на Яндекс.Картах</a>
        </div>
      <?php endif; ?>
    </div>
    <div class="foot-bottom">
      <span>© <?= date('Y') ?> «Молодая Гвардия Единой России», Щёлково</span>
      <div class="socials">
        <?php if (setting('org_vk')): ?><a href="<?= e(setting('org_vk')) ?>" target="_blank" rel="noopener" aria-label="ВКонтакте"><?= icon('vk') ?></a><?php endif; ?>
        <?php if (setting('org_tg')): ?><a href="<?= e(setting('org_tg')) ?>" target="_blank" rel="noopener" aria-label="Telegram"><?= icon('telegram-brand') ?></a><?php endif; ?>
      </div>
    </div>
  </div>
</footer>

<div class="lb" id="lightbox" role="dialog" aria-modal="true" aria-label="Просмотр фото" hidden>
  <button class="lb-btn lb-close" type="button" id="lightboxClose" aria-label="Закрыть"><?= icon('close') ?></button>
  <button class="lb-btn lb-prev" type="button" id="lightboxPrev" aria-label="Предыдущее фото"><?= icon('caret-left') ?></button>
  <figure><img id="lightboxImg" src="" alt=""><figcaption id="lightboxCap"></figcaption></figure>
  <button class="lb-btn lb-next" type="button" id="lightboxNext" aria-label="Следующее фото"><?= icon('caret-right') ?></button>
</div>

<script src="<?= assetUrl('assets/js/main.js') ?>"></script>
</body>
</html>
<?php
// Автозагрузка постов из Telegram без cron: не чаще раза в 15 минут и уже после того,
// как страница отдана посетителю (нужен PHP-FPM; на других режимах просто не срабатывает).
if (function_exists('fastcgi_finish_request') && setting('telegram_channel') !== ''
    && time() - (int)strtotime(setting('telegram_last_sync_at') ?: '2000-01-01') > 900) {
    register_shutdown_function(function () {
        fastcgi_finish_request();
        require_once __DIR__ . '/telegram.php';
        try {
            runTelegramSync(3, 18.0);
        } catch (Throwable $e) {
            // ошибку видно в «Разработчик → Telegram», посетителю она не мешает
        }
    });
}
