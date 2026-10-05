<?php
declare(strict_types=1);

/**
 * Синхронизация новостей из Telegram-канала.
 *
 * Два источника, оба складывают посты в таблицу news сразу опубликованными:
 *  1. Bot API (getUpdates). Бот должен быть администратором канала. Telegram
 *     хранит новые посты для бота только 24 часа: если за сутки никто не
 *     забрал их, они пропадают из очереди навсегда.
 *  2. Публичная страница канала t.me/s/<имя>. Там видны последние ~20 постов
 *     без ограничения по времени, поэтому она подбирает всё, что бот упустил.
 * Повторы отсекаются по tg_message_id (у альбома это id первого сообщения).
 */

/** Низкоуровневый вызов Bot API. Бросает исключение с текстом ошибки Telegram. */
function tgApiRequest(string $token, string $method, array $params = []): array
{
    $url = 'https://api.telegram.org/bot' . $token . '/' . $method;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($params),
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $raw = curl_exec($ch);
    $curlError = curl_error($ch);

    if ($raw === false) {
        throw new RuntimeException('Не удалось связаться с Telegram: ' . $curlError);
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['ok'])) {
        throw new RuntimeException('Telegram API: ' . (is_array($data) ? ($data['description'] ?? 'неизвестная ошибка') : 'некорректный ответ'));
    }
    return $data['result'];
}

/** Скачивает файл по file_id и возвращает его содержимое или null. */
function tgDownloadFile(string $token, string $fileId): ?string
{
    try {
        $info = tgApiRequest($token, 'getFile', ['file_id' => $fileId]);
    } catch (Throwable $e) {
        return null;
    }
    $path = $info['file_path'] ?? null;
    if (!$path) {
        return null;
    }
    $url = 'https://api.telegram.org/file/bot' . $token . '/' . $path;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $data = curl_exec($ch);
    return $data === false ? null : $data;
}

/** Сохраняет скачанное фото (если это валидное изображение) в uploads/news/. Возвращает имя файла или null. */
function tgSaveNewsCover(string $token, string $fileId): ?string
{
    $bytes = tgDownloadFile($token, $fileId);
    if ($bytes === null) {
        return null;
    }
    $tmp = tempnam(sys_get_temp_dir(), 'tgphoto');
    file_put_contents($tmp, $bytes);
    $filename = resizeAndSaveImage($tmp, 'news', 1600);
    @unlink($tmp);
    return $filename;
}

/** Забирает новые посты через Bot API. Возвращает ['imported'=>int,'skipped'=>int]. */
function tgBotImport(string $token, string $channel): array
{
    // На случай, если у бота когда-то был включён webhook — getUpdates с ним не работает.
    // Безопасно вызывать каждый раз: если webhook не установлен, это no-op.
    try {
        tgApiRequest($token, 'deleteWebhook', []);
    } catch (Throwable $e) {
        // не критично — попробуем getUpdates всё равно
    }

    $lastUpdateId = (int)setting('telegram_last_update_id', '0');
    $updates = tgApiRequest($token, 'getUpdates', [
        'offset'          => $lastUpdateId + 1,
        'timeout'         => 0,
        'allowed_updates' => json_encode(['channel_post']),
        'limit'           => 100,
    ]);

    $imported = 0;
    $skipped  = 0;
    $maxUpdateId = $lastUpdateId;

    // Канал может быть указан как @username или числовой chat_id (-100...).
    $wantUsername = ltrim($channel, '@');
    $wantChatId   = ctype_digit(ltrim($channel, '-')) ? $channel : null;

    // Сначала отбираем посты нашего канала. Фотоальбом Telegram присылает как
    // несколько отдельных channel_post с одинаковым media_group_id -- их нужно
    // собрать в один пост, а не импортировать как несколько новостей.
    $posts = [];
    foreach ($updates as $upd) {
        $maxUpdateId = max($maxUpdateId, (int)$upd['update_id']);
        $post = $upd['channel_post'] ?? null;
        if (!$post) {
            continue;
        }

        $chat = $post['chat'] ?? [];
        $matches = ($wantChatId !== null && (string)($chat['id'] ?? '') === $wantChatId)
            || ($wantUsername !== '' && strcasecmp((string)($chat['username'] ?? ''), $wantUsername) === 0);
        if (!$matches) {
            $skipped++;
            continue;
        }
        $posts[] = $post;
    }

    $groups = []; // media_group_id => [посты...], в порядке появления
    $singles = [];
    foreach ($posts as $post) {
        $gid = $post['media_group_id'] ?? null;
        if ($gid !== null) {
            $groups[$gid][] = $post;
        } else {
            $singles[] = [$post];
        }
    }

    foreach (array_merge($groups, $singles) as $groupPosts) {
        usort($groupPosts, fn($a, $b) => $a['message_id'] <=> $b['message_id']);
        $tgMessageId = (int)$groupPosts[0]['message_id'];
        if (fetchValue('SELECT id FROM news WHERE tg_message_id = ?', [$tgMessageId])) {
            $skipped += count($groupPosts); // уже импортировано раньше
            continue;
        }

        // В альбоме подпись есть только у одного из сообщений группы.
        $text = '';
        foreach ($groupPosts as $p) {
            $t = trim((string)($p['text'] ?? $p['caption'] ?? ''));
            if ($t !== '') {
                $text = $t;
                break;
            }
        }
        if ($text === '') {
            $skipped += count($groupPosts); // стикер, голосовое и т.п. без текста — пропускаем
            continue;
        }

        $images = [];
        foreach ($groupPosts as $p) {
            if (!empty($p['photo'])) {
                // В массиве photo — несколько размеров одного фото, берём самое крупное (последнее).
                $best = end($p['photo']);
                $filename = tgSaveNewsCover($token, $best['file_id']);
                if ($filename) {
                    $images[] = $filename;
                }
            }
        }
        tgCreateNews($tgMessageId, (int)($groupPosts[0]['date'] ?? time()), $text, $images);
        $imported++;
    }

    setSetting('telegram_last_update_id', (string)$maxUpdateId);
    return ['imported' => $imported, 'skipped' => $skipped];
}

/** Создаёт опубликованную новость из поста канала. $images — имена файлов в uploads/news. */
function tgCreateNews(int $tgMessageId, int $timestamp, string $text, array $images): void
{
    $lines = preg_split('/\R/', $text);
    // Без эмодзи по краям; если первая строка из одних эмодзи — берём следующую содержательную
    $title = cleanNewsTitle(trim($lines[0] ?? ''), $text);
    if (mb_strlen($title) > 180) {
        $title = mb_substr($title, 0, 180) . '…';
    }
    $excerpt = mb_strimwidth(preg_replace('/\s+/u', ' ', $text), 0, 300, '…');

    q('INSERT INTO news (title, excerpt, body, cover, status, published_at, tg_message_id) VALUES (?,?,?,?,?,?,?)',
      [$title, $excerpt, $text, $images[0] ?? null, 'published', date('Y-m-d H:i:s', $timestamp), $tgMessageId]);
    $newsId = (int)db()->lastInsertId();
    foreach (array_values($images) as $i => $filename) {
        q('INSERT INTO news_images (news_id, image, sort) VALUES (?,?,?)', [$newsId, 'uploads/news/' . $filename, $i * 10]);
    }
}

/** GET-запрос с понятным User-Agent. Возвращает тело ответа или null. */
function tgHttpGet(string $url, int $timeout = 20, int $connectTimeout = 10): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => $connectTimeout,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; sh-129-mg.ru news import)',
        CURLOPT_HTTPHEADER     => ['Accept-Language: ru'],
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($body === false || $code >= 400) {
        // Запоминаем причину, чтобы показать её администратору в сообщении о загрузке
        $GLOBALS['tg_http_error'] = $body === false ? ('нет ответа: ' . curl_error($ch)) : ('ответ ' . $code);
        return null;
    }
    return (string)$body;
}

/** Текст поста из блока tgme_widget_message_text: переносы строк сохраняются, эмодзи остаются символами. */
function tgNodeText(DOMNode $node): string
{
    $out = '';
    foreach ($node->childNodes as $child) {
        if ($child instanceof DOMText) {
            $out .= $child->nodeValue;
        } elseif ($child instanceof DOMElement) {
            $out .= strtolower($child->tagName) === 'br' ? "\n" : tgNodeText($child);
        }
    }
    return $out;
}

/**
 * Разбирает публичную страницу канала t.me/s/<имя>. Возвращает посты
 * от старых к новым: [['id'=>int,'ts'=>int,'text'=>string,'images'=>[url,...]], ...].
 */
function tgFetchPublicPosts(string $username): array
{
    $html = tgHttpGet('https://t.me/s/' . rawurlencode($username));
    if ($html === null) {
        throw new RuntimeException('Не удалось открыть публичную страницу канала t.me/s/' . $username);
    }
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    $xp = new DOMXPath($doc);
    $cls = fn(string $c) => "contains(concat(' ', normalize-space(@class), ' '), ' $c ')";

    $posts = [];
    foreach ($xp->query("//div[{$cls('tgme_widget_message')} and @data-post]") as $msg) {
        /** @var DOMElement $msg */
        [$owner, $id] = array_pad(explode('/', $msg->getAttribute('data-post'), 2), 2, '');
        if (strcasecmp($owner, $username) !== 0 || !ctype_digit($id)) {
            continue;
        }
        $time = $xp->query(".//a[{$cls('tgme_widget_message_date')}]//time[@datetime]", $msg)->item(0);
        $textNode = $xp->query(".//div[{$cls('tgme_widget_message_text')} and not(ancestor::*[{$cls('tgme_widget_message_reply')}])]", $msg)->item(0);
        $images = [];
        foreach ($xp->query(".//a[{$cls('tgme_widget_message_photo_wrap')}]", $msg) as $ph) {
            if (preg_match("/background-image:url\\('([^']+)'\\)/", $ph->getAttribute('style'), $m)) {
                $images[] = str_starts_with($m[1], '//') ? 'https:' . $m[1] : $m[1];
            }
        }
        if (!$images) {
            // Пост с видео: берём превью ролика как обложку
            foreach ($xp->query(".//i[{$cls('tgme_widget_message_video_thumb')}]", $msg) as $th) {
                if (preg_match("/background-image:url\\('([^']+)'\\)/", $th->getAttribute('style'), $m)) {
                    $images[] = str_starts_with($m[1], '//') ? 'https:' . $m[1] : $m[1];
                    break;
                }
            }
        }
        $posts[] = [
            'id'     => (int)$id,
            'ts'     => $time ? (int)strtotime($time->getAttribute('datetime')) : time(),
            'text'   => $textNode ? trim(tgNodeText($textNode)) : '',
            'images' => array_slice(array_values(array_unique($images)), 0, 10),
        ];
    }
    usort($posts, fn($a, $b) => $a['id'] <=> $b['id']);
    return $posts;
}

/** Скачивает картинку по ссылке и сохраняет в uploads/news. */
function tgSaveImageFromUrl(string $url): ?string
{
    // Сервер картинок Telegram (cdn*.telesco.pe) с хостинга бывает недоступен.
    // Тогда берём ту же картинку через открытый посредник images.weserv.nl,
    // и до конца запуска сразу идём через него, чтобы не ждать таймауты.
    $bytes = null;
    if (empty($GLOBALS['tg_cdn_down'])) {
        $bytes = tgHttpGet($url, 12, 5);
        if ($bytes === null) {
            $GLOBALS['tg_cdn_down'] = true;
        }
    }
    if ($bytes === null) {
        $bytes = tgHttpGet('https://images.weserv.nl/?url=' . rawurlencode($url), 20, 8);
    }
    if ($bytes === null) {
        return null;
    }
    $tmp = tempnam(sys_get_temp_dir(), 'tgweb');
    file_put_contents($tmp, $bytes);
    $filename = resizeAndSaveImage($tmp, 'news', 1600);
    @unlink($tmp);
    return $filename;
}

/**
 * Подбирает с публичной страницы канала посты, которых ещё нет на сайте.
 * Берём только посты не старше самой ранней новости из Telegram на сайте,
 * чтобы не вытащить архив канала, и пропускаем посты, удалённые администратором.
 * За один запуск не больше $limit постов, чтобы уложиться во время запроса.
 */
function tgWebImport(string $username, int $limit = 8, float $budget = 20.0): array
{
    // Хостинг обрывает долгие запросы, поэтому работаем не дольше $budget секунд:
    // пост, который не успели обработать, останется на следующий запуск.
    $deadline = microtime(true) + $budget;
    $posts = tgFetchPublicPosts($username);
    $since = fetchValue('SELECT MIN(published_at) FROM news WHERE tg_message_id IS NOT NULL');
    $sinceTs = $since ? strtotime((string)$since) - 86400 : 0;
    $ignored = array_filter(array_map('intval', explode(',', setting('telegram_ignored_ids'))));
    $locked  = array_filter(array_map('intval', explode(',', setting('telegram_photos_locked'))));
    $GLOBALS['tg_http_error'] = null;

    $download = function (array $urls) use (&$photoFails): array {
        $files = [];
        foreach ($urls as $i => $url) {
            if ($i > 0) {
                usleep(150000); // не дёргаем сервер картинок слишком часто
            }
            $f = tgSaveImageFromUrl($url);
            if ($f) {
                $files[] = $f;
            } else {
                $photoFails++;
            }
        }
        return $files;
    };

    $imported = 0; $skipped = 0; $left = 0; $healed = 0; $photoFails = 0;
    foreach ($posts as $post) {
        if ($post['ts'] < $sinceTs || in_array($post['id'], $ignored, true)) {
            continue;
        }
        $existing = fetchOne('SELECT id, cover, (SELECT COUNT(*) FROM news_images i WHERE i.news_id = n.id) AS photos FROM news n WHERE tg_message_id = ?', [$post['id']]);
        if ($existing) {
            // Пост уже на сайте, но фото скачались не все: докачиваем, пока есть время
            if (count($post['images']) > (int)$existing['photos'] && !in_array($post['id'], $locked, true)
                && microtime(true) < $deadline) {
                $files = $download($post['images']);
                if (count($files) > (int)$existing['photos']) {
                    foreach (fetchAll('SELECT image FROM news_images WHERE news_id = ?', [$existing['id']]) as $old) {
                        @unlink(APP_ROOT . '/' . $old['image']);
                    }
                    q('DELETE FROM news_images WHERE news_id = ?', [$existing['id']]);
                    foreach ($files as $i => $f) {
                        q('INSERT INTO news_images (news_id, image, sort) VALUES (?,?,?)', [$existing['id'], 'uploads/news/' . $f, $i * 10]);
                    }
                    q('UPDATE news SET cover = ? WHERE id = ?', [$files[0], $existing['id']]);
                    $healed++;
                } else {
                    foreach ($files as $f) {
                        @unlink(APP_ROOT . '/uploads/news/' . $f);
                    }
                }
            }
            continue;
        }
        if ($post['text'] === '') {
            $skipped++;
            continue;
        }
        if ($imported >= $limit || ($imported > 0 && microtime(true) > $deadline)) {
            $left++;
            continue;
        }
        // Текст публикуем сразу, даже если часть фото не скачалась: их докачает следующий запуск
        tgCreateNews($post['id'], $post['ts'], $post['text'], $download($post['images']));
        $imported++;
    }
    return ['imported' => $imported, 'skipped' => $skipped, 'left' => $left, 'healed' => $healed,
            'photo_fails' => $photoFails, 'photo_error' => $GLOBALS['tg_http_error'] ?? null];
}

/**
 * Основная синхронизация: сначала бот, потом публичная страница канала.
 * Возвращает ['imported'=>int,'skipped'=>int,'left'=>int,'notes'=>[...]] или
 * бросает исключение, если не сработал ни один источник.
 */
function runTelegramSync(int $webLimit = 8, float $budget = 20.0): array
{
    $token   = setting('telegram_bot_token');
    $channel = trim(setting('telegram_channel'));
    if ($channel === '') {
        throw new RuntimeException('Укажи канал в настройках Telegram.');
    }
    @set_time_limit(150);

    // Не даём двум синхронизациям идти одновременно (кнопка + автозапуск)
    $lockFile = APP_ROOT . '/storage/telegram_sync.lock';
    $lock = @fopen($lockFile, 'c');
    if ($lock && !flock($lock, LOCK_EX | LOCK_NB)) {
        throw new RuntimeException('Загрузка уже идёт, подожди минуту.');
    }

    // Отмечаем запуск сразу: если хостинг оборвёт запрос, автозапуск не будет повторяться на каждом посещении
    setSetting('telegram_last_sync_at', date('Y-m-d H:i:s'));
    $imported = 0; $skipped = 0; $left = 0; $healed = 0; $notes = []; $ok = false;
    if ($token !== '') {
        try {
            $r = tgBotImport($token, $channel);
            $imported += $r['imported']; $skipped += $r['skipped']; $ok = true;
        } catch (Throwable $e) {
            $notes[] = 'бот: ' . $e->getMessage();
        }
    }
    $username = ltrim($channel, '@');
    if (preg_match('/^[A-Za-z0-9_]{4,}$/', $username)) {
        try {
            $r = tgWebImport($username, $webLimit, $budget);
            $imported += $r['imported']; $skipped += $r['skipped']; $left += $r['left']; $ok = true;
            $healed = $r['healed'];
            if ($r['photo_fails'] > 0) {
                $notes[] = 'не скачалось фото: ' . $r['photo_fails'] . ($r['photo_error'] ? ' (' . $r['photo_error'] . ')' : '') . ', докачаю при следующей загрузке';
            }
        } catch (Throwable $e) {
            $notes[] = 'страница канала: ' . $e->getMessage();
        }
    }
    if ($lock) {
        flock($lock, LOCK_UN);
        fclose($lock);
    }

    setSetting('telegram_last_sync_at', date('Y-m-d H:i:s'));
    setSetting('telegram_last_sync_result', "Импортировано: {$imported}, докачаны фото: {$healed}, пропущено: {$skipped}" . ($notes ? '. Заметки: ' . implode('; ', $notes) : ''));
    if (!$ok) {
        throw new RuntimeException(implode('; ', $notes) ?: 'Не настроен ни бот, ни публичный канал.');
    }
    return ['imported' => $imported, 'skipped' => $skipped, 'left' => $left, 'healed' => $healed, 'notes' => $notes];
}
