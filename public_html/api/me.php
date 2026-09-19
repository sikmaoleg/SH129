<?php
declare(strict_types=1);
require_once __DIR__ . '/_api.php';

$me = apiRequireUser();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    apiSuccess(['user' => apiUserPublic($me)]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('Метод не поддерживается.', 405);
}

$in = apiInput();
$action = $in['action'] ?? '';

if ($action === 'update') {
    $fields = [
        'last_name'   => trim((string)($in['lastName'] ?? '')),
        'first_name'  => trim((string)($in['firstName'] ?? '')),
        'middle_name' => trim((string)($in['middleName'] ?? '')),
        'phone'       => trim((string)($in['phone'] ?? '')),
        'vk'          => trim((string)($in['vk'] ?? '')),
        'telegram'    => trim((string)($in['telegram'] ?? '')),
        'school'      => trim((string)($in['school'] ?? '')),
        'about'       => trim((string)($in['about'] ?? '')),
    ];
    if ($fields['last_name'] === '' || $fields['first_name'] === '') {
        apiError('Фамилия и имя обязательны.', 422);
    }
    q('UPDATE users SET last_name=?, first_name=?, middle_name=?, phone=?, vk=?, telegram=?, school=?, about=? WHERE id=?',
      [...array_values($fields), (int)$me['id']]);
    logAction('profile_update', 'user', (int)$me['id']);
    $updated = fetchOne('SELECT * FROM users WHERE id = ?', [(int)$me['id']]);
    apiSuccess(['user' => apiUserPublic($updated)]);
}

if ($action === 'avatar_upload') {
    $result = handleAvatarUpload($_FILES['avatar'] ?? []);
    if (isset($result['error'])) {
        apiError($result['error'], 422);
    }
    $old = $me['avatar'];
    q('UPDATE users SET avatar = ? WHERE id = ?', [$result['filename'], (int)$me['id']]);
    deleteAvatarFile($old);
    logAction('avatar_update', 'user', (int)$me['id']);
    $updated = fetchOne('SELECT * FROM users WHERE id = ?', [(int)$me['id']]);
    apiSuccess(['user' => apiUserPublic($updated)]);
}

if ($action === 'avatar_remove') {
    if ($me['avatar']) {
        deleteAvatarFile($me['avatar']);
        q('UPDATE users SET avatar = NULL WHERE id = ?', [(int)$me['id']]);
        logAction('avatar_remove', 'user', (int)$me['id']);
    }
    $updated = fetchOne('SELECT * FROM users WHERE id = ?', [(int)$me['id']]);
    apiSuccess(['user' => apiUserPublic($updated)]);
}

if ($action === 'password') {
    $cur  = (string)($in['currentPassword'] ?? '');
    $new  = (string)($in['newPassword'] ?? '');
    $new2 = (string)($in['newPassword2'] ?? '');
    if (!password_verify($cur, $me['password_hash'])) {
        apiError('Текущий пароль указан неверно.', 422);
    }
    if (mb_strlen($new) < 8) {
        apiError('Новый пароль должен быть не короче 8 символов.', 422);
    }
    if ($new !== $new2) {
        apiError('Новые пароли не совпадают.', 422);
    }
    q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), (int)$me['id']]);
    logAction('password_change', 'user', (int)$me['id']);
    apiSuccess(['message' => 'Пароль изменён.']);
}

apiError('Неизвестное действие.', 404);
