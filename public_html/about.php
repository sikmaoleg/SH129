<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'О нас — Молодая Гвардия Щёлково';
$activeNav = 'about';
$directions = fetchAll('SELECT * FROM directions ORDER BY sort ASC, id ASC');
$dirIcons = ['patriot' => 'flag', 'help' => 'heart-hand', 'eco' => 'leaf', 'media' => 'camera', 'sport' => 'dumbbell', 'school' => 'academic-cap'];
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div class="container">
    <div class="breadcrumbs"><a href="<?= url('index.php') ?>">Главная</a> / О нас</div>
    <h1>О местном отделении</h1>
  </div>
</div>

<section class="section">
  <div class="container">
    <div class="article">
      <p><strong>«Молодая Гвардия Единой России»</strong> — всероссийская общественная организация. Наше местное отделение работает в Щёлковском городском округе Московской области и объединяет молодёжь, которая занимается добровольчеством: помогает жителям, участвует в городских проектах и проводит собственные мероприятия.</p>
      <p>Мы принимаем в движение жителей округа от 14 лет. Волонтёру не нужен опыт — достаточно желания участвовать. Координатор подскажет, с какого мероприятия удобнее начать, и поможет освоиться в команде.</p>
      <h2>Чем занимается отделение</h2>
      <p>Работа отделения разделена на направления. Волонтёр может участвовать в любом из них и переходить между направлениями — многие совмещают сразу несколько.</p>
    </div>

    <div class="dir-grid" id="directions" style="margin-top:36px;">
      <?php foreach ($directions as $i => $d): ?>
        <div class="dir-card">
          <div class="num"><?= icon($dirIcons[$d['slug']] ?? 'target') ?></div>
          <h3><?= e($d['title']) ?></h3>
          <p><?= e($d['description']) ?></p>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="article" style="margin-top:44px;">
      <h2>Как устроено участие</h2>
      <p>Каждый волонтёр отделения получает личный кабинет. В нём — афиша мероприятий с записью, история собственного участия и отметки о проделанной работе. Координаторы отмечают участие после каждого мероприятия, поэтому вклад волонтёра фиксируется и не теряется.</p>
      <p>Чтобы попасть в движение, подайте заявку — координатор проверит анкету и откроет доступ.</p>
      <p><a href="<?= url('register.php') ?>" class="btn btn-accent">Подать заявку</a></p>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
