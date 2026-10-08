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

    // Старые версии приложения присылают agree, новые agreePd
    $result = registerVolunteer([
        'last_name'      => $in['lastName'] ?? '',
        'first_name'     => $in['firstName'] ?? '',
        'middle_name'    => $in['middleName'] ?? '',
        'email'          => $in['email'] ?? '',
        'phone'          => $in['phone'] ?? '',
        'birth_date'     => $in['birthDate'] ?? '',
        'vk'             => $in['vk'] ?? '',
        'telegram'       => $in['telegram'] ?? '',
        'school'         => $in['school'] ?? '',
        'password'       => $in['password'] ?? '',
        'password2'      => $in['password2'] ?? '',
        'agree_pd'       => !empty($in['agreePd']) || !empty($in['agree']),
        'agree_public'   => !empty($in['agreePublic']),
        'guardian_name'  => $in['guardianName'] ?? '',
        'guardian_phone' => $in['guardianPhone'] ?? '',
        'agree_guardian' => !empty($in['agreeGuardian']),
    ], 'app');
    if (isset($result['errors'])) {
        apiError(implode(' ', array_unique(array_values($result['errors']))), 422);
    }

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

    if (loginThrottled()) {
        apiError('Слишком много неудачных попыток входа. Подождите 15 минут.', 429);
    }
    $user = fetchOne('SELECT * FROM users WHERE email = ?', [$email]);
    if (!$user || !password_verify($password, $user['password_hash'])) {
        logAction('login_failed', 'user', $user ? (int)$user['id'] : null);
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
