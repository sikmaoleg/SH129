<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Молодая Гвардия Щёлково';
$activeNav = '';
$navCta    = 'home';

// Фото первого экрана — управляются администратором в admin/hero.php
$heroPhotos = fetchAll('SELECT image, caption FROM hero_slides ORDER BY sort ASC, id ASC');
if (!$heroPhotos) {
    $heroPhotos = [['image' => 'assets/img/team.webp', 'caption' => 'Команда отделения']];
}
// Одна из фотографий уходит в блок «Часть большой команды»
$orgPhoto = $heroPhotos[count($heroPhotos) > 3 ? 3 : 0];

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

// Направления работы: подпись в свёрнутой полосе, иконка и фото по slug
$dirMeta = [
    'patriot' => ['Патриотика', 'flag-banner', 'assets/img/march.webp', '40% 50%'],
    'help'    => ['Помощь', 'hand-heart', 'assets/img/dir-help.webp', '52% 50%'],
    'eco'     => ['Экология', 'plant', 'assets/img/rain.webp', '50% 50%'],
    'media'   => ['Медиа', 'camera', 'assets/img/creative.webp', '50% 50%'],
    'sport'   => ['Спорт', 'sneaker', 'assets/img/dir-sport.webp', '40% 50%'],
    'school'  => ['Лидерство', 'graduation-cap', 'assets/img/dir-school.webp', '60% 40%'],
];
$directions = fetchAll('SELECT title, slug, description FROM directions ORDER BY sort ASC, id ASC');

$news = fetchAll(
    "SELECT id, title, excerpt, body, cover, published_at
     FROM news WHERE status = 'published' AND published_at <= NOW()
     ORDER BY published_at DESC LIMIT 6"
);

$team = fetchAll(
    "SELECT id, last_name, first_name, position, avatar FROM users
     WHERE status = 'approved' AND position IN ('leader','local_staff')
     ORDER BY FIELD(position, 'leader', 'local_staff'), (avatar IS NULL), last_name ASC LIMIT 7"
);

$honorId = (int)setting('honor_user_id');
$honor   = $honorId > 0 ? fetchOne("SELECT * FROM users WHERE id = ? AND status = 'approved'", [$honorId]) : null;
$registrationOpen = setting('registration_open', '1') === '1';

require __DIR__ . '/includes/header.php';
?>

<!-- Первый экран -->
<section class="hero">
  <div class="wrap hero-grid">
    <div class="hero-copy">
      <p class="eyebrow hero-fade d1">Люди. Идеи. Дела.</p>
      <h1 class="hero-title display" aria-label="Щёлково делаем мы">
        <span class="line" aria-hidden="true"><span>Щёлково</span></span>
        <span class="line" aria-hidden="true"><span>делаем <em class="stamp">мы</em></span></span>
      </h1>
      <p class="hero-sub hero-fade d2">Местное отделение «Молодой Гвардии Единой России». Объединяем молодёжь округа, которой не&nbsp;всё равно, какими будут страна и&nbsp;родной город.</p>
      <div class="hero-ctas hero-fade d3">
        <?php if ($me): ?>
          <a class="btn btn-accent btn-lg" href="<?= url(homeForRole($me['role'])) ?>" id="heroCta">Личный кабинет<?= icon('arrow-right') ?></a>
        <?php elseif ($registrationOpen): ?>
          <a class="btn btn-accent btn-lg" href="<?= url('register.php') ?>" id="heroCta">Стать волонтёром<?= icon('arrow-right') ?></a>
        <?php else: ?>
          <a class="btn btn-accent btn-lg" href="<?= url('contacts.php') ?>" id="heroCta">Связаться с нами<?= icon('arrow-right') ?></a>
        <?php endif; ?>
        <a class="link-arrow" href="#creed">Во что мы верим<?= icon('arrow-up-right') ?></a>
      </div>
    </div>
    <div class="hero-media">
      <div class="frame-shadow"></div>
      <div class="frame" id="heroFrame">
        <?php foreach ($heroPhotos as $i => $slide): ?>
          <img class="<?= $i === 0 ? 'is-on' : '' ?>" src="<?= url($slide['image']) ?>" alt="<?= e($slide['caption'] ?? '') ?>" <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<!-- Цифры -->
<section class="numbers" aria-label="Отделение в цифрах">
  <div class="wrap">
    <div class="numbers-row" data-reveal>
      <div class="num"><b class="display" data-count="<?= $statVolunteers ?>"><?= number_format($statVolunteers, 0, '.', ' ') ?><span class="plus">+</span></b><span class="num-label"><?= plural($statVolunteers, 'волонтёр', 'волонтёра', 'волонтёров') ?> в&nbsp;отделении</span></div>
      <div class="num"><b class="display" data-count="<?= $statEvents ?>"><?= number_format($statEvents, 0, '.', ' ') ?></b><span class="num-label"><?= plural($statEvents, 'мероприятие', 'мероприятия', 'мероприятий') ?> в&nbsp;<?= date('Y') ?>&nbsp;году</span></div>
      <div class="num"><b class="display" data-count="<?= $statHours ?>"><?= number_format($statHours, 0, '.', ' ') ?><span class="plus">+</span></b><span class="num-label"><?= plural($statHours, 'час', 'часа', 'часов') ?> добровольчества</span></div>
    </div>
  </div>
</section>

<!-- Лента -->
<div class="band" aria-hidden="true">
  <div class="band-track">
    <?php for ($k = 0; $k < 2; $k++): ?>
      <div class="band-group">
        <span>Люди</span><?= icon('star-four') ?>
        <span>Идеи</span><?= icon('star-four') ?>
        <span>Дела</span><?= icon('star-four') ?>
        <span>Молодая Гвардия Щёлково</span><?= icon('star-four') ?>
      </div>
    <?php endfor; ?>
  </div>
</div>

<!-- Во что мы верим -->
<section class="creed" id="creed">
  <div class="wrap">
    <h2 class="h2 display" data-reveal>Во что мы верим</h2>
    <div class="creed-list">
      <article class="creed-row" style="--i:0" data-reveal>
        <h3 class="creed-word display">Люди<span>.</span></h3>
        <p>Молодёжь Щёлковского округа от&nbsp;14&nbsp;лет: школьники, студенты, работающие ребята. Нас объединяет не&nbsp;возраст, а&nbsp;желание участвовать в&nbsp;жизни своего города.</p>
      </article>
      <article class="creed-row" style="--i:1" data-reveal>
        <h3 class="creed-word display">Идеи<span>.</span></h3>
        <p>Любовь к&nbsp;своей стране и&nbsp;уважение к&nbsp;её истории. Память о&nbsp;подвиге предков, забота о&nbsp;старшем поколении, ответственность за&nbsp;место, где живёшь.</p>
      </article>
      <article class="creed-row" style="--i:2" data-reveal>
        <h3 class="creed-word display">В&nbsp;деле<span>.</span></h3>
        <p>Идеи ничего не&nbsp;стоят без поступков. Поэтому мы выходим на&nbsp;шествия и&nbsp;памятные акции, помогаем жителям, участвуем в&nbsp;благоустройстве и&nbsp;устраиваем праздники для&nbsp;округа.</p>
      </article>
    </div>
  </div>
</section>

<!-- О Молодой Гвардии -->
<section class="org">
  <div class="wrap org-grid">
    <div class="org-media" data-reveal>
      <div class="frame-shadow"></div>
      <div class="frame"><img class="is-static" src="<?= url($orgPhoto['image']) ?>" alt="<?= e($orgPhoto['caption'] ?? 'Команда отделения') ?>" loading="lazy"></div>
    </div>
    <div class="org-copy" data-reveal>
      <h2 class="h2 display">Часть большой команды</h2>
      <div class="org-year"><b class="display">2005</b><span>год основания «Молодой Гвардии Единой России»</span></div>
      <p>Организация создана 16&nbsp;ноября 2005&nbsp;года в&nbsp;Воронеже. Сегодня это всероссийское молодёжное движение с&nbsp;отделениями в&nbsp;большинстве регионов страны.</p>
      <p>Наше местное отделение работает в&nbsp;Щёлковском городском округе: проводит патриотические и&nbsp;городские проекты, помогает жителям и&nbsp;зовёт в&nbsp;команду всех, кому не&nbsp;всё равно.</p>
      <a class="link-arrow" href="<?= url('about.php') ?>">Подробнее об&nbsp;организации<?= icon('arrow-up-right') ?></a>
    </div>
  </div>
</section>

<?php if ($directions): ?>
<!-- Направления -->
<section class="dirs" id="dirs">
  <div class="wrap">
    <div data-reveal>
      <h2 class="h2 display">Чем мы занимаемся</h2>
      <p class="lead"><?= count($directions) ?> <?= plural(count($directions), 'направление', 'направления', 'направлений') ?> работы отделения. В&nbsp;каждом есть место и&nbsp;новичку, и&nbsp;тому, кто готов вести за&nbsp;собой.</p>
    </div>
    <div class="dir-strip" data-reveal>
      <?php foreach ($directions as $i => $d):
        $meta = $dirMeta[$d['slug']] ?? [explode(' ', $d['title'])[0], 'star-four', 'assets/img/team.webp', '50% 50%']; ?>
        <article class="dir <?= $i === 0 ? 'is-open' : '' ?>" tabindex="0" aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>">
          <img src="<?= url($meta[2]) ?>" alt="" loading="lazy" style="object-position:<?= e($meta[3]) ?>">
          <span class="tint"></span>
          <span class="dir-ico"><?= icon($meta[1]) ?></span>
          <div class="dir-body">
            <span class="dir-short" aria-hidden="true"><?= e($meta[0]) ?></span>
            <div class="dir-full"><h3 class="display"><?= e($d['title']) ?></h3><?php if ($d['description']): ?><p><?= e($d['description']) ?>.</p><?php endif; ?></div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($news): ?>
<!-- Новости -->
<section class="news" id="news">
  <div class="wrap">
    <div class="sec-head" data-reveal>
      <h2 class="h2 display">Новости</h2>
      <div class="sec-tools">
        <a class="link-arrow" href="<?= url('news.php') ?>">Все новости<?= icon('arrow-up-right') ?></a>
        <button class="sq-btn" type="button" data-rail="-1" aria-label="Предыдущие новости"><?= icon('arrow-left') ?></button>
        <button class="sq-btn" type="button" data-rail="1" aria-label="Следующие новости"><?= icon('arrow-right') ?></button>
      </div>
    </div>
  </div>
  <div class="news-rail" id="newsRail" tabindex="0" aria-label="Лента новостей" data-reveal>
    <?php foreach ($news as $i => $n): $title = cleanNewsTitle($n['title'], (string)$n['body']); ?>
      <article class="n-card <?= $i === 0 ? 'n-lead' : '' ?>">
        <a href="<?= url('news-item.php?id=' . (int)$n['id']) ?>">
          <div class="n-img"><img src="<?= e($n['cover'] ? url('uploads/news/' . $n['cover']) : url('assets/img/march.webp')) ?>" alt="" loading="lazy"></div>
          <time datetime="<?= e(substr($n['published_at'], 0, 10)) ?>"><?= e(ruDate($n['published_at'])) ?></time>
          <h3><?= e($title) ?></h3>
          <?php if ($i === 0): ?><p><?= e(newsExcerpt($n, 190)) ?></p><?php endif; ?>
        </a>
      </article>
    <?php endforeach; ?>
    <span class="n-end" aria-hidden="true"></span>
  </div>
</section>
<?php endif; ?>

<?php if ($honor): ?>
<!-- Волонтёр месяца -->
<section class="honor">
  <div class="wrap">
    <div class="honor-card" data-reveal>
      <div class="honor-ava">
        <?php if ($honor['avatar']): ?>
          <img src="<?= url('uploads/avatars/' . $honor['avatar']) ?>" alt="">
        <?php else: ?>
          <span><?= e(mb_substr($honor['first_name'], 0, 1) . mb_substr($honor['last_name'], 0, 1)) ?></span>
        <?php endif; ?>
      </div>
      <div>
        <p class="kicker"><?= icon('crown') ?>Волонтёр месяца</p>
        <h2 class="display"><?= e($honor['first_name'] . ' ' . $honor['last_name']) ?></h2>
        <?php if (setting('honor_note')): ?><p class="note"><?= e(setting('honor_note')) ?></p><?php endif; ?>
      </div>
      <a class="btn btn-outline" href="<?= url('team.php') ?>">Вся команда<?= icon('arrow-right') ?></a>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($team): ?>
<!-- Команда -->
<section class="team" id="team">
  <div class="wrap">
    <div class="sec-head" data-reveal>
      <h2 class="h2 display">Штаб отделения</h2>
      <a class="link-arrow" href="<?= url('team.php') ?>">Вся команда<?= icon('arrow-up-right') ?></a>
    </div>
    <div class="team-grid" data-reveal>
      <?php foreach ($team as $i => $m): $name = $m['first_name'] . ' ' . $m['last_name']; ?>
        <figure class="t <?= $i === 0 && $m['position'] === 'leader' ? 't-lead' : '' ?>">
          <?php if ($m['avatar']): ?>
            <img src="<?= url('uploads/avatars/' . $m['avatar']) ?>" alt="<?= e($name) ?>" loading="lazy">
          <?php else: ?>
            <span class="initials" aria-hidden="true"><?= e(mb_substr($m['first_name'], 0, 1) . mb_substr($m['last_name'], 0, 1)) ?></span>
          <?php endif; ?>
          <figcaption><b><?= e($name) ?></b><span><?= e($m['position'] === 'leader' ? 'Руководитель местного отделения' : positionLabel($m['position'])) ?></span></figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- Вступление -->
<section class="join" id="join">
  <div class="wrap join-grid">
    <div class="join-copy">
      <h2 class="join-title display" data-reveal>Твой ход</h2>
      <p>Принимаем жителей Щёлковского округа от&nbsp;14&nbsp;лет. Опыт не&nbsp;нужен, достаточно желания участвовать.</p>
      <?php if ($registrationOpen && !$me): ?>
        <div class="join-ctas"><a class="btn btn-light btn-lg" href="<?= url('register.php') ?>">Стать волонтёром<?= icon('arrow-right') ?></a></div>
      <?php endif; ?>
    </div>
    <ul class="join-steps" data-reveal>
      <li><b class="display">Приходи в&nbsp;штаб</b><span><?= e(setting('org_address', 'Щёлково')) ?></span></li>
      <?php if (setting('org_leader_phone')): ?>
        <li><b class="display">Звони руководителю</b><span><?= e(setting('org_leader_name')) ?><?= setting('org_leader_name') ? ', ' : '' ?><a href="tel:<?= e(preg_replace('/[^\d+]/', '', setting('org_leader_phone'))) ?>"><?= e(setting('org_leader_phone')) ?></a></span></li>
      <?php endif; ?>
      <li><b class="display">Пиши нам</b><span>
        <?php $links = [];
          if (setting('org_email')) { $links[] = '<a href="mailto:' . e(setting('org_email')) . '">' . e(setting('org_email')) . '</a>'; }
          if (setting('org_vk')) { $links[] = '<a href="' . e(setting('org_vk')) . '" target="_blank" rel="noopener">ВКонтакте</a>'; }
          if (setting('org_tg')) { $links[] = '<a href="' . e(setting('org_tg')) . '" target="_blank" rel="noopener">Telegram</a>'; }
          $last = array_pop($links);
          echo $links ? implode(', ', $links) . ' и&nbsp;' . $last : ($last ?? '<a href="' . url('contacts.php') . '">Контакты</a>'); ?>
      </span></li>
    </ul>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
