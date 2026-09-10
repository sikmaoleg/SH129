<?php
/**
 * Ядро приложения: конфиг, база, сессия, вспомогательные функции.
 * Подключается первой строкой в каждом скрипте.
 */

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

// ---------------------------------------------------------------------
// Конфигурация
// ---------------------------------------------------------------------
$configFile = APP_ROOT . '/config/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Файл config/config.php не найден. Скопируйте config/config.sample.php и заполните его, либо откройте /install/');
}
$config = require $configFile;

date_default_timezone_set($config['timezone'] ?? 'Europe/Moscow');

if (!empty($config['debug'])) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}

// ---------------------------------------------------------------------
// Подключение к базе
// ---------------------------------------------------------------------
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    global $config;
    $d = $config['db'];
    $dsn = "mysql:host={$d['host']};dbname={$d['name']};charset={$d['charset']}";
    try {
        $pdo = new PDO($dsn, $d['user'], $d['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        if (!empty($GLOBALS['config']['debug'])) {
            exit('Ошибка подключения к базе: ' . $e->getMessage());
        }
        exit('Сайт временно недоступен. Не удалось подключиться к базе данных.');
    }
    return $pdo;
}

/** Короткие обёртки над PDO */
function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}
function fetchOne(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}
function fetchAll(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}
function fetchValue(string $sql, array $params = [])
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

// ---------------------------------------------------------------------
// Настройки из таблицы settings
// ---------------------------------------------------------------------
function settings(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (fetchAll('SELECT `key`,`value` FROM settings') as $r) {
            $cache[$r['key']] = $r['value'];
        }
    }
    return $cache;
}
function setting(string $key, string $default = ''): string
{
    $s = settings();
    return $s[$key] ?? $default;
}
function setSetting(string $key, string $value): void
{
    q('INSERT INTO settings (`key`,`value`) VALUES (?,?)
       ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)', [$key, $value]);
}

// ---------------------------------------------------------------------
// Сессия
// ---------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    // Храним файлы сессии в своей папке, а не в общей системной (session.save_path
    // из php.ini хостинга). На shared-хостинге эта общая папка иногда недоступна на
    // запись для конкретного аккаунта или чистится раньше времени — тогда сессия
    // молча не сохраняется и пользователя после входа тут же выбрасывает обратно на
    // login.php. Собственная папка снимает эту зависимость.
    $sessionDir = APP_ROOT . '/storage/sessions';
    if (!is_dir($sessionDir)) {
        @mkdir($sessionDir, 0755, true);
    }
    if (is_dir($sessionDir) && is_writable($sessionDir)) {
        session_save_path($sessionDir);
    }

    // secure-флаг куки сессии берём из протокола base_url, а не из $_SERVER['HTTPS']:
    // на этом хостинге (обратный прокси перед PHP) HTTPS иногда непусто даже для
    // обычных http-запросов. Кука с secure=true на http-сайте браузер никогда не
    // отправит обратно — сессия молча "не сохраняется" на каждом запросе.
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => str_starts_with($config['base_url'] ?? '', 'https://'),
    ]);
    session_start();
}

// ---------------------------------------------------------------------
// Вывод и ссылки
// ---------------------------------------------------------------------
function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function url(string $path = ''): string
{
    global $config;
    return rtrim($config['base_url'] ?? '', '/') . '/' . ltrim($path, '/');
}
function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)));
    exit;
}

// ---------------------------------------------------------------------
// CSRF-защита
// ---------------------------------------------------------------------
function csrfToken(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function csrfField(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrfToken()) . '">';
}
function csrfCheck(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    $sent = $_POST['_csrf'] ?? '';
    if (!is_string($sent) || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(419);
        exit('Сессия устарела. Обновите страницу и повторите отправку формы.');
    }
}

// ---------------------------------------------------------------------
// Флеш-сообщения
// ---------------------------------------------------------------------
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}
function takeFlash(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

// ---------------------------------------------------------------------
// Форматирование
// ---------------------------------------------------------------------
const RU_MONTHS = [1=>'января',2=>'февраля',3=>'марта',4=>'апреля',5=>'мая',6=>'июня',
                   7=>'июля',8=>'августа',9=>'сентября',10=>'октября',11=>'ноября',12=>'декабря'];
const RU_MONTHS_SHORT = [1=>'ЯНВ',2=>'ФЕВ',3=>'МАР',4=>'АПР',5=>'МАЯ',6=>'ИЮН',
                         7=>'ИЮЛ',8=>'АВГ',9=>'СЕН',10=>'ОКТ',11=>'НОЯ',12=>'ДЕК'];

function ruDate(?string $datetime, bool $withTime = false): string
{
    if (!$datetime) {
        return '—';
    }
    $ts = strtotime($datetime);
    $out = date('j', $ts) . ' ' . RU_MONTHS[(int)date('n', $ts)] . ' ' . date('Y', $ts);
    return $withTime ? $out . ', ' . date('H:i', $ts) : $out;
}
function plural(int $n, string $one, string $few, string $many): string
{
    $n = abs($n) % 100;
    $n1 = $n % 10;
    if ($n > 10 && $n < 20) return $many;
    if ($n1 > 1 && $n1 < 5)  return $few;
    if ($n1 === 1)           return $one;
    return $many;
}

// ---------------------------------------------------------------------
// Роли (организационные позиции) волонтёров
// ---------------------------------------------------------------------
const POSITION_LABELS = [
    'volunteer'   => 'Волонтёр',
    'activist'    => 'Активист',
    'staff'       => 'Член Аппарата',
    'local_staff' => 'Член местного штаба',
    'leader'      => 'Руководитель',
];
function positionLabel(?string $position): string
{
    return POSITION_LABELS[$position] ?? POSITION_LABELS['volunteer'];
}

// ---------------------------------------------------------------------
// Уровни волонтёра
// ---------------------------------------------------------------------
function levels(): array
{
    $thresholds = array_map('intval', explode(',', setting('level_thresholds', '0,100,300,700,1500')));
    $names      = explode(',', setting('level_names', 'Новичок,Активист,Опытный,Наставник,Легенда'));
    $out = [];
    foreach ($thresholds as $i => $min) {
        $out[] = [
            'index' => $i + 1,
            'name'  => trim($names[$i] ?? ('Уровень ' . ($i + 1))),
            'min'   => $min,
            'max'   => isset($thresholds[$i + 1]) ? $thresholds[$i + 1] - 1 : null,
        ];
    }
    return $out;
}
function levelFor(int $points): array
{
    $levels  = levels();
    $current = $levels[0];
    foreach ($levels as $l) {
        if ($points >= $l['min']) {
            $current = $l;
        }
    }
    $next = null;
    foreach ($levels as $l) {
        if ($l['min'] > $points) { $next = $l; break; }
    }
    $progress = 100;
    if ($next) {
        $span     = max(1, $next['min'] - $current['min']);
        $progress = (int)round((($points - $current['min']) / $span) * 100);
    }
    return [
        'current'  => $current,
        'next'     => $next,
        'progress' => max(0, min(100, $progress)),
        'to_next'  => $next ? max(0, $next['min'] - $points) : 0,
    ];
}

// ---------------------------------------------------------------------
// Журнал действий
// ---------------------------------------------------------------------
function logAction(string $action, ?string $entity = null, ?int $entityId = null, ?string $meta = null): void
{
    q('INSERT INTO audit_log (user_id, action, entity, entity_id, meta, ip)
       VALUES (?,?,?,?,?,?)', [
        $_SESSION['user_id'] ?? null,
        $action,
        $entity,
        $entityId,
        $meta !== null ? mb_substr($meta, 0, 500) : null,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

require_once __DIR__ . '/auth.php';
