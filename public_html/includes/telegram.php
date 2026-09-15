<?php
declare(strict_types=1);

/**
 * Синхронизация новостей из Telegram-канала.
 *
 * Бот должен быть добавлен администратором канала. Раз в запуск (по cron
 * или вручную из панели разработчика) мы опрашиваем Bot API методом
 * getUpdates и забираем новые сообщения канала (channel_post), превращая
 * каждое в черновик в таблице news — координатор проверяет и публикует.
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
    curl_close($ch);

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
    curl_close($ch);
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
    $info = @getimagesize($tmp);
    $loaders = [IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_PNG => 'imagecreatefrompng', IMAGETYPE_WEBP => 'imagecreatefromwebp'];
    $src = ($info && isset($loaders[$info[2]])) ? $loaders[$info[2]]($tmp) : false;
    @unlink($tmp);
    if (!$src) {
        return null;
    }

    // Слишком крупные фото уменьшаем, чтобы не раздувать хранилище — пропорции не трогаем.
    $w = imagesx($src);
    $h = imagesy($src);
    $maxSide = max($w, $h);
    if ($maxSide > 1600) {
        $scale = 1600 / $maxSide;
        $nw = (int)round($w * $scale);
        $nh = (int)round($h * $scale);
        $dst = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);
        $src = $dst;
    }

    $useWebp  = function_exists('imagewebp');
    $filename = bin2hex(random_bytes(16)) . ($useWebp ? '.webp' : '.jpg');
    $dir      = __DIR__ . '/../uploads/news/';
    $saved    = $useWebp ? imagewebp($src, $dir . $filename, 85) : imagejpeg($src, $dir . $filename, 88);
    imagedestroy($src);

    return $saved ? $filename : null;
}

/**
 * Основной цикл синхронизации: забирает новые посты канала и создаёт
 * черновики в news. Возвращает ['imported'=>int,'skipped'=>int] или
 * бросает исключение с человекочитаемым текстом ошибки.
 */
function runTelegramSync(): array
{
    $token   = setting('telegram_bot_token');
    $channel = trim(setting('telegram_channel'));
    if ($token === '' || $channel === '') {
        throw new RuntimeException('Укажите токен бота и канал в настройках.');
    }

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

        $text = trim((string)($post['text'] ?? $post['caption'] ?? ''));
        if ($text === '') {
            $skipped++; // стикер, голосовое и т.п. без текста — пропускаем
            continue;
        }

        $tgMessageId = (int)$post['message_id'];
        if (fetchValue('SELECT id FROM news WHERE tg_message_id = ?', [$tgMessageId])) {
            $skipped++; // уже импортировано раньше
            continue;
        }

        $lines = preg_split('/\R/', $text);
        $title = trim($lines[0] ?? 'Новость из Telegram');
        if (mb_strlen($title) > 180) {
            $title = mb_substr($title, 0, 180) . '…';
        }
        $excerpt = mb_strimwidth(preg_replace('/\s+/u', ' ', $text), 0, 300, '…');

        $cover = null;
        if (!empty($post['photo'])) {
            // В массиве photo — несколько размеров одного фото, берём самое крупное (последнее).
            $best = end($post['photo']);
            $cover = tgSaveNewsCover($token, $best['file_id']);
        }

        q('INSERT INTO news (title, excerpt, body, cover, status, published_at, tg_message_id) VALUES (?,?,?,?,?,?,?)',
          [$title, $excerpt, $text, $cover, 'draft', date('Y-m-d H:i:s', (int)($post['date'] ?? time())), $tgMessageId]);
        $imported++;
    }

    setSetting('telegram_last_update_id', (string)$maxUpdateId);
    setSetting('telegram_last_sync_at', date('Y-m-d H:i:s'));
    $summary = "Импортировано: {$imported}, пропущено: {$skipped}";
    setSetting('telegram_last_sync_result', $summary);

    return ['imported' => $imported, 'skipped' => $skipped];
}
