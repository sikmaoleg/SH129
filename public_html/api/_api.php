<?php
declare(strict_types=1);

/**
 * Общий бутстрап для JSON API мобильного приложения.
 * Авторизация — через Bearer-токен (таблица api_tokens), а не через
 * сессионную куку: подключаем bootstrap.php ради db()/fetchOne()/helpers,
 * но пользователя для API-запроса определяем отдельно от currentUser(),
 * которая читает $_SESSION.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

/** Тело запроса: JSON, если пришёл Content-Type: application/json, иначе обычный $_POST (для multipart-загрузок). */
function apiInput(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $data = $raw !== false ? json_decode($raw, true) : null;
        return is_array($data) ? $data : [];
    }
    return $_POST;
}

function apiSuccess(array $data = [], int $code = 200): never
{
    http_response_code($code);
    echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE);
    exit;
}

function apiError(string $message, int $code = 400): never
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

/** Пользователь текущего запроса по Bearer-токену, или null. */
function apiUser(): ?array
{
    static $user = null;
    static $loaded = false;
    if ($loaded) {
        return $user;
    }
    $loaded = true;

    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (!preg_match('/^Bearer\s+([A-Za-z0-9]+)$/', trim($header), $m)) {
        return null;
    }
    $hash = hash('sha256', $m[1]);
    $row = fetchOne(
        "SELECT u.* FROM api_tokens t JOIN users u ON u.id = t.user_id WHERE t.token_hash = ?",
        [$hash]
    );
    if (!$row || $row['status'] !== 'approved') {
        return null;
    }
    q('UPDATE api_tokens SET last_used_at = NOW() WHERE token_hash = ?', [$hash]);
    $user = $row;
    // logAction()/awardPoints() атрибутируют действие через $_SESSION['user_id'] --
    // сессии тут нет, но так они правильно пишут автора и для API-запросов.
    $_SESSION['user_id'] = (int)$row['id'];
    return $user;
}

function apiRequireUser(): array
{
    $u = apiUser();
    if (!$u) {
        apiError('Требуется авторизация.', 401);
    }
    return $u;
}

function apiRequireAdmin(): array
{
    $u = apiRequireUser();
    if (!in_array($u['role'], ['admin', 'dev'], true)) {
        apiError('Доступ только для администраторов.', 403);
    }
    return $u;
}

function apiRequireDev(): array
{
    $u = apiRequireUser();
    if ($u['role'] !== 'dev') {
        apiError('Доступ только для разработчиков.', 403);
    }
    return $u;
}

/** Публичное представление пользователя (без password_hash и служебных полей). */
function apiUserPublic(array $u): array
{
    return [
        'id'          => (int)$u['id'],
        'email'       => $u['email'],
        'lastName'    => $u['last_name'],
        'firstName'   => $u['first_name'],
        'middleName'  => $u['middle_name'],
        'phone'       => $u['phone'],
        'birthDate'   => $u['birth_date'],
        'vk'          => $u['vk'],
        'telegram'    => $u['telegram'],
        'school'      => $u['school'],
        'about'       => $u['about'],
        'avatar'      => $u['avatar'] ? url('uploads/avatars/' . $u['avatar']) : null,
        'role'        => $u['role'],
        'position'    => $u['position'],
        'positionLabel' => positionLabel($u['position'] ?? null),
        'points'      => (int)$u['points'],
        'hours'       => (float)$u['hours'],
        'memberSince' => membershipDate($u),
    ];
}
