</main>
<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div class="footer-brand">
        <img src="<?= url('assets/img/logo.webp') ?>" alt="Молодая Гвардия Щёлково">
        <p>Местное отделение Всероссийской общественной организации «Молодая Гвардия Единой России» в Щёлковском городском округе Московской области.</p>
      </div>
      <div class="footer-cols">
        <div class="footer-col">
          <h4>Об организации</h4>
          <a href="<?= url('about.php') ?>">О нас</a>
          <a href="<?= url('team.php') ?>">Команда</a>
          <a href="<?= url('news.php') ?>">Новости</a>
          <a href="<?= url('gallery.php') ?>">Фотогалерея</a>
        </div>
        <div class="footer-col">
          <h4>Волонтёрам</h4>
          <a href="<?= url('events.php') ?>">Мероприятия</a>
          <a href="<?= url('register.php') ?>">Стать волонтёром</a>
          <a href="<?= url('login.php') ?>">Личный кабинет</a>
        </div>
        <div class="footer-col">
          <h4>Контакты</h4>
          <p><?= icon('map-pin') ?><?= e(setting('org_address', 'Московская область, г. Щёлково')) ?></p>
          <a href="mailto:<?= e(setting('org_email')) ?>"><?= icon('mail') ?><?= e(setting('org_email', 'info@example.ru')) ?></a>
          <a href="<?= url('contacts.php') ?>"><?= icon('arrow-right') ?>Написать нам</a>
        </div>
      </div>
    </div>
    <div class="footer-bottom">
      <span>© <?= date('Y') ?> «Молодая Гвардия Единой России» · Щёлково</span>
      <div class="socials">
        <?php if (setting('org_vk')): ?><a href="<?= e(setting('org_vk')) ?>" aria-label="ВКонтакте" target="_blank" rel="noopener">VK</a><?php endif; ?>
        <?php if (setting('org_tg')): ?><a href="<?= e(setting('org_tg')) ?>" aria-label="Telegram" target="_blank" rel="noopener"><?= icon('telegram') ?></a><?php endif; ?>
      </div>
    </div>
  </div>
</footer>

<div class="lightbox" id="lightbox" role="dialog" aria-modal="true" aria-label="Просмотр фото">
  <button type="button" class="lightbox-close" id="lightboxClose" aria-label="Закрыть"><?= icon('close') ?></button>
  <button type="button" class="lightbox-prev" id="lightboxPrev" aria-label="Предыдущее фото"><?= icon('arrow-right', 'icon icon-flip') ?></button>
  <img src="" alt="" id="lightboxImg">
  <button type="button" class="lightbox-next" id="lightboxNext" aria-label="Следующее фото"><?= icon('arrow-right') ?></button>
</div>

<script src="<?= url('assets/js/main.js') ?>"></script>
</body>
</html>
