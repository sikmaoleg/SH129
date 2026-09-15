<?php
/**
 * Точка входа для регулярной синхронизации новостей из Telegram-канала.
 * Запускается либо по cron (php cron/telegram_sync.php), либо по URL
 * с секретным токеном (?token=...), если хостинг умеет дёргать cron
 * только по HTTP. Настраивается в панели разработчика: /dev/telegram.php.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/telegram.php';

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    header('Content-Type: text/plain; charset=UTF-8');
    $secret = setting('telegram_sync_secret');
    $given  = (string)($_GET['token'] ?? '');
    if ($secret === '' || !hash_equals($secret, $given)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

try {
    $result = runTelegramSync();
    echo "OK: импортировано {$result['imported']}, пропущено {$result['skipped']}\n";
} catch (Throwable $e) {
    if (!$isCli) {
        http_response_code(500);
    }
    echo 'ERROR: ' . $e->getMessage() . "\n";
}
