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
            PDO::ATTR_TIMEOUT            => 5,
        ]);
        // Некоторые хостинги игнорируют charset из DSN при handshake
        // (skip_character_set_client_handshake) и молча открывают
        // соединение в utf8mb3 — принудительно переключаем явной командой.
        $pdo->exec("SET NAMES '{$d['charset']}'");
    } catch (PDOException $e) {
        // База недоступна (например, сервер хостинга перегружен): отвечаем быстро
        // и честно, чтобы браузеры и поисковики повторили запрос позже.
        http_response_code(503);
        header('Retry-After: 60');
        header('Content-Type: text/html; charset=utf-8');
        $detail = !empty($GLOBALS['config']['debug']) ? '<p style="font-size:13px;color:#5F6888">' . htmlspecialchars($e->getMessage()) . '</p>' : '';
        exit('<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Сайт временно недоступен</title></head>'
            . '<body style="margin:0;min-height:100vh;display:grid;place-items:center;background:#F1F3F9;color:#0B1233;font:16px/1.5 system-ui,sans-serif;padding:24px">'
            . '<div style="max-width:460px"><h1 style="font-size:28px;margin:0 0 12px">Сайт на минутку прилёг</h1>'
            . '<p style="margin:0 0 16px;color:#454F70">Сервер базы данных сейчас перегружен. Обновите страницу через минуту.</p>'
            . '<p style="margin:0"><a href="" style="color:#2A45F0;font-weight:600">Обновить</a></p>' . $detail . '</div></body></html>');
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
/** Ссылка на статику (css/js) с версией по времени изменения файла -- чтобы
 *  правки не залипали в недельном браузерном кэше (см. .htaccess) до жёсткого
 *  обновления страницы пользователем. */
function assetUrl(string $path): string
{
    $file = APP_ROOT . '/' . ltrim($path, '/');
    $version = is_file($file) ? filemtime($file) : time();
    return url($path) . '?v=' . $version;
}
/** Блок кнопок «Поделиться»: ВК, Telegram, копирование ссылки */
/** Короткая ссылка на мероприятие для рассылок: sh-129-mg.ru/e/12 */
function eventLink(int $id): string
{
    $link = url('e/' . $id);
    if (str_starts_with($link, '/')) {
        // base_url не задан: дописываем домен, чтобы ссылку можно было отправить в чат
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $host  = preg_replace('/[^A-Za-z0-9.:\-]/', '', (string)($_SERVER['HTTP_HOST'] ?? ''));
        if ($host !== '') {
            $link = ($https ? 'https://' : 'http://') . $host . $link;
        }
    }
    return $link;
}

/** Безопасный адрес возврата после входа: только путь на нашем сайте. */
function safeReturnPath(string $path): string
{
    $path = trim($path);
    $bad = !str_starts_with($path, '/') || str_starts_with($path, '//')
        || strpbrk($path, "\\\r\n") !== false;
    return $bad ? '' : $path;
}

function shareButtons(string $pageUrl, string $title): string
{
    $u = urlencode($pageUrl);
    $t = urlencode($title);
    $vk = "https://vk.com/share.php?url={$u}&title={$t}";
    $tg = "https://t.me/share/url?url={$u}&text={$t}";
    return '<div class="share-row">'
        . '<span class="share-label">Поделиться:</span>'
        . '<a href="' . e($vk) . '" target="_blank" rel="noopener" class="share-btn" aria-label="ВКонтакте">' . icon('vk') . '</a>'
        . '<a href="' . e($tg) . '" target="_blank" rel="noopener" class="share-btn" aria-label="Telegram">' . icon('telegram') . '</a>'
        . '<button type="button" class="share-btn" data-copy-link="' . e($pageUrl) . '" aria-label="Скопировать ссылку">' . icon('link') . '</button>'
        . '</div>';
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
// Загрузка фото профиля
// ---------------------------------------------------------------------
/**
 * Валидирует загруженный файл (реальный формат — через getimagesize, а не
 * MIME от клиента), обрезает по центру до квадрата, уменьшает до 480px и
 * сохраняет в uploads/avatars/. Возвращает ['filename' => string] или
 * ['error' => string] — саму запись в users и удаление старого файла
 * делает вызывающий код, он знает старое значение и id пользователя.
 */
function handleAvatarUpload(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['error' => 'Выберите файл с фотографией.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => match ($file['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Файл слишком большой.',
            default => 'Не удалось загрузить файл. Попробуйте ещё раз.',
        }];
    }
    if ($file['size'] > 50 * 1024 * 1024) {
        return ['error' => 'Файл слишком большой — до 50 МБ.'];
    }

    $info = @getimagesize($file['tmp_name']);
    $src = ($info && isset(IMAGE_LOADERS[$info[2]])) ? loadImageAsGd($file['tmp_name'], $info[2]) : false;
    if (!$src) {
        if (looksLikeHeic($file['tmp_name'])) {
            return ['error' => 'Это фото в формате HEIC/HEIF — на айфоне такое получается при настройке камеры «Высокая эффективность». Откройте фото в приложении «Фото», нажмите «Поделиться» → «Сохранить как JPEG» (или пришлите файл через мессенджер — он обычно сам конвертирует в JPEG) и загрузите заново.'];
        }
        return ['error' => 'Не удалось распознать файл как изображение. Поддерживаются JPG, PNG и WEBP — попробуйте пересохранить фото в одном из этих форматов.'];
    }
    // Защита от нехватки памяти на очень крупных снимках (50 МБ файл может оказаться
    // фото в десятки мегапикселей — imagecreatefrom* держит его целиком в памяти).
    if ($info[0] * $info[1] > 50_000_000) {
        return ['error' => 'Слишком высокое разрешение фото. Уменьшите изображение и попробуйте снова.'];
    }

    $w = imagesx($src);
    $h = imagesy($src);
    $side   = min($w, $h);
    $target = min(480, $side);
    $dst = imagecreatetruecolor($target, $target);
    imagecopyresampled($dst, $src, 0, 0, (int)(($w - $side) / 2), (int)(($h - $side) / 2), $target, $target, $side, $side);

    $useWebp  = function_exists('imagewebp');
    $filename = bin2hex(random_bytes(16)) . ($useWebp ? '.webp' : '.jpg');
    $dir      = __DIR__ . '/../uploads/avatars/';
    $saved    = $useWebp ? imagewebp($dst, $dir . $filename, 82) : imagejpeg($dst, $dir . $filename, 85);

    return $saved ? ['filename' => $filename] : ['error' => 'Не удалось сохранить фото. Попробуйте ещё раз.'];
}

/** Удаляет файл аватара с диска, если он существует. */
function deleteAvatarFile(?string $filename): void
{
    if (!$filename) {
        return;
    }
    $path = __DIR__ . '/../uploads/avatars/' . $filename;
    if (is_file($path)) {
        @unlink($path);
    }
}

/** Форматы, которые умеем обрабатывать, и соответствующие функции загрузки в GD. */
const IMAGE_LOADERS = [
    IMAGETYPE_JPEG => 'imagecreatefromjpeg',
    IMAGETYPE_PNG  => 'imagecreatefrompng',
    IMAGETYPE_WEBP => 'imagecreatefromwebp',
];

/**
 * Загружает изображение в GD-ресурс. Если родной загрузчик GD не справился
 * (типичный случай — JPEG в цветовой модели CMYK или с нестандартными
 * маркерами, которые libjpeg в составе GD не умеет декодировать, хотя файл
 * абсолютно валиден), пробуем ImageMagick, если он установлен на сервере —
 * он заметно терпимее к таким файлам. Дальше вся остальная обработка
 * (обрезка, resize, сохранение) — уже обычный GD-код, независимо от того,
 * кто фактически декодировал исходник.
 */
function loadImageAsGd(string $path, int $imageType)
{
    if (!isset(IMAGE_LOADERS[$imageType])) {
        return false;
    }
    $src = @(IMAGE_LOADERS[$imageType])($path);
    if ($src !== false) {
        return $src;
    }
    if (!extension_loaded('imagick')) {
        return false;
    }
    try {
        $im = new Imagick($path);
        $im->setImageFormat('png32'); // без потерь, с альфа-каналом — просто мост в GD
        $blob = $im->getImageBlob();
        $im->destroy();
        $gd = @imagecreatefromstring($blob);
        return $gd !== false ? $gd : false;
    } catch (Throwable $e) {
        return false;
    }
}

/** Грубая проверка по сигнатуре файла: HEIC/HEIF (типичный формат фото на iPhone). */
function looksLikeHeic(string $path): bool
{
    $head = @file_get_contents($path, false, null, 0, 12);
    if ($head === false || strlen($head) < 12 || substr($head, 4, 4) !== 'ftyp') {
        return false;
    }
    return in_array(substr($head, 8, 4), ['heic', 'heix', 'heim', 'heis', 'hevc', 'hevx', 'mif1', 'msf1'], true);
}

/**
 * Валидирует файл изображения по содержимому (не по MIME от клиента), при
 * необходимости уменьшает (без обрезки — пропорции не трогаем) и сохраняет
 * в uploads/<subdir>/. Возвращает имя файла или null, если это не изображение.
 */
function resizeAndSaveImage(string $srcPath, string $subdir, int $maxDim = 1800): ?string
{
    $info = @getimagesize($srcPath);
    $src = ($info && isset(IMAGE_LOADERS[$info[2]])) ? loadImageAsGd($srcPath, $info[2]) : false;
    if (!$src) {
        return null;
    }

    $w = imagesx($src);
    $h = imagesy($src);
    if (max($w, $h) > $maxDim) {
        $scale = $maxDim / max($w, $h);
        $nw = (int)round($w * $scale);
        $nh = (int)round($h * $scale);
        $dst = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $src = $dst;
    }

    $useWebp  = function_exists('imagewebp');
    $filename = bin2hex(random_bytes(16)) . ($useWebp ? '.webp' : '.jpg');
    $dir      = __DIR__ . '/../uploads/' . $subdir . '/';
    $saved    = $useWebp ? imagewebp($src, $dir . $filename, 85) : imagejpeg($src, $dir . $filename, 88);

    return $saved ? $filename : null;
}

// ---------------------------------------------------------------------
// Форматирование
// ---------------------------------------------------------------------
const RU_MONTHS = [1=>'января',2=>'февраля',3=>'марта',4=>'апреля',5=>'мая',6=>'июня',
                   7=>'июля',8=>'августа',9=>'сентября',10=>'октября',11=>'ноября',12=>'декабря'];
const RU_MONTHS_SHORT = [1=>'ЯНВ',2=>'ФЕВ',3=>'МАР',4=>'АПР',5=>'МАЯ',6=>'ИЮН',
                         7=>'ИЮЛ',8=>'АВГ',9=>'СЕН',10=>'ОКТ',11=>'НОЯ',12=>'ДЕК'];

/**
 * Дата «в организации с»: реальная дата вступления в МГЕР, если админ её
 * указал на карточке волонтёра, иначе дата регистрации на сайте — чтобы
 * это значение не расходилось в разных местах интерфейса.
 */
function membershipDate(array $user): ?string
{
    return $user['mger_joined_at'] ?: $user['created_at'];
}

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
// Тексты новостей (посты из Telegram приходят с эмодзи и хэштегами)
// ---------------------------------------------------------------------
/** Убирает эмодзи, тире и пробелы по краям строки. Внутри строки текст не трогаем. */
function trimDecor(string $s): string
{
    $s = preg_replace('/^[\p{So}\p{Sk}\p{Cf}\p{Mn}\p{Me}\p{Pd}\p{Zs}\s]+/u', '', $s) ?? $s;
    $s = preg_replace('/[\p{So}\p{Sk}\p{Cf}\p{Mn}\p{Me}\p{Zs}\s]+$/u', '', $s) ?? $s;
    return trim($s);
}

/**
 * Заголовок новости для показа. Если в заголовке нет ни одной буквы
 * (например, «❗️❗️❗️»), берётся первая содержательная строка текста.
 */
function cleanNewsTitle(string $title, string $body = ''): string
{
    $t = trimDecor($title);
    if (preg_match('/\p{L}/u', $t)) {
        return $t;
    }
    foreach (preg_split('/\R/u', $body) ?: [] as $line) {
        $line = trimDecor($line);
        if (preg_match('/\p{L}.*\p{L}/u', $line)) {
            if (mb_strlen($line) > 110) {
                $cut  = mb_substr($line, 0, 110);
                $line = rtrim(mb_substr($cut, 0, mb_strrpos($cut, ' ') ?: 110), ' ,.;:') . '…';
            }
            return $line;
        }
    }
    return $t !== '' ? $t : 'Новость';
}

/**
 * Разбирает текст новости: абзацы без повтора заголовка и без строк из одних
 * хэштегов, плюс сами хэштеги отдельным списком.
 */
function newsBodyParts(string $body, string $title = ''): array
{
    $paras = [];
    $tags  = [];
    $titleKey = mb_strtolower(cleanNewsTitle($title, $body));
    foreach (preg_split('/\R{2,}/u', trim($body)) ?: [] as $para) {
        $lines = [];
        foreach (preg_split('/\R/u', $para) ?: [] as $line) {
            if (preg_match('/^\s*(#[\p{L}\p{N}_]+\s*)+$/u', $line)) {
                preg_match_all('/#([\p{L}\p{N}_]+)/u', $line, $m);
                $tags = array_merge($tags, $m[1]);
                continue;
            }
            $lines[] = rtrim($line);
        }
        $text = trim(implode("\n", $lines));
        // Абзац из одних эмодзи или знаков — просто украшение
        if (!preg_match('/[\p{L}\p{N}]/u', $text)) {
            continue;
        }
        // Первая строка поста обычно и есть заголовок — не повторяем её в тексте
        if (!$paras && $titleKey !== '') {
            $first = preg_split('/\R/u', $text)[0];
            if (mb_strtolower(trimDecor($first)) === $titleKey) {
                $text = trim(mb_substr($text, mb_strlen($first)));
                if (!preg_match('/[\p{L}\p{N}]/u', $text)) {
                    continue;
                }
            }
        }
        $paras[] = $text;
    }
    return ['paras' => $paras, 'tags' => array_values(array_unique($tags))];
}

/** Короткий анонс новости для карточек: без заголовка, хэштегов и лишних пробелов. */
function newsExcerpt(array $n, int $len = 160): string
{
    $source = (string)($n['body'] ?? '') !== '' ? (string)$n['body'] : (string)($n['excerpt'] ?? '');
    $parts  = newsBodyParts($source, (string)($n['title'] ?? ''));
    $text   = preg_replace('/\s+/u', ' ', implode(' ', $parts['paras'])) ?? '';
    $text   = trimDecor($text);
    return mb_strimwidth($text, 0, $len, '…');
}

/** Понятное название действия из журнала: [текст, иконка, оттенок]. */
function actionLabel(string $action): array
{
    return [
        'login'                    => ['Вход в панель', 'sign-in', ''],
        'login_failed'             => ['Неудачная попытка входа', 'alert', 'warn'],
        'consent_confirm'          => ['Подтвердил согласие на обработку данных', 'check-circle', 'ok'],
        'public_consent_on'        => ['Разрешил показ имени и фото на сайте', 'eye', 'info'],
        'public_consent_off'       => ['Отключил показ имени и фото на сайте', 'eye-off', ''],
        'public_consent_paper'     => ['Отмечено письменное согласие на публикацию', 'eye', 'info'],
        'data_export'              => ['Скачал свои данные', 'download', ''],
        'user_delete'              => ['Удалены данные волонтёра', 'trash', 'warn'],
        'logout'                   => ['Выход', 'sign-out', ''],
        'register'                 => ['Заявка на вступление', 'user-plus', 'info'],
        'user_approve'             => ['Заявка одобрена', 'check-circle', 'ok'],
        'user_reject'              => ['Заявка отклонена', 'prohibit', ''],
        'user_block'               => ['Доступ закрыт', 'prohibit', 'warn'],
        'user_unblock'             => ['Доступ восстановлен', 'check-circle', 'ok'],
        'user_role'                => ['Изменена роль', 'shield-check', ''],
        'user_position'            => ['Изменена позиция', 'users', ''],
        'profile_update'           => ['Обновлён профиль', 'user-circle', ''],
        'profile_update_by_admin'  => ['Профиль изменён администратором', 'pencil', ''],
        'password_change'          => ['Смена пароля', 'key', ''],
        'avatar_update'            => ['Новое фото профиля', 'image', ''],
        'avatar_remove'            => ['Фото профиля удалено', 'image', ''],
        'mger_joined_update'       => ['Изменена дата вступления', 'calendar', ''],
        'coordinator_notes_update' => ['Изменена заметка', 'pencil', ''],
        'event_create'             => ['Создано мероприятие', 'calendar-check', 'info'],
        'event_update'             => ['Изменено мероприятие', 'calendar-check', ''],
        'event_delete'             => ['Удалено мероприятие', 'trash', ''],
        'event_finish'             => ['Мероприятие завершено', 'check-circle', 'ok'],
        'event_signup'             => ['Запись на мероприятие', 'calendar-plus', 'info'],
        'event_cancel'             => ['Отмена записи', 'undo', ''],
        'attendance_mark'          => ['Отмечено участие', 'list-checks', 'ok'],
        'points_manual'            => ['Начислены баллы', 'star', 'ok'],
        'badge_create'             => ['Создано достижение', 'medal', ''],
        'badge_delete'             => ['Удалено достижение', 'trash', ''],
        'badge_award'              => ['Выдано достижение', 'medal', 'ok'],
        'badge_revoke'             => ['Снято достижение', 'undo', ''],
        'news_create'              => ['Опубликована новость', 'newspaper', 'info'],
        'news_update'              => ['Изменена новость', 'newspaper', ''],
        'news_delete'              => ['Удалена новость', 'trash', ''],
        'hero_slide_create'        => ['Фото добавлено на главную', 'image', ''],
        'hero_slide_delete'        => ['Фото убрано с главной', 'image', ''],
        'hero_slide_reorder'       => ['Изменён порядок фото', 'image', ''],
        'honor_update'             => ['Обновлена доска почёта', 'crown', ''],
        'settings_update'          => ['Изменены настройки сайта', 'gear', ''],
        'telegram_settings_update' => ['Изменены настройки Telegram', 'telegram', ''],
        'telegram_sync'            => ['Загружены посты из Telegram', 'telegram', 'info'],
        'logs_cleanup'             => ['Очищен журнал', 'trash', ''],
    ][$action] ?? [$action, 'clipboard', ''];
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

require_once __DIR__ . '/icons.php';
require_once __DIR__ . '/privacy.php';
try {
    ensureSchema();
    privacyHousekeeping();
} catch (Throwable $e) {
    error_log('privacy bootstrap: ' . $e->getMessage()); // например, во время установки, пока нет таблиц
}
require_once __DIR__ . '/auth.php';
