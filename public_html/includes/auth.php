<?php
/**
 * Авторизация, роли и права доступа.
 *
 * Роли:
 *   volunteer — волонтёр: личный кабинет, запись на события, рейтинг
 *   admin     — администратор: одобрение заявок, события, начисление баллов, новости
 *   dev       — разработчик: всё выше + системная панель
 *
 * Статусы учётной записи:
 *   pending   — зарегистрирован, ждёт одобрения администратором (войти нельзя)
 *   approved  — одобрен, полный доступ
 *   rejected  — заявка отклонена
 *   blocked   — доступ заблокирован
 */

declare(strict_types=1);

/** Текущий пользователь или null */
function currentUser(): ?array
{
    static $user = null;
    static $loaded = false;
    if ($loaded) {
        return $user;
    }
    $loaded = true;
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $user = fetchOne('SELECT * FROM users WHERE id = ?', [(int)$_SESSION['user_id']]);
    // Учётку могли заблокировать уже после входа
    if ($user && $user['status'] !== 'approved') {
        logout();
        $user = null;
    }
    return $user;
}

function isLoggedIn(): bool
{
    return currentUser() !== null;
}

function userRole(): string
{
    return currentUser()['role'] ?? 'guest';
}

/** dev имеет все права admin */
function isAdmin(): bool
{
    return in_array(userRole(), ['admin', 'dev'], true);
}
function isDev(): bool
{
    return userRole() === 'dev';
}

/** Попытка входа. Возвращает ['ok'=>bool,'error'=>string,'user'=>array] */
function attemptLogin(string $email, string $password): array
{
    $email = mb_strtolower(trim($email));
    $user  = fetchOne('SELECT * FROM users WHERE email = ?', [$email]);

    // Одинаковая формулировка для несуществующего адреса и неверного пароля,
    // чтобы нельзя было подобрать список зарегистрированных адресов.
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['ok' => false, 'error' => 'Неверная почта или пароль.'];
    }

    switch ($user['status']) {
        case 'pending':
            return ['ok' => false, 'error' => 'Заявка ещё на рассмотрении у администратора. Мы напишем, как только её одобрят.'];
        case 'rejected':
            $reason = $user['reject_reason'] ? ' Причина: ' . $user['reject_reason'] : '';
            return ['ok' => false, 'error' => 'Заявка отклонена.' . $reason];
        case 'blocked':
            return ['ok' => false, 'error' => 'Доступ к учётной записи закрыт. Свяжитесь с координатором.'];
    }

    // Новый идентификатор сессии — защита от фиксации сессии
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    q('UPDATE users SET last_login_at = NOW() WHERE id = ?', [(int)$user['id']]);
    logAction('login', 'user', (int)$user['id']);

    return ['ok' => true, 'user' => $user];
}

function logout(): void
{
    if (!empty($_SESSION['user_id'])) {
        logAction('logout', 'user', (int)$_SESSION['user_id']);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Куда отправить пользователя после входа */
function homeForRole(string $role): string
{
    return match ($role) {
        'dev'   => 'dev/',
        'admin' => 'admin/',
        default => 'cabinet/',
    };
}

// ---------------------------------------------------------------------
// Ограничители доступа
// ---------------------------------------------------------------------
function requireLogin(): array
{
    $user = currentUser();
    if (!$user) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        flash('error', 'Войдите, чтобы открыть эту страницу.');
        redirect('login.php');
    }
    return $user;
}

function requireAdmin(): array
{
    $user = requireLogin();
    if (!isAdmin()) {
        http_response_code(403);
        exit('Доступ только для администраторов.');
    }
    return $user;
}

function requireDev(): array
{
    $user = requireLogin();
    if (!isDev()) {
        http_response_code(403);
        exit('Доступ только для разработчиков.');
    }
    return $user;
}

// ---------------------------------------------------------------------
// Начисление баллов
// ---------------------------------------------------------------------
/**
 * Начисляет баллы волонтёру и пишет запись в историю.
 * Обе операции в транзакции, чтобы баланс и история не разъехались.
 */
function awardPoints(int $userId, int $points, string $reason, ?int $eventId = null, float $hours = 0): void
{
    $pdo = db();
    $own = !$pdo->inTransaction();
    if ($own) {
        $pdo->beginTransaction();
    }
    try {
        q('UPDATE users SET points = GREATEST(0, points + ?), hours = GREATEST(0, hours + ?) WHERE id = ?',
          [$points, $hours, $userId]);
        q('INSERT INTO point_transactions (user_id, points, reason, event_id, created_by)
           VALUES (?,?,?,?,?)',
          [$userId, $points, $reason, $eventId, $_SESSION['user_id'] ?? null]);
        if ($own) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/** Выдать бейдж по коду (повторная выдача игнорируется) */
function awardBadge(int $userId, string $code): void
{
    $badgeId = fetchValue('SELECT id FROM badges WHERE code = ?', [$code]);
    if ($badgeId) {
        q('INSERT IGNORE INTO user_badges (user_id, badge_id, awarded_by) VALUES (?,?,?)',
          [$userId, (int)$badgeId, $_SESSION['user_id'] ?? null]);
    }
}
