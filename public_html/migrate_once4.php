<?php
require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: text/plain; charset=UTF-8');

$exists = fetchValue(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'news' AND COLUMN_NAME = 'tg_message_id'"
);

if ((int)$exists > 0) {
    echo "OK: column news.tg_message_id already exists.\n";
} else {
    db()->exec("ALTER TABLE news ADD COLUMN tg_message_id BIGINT NULL, ADD UNIQUE KEY uniq_tg_message (tg_message_id)");
    echo "DONE: news.tg_message_id added.\n";
}
