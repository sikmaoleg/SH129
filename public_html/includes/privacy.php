<?php
/**
 * Персональные данные по 152-ФЗ: согласия, сроки хранения, регистрация и удаление.
 *
 * - Согласие на обработку оформляется отдельным документом (consent.php) и
 *   фиксируется с датой, версией текста, IP и источником (сайт, приложение, бумага).
 * - Согласие на распространение (показ имени и фото на открытых страницах сайта)
 *   отдельное и необязательное (ст. 10.1): без него человек не попадает в команду
 *   и на доску почёта.
 * - За участника младше 18 лет согласие подтверждает законный представитель.
 * - Отзыв согласия = удаление учётной записи и связанных данных.
 */

/** Версия текстов согласий. Меняется, когда меняется текст документа. */
const CONSENT_VERSION = '2026-10-08';

/** Последняя версия структуры базы, которую ожидает код. */
const SCHEMA_VERSION = 1;

/**
 * Досоздаёт недостающие поля и таблицы один раз после выкладки.
 * Без отдельных скриптов миграции: версия хранится в settings.schema_version.
 */
function ensureSchema(): void
{
    if ((int)setting('schema_version', '0') >= SCHEMA_VERSION) {
        return;
    }
    // Только один запрос делает миграцию, остальные ждут не дольше 10 секунд
    if ((int)fetchValue("SELECT GET_LOCK('mg_schema', 10)") !== 1) {
        return;
    }
    try {
        if ((int)fetchValue("SELECT value FROM settings WHERE `key` = 'schema_version'") < 1) {
            db()->exec("ALTER TABLE users
                ADD COLUMN IF NOT EXISTS pd_consent_at DATETIME NULL,
                ADD COLUMN IF NOT EXISTS pd_consent_version VARCHAR(20) NULL,
                ADD COLUMN IF NOT EXISTS public_consent TINYINT(1) NULL,
                ADD COLUMN IF NOT EXISTS public_consent_at DATETIME NULL,
                ADD COLUMN IF NOT EXISTS guardian_name VARCHAR(190) NULL,
                ADD COLUMN IF NOT EXISTS guardian_phone VARCHAR(32) NULL,
                ADD COLUMN IF NOT EXISTS guardian_consent_at DATETIME NULL");
            db()->exec("CREATE TABLE IF NOT EXISTS consent_log (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT UNSIGNED NULL,
                kind VARCHAR(32) NOT NULL,
                action VARCHAR(16) NOT NULL,
                version VARCHAR(20) NULL,
                source VARCHAR(20) NOT NULL,
                ip VARCHAR(45) NULL,
                user_agent VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            setSetting('schema_version', '1');
        }
    } catch (Throwable $e) {
        error_log('ensureSchema: ' . $e->getMessage());
    } finally {
        fetchValue("SELECT RELEASE_LOCK('mg_schema')");
    }
}

/**
 * Раз в сутки удаляет данные, которые больше не нужны для целей обработки:
 * отклонённые заявки через 30 дней и записи журнала старше года.
 */
function privacyHousekeeping(): void
{
    if (setting('housekeeping_at') === date('Y-m-d')) {
        return;
    }
    setSetting('housekeeping_at', date('Y-m-d'));
    try {
        foreach (fetchAll("SELECT id FROM users WHERE status = 'rejected' AND approved_at IS NOT NULL
                           AND approved_at < DATE_SUB(NOW(), INTERVAL 30 DAY)") as $r) {
            deleteUserData((int)$r['id'], 'retention');
        }
        q('DELETE FROM audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)');
        q('DELETE FROM consent_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 5 YEAR)');
    } catch (Throwable $e) {
        error_log('privacyHousekeeping: ' . $e->getMessage());
    }
}

/** Запись в журнал согласий: выдано или отозвано, какой версии, откуда. */
function recordConsent(?int $userId, string $kind, string $action, string $source): void
{
    q('INSERT INTO consent_log (user_id, kind, action, version, source, ip, user_agent) VALUES (?,?,?,?,?,?,?)', [
        $userId, $kind, $action, CONSENT_VERSION, $source,
        $_SERVER['REMOTE_ADDR'] ?? null,
        mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null,
    ]);
}

/** Полный возраст по дате рождения (Y-m-d) или null. */
function ageFromBirthDate(?string $birthDate): ?int
{
    if (!$birthDate || !strtotime($birthDate)) {
        return null;
    }
    return (int)(new DateTime($birthDate))->diff(new DateTime())->y;
}

/** Реквизиты оператора персональных данных для документов. */
function operatorInfo(): array
{
    $name = trim(setting('legal_operator_name'));
    return [
        'name'    => $name !== '' ? $name : setting('org_name', 'Молодая Гвардия Щёлково'),
        'short'   => setting('org_name', 'Молодая Гвардия Щёлково'),
        'inn'     => trim(setting('legal_inn')),
        'ogrn'    => trim(setting('legal_ogrn')),
        'address' => trim(setting('legal_address')) ?: setting('org_address'),
        'email'   => trim(setting('legal_email')) ?: setting('org_email'),
        'phone'   => setting('org_leader_phone'),
        'site'    => url(''),
        'filled'  => $name !== '' && trim(setting('legal_inn')) !== '',
    ];
}

/**
 * Регистрация волонтёра с сайта или из приложения.
 * $in: last_name, first_name, middle_name, email, phone, birth_date, vk, telegram,
 *      school, password, password2, agree_pd, agree_public, guardian_name,
 *      guardian_phone, agree_guardian.
 * Возвращает ['errors' => [...]] или ['id' => int].
 */
function registerVolunteer(array $in, string $source): array
{
    $v = [];
    foreach (['last_name', 'first_name', 'middle_name', 'email', 'phone', 'birth_date', 'vk', 'telegram',
              'school', 'guardian_name', 'guardian_phone'] as $k) {
        $v[$k] = trim((string)($in[$k] ?? ''));
    }
    $v['email'] = mb_strtolower($v['email']);
    $password  = (string)($in['password'] ?? '');
    $password2 = (string)($in['password2'] ?? '');

    $errors = [];
    if ($v['last_name'] === '')  $errors['last_name'] = 'Укажите фамилию.';
    if ($v['first_name'] === '') $errors['first_name'] = 'Укажите имя.';

    if ($v['email'] === '') {
        $errors['email'] = 'Укажите электронную почту.';
    } elseif (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Электронная почта указана в неверном формате.';
    } elseif (fetchValue('SELECT id FROM users WHERE email = ?', [$v['email']])) {
        $errors['email'] = 'Этот адрес уже зарегистрирован. Попробуйте войти.';
    }

    if ($v['phone'] === '') {
        $errors['phone'] = 'Укажите номер телефона: по нему с вами свяжется администратор.';
    }

    $age = null;
    if ($v['birth_date'] === '') {
        $errors['birth_date'] = 'Укажите дату рождения.';
    } else {
        $ts = strtotime($v['birth_date']);
        $age = ageFromBirthDate($v['birth_date']);
        if (!$ts || $ts > time() || $age === null) {
            $errors['birth_date'] = 'Дата рождения указана неверно.';
        } elseif ($age < 14) {
            $errors['birth_date'] = 'Вступить в организацию можно с 14 лет.';
        } elseif ($age > 100) {
            $errors['birth_date'] = 'Проверьте дату рождения.';
        }
    }

    if (mb_strlen($password) < 8)  $errors['password'] = 'Пароль должен быть не короче 8 символов.';
    if ($password !== $password2)  $errors['password2'] = 'Пароли не совпадают.';

    if (empty($in['agree_pd'])) {
        $errors['agree_pd'] = 'Нужно согласие на обработку персональных данных.';
    }
    $minor = $age !== null && $age >= 14 && $age < 18;
    if ($minor) {
        if (mb_strlen($v['guardian_name']) < 5) {
            $errors['guardian_name'] = 'Укажите ФИО родителя или законного представителя.';
        }
        if ($v['guardian_phone'] === '') {
            $errors['guardian_phone'] = 'Укажите телефон родителя или законного представителя.';
        }
        if (empty($in['agree_guardian'])) {
            $errors['agree_guardian'] = 'Нужно подтверждение согласия родителя или законного представителя.';
        }
    }

    if ($errors) {
        return ['errors' => $errors];
    }

    $public = !empty($in['agree_public']);
    q('INSERT INTO users (email, password_hash, last_name, first_name, middle_name, phone, birth_date,
                          vk, telegram, school, role, status, pd_consent_at, pd_consent_version,
                          public_consent, public_consent_at, guardian_name, guardian_phone, guardian_consent_at)
       VALUES (?,?,?,?,?,?,?,?,?,?,\'volunteer\',\'pending\',NOW(),?,?,?,?,?,?)', [
        $v['email'], password_hash($password, PASSWORD_DEFAULT),
        $v['last_name'], $v['first_name'], $v['middle_name'] ?: null,
        $v['phone'], $v['birth_date'],
        $v['vk'] ?: null, $v['telegram'] ?: null, $v['school'] ?: null,
        CONSENT_VERSION, $public ? 1 : 0, $public ? date('Y-m-d H:i:s') : null,
        $minor ? $v['guardian_name'] : null, $minor ? $v['guardian_phone'] : null,
        $minor ? date('Y-m-d H:i:s') : null,
    ]);
    $id = (int)db()->lastInsertId();
    recordConsent($id, 'pd', 'grant', $source);
    if ($public) {
        recordConsent($id, 'public', 'grant', $source);
    }
    if ($minor) {
        recordConsent($id, 'guardian', 'grant', $source);
    }
    q('INSERT INTO audit_log (user_id, action, entity, entity_id, meta, ip) VALUES (?,?,?,?,?,?)',
      [$id, 'register', 'user', $id, null, $_SERVER['REMOTE_ADDR'] ?? null]);
    return ['id' => $id];
}

/** Включает или выключает показ человека на открытых страницах сайта. */
function setPublicConsent(int $userId, bool $on, string $source): void
{
    q('UPDATE users SET public_consent = ?, public_consent_at = ? WHERE id = ?',
      [$on ? 1 : 0, $on ? date('Y-m-d H:i:s') : null, $userId]);
    recordConsent($userId, 'public', $on ? 'grant' : 'revoke', $source);
    if (!$on && (int)setting('honor_user_id') === $userId) {
        setSetting('honor_user_id', '0');
    }
}

/**
 * Удаляет учётную запись и связанные данные: записи на мероприятия, баллы,
 * достижения, токены приложения (каскадом), фото профиля; обезличивает журнал.
 * Сам факт отзыва согласия остаётся в журнале согласий без персональных данных.
 */
function deleteUserData(int $userId, string $reason): void
{
    $u = fetchOne('SELECT id, avatar FROM users WHERE id = ?', [$userId]);
    if (!$u) {
        return;
    }
    if (!empty($u['avatar']) && function_exists('deleteAvatarFile')) {
        deleteAvatarFile($u['avatar']);
    }
    q("UPDATE audit_log SET meta = NULL, ip = NULL WHERE user_id = ? OR (entity = 'user' AND entity_id = ?)", [$userId, $userId]);
    q('UPDATE consent_log SET ip = NULL, user_agent = NULL WHERE user_id = ?', [$userId]);
    if ((int)setting('honor_user_id') === $userId) {
        setSetting('honor_user_id', '0');
    }
    q('DELETE FROM users WHERE id = ?', [$userId]);
    // Факт отзыва храним без IP и браузера: только номер бывшей учётной записи и дату
    q('INSERT INTO consent_log (user_id, kind, action, version, source) VALUES (?,?,?,?,?)',
      [$userId, 'pd', 'revoke', CONSENT_VERSION, $reason]);
}
