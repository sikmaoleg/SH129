<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Молодая Гвардия · Щёлково — волонтёрское движение округа';
$activeNav = '';

$news = fetchAll(
    "SELECT id, title, excerpt, cover, published_at
     FROM news WHERE status = 'published' AND published_at <= NOW()
     ORDER BY published_at DESC LIMIT 3"
);

$events = fetchAll(
    "SELECT e.*, d.title AS direction_title,
            (SELECT COUNT(*) FROM event_registrations r
              WHERE r.event_id = e.id AND r.status <> 'cancelled') AS taken
     FROM events e
     LEFT JOIN directions d ON d.id = e.direction_id
     WHERE e.status = 'published' AND e.starts_at >= NOW()
     ORDER BY e.starts_at ASC LIMIT 4"
);

// Показатели: если администратор не заполнил их вручную — считаем по базе
$statVolunteers = (int)setting('stat_volunteers');
if ($statVolunteers <= 0) {
    $statVolunteers = (int)fetchValue("SELECT COUNT(*) FROM users WHERE status = 'approved' AND role = 'volunteer'");
}
$statEvents = (int)setting('stat_events');
if ($statEvents <= 0) {
    $statEvents = (int)fetchValue("SELECT COUNT(*) FROM events WHERE status IN ('published','finished')");
}
$statHours = (int)setting('stat_hours');
if ($statHours <= 0) {
    $statHours = (int)fetchValue('SELECT COALESCE(SUM(hours),0) FROM users');
}

$honorId = (int)setting('honor_user_id');
$honor   = $honorId > 0 ? fetchOne("SELECT * FROM users WHERE id = ? AND status = 'approved'", [$honorId]) : null;

require __DIR__ . '/includes/header.php';
?>

<section class="hero">
  <div class="container">
    <div class="hero-body">
      <span class="hero-tag"><?= icon('map-pin') ?>Щёлковский городской округ</span>
      <h1>Волонтёрское движение «Молодая Гвардия»</h1>
      <p>Местное отделение объединяет молодёжь округа для добровольческой работы: помощь жителям, экологические и патриотические проекты, спортивные и городские мероприятия.</p>
      <div class="hero-actions">
        <a href="<?= url('register.php') ?>" class="btn btn-accent"><?= icon('arrow-right') ?>Стать волонтёром</a>
        <a href="<?= url('events.php') ?>" class="btn btn-ghost">Ближайшие мероприятия</a>
      </div>
      <div class="hero-stats">
        <div class="stat"><?= icon('users') ?><b><?= number_format($statVolunteers, 0, '.', ' ') ?>+</b><span><?= plural($statVolunteers, 'волонтёр', 'волонтёра', 'волонтёров') ?></span></div>
        <div class="stat"><?= icon('calendar') ?><b><?= number_format($statEvents, 0, '.', ' ') ?></b><span><?= plural($statEvents, 'мероприятие', 'мероприятия', 'мероприятий') ?> в 2026 году</span></div>
        <div class="stat"><?= icon('clock') ?><b><?= number_format($statHours, 0, '.', ' ') ?>+</b><span><?= plural($statHours, 'час', 'часа', 'часов') ?> добровольчества</span></div>
      </div>
    </div>
    <div class="hero-visual">
      <div class="hero-visual-card">
        <img src="<?= url('assets/img/team.webp') ?>" alt="Волонтёры «Молодой Гвардии» Щёлково">
      </div>
    </div>
  </div>
</section>

<?php if ($honor): ?>
<!-- ---------- Доска почёта ---------- -->
<section class="section" style="padding-top:0;">
  <div class="container">
    <div class="honor-card">
      <div class="honor-avatar">
        <?php if ($honor['avatar']): ?>
          <img src="<?= url('uploads/avatars/' . $honor['avatar']) ?>" alt="">
        <?php else: ?>
          <span><?= e(mb_substr($honor['first_name'], 0, 1) . mb_substr($honor['last_name'], 0, 1)) ?></span>
        <?php endif; ?>
      </div>
      <div class="honor-body">
        <span class="honor-eyebrow"><?= icon('medal') ?>Волонтёр месяца</span>
        <h3><?= e($honor['last_name'] . ' ' . $honor['first_name']) ?></h3>
        <?php if (setting('honor_note')): ?><p><?= e(setting('honor_note')) ?></p><?php endif; ?>
      </div>
      <a href="<?= url('team.php') ?>" class="btn btn-outline btn-sm">Вся команда →</a>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ---------- Новости ---------- -->
<section class="section">
  <div class="container">
    <div class="section-head">
      <div>
        <span class="section-eyebrow">Что нового</span><h2>Новости отделения</h2>
        <p>Чем живёт «Молодая Гвардия» Щёлково прямо сейчас.</p>
      </div>
      <a href="<?= url('news.php') ?>" class="section-link">Все новости →</a>
    </div>

    <?php if ($news): ?>
      <div class="news-grid">
        <?php foreach ($news as $n): ?>
          <article class="news-card">
            <div class="news-card-img">
              <img src="<?= e($n['cover'] ? url('uploads/news/' . $n['cover']) : url('assets/img/march.webp')) ?>" alt="" loading="lazy">
            </div>
            <div class="news-card-body">
              <span class="news-date"><?= e(ruDate($n['published_at'])) ?></span>
              <h3><a href="<?= url('news-item.php?id=' . (int)$n['id']) ?>"><?= e($n['title']) ?></a></h3>
              <p><?= e(mb_strimwidth((string)$n['excerpt'], 0, 140, '…')) ?></p>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty">
        <b>Новостей пока нет</b>
        Первая публикация появится здесь сразу после того, как администратор добавит её в панели управления.
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- ---------- Афиша ---------- -->
<section class="section section-alt">
  <div class="container">
    <div class="section-head">
      <div>
        <span class="section-eyebrow">Афиша</span><h2>Ближайшие мероприятия</h2>
        <p>Запись открыта для волонтёров отделения. Не состоите в движении — подайте заявку, это займёт пару минут.</p>
      </div>
      <a href="<?= url('events.php') ?>" class="section-link">Вся афиша →</a>
    </div>

    <?php if ($events): ?>
      <div class="event-list">
        <?php foreach ($events as $ev): $ts = strtotime($ev['starts_at']); ?>
          <div class="event-row">
            <div class="event-date">
              <b><?= date('j', $ts) ?></b>
              <span><?= RU_MONTHS_SHORT[(int)date('n', $ts)] ?></span>
            </div>
            <div class="event-info">
              <h3><?= e($ev['title']) ?></h3>
              <div class="event-meta">
                <span><?= icon('clock') ?><?= date('H:i', $ts) ?></span>
                <?php if ($ev['location']): ?><span><?= icon('map-pin') ?><?= e($ev['location']) ?></span><?php endif; ?>
                <?php if ($ev['direction_title']): ?><span><?= icon('target') ?><?= e($ev['direction_title']) ?></span><?php endif; ?>
                <?php if ((int)$ev['capacity'] > 0): ?>
                  <span><?= icon('users') ?><?= max(0, (int)$ev['capacity'] - (int)$ev['taken']) ?> из <?= (int)$ev['capacity'] ?></span>
                <?php endif; ?>
              </div>
            </div>
            <div class="event-actions">
              <a href="<?= url('event.php?id=' . (int)$ev['id']) ?>" class="btn btn-outline btn-sm">Подробнее</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty">
        <b>Афиша пока пуста</b>
        Ближайшие мероприятия появятся здесь, как только их добавят в панели управления.
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- ---------- Галерея ---------- -->
<section class="section section-alt">
  <div class="container">
    <div class="section-head">
      <div>
        <span class="section-eyebrow">Галерея</span><h2>Наша работа в кадре</h2>
        <p>Фотографии с мероприятий отделения.</p>
      </div>
      <a href="<?= url('gallery.php') ?>" class="section-link">Вся галерея →</a>
    </div>
    <div class="gal-grid">
      <figure class="gal-item gal-tall">
        <img src="<?= url('assets/img/march.webp') ?>" alt="Городское шествие" loading="lazy">
        <figcaption>Городское шествие</figcaption>
      </figure>
      <figure class="gal-item gal-wide">
        <img src="<?= url('assets/img/cake.webp') ?>" alt="День рождения отделения" loading="lazy">
        <figcaption>День рождения отделения</figcaption>
      </figure>
      <figure class="gal-item">
        <img src="<?= url('assets/img/rink.webp') ?>" alt="Спортивное мероприятие" loading="lazy">
        <figcaption>Спорт и ЗОЖ</figcaption>
      </figure>
      <figure class="gal-item">
        <img src="<?= url('assets/img/rain.webp') ?>" alt="Работа в любую погоду" loading="lazy">
        <figcaption>В любую погоду</figcaption>
      </figure>
      <figure class="gal-item gal-wide">
        <img src="<?= url('assets/img/creative.webp') ?>" alt="Съёмка команды" loading="lazy">
        <figcaption>МедиаГвардия</figcaption>
      </figure>
    </div>
  </div>
</section>

<!-- ---------- Вступление ---------- -->
<section class="join-band">
  <div class="container">
    <div>
      <h2>Как вступить в движение</h2>
      <p>Заявку может подать любой житель округа от 14 лет. После проверки анкеты координатор откроет доступ в личный кабинет, где будут ваши мероприятия и достижения.</p>
      <a href="<?= url('register.php') ?>" class="btn btn-light">Подать заявку</a>
    </div>
    <div class="join-steps">
      <div class="join-step">
        <b>01</b>
        <div>
          <h3>Заполните анкету</h3>
          <p>Контакты и дата рождения.</p>
        </div>
      </div>
      <div class="join-step">
        <b>02</b>
        <div>
          <h3>Дождитесь одобрения</h3>
          <p>Координатор проверит заявку и подтвердит вашу учётную запись.</p>
        </div>
      </div>
      <div class="join-step">
        <b>03</b>
        <div>
          <h3>Войдите в личный кабинет</h3>
          <p>Записывайтесь на мероприятия и следите за своей активностью.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
