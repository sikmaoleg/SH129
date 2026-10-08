<?php
/**
 * Подтверждение согласия для участников, зарегистрированных до появления
 * отдельного документа о согласии. Без подтверждения кабинет не открывается;
 * вместо этого можно удалить учётную запись.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/legal.php';
$me = requireLogin();

$next = safeReturnPath((string)($_GET['next'] ?? $_POST['next'] ?? ''));
if (!empty($me['pd_consent_at'])) {
    redirect($next !== '' ? $next : homeForRole($me['role']));
}

$age = ageFromBirthDate($me['birth_date'] ?? null);
$isMinor = $age !== null && $age < 18;
$errors = [];
$old = [
    'guardian_name'  => (string)($me['guardian_name'] ?? ''),
    'guardian_phone' => (string)($me['guardian_phone'] ?? ''),
];
$publicChecked = (int)($me['public_consent'] ?? 0) === 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        if ($me['role'] === 'dev') {
            $errors['delete'] = 'Учётную запись разработчика удалить отсюда нельзя: сначала передайте роль другому человеку.';
        } elseif (!password_verify((string)($_POST['password'] ?? ''), $me['password_hash'])) {
            $errors['delete'] = 'Пароль указан неверно.';
        } else {
            deleteUserData((int)$me['id'], 'self');
            logout();
            session_start();
            session_regenerate_id(true); // новая сессия, чтобы сообщение дошло после выхода
            flash('info', 'Учётная запись и ваши данные удалены. Спасибо, что были с нами.');
            redirect('index.php');
        }
    } else {
        $old['guardian_name']  = trim((string)($_POST['guardian_name'] ?? ''));
        $old['guardian_phone'] = trim((string)($_POST['guardian_phone'] ?? ''));
        $publicChecked = !empty($_POST['agree_public']);
        if (empty($_POST['agree_pd'])) {
            $errors['agree_pd'] = 'Отметьте согласие на обработку персональных данных или удалите учётную запись.';
        }
        if ($isMinor) {
            if (mb_strlen($old['guardian_name']) < 5)  $errors['guardian_name'] = 'Укажите ФИО родителя или законного представителя.';
            if ($old['guardian_phone'] === '')         $errors['guardian_phone'] = 'Укажите телефон родителя или законного представителя.';
            if (empty($_POST['agree_guardian']))       $errors['agree_guardian'] = 'Нужно подтверждение согласия родителя или законного представителя.';
        }
        if (!$errors) {
            q('UPDATE users SET pd_consent_at = NOW(), pd_consent_version = ?, guardian_name = ?, guardian_phone = ?, guardian_consent_at = ? WHERE id = ?', [
                CONSENT_VERSION,
                $isMinor ? $old['guardian_name'] : ($me['guardian_name'] ?: null),
                $isMinor ? $old['guardian_phone'] : ($me['guardian_phone'] ?: null),
                $isMinor ? date('Y-m-d H:i:s') : ($me['guardian_consent_at'] ?: null),
                (int)$me['id'],
            ]);
            recordConsent((int)$me['id'], 'pd', 'grant', 'site');
            if ($isMinor) {
                recordConsent((int)$me['id'], 'guardian', 'grant', 'site');
            }
            setPublicConsent((int)$me['id'], $publicChecked, 'site');
            logAction('consent_confirm', 'user', (int)$me['id']);
            flash('success', 'Спасибо! Согласие сохранено.' . ($publicChecked ? '' : ' Имя и фото на открытых страницах сайта не показываются: это можно изменить в разделе «Мои данные».'));
            redirect($next !== '' ? $next : homeForRole($me['role']));
        }
    }
}

$pageTitle = 'Согласие на обработку данных: Молодая Гвардия Щёлково';
$activeNav = '';
$navCta    = 'none';
require __DIR__ . '/../includes/header.php';
?>
<section class="sec form-page">
  <div class="wrap form-grid">
    <div class="form-side">
      <h1 class="phead-title display" style="font-size:clamp(48px,6vw,88px)">Подтвердите согласие</h1>
      <p class="phead-lead">Мы оформили по&nbsp;закону документы о&nbsp;персональных данных. Чтобы продолжить пользоваться личным кабинетом, прочитайте их и&nbsp;подтвердите согласие.</p>
      <p class="side-note"><?= icon('info') ?>Если не&nbsp;согласны, можно удалить учётную запись: данные будут уничтожены.</p>
    </div>

    <div>
      <form class="vform" method="post" novalidate data-validate>
        <?= csrfField() ?>
        <input type="hidden" name="action" value="confirm">
        <?php if ($next !== ''): ?><input type="hidden" name="next" value="<?= e($next) ?>"><?php endif; ?>
        <?php $mainErrors = array_diff_key($errors, ['delete' => 1]); ?>
        <?php if ($mainErrors): ?>
          <div class="alert alert-error" role="alert"><ul><?php foreach ($mainErrors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <fieldset>
          <legend>Согласие на обработку персональных данных</legend>
          <div class="consent-box" tabindex="0" aria-label="Текст согласия"><?= legalRender(legalConsentHtml()) ?></div>
          <p class="hint">Также доступно отдельной страницей: <a href="<?= url('consent.php') ?>" target="_blank" rel="noopener">согласие</a>, <a href="<?= url('privacy.php') ?>" target="_blank" rel="noopener">Политика</a>.</p>
        </fieldset>

        <?php if ($isMinor): ?>
          <fieldset class="guardian">
            <legend>Родитель или законный представитель</legend>
            <p class="guardian-note"><?= icon('info') ?><span>Вам меньше 18 лет, поэтому согласие даётся с&nbsp;ведома родителя или законного представителя.</span></p>
            <div class="frow">
              <div class="field<?= isset($errors['guardian_name']) ? ' bad' : '' ?>"><label for="guardian_name">ФИО родителя или представителя <i>*</i></label><input id="guardian_name" name="guardian_name" value="<?= e($old['guardian_name']) ?>" required><p class="err"><?= e($errors['guardian_name'] ?? 'Укажите ФИО полностью') ?></p></div>
              <div class="field<?= isset($errors['guardian_phone']) ? ' bad' : '' ?>"><label for="guardian_phone">Его телефон <i>*</i></label><input id="guardian_phone" name="guardian_phone" type="tel" inputmode="tel" value="<?= e($old['guardian_phone']) ?>" required><p class="err"><?= e($errors['guardian_phone'] ?? 'Укажите телефон') ?></p></div>
            </div>
            <label class="check"><input type="checkbox" name="agree_guardian" value="1" required><span>Мой родитель или законный представитель ознакомлен с&nbsp;согласием и&nbsp;Политикой и&nbsp;согласен <i>*</i></span></label>
            <p class="err err-check <?= isset($errors['agree_guardian']) ? 'show' : '' ?>" data-for="agree_guardian">Нужно подтверждение родителя или представителя</p>
          </fieldset>
        <?php endif; ?>

        <fieldset class="consents">
          <legend>Ваше решение</legend>
          <label class="check"><input type="checkbox" name="agree_pd" value="1" required><span>Даю согласие на&nbsp;обработку персональных данных на&nbsp;условиях выше и&nbsp;подтверждаю, что ознакомлен(а) с&nbsp;Политикой <i>*</i></span></label>
          <p class="err err-check <?= isset($errors['agree_pd']) ? 'show' : '' ?>" data-for="agree_pd">Без этого согласия кабинет недоступен</p>
          <label class="check"><input type="checkbox" name="agree_public" value="1" <?= $publicChecked ? 'checked' : '' ?>><span>Разрешаю показывать моё имя и&nbsp;фото на&nbsp;сайте, в&nbsp;команде и&nbsp;на&nbsp;доске почёта (<a href="<?= url('consent-public.php') ?>" target="_blank" rel="noopener">согласие на&nbsp;распространение</a>). Необязательно.</span></label>
        </fieldset>
        <button class="btn btn-accent btn-lg btn-block" type="submit">Подтвердить и&nbsp;продолжить<?= icon('arrow-right') ?></button>
      </form>

      <details class="consent-delete" <?= isset($errors['delete']) ? 'open' : '' ?>>
        <summary>Не согласен, удалить мою учётную запись</summary>
        <form method="post" class="vform">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="delete">
          <?php if (isset($errors['delete'])): ?><div class="alert alert-error" role="alert"><?= e($errors['delete']) ?></div><?php endif; ?>
          <p>Удалятся анкета, записи на&nbsp;мероприятия, баллы, достижения и&nbsp;фото. Восстановить их будет нельзя.</p>
          <div class="field"><label for="del_password">Пароль для подтверждения</label><input id="del_password" name="password" type="password" autocomplete="current-password" required></div>
          <button class="btn btn-outline" type="submit" data-confirm="Удалить учётную запись и все данные безвозвратно?"><?= icon('trash') ?>Удалить учётную запись</button>
        </form>
      </details>
      <p class="form-foot"><a href="<?= url('logout.php') ?>">Выйти</a></p>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
