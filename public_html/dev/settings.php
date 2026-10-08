<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireDev();

$panelSection = 'dev';
$panelTitle   = 'Настройки';
$activeItem   = 'settings';

$editable = [
    'org_name'           => ['Название организации', 'text'],
    'org_leader_name'    => ['ФИО руководителя местного отделения', 'text'],
    'org_leader_phone'   => ['Телефон руководителя', 'text'],
    'org_email'          => ['Электронная почта', 'text'],
    'org_address'        => ['Адрес', 'text'],
    'org_map_coords'     => ['Координаты офиса на карте (широта,долгота)', 'text'],
    'org_vk'             => ['Ссылка на ВКонтакте', 'text'],
    'org_tg'             => ['Ссылка на Telegram', 'text'],
    'registration_open'  => ['Приём заявок открыт (1 — да, 0 — нет)', 'text'],
    'points_coordinator' => ['Баллов за координацию (ориентир)', 'number'],
    'level_thresholds'   => ['Пороги уровней через запятую', 'text'],
    'level_names'        => ['Названия уровней через запятую', 'text'],
    'stat_volunteers'    => ['Волонтёров на главной (0 — считать автоматически)', 'number'],
    'stat_events'        => ['Мероприятий на главной (0 — автоматически)', 'number'],
    'stat_hours'         => ['Часов на главной (0 — автоматически)', 'number'],
    'legal_operator_name' => ['Оператор персональных данных', 'text'],
    'legal_inn'           => ['ИНН оператора', 'text'],
    'legal_ogrn'          => ['ОГРН оператора', 'text'],
    'legal_address'       => ['Юридический адрес оператора', 'text'],
    'legal_email'         => ['Почта для запросов по персональным данным', 'text'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $errors = [];

    // Проверяем согласованность уровней до сохранения
    $th = array_map('trim', explode(',', (string)($_POST['level_thresholds'] ?? '')));
    $nm = array_map('trim', explode(',', (string)($_POST['level_names'] ?? '')));
    if (count($th) !== count($nm)) {
        $errors[] = 'Количество порогов и названий уровней должно совпадать.';
    }
    foreach ($th as $v) {
        if ($v === '' || !ctype_digit($v)) {
            $errors[] = 'Пороги уровней: только целые числа.';
            break;
        }
    }
    $sorted = $th;
    sort($sorted, SORT_NUMERIC);
    if ($sorted !== $th) {
        $errors[] = 'Пороги уровней должны идти по возрастанию.';
    }

    if ($errors) {
        foreach ($errors as $er) { flash('error', $er); }
    } else {
        foreach ($editable as $key => $_) {
            if (array_key_exists($key, $_POST)) {
                setSetting($key, trim((string)$_POST[$key]));
            }
        }
        logAction('settings_update', 'settings');
        flash('success', 'Настройки сохранены.');
    }
    redirect('dev/settings.php');
}

$lv = levels();
$f = fn(string $key, string $label, string $hint = '', string $type = 'text', string $ph = '') =>
    '<div class="field"><label for="s_' . $key . '">' . e($label) . '</label><input class="input" type="' . $type . '" id="s_' . $key . '" name="' . $key . '" value="' . e(setting($key)) . '"' . ($ph !== '' ? ' placeholder="' . e($ph) . '"' : '') . '>' . ($hint !== '' ? '<span class="hint">' . e($hint) . '</span>' : '') . '</div>';
$stats = [
    'stat_volunteers' => ['Волонтёров', 'Сейчас считается по базе'],
    'stat_events'     => ['Мероприятий', 'Проведённые мероприятия из базы'],
    'stat_hours'      => ['Часов добровольчества', 'Сумма часов из базы'],
];
$panelLead = 'Контакты, приём заявок, уровни и цифры на главной. Изменения появятся на сайте сразу после сохранения.';

require __DIR__ . '/../includes/panel_header.php';
?>

<form method="post" id="settingsForm" class="set-layout">
  <?= csrfField() ?>
  <nav class="set-nav" aria-label="Разделы настроек">
    <a href="#set-org" class="on">Организация</a>
    <a href="#set-reg">Приём заявок</a>
    <a href="#set-levels">Баллы и уровни</a>
    <a href="#set-stats">Цифры на главной</a>
    <a href="#set-legal">Персональные данные</a>
  </nav>

  <div class="stack">
    <section class="card set-sec" id="set-org">
      <div class="card-h"><div><h2>Организация</h2><p>Шапка, подвал, страница контактов</p></div></div>
      <div class="card-b stack">
        <?= $f('org_name', 'Название организации') ?>
        <div class="frow"><?= $f('org_leader_name', 'Руководитель местного отделения') ?><?= $f('org_leader_phone', 'Телефон руководителя', '', 'tel') ?></div>
        <div class="frow"><?= $f('org_email', 'Электронная почта', '', 'email') ?><?= $f('org_address', 'Адрес') ?></div>
        <?= $f('org_map_coords', 'Координаты офиса на карте', 'Широта и долгота через запятую, как в Яндекс Картах', 'text', '55.919256, 37.994084') ?>
        <div class="frow"><?= $f('org_vk', 'Ссылка на ВКонтакте') ?><?= $f('org_tg', 'Ссылка на Telegram') ?></div>
      </div>
    </section>

    <section class="card set-sec" id="set-reg">
      <div class="card-h"><div><h2>Приём заявок</h2><p>Кнопка «Вступить» и анкета на сайте</p></div></div>
      <div class="card-b">
        <input type="hidden" id="registration_open" name="registration_open" value="<?= setting('registration_open', '1') === '1' ? '1' : '0' ?>">
        <label class="switchbox">
          <span><b>Анкета открыта</b><small>Если выключить, форма вступления покажет, что приём временно закрыт</small></span>
          <input type="checkbox" id="registration_toggle" <?= setting('registration_open', '1') === '1' ? 'checked' : '' ?>><span class="toggle"></span>
        </label>
      </div>
    </section>

    <section class="card set-sec" id="set-levels">
      <div class="card-h"><div><h2>Баллы и уровни</h2><p>Уровень волонтёра зависит от суммы баллов</p></div></div>
      <div class="card-b stack">
        <?= $f('points_coordinator', 'Баллов за координацию', 'Подсказка на странице начисления, сама ничего не начисляет', 'number') ?>
        <input type="hidden" id="level_thresholds" name="level_thresholds" value="<?= e(setting('level_thresholds', '0,100,300,700,1500')) ?>">
        <input type="hidden" id="level_names" name="level_names" value="<?= e(setting('level_names', 'Новичок,Активист,Опытный,Наставник,Легенда')) ?>">
        <div class="stack" style="gap:10px" id="lvRows">
          <div class="lv-row lv-headrow"><span></span><span class="lbl">Название уровня</span><span class="lbl">С каких баллов</span></div>
          <?php foreach ($lv as $l): ?>
            <div class="lv-row">
              <span class="lv-n"><?= $l['index'] ?></span>
              <input class="input" data-lv="name" value="<?= e($l['name']) ?>" aria-label="Название уровня <?= $l['index'] ?>" required>
              <input class="input num" data-lv="min" type="number" min="0" step="1" value="<?= (int)$l['min'] ?>" aria-label="Порог уровня <?= $l['index'] ?>" required <?= $l['index'] === 1 ? 'readonly' : '' ?>>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="row" style="flex-wrap:wrap">
          <button type="button" class="btn btn-line btn-sm" data-lv-add><?= icon('plus') ?>Добавить уровень</button>
          <button type="button" class="btn btn-ghost btn-sm" data-lv-del><?= icon('trash') ?>Убрать последний</button>
          <span class="hint">Первый уровень всегда с нуля. Пороги идут по возрастанию.</span>
        </div>
      </div>
    </section>

    <section class="card set-sec" id="set-stats">
      <div class="card-h"><div><h2>Цифры на главной</h2><p>По умолчанию считаются сами. Включи, чтобы задать число вручную</p></div></div>
      <div class="card-b stack">
        <?php foreach ($stats as $key => [$label, $hint]): $val = (int)setting($key, '0'); ?>
          <div class="stat-row">
            <label class="switchbox">
              <span><b><?= e($label) ?></b><small><?= $val > 0 ? 'Задано вручную' : e($hint) ?></small></span>
              <input type="checkbox" data-stat-toggle="s_<?= $key ?>" <?= $val > 0 ? 'checked' : '' ?> aria-label="<?= e($label) ?>: задать вручную"><span class="toggle"></span>
            </label>
            <input class="input num" type="number" min="0" id="s_<?= $key ?>" name="<?= $key ?>" value="<?= $val ?>" data-last="<?= $val ?: '' ?>" <?= $val > 0 ? '' : 'disabled' ?> aria-label="<?= e($label) ?>">
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="card set-sec" id="set-legal">
      <div class="card-h"><div><h2>Оператор персональных данных</h2><p>Подставляется в Политику и тексты согласий на сайте</p></div><?= operatorInfo()['filled'] ? '<span class="chip ok">' . icon('check') . 'Заполнено</span>' : '<span class="chip warn">' . icon('alert') . 'Не заполнено</span>' ?></div>
      <div class="card-b stack">
        <?= $f('legal_operator_name', 'Полное наименование организации', 'Как в ЕГРЮЛ: юрлицо, которое отвечает за данные волонтёров (например, региональное отделение)', 'text', 'Московское областное региональное отделение ВОО «Молодая Гвардия Единой России»') ?>
        <div class="frow"><?= $f('legal_inn', 'ИНН') ?><?= $f('legal_ogrn', 'ОГРН') ?></div>
        <?= $f('legal_address', 'Юридический адрес', 'Если пусто, берётся адрес штаба') ?>
        <?= $f('legal_email', 'Почта для запросов по персональным данным', 'Сюда пишут, чтобы узнать, исправить или удалить свои данные. Если пусто, берётся общая почта') ?>
        <p class="hint">Документы: <a href="<?= url('privacy.php') ?>" target="_blank" rel="noopener">Политика</a>, <a href="<?= url('consent.php') ?>" target="_blank" rel="noopener">Согласие на обработку</a>, <a href="<?= url('consent-public.php') ?>" target="_blank" rel="noopener">Согласие на распространение</a>.</p>
      </div>
    </section>

    <div class="dirty" id="dirty" hidden>
      <?= icon('alert') ?><p>Есть несохранённые изменения</p>
      <a class="btn btn-line btn-sm" href="<?= url('dev/settings.php') ?>">Отменить</a>
      <button type="submit" class="btn btn-accent btn-sm">Сохранить</button>
    </div>
    <div><button type="submit" class="btn btn-accent">Сохранить настройки</button></div>
  </div>
</form>

<?php require __DIR__ . '/../includes/panel_footer.php'; ?>
