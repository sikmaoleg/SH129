<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$me = requireAdmin();

$userId = (int)($_GET['id'] ?? 0);
$v = $userId ? fetchOne("SELECT * FROM users WHERE id = ? AND status IN ('approved','blocked')", [$userId]) : null;
if (!$v) {
    flash('error', 'Волонтёр не найден.');
    redirect('admin/users.php');
}

$panelSection = 'admin';
$panelTitle   = $v['first_name'] . ' ' . $v['last_name'];
$panelCrumb   = ['Волонтёры', 'admin/users.php'];
$activeItem   = 'users';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $action = $_POST['action'] ?? 'notes';

    if ($action === 'joined') {
        $joined = trim((string)($_POST['mger_joined_at'] ?? ''));
        if ($joined !== '' && !DateTime::createFromFormat('Y-m-d', $joined)) {
            flash('error', 'Некорректная дата вступления.');
        } else {
            q('UPDATE users SET mger_joined_at = ? WHERE id = ?', [$joined !== '' ? $joined : null, $userId]);
            logAction('mger_joined_update', 'user', $userId, $joined);
            flash('success', 'Дата вступления сохранена.');
        }
    } elseif ($action === 'public_consent') {
        $on = ($_POST['value'] ?? '') === '1';
        setPublicConsent($userId, $on, $on ? 'paper' : 'admin');
        logAction($on ? 'public_consent_paper' : 'public_consent_off', 'user', $userId);
        flash('success', $on
            ? 'Отмечено: письменное согласие на публикацию получено. Волонтёр может появиться в команде и на доске почёта.'
            : 'Публикация отключена: волонтёр больше не показывается на открытых страницах сайта.');
    } elseif ($action === 'delete_data') {
        if ($userId === (int)$me['id']) {
            flash('error', 'Свою учётную запись удаляйте в личном кабинете.');
        } elseif ($v['role'] !== 'volunteer' && !isDev()) {
            flash('error', 'Удалить администратора или разработчика может только разработчик.');
        } elseif (mb_strtolower(trim((string)($_POST['confirm_name'] ?? ''))) !== mb_strtolower($v['last_name'])) {
            flash('error', 'Для подтверждения введите фамилию волонтёра.');
        } else {
            $who = $v['first_name'] . ' ' . $v['last_name'];
            deleteUserData($userId, 'admin');
            logAction('user_delete', 'user', null, 'по запросу на удаление данных');
            flash('success', 'Данные волонтёра ' . $who . ' удалены.');
            redirect('admin/users.php');
        }
    } elseif ($action === 'badge_award') {
        $badgeId = (int)($_POST['badge_id'] ?? 0);
        if ($badgeId && fetchValue('SELECT id FROM badges WHERE id = ?', [$badgeId])) {
            q('INSERT IGNORE INTO user_badges (user_id, badge_id, awarded_by) VALUES (?,?,?)',
              [$userId, $badgeId, (int)$me['id']]);
            logAction('badge_award', 'user', $userId, (string)$badgeId);
            flash('success', 'Достижение выдано.');
        } else {
            flash('error', 'Достижение не найдено.');
        }
    } elseif ($action === 'badge_revoke') {
        $badgeId = (int)($_POST['badge_id'] ?? 0);
        q('DELETE FROM user_badges WHERE user_id = ? AND badge_id = ?', [$userId, $badgeId]);
        logAction('badge_revoke', 'user', $userId, (string)$badgeId);
        flash('info', 'Достижение снято.');
    } elseif ($action === 'avatar_upload') {
        $result = handleAvatarUpload($_FILES['avatar'] ?? []);
        if (isset($result['error'])) {
            flash('error', $result['error']);
        } else {
            deleteAvatarFile($v['avatar']);
            q('UPDATE users SET avatar = ? WHERE id = ?', [$result['filename'], $userId]);
            logAction('avatar_update', 'user', $userId);
            flash('success', 'Фото профиля обновлено.');
        }
    } elseif ($action === 'avatar_remove') {
        if ($v['avatar']) {
            deleteAvatarFile($v['avatar']);
            q('UPDATE users SET avatar = NULL WHERE id = ?', [$userId]);
            logAction('avatar_remove', 'user', $userId);
            flash('info', 'Фото профиля удалено.');
        }
    } elseif ($action === 'profile') {
        $fields = [
            'last_name'   => trim((string)($_POST['last_name'] ?? '')),
            'first_name'  => trim((string)($_POST['first_name'] ?? '')),
            'middle_name' => trim((string)($_POST['middle_name'] ?? '')),
            'email'       => mb_strtolower(trim((string)($_POST['email'] ?? ''))),
            'phone'       => trim((string)($_POST['phone'] ?? '')),
            'birth_date'  => trim((string)($_POST['birth_date'] ?? '')),
            'vk'          => trim((string)($_POST['vk'] ?? '')),
            'telegram'    => trim((string)($_POST['telegram'] ?? '')),
            'school'      => trim((string)($_POST['school'] ?? '')),
            'about'       => trim((string)($_POST['about'] ?? '')),
        ];
        $errors = [];
        if ($fields['last_name'] === '' || $fields['first_name'] === '') {
            $errors[] = 'Фамилия и имя обязательны.';
        }
        if ($fields['email'] === '' || !filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Электронная почта указана в неверном формате.';
        } elseif (fetchValue('SELECT id FROM users WHERE email = ? AND id <> ?', [$fields['email'], $userId])) {
            $errors[] = 'Этот адрес уже используется другим пользователем.';
        }
        if ($fields['birth_date'] !== '' && !DateTime::createFromFormat('Y-m-d', $fields['birth_date'])) {
            $errors[] = 'Некорректная дата рождения.';
        }
        if ($errors) {
            foreach ($errors as $er) { flash('error', $er); }
        } else {
            q('UPDATE users SET last_name=?, first_name=?, middle_name=?, email=?, phone=?, birth_date=?, vk=?, telegram=?, school=?, about=? WHERE id=?', [
                $fields['last_name'], $fields['first_name'], $fields['middle_name'] ?: null,
                $fields['email'], $fields['phone'] ?: null, $fields['birth_date'] !== '' ? $fields['birth_date'] : null,
                $fields['vk'] ?: null, $fields['telegram'] ?: null, $fields['school'] ?: null, $fields['about'] ?: null,
                $userId,
            ]);
            logAction('profile_update_by_admin', 'user', $userId);
            flash('success', 'Данные волонтёра обновлены.');
        }
    } else {
        $notes = trim((string)($_POST['coordinator_notes'] ?? ''));
        q('UPDATE users SET coordinator_notes = ? WHERE id = ?', [$notes !== '' ? $notes : null, $userId]);
        logAction('coordinator_notes_update', 'user', $userId);
        flash('success', 'Заметка сохранена.');
    }
    redirect('admin/volunteer.php?id=' . $userId);
}

$history = fetchAll(
    'SELECT t.*, a.last_name AS by_last, a.first_name AS by_first
     FROM point_transactions t LEFT JOIN users a ON a.id = t.created_by
     WHERE t.user_id = ? ORDER BY t.created_at DESC LIMIT 20', [$userId]
);

$allBadges = fetchAll('SELECT * FROM badges ORDER BY title ASC');
$myBadgeIds = fetchAll('SELECT badge_id, awarded_at FROM user_badges WHERE user_id = ?', [$userId]);
$earnedBadges = [];
foreach ($myBadgeIds as $r) {
    $earnedBadges[(int)$r['badge_id']] = $r['awarded_at'];
}
$availableBadges = array_filter($allBadges, fn($b) => !isset($earnedBadges[(int)$b['id']]));

$lvl      = levelFor((int)$v['points']);
$attended = (int)fetchValue("SELECT COUNT(*) FROM event_registrations WHERE user_id = ? AND status = 'attended'", [$userId]);
$ini      = mb_substr($v['first_name'], 0, 1) . mb_substr($v['last_name'], 0, 1);
$tgOk     = $v['telegram'] && preg_match('/^@?[A-Za-z0-9_]{4,}$/', $v['telegram']);
$panelLead = e(positionLabel($v['position'])) . ' · в организации с ' . e(ruDate(membershipDate($v)))
    . ($v['status'] === 'blocked' ? ' · <b>заблокирован</b>' : '');
$panelActions = ($v['phone'] ? '<a class="btn btn-line" href="tel:' . e(preg_replace('/[^\d+]/', '', $v['phone'])) . '">' . icon('phone') . 'Позвонить</a>' : '')
    . ($tgOk ? '<a class="btn btn-line" href="https://t.me/' . e(ltrim($v['telegram'], '@')) . '" target="_blank" rel="noopener">' . icon('telegram') . 'Telegram</a>' : '')
    . '<a class="btn btn-accent" href="' . url('admin/points.php?user_id=' . $userId) . '">' . icon('star') . 'Начислить баллы</a>';

require __DIR__ . '/../includes/panel_header.php';
?>

<div class="kpis">
  <div class="kpi"><div class="kpi-l"><span>Баллы</span><?= icon('star') ?></div><div class="kpi-v num"><?= number_format((int)$v['points'], 0, ',', ' ') ?></div><div class="kpi-d"><?= $lvl['next'] ? 'до уровня «' . e($lvl['next']['name']) . '» ' . (int)$lvl['to_next'] : 'максимальный уровень' ?></div></div>
  <div class="kpi"><div class="kpi-l"><span>Уровень</span><?= icon('trophy') ?></div><div class="kpi-v" style="font-size:30px"><?= e($lvl['current']['name']) ?></div><div class="meter-bar" style="margin-top:6px"><i style="width:<?= (int)$lvl['progress'] ?>%"></i></div></div>
  <div class="kpi"><div class="kpi-l"><span>Мероприятий</span><?= icon('calendar-check') ?></div><div class="kpi-v num"><?= $attended ?></div><div class="kpi-d">с отметкой «пришёл»</div></div>
  <div class="kpi"><div class="kpi-l"><span>Достижений</span><?= icon('medal') ?></div><div class="kpi-v num"><?= count($earnedBadges) ?></div><div class="kpi-d">из <?= count($allBadges) ?></div></div>
</div>

<div class="grid g-main">
  <section class="card">
    <div class="card-h"><div><h2>Данные</h2><p>Администратор может поправить анкету за волонтёра</p></div></div>
    <form method="post" class="card-b stack">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="profile">
      <div class="frow">
        <div class="field"><label for="last_name">Фамилия</label><input class="input" type="text" id="last_name" name="last_name" value="<?= e($v['last_name']) ?>" required></div>
        <div class="field"><label for="first_name">Имя</label><input class="input" type="text" id="first_name" name="first_name" value="<?= e($v['first_name']) ?>" required></div>
      </div>
      <div class="frow">
        <div class="field"><label for="middle_name">Отчество</label><input class="input" type="text" id="middle_name" name="middle_name" value="<?= e($v['middle_name']) ?>"></div>
        <div class="field"><label for="birth_date">Дата рождения</label><input class="input" type="date" id="birth_date" name="birth_date" value="<?= e($v['birth_date'] ?? '') ?>"></div>
      </div>
      <div class="frow">
        <div class="field"><label for="email">Электронная почта</label><input class="input" type="email" id="email" name="email" value="<?= e($v['email']) ?>" required></div>
        <div class="field"><label for="phone">Телефон</label><input class="input" type="tel" id="phone" name="phone" value="<?= e($v['phone']) ?>"></div>
      </div>
      <div class="frow">
        <div class="field"><label for="vk">ВКонтакте</label><input class="input" type="text" id="vk" name="vk" value="<?= e($v['vk']) ?>"></div>
        <div class="field"><label for="telegram">Telegram</label><input class="input" type="text" id="telegram" name="telegram" value="<?= e($v['telegram']) ?>"></div>
      </div>
      <div class="field"><label for="school">Школа, колледж или работа</label><input class="input" type="text" id="school" name="school" value="<?= e($v['school']) ?>"></div>
      <div class="field"><label for="about">О себе</label><textarea class="textarea" id="about" name="about"><?= e($v['about']) ?></textarea></div>
      <div><button type="submit" class="btn btn-accent">Сохранить данные</button></div>
    </form>
  </section>

  <div class="stack">
    <section class="card">
      <div class="card-h"><div><h2>Фото</h2><p>Обрежется по центру до квадрата</p></div></div>
      <div class="card-b">
        <div class="row" style="align-items:flex-start;gap:18px">
          <span class="ava lg"><?php if ($v['avatar']): ?><img src="<?= url('uploads/avatars/' . $v['avatar']) ?>" alt=""><?php else: ?><?= e($ini) ?><?php endif; ?></span>
          <div class="stack" style="gap:10px;min-width:0;flex:1">
            <form method="post" enctype="multipart/form-data" class="stack" style="gap:10px">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="avatar_upload">
              <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" required aria-label="Файл с фото" style="font-size:13.5px;max-width:100%">
              <div><button type="submit" class="btn btn-line btn-sm"><?= icon('upload') ?>Загрузить</button></div>
            </form>
            <?php if ($v['avatar']): ?>
              <form method="post" style="margin:0">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="avatar_remove">
                <button type="submit" class="btn btn-ghost btn-sm" data-confirm="Удалить фото профиля волонтёра?"><?= icon('trash') ?>Удалить фото</button>
              </form>
            <?php endif; ?>
            <span class="hint">JPG, PNG или WEBP, до 50 МБ.</span>
          </div>
        </div>
      </div>
    </section>

    <section class="card">
      <div class="card-h"><div><h2>Вступление в МГЕР</h2><p>Видят только администраторы</p></div></div>
      <form method="post" class="card-b">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="joined">
        <div class="row" style="align-items:flex-end;flex-wrap:wrap">
          <div class="field" style="flex:1;min-width:160px;margin:0"><label for="mger_joined_at">Дата вступления</label><input class="input" type="date" id="mger_joined_at" name="mger_joined_at" value="<?= e($v['mger_joined_at'] ?? '') ?>"></div>
          <button type="submit" class="btn btn-line">Сохранить</button>
        </div>
        <p class="hint" style="margin-top:8px">Оставь пустым, если дата неизвестна.</p>
      </form>
    </section>

    <section class="card">
      <div class="card-h"><div><h2>Заметка</h2><p>Сам волонтёр её не видит</p></div></div>
      <form method="post" class="card-b stack">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="notes">
        <textarea class="textarea" name="coordinator_notes" rows="5" aria-label="Заметка администратора" placeholder="Например: хорошо справляется с координацией, можно давать задачи крупнее"><?= e($v['coordinator_notes'] ?? '') ?></textarea>
        <div><button type="submit" class="btn btn-line">Сохранить заметку</button></div>
      </form>
    </section>
  </div>
</div>

<section class="card" style="margin-top:18px">
  <div class="card-h"><div><h2>Персональные данные</h2><p>Согласия волонтёра и удаление данных по его запросу</p></div></div>
  <ul class="list">
    <li>
      <span class="dot <?= $v['pd_consent_at'] ? 'ok' : 'warn' ?>"><?= icon($v['pd_consent_at'] ? 'check' : 'alert') ?></span>
      <div class="grow"><b>Согласие на обработку</b><small><?= $v['pd_consent_at'] ? 'Дано ' . e(ruDate($v['pd_consent_at'], true)) . ', редакция ' . e(ruDate((string)$v['pd_consent_version'])) : 'Ещё не подтверждено: волонтёр подтвердит при следующем входе в кабинет' ?></small></div>
    </li>
    <li>
      <span class="dot <?= (int)$v['public_consent'] === 1 ? 'ok' : '' ?>"><?= icon((int)$v['public_consent'] === 1 ? 'eye' : 'eye-off') ?></span>
      <div class="grow"><b>Публикация имени и фото на сайте</b><small><?= (int)$v['public_consent'] === 1 ? 'Разрешена с ' . e(ruDate((string)$v['public_consent_at'], true)) . '. Может быть в команде и на доске почёта.' : 'Не разрешена: на открытых страницах сайта не показывается.' ?></small></div>
      <form method="post" style="margin:0">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="public_consent">
        <input type="hidden" name="value" value="<?= (int)$v['public_consent'] === 1 ? '0' : '1' ?>">
        <?php if ((int)$v['public_consent'] === 1): ?>
          <button class="btn btn-line btn-sm" type="submit" data-confirm="Отключить публикацию? Волонтёр пропадёт из команды и с доски почёта.">Отключить</button>
        <?php else: ?>
          <button class="btn btn-line btn-sm" type="submit" data-confirm="Отмечайте, только если волонтёр подписал согласие на распространение на бумаге. Сохранить отметку?">Есть письменное согласие</button>
        <?php endif; ?>
      </form>
    </li>
    <?php if ($v['guardian_name'] || (ageFromBirthDate($v['birth_date']) ?? 99) < 18): ?>
      <li>
        <span class="dot <?= $v['guardian_consent_at'] ? 'ok' : 'warn' ?>"><?= icon('users') ?></span>
        <div class="grow"><b>Законный представитель</b><small><?= $v['guardian_name'] ? e($v['guardian_name']) . ($v['guardian_phone'] ? ', ' . e($v['guardian_phone']) : '') . ($v['guardian_consent_at'] ? '. Согласие подтверждено ' . e(ruDate($v['guardian_consent_at'])) : '') : 'Не указан. Волонтёру нет 18 лет: укажет при подтверждении согласия в кабинете.' ?></small></div>
      </li>
    <?php endif; ?>
  </ul>
  <form method="post" class="card-f danger-zone" style="justify-content:flex-start;border-style:dashed">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="delete_data">
    <p>Если волонтёр отозвал согласие или попросил удалить данные: учётная запись, записи на мероприятия, баллы и достижения удалятся безвозвратно.</p>
    <input class="input" name="confirm_name" placeholder="Фамилия для подтверждения" aria-label="Фамилия для подтверждения" style="max-width:240px" required>
    <button class="btn btn-ghost" type="submit" data-confirm="Удалить все данные волонтёра безвозвратно?"><?= icon('trash') ?>Удалить данные</button>
  </form>
</section>

<div class="grid g2" style="margin-top:18px">
  <section class="card">
    <div class="card-h"><div><h2>Достижения</h2><p><?= $earnedBadges ? 'Получено ' . count($earnedBadges) : 'Пока ни одного' ?></p></div><a class="btn btn-line btn-sm" href="<?= url('admin/badges.php') ?>">Все достижения</a></div>
    <div class="card-b stack">
      <?php if ($allBadges): ?>
        <?php if ($earnedBadges): ?>
          <ul class="list" style="margin:-20px -20px 0">
            <?php foreach ($allBadges as $b): if (!isset($earnedBadges[(int)$b['id']])) continue; ?>
              <li>
                <span class="bdg-ico sm"><?= $b['icon'] !== '' && $b['icon'] !== null ? e($b['icon']) : icon('badge-check') ?></span>
                <div class="grow"><b><?= e($b['title']) ?></b><small>Выдано <?= e(ruDate($earnedBadges[(int)$b['id']])) ?></small></div>
                <form method="post" style="margin:0">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="badge_revoke">
                  <input type="hidden" name="badge_id" value="<?= (int)$b['id'] ?>">
                  <button type="submit" class="btn btn-ghost btn-sm" data-confirm="Снять достижение «<?= e($b['title']) ?>»?">Снять</button>
                </form>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <?php if ($availableBadges): ?>
          <form method="post" class="row" style="flex-wrap:wrap">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="badge_award">
            <select class="select" name="badge_id" required aria-label="Достижение" style="flex:1;min-width:200px">
              <option value="">Выбери достижение</option>
              <?php foreach ($availableBadges as $b): ?><option value="<?= (int)$b['id'] ?>"><?= e($b['title']) ?></option><?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-accent"><?= icon('medal') ?>Выдать</button>
          </form>
        <?php else: ?>
          <p class="muted">Все существующие достижения уже выданы.</p>
        <?php endif; ?>
      <?php else: ?>
        <div class="empty"><?= icon('medal') ?><b>Достижения ещё не созданы</b><a href="<?= url('admin/badges.php?new=1') ?>">Создать первое</a></div>
      <?php endif; ?>
    </div>
  </section>

  <section class="card">
    <div class="card-h"><div><h2>История баллов</h2><p>Последние 20 операций</p></div></div>
    <?php if ($history): ?>
      <ul class="list">
        <?php foreach ($history as $t): $pv = (int)$t['points']; ?>
          <li>
            <span class="num <?= $pv >= 0 ? 'pos' : 'neg' ?>" style="min-width:52px"><?= $pv > 0 ? '+' . $pv : '−' . abs($pv) ?></span>
            <div class="grow"><b style="font-weight:500"><?= e($t['reason']) ?></b><small><?= e(ruDate($t['created_at'], true)) ?> · <?= e(trim(($t['by_first'] ?? '') . ' ' . ($t['by_last'] ?? '')) ?: 'система') ?></small></div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <div class="empty"><?= icon('star') ?><b>Начислений ещё не было</b></div>
    <?php endif; ?>
  </section>
</div>

<?php require __DIR__ . '/../includes/panel_footer.php'; ?>
