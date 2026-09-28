<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'О нас: Молодая Гвардия Щёлково';
$activeNav = 'about';
$photo = fetchOne('SELECT image, caption FROM hero_slides ORDER BY sort ASC, id ASC LIMIT 1 OFFSET 2')
      ?? ['image' => 'assets/img/creative.webp', 'caption' => 'Команда отделения'];
require __DIR__ . '/includes/header.php';
?>
<header class="phead">
  <div class="wrap">
    <nav class="crumbs" aria-label="Навигация"><a href="<?= url('index.php') ?>">Главная</a><span>/</span><span>О нас</span></nav>
    <h1 class="phead-title display">О&nbsp;<em class="stamp">нас</em></h1>
    <p class="phead-lead">Местное отделение «Молодой Гвардии Единой России» в&nbsp;Щёлковском городском округе Московской области.</p>
  </div>
</header>

<section class="sec">
  <div class="wrap">
    <p class="manifesto" data-reveal>Мы объединяем молодёжь округа, которая <mark>помогает жителям</mark>, участвует в&nbsp;<mark>патриотических и&nbsp;городских проектах</mark> и&nbsp;сама придумывает дела для своего города.</p>
  </div>
</section>

<section class="sec">
  <div class="wrap">
    <h2 class="h2 display" data-reveal>История</h2>
    <ol class="timeline" data-reveal>
      <li style="--i:0"><b class="display">2000</b><span>Появляется движение «Молодёжное Единство», из&nbsp;которого вырастет организация.</span></li>
      <li style="--i:1"><b class="display">2005</b><span>16&nbsp;ноября на&nbsp;учредительном съезде в&nbsp;Воронеже создана «Молодая Гвардия Единой России», молодёжное крыло партии «Единая Россия».</span></li>
      <li style="--i:2"><b class="display">Сегодня</b><span>Всероссийская общественно-политическая молодёжная организация с&nbsp;отделениями в&nbsp;большинстве регионов страны.</span></li>
      <li style="--i:3" class="now"><b class="display">Щёлково</b><span>Наше местное отделение работает в&nbsp;Щёлковском городском округе.</span></li>
    </ol>
  </div>
</section>

<section class="sec">
  <div class="wrap">
    <h2 class="h2 display" data-reveal>За что мы берёмся</h2>
    <ul class="tags" data-reveal>
      <li class="fill">Патриотическое воспитание</li>
      <li>Добровольчество</li>
      <li>Социальные проекты</li>
      <li class="fill">Поддержка ветеранов</li>
      <li>Забота о&nbsp;старшем поколении</li>
      <li>Экология</li>
      <li>Благоустройство</li>
      <li class="fill">Спорт и&nbsp;ЗОЖ</li>
      <li>Городская среда</li>
      <li>Работа со&nbsp;студенчеством</li>
    </ul>
  </div>
</section>

<section class="sec">
  <div class="wrap split">
    <div class="split-media" data-reveal>
      <div class="frame-shadow"></div>
      <div class="frame"><img class="is-static" src="<?= url($photo['image']) ?>" alt="<?= e($photo['caption'] ?? '') ?>" loading="lazy"></div>
    </div>
    <div data-reveal>
      <h2 class="h2 display">Кого мы ждём</h2>
      <p class="body-l">Мы принимаем в&nbsp;организацию жителей округа от&nbsp;14&nbsp;лет. Опыт не&nbsp;нужен, достаточно желания участвовать.</p>
      <p class="body-l">На&nbsp;первом деле рядом будут те, кто уже в&nbsp;команде: подскажут, с&nbsp;чего начать, и&nbsp;помогут освоиться.</p>
      <?php if (setting('registration_open', '1') === '1'): ?>
        <div class="row-ctas"><a class="btn btn-accent btn-lg" href="<?= url('register.php') ?>">Стать волонтёром<?= icon('arrow-right') ?></a></div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
