<?php
declare(strict_types=1);
require_once __DIR__ . '/_api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('Метод не поддерживается.', 405);
}

$action = $_GET['action'] ?? '';
$in = apiInput();

// -----------------------------------------------------------------------
// Регистрация -- те же проверки, что и в register.php
// -----------------------------------------------------------------------
if ($action === 'register') {
    if (setting('registration_open', '1') !== '1') {
        apiError('Приём заявок временно закрыт.', 403);
    }

    $old = [
        'last_name'   => trim((string)($in['lastName'] ?? '')),
        'first_name'  => trim((string)($in['firstName'] ?? '')),
        'middle_name' => trim((string)($in['middleName'] ?? '')),
        'email'       => mb_strtolower(trim((string)($in['email'] ?? ''))),
        'phone'       => trim((string)($in['phone'] ?? '')),
        'birth_date'  => trim((string)($in['birthDate'] ?? '')),
        'vk'          => trim((string)($in['vk'] ?? '')),
        'telegram'    => trim((string)($in['telegram'] ?? '')),
        'school'      => trim((string)($in['school'] ?? '')),
    ];
    $password  = (string)($in['password'] ?? '');
    $password2 = (string)($in['password2'] ?? '');
    $agree     = !empty($in['agree']);

    $errors = [];
    if ($old['last_name'] === '')  $errors[] = 'Укажите фамилию.';
    if ($old['first_name'] === '') $errors[] = 'Укажите имя.';

    if ($old['email'] === '') {
        $errors[] = 'Укажите электронную почту.';
    } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Электронная почта указана в неверном формате.';
    } elseif (fetchValue('SELECT id FROM users WHERE email = ?', [$old['email']])) {
        $errors[] = 'Этот адрес уже зарегистрирован. Попробуйте войти.';
    }

    if ($old['phone'] === '') {
        $errors[] = 'Укажите номер телефона — по нему с вами свяжется администратор.';
    }

    if ($old['birth_date'] === '') {
        $errors[] = 'Укажите дату рождения.';
    } else {
        $ts = strtotime($old['birth_date']);
        if (!$ts || $ts > time()) {
            $errors[] = 'Дата рождения указана неверно.';
        } else {
            $age = (int)((new DateTime($old['birth_date']))->diff(new DateTime())->y);
            if ($age < 14)  $errors[] = 'Вступить в организацию можно с 14 лет.';
            if ($age > 100) $errors[] = 'Проверьте дату рождения.';
        }
    }

    if (mb_strlen($password) < 8)   $errors[] = 'Пароль должен быть не короче 8 символов.';
    if ($password !== $password2)   $errors[] = 'Пароли не совпадают.';
    if (!$agree)                    $errors[] = 'Нужно согласие на обработку персональных данных.';

    if ($errors) {
        apiError(implode(' ', $errors), 422);
    }

    q('INSERT INTO users (email, password_hash, last_name, first_name, middle_name,
                          phone, birth_date, vk, telegram, school, role, status)
       VALUES (?,?,?,?,?,?,?,?,?,?,\'volunteer\',\'pending\')', [
        $old['email'],
        password_hash($password, PASSWORD_DEFAULT),
        $old['last_name'],
        $old['first_name'],
        $old['middle_name'] ?: null,
        $old['phone'],
        $old['birth_date'],
        $old['vk'] ?: null,
        $old['telegram'] ?: null,
        $old['school'] ?: null,
    ]);
    $newId = (int)db()->lastInsertId();
    q('INSERT INTO audit_log (user_id, action, entity, entity_id, meta, ip) VALUES (?,?,?,?,?,?)',
      [$newId, 'register', 'user', $newId, $old['email'], $_SERVER['REMOTE_ADDR'] ?? null]);

    apiSuccess(['message' => 'Заявка отправлена. Вход откроется после одобрения администратором.']);
}

// -----------------------------------------------------------------------
// Вход -- та же логика, что и attemptLogin(), но без работы с сессией:
// вместо неё выдаём Bearer-токен для api_tokens.
// -----------------------------------------------------------------------
if ($action === 'login') {
    $email    = mb_strtolower(trim((string)($in['email'] ?? '')));
    $password = (string)($in['password'] ?? '');
    if ($email === '' || $password === '') {
        apiError('Укажите почту и пароль.', 422);
    }

    $user = fetchOne('SELECT * FROM users WHERE email = ?', [$email]);
    if (!$user || !password_verify($password, $user['password_hash'])) {
        apiError('Неверная почта или пароль.', 401);
    }

    switch ($user['status']) {
        case 'pending':
            apiError('Заявка ещё на рассмотрении у администратора.', 403);
        case 'rejected':
            $reason = $user['reject_reason'] ? ' Причина: ' . $user['reject_reason'] : '';
            apiError('Заявка отклонена.' . $reason, 403);
        case 'blocked':
            apiError('Доступ к учётной записи закрыт. Свяжитесь с администратором.', 403);
    }

    $token = bin2hex(random_bytes(32));
    q('INSERT INTO api_tokens (user_id, token_hash, device) VALUES (?,?,?)',
      [(int)$user['id'], hash('sha256', $token), trim((string)($in['device'] ?? '')) ?: null]);
    q('UPDATE users SET last_login_at = NOW() WHERE id = ?', [(int)$user['id']]);
    q('INSERT INTO audit_log (user_id, action, entity, entity_id, meta, ip) VALUES (?,?,?,?,?,?)',
      [(int)$user['id'], 'login', 'user', (int)$user['id'], 'app', $_SERVER['REMOTE_ADDR'] ?? null]);

    apiSuccess(['token' => $token, 'user' => apiUserPublic($user)]);
}

// -----------------------------------------------------------------------
// Выход -- удаляем только текущий токен, остальные устройства не трогаем.
// -----------------------------------------------------------------------
if ($action === 'logout') {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/^Bearer\s+([A-Za-z0-9]+)$/', trim($header), $m)) {
        q('DELETE FROM api_tokens WHERE token_hash = ?', [hash('sha256', $m[1])]);
    }
    apiSuccess();
}

apiError('Неизвестное действие.', 404);
