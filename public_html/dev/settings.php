<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireDev();

$panelSection = 'dev';
$panelTitle   = 'Настройки сайта';
$activeItem   = 'settings';

$editable = [
    'org_name'           => ['Название организации', 'text'],
    'org_leader_name'    => ['ФИО руководителя местного отделения', 'text'],
    'org_leader_phone'   => ['Телефон руководителя', 'text'],
    'org_email'          => ['Электронная почта', 'text'],
    'org_address'        => ['Адрес', 'text'],
    'org_vk'             => ['Ссылка на ВКонтакте', 'text'],
    'org_tg'             => ['Ссылка на Telegram', 'text'],
    'registration_open'  => ['Приём заявок открыт (1 — да, 0 — нет)', 'text'],
    'points_per_hour'    => ['Очков за час работы (ориентир)', 'number'],
    'points_coordinator' => ['Очков за координацию (ориентир)', 'number'],
    'level_thresholds'   => ['Пороги уровней через запятую', 'text'],
    'level_names'        => ['Названия уровней через запятую', 'text'],
    'stat_volunteers'    => ['Волонтёров на главной (0 — считать автоматически)', 'number'],
    'stat_events'        => ['Мероприятий на главной (0 — автоматически)', 'number'],
    'stat_hours'         => ['Часов на главной (0 — автоматически)', 'number'],
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
            $errors[] = 'Пороги уровней — только целые числа через запятую.';
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

require __DIR__ . '/../includes/panel_header.php';
?>

<div class="card">
  <div class="card-head">
    <div><h2>Параметры сайта</h2><p>Эти значения подставляются в шапку, подвал и расчёт уровней.</p></div>
  </div>
  <div class="card-body">
    <form method="post">
      <?= csrfField() ?>
      <?php foreach ($editable as $key => [$label, $type]): ?>
        <div class="field">
          <label for="s_<?= e($key) ?>"><?= e($label) ?></label>
          <input type="<?= $type === 'number' ? 'number' : 'text' ?>" id="s_<?= e($key) ?>" name="<?= e($key) ?>"
                 value="<?= e(setting($key)) ?>">
          <div class="hint mono"><?= e($key) ?></div>
        </div>
      <?php endforeach; ?>
      <button type="submit" class="btn btn-primary">Сохранить настройки</button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-head"><div><h2>Текущая шкала уровней</h2></div></div>
  <div class="card-body card-body-flush table-wrap">
    <table class="data">
      <thead><tr><th>№</th><th>Название</th><th>Диапазон очков</th></tr></thead>
      <tbody>
        <?php foreach (levels() as $l): ?>
          <tr><td class="num"><?= $l['index'] ?></td><td><?= e($l['name']) ?></td>
              <td class="mono"><?= $l['min'] ?><?= $l['max'] !== null ? '–' . $l['max'] : '+' ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../includes/panel_footer.php'; ?>
