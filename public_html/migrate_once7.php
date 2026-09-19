<?php
require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: text/plain; charset=UTF-8');

$exists = fetchValue(
    "SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'api_tokens'"
);

if ((int)$exists > 0) {
    echo "OK: table api_tokens already exists.\n";
} else {
    db()->exec(
        "CREATE TABLE `api_tokens` (
           `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
           `user_id`      INT UNSIGNED NOT NULL,
           `token_hash`   CHAR(64) NOT NULL,
           `device`       VARCHAR(190)     NULL,
           `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
           `last_used_at` DATETIME         NULL,
           PRIMARY KEY (`id`),
           UNIQUE KEY `uniq_token_hash` (`token_hash`),
           KEY `idx_user` (`user_id`),
           CONSTRAINT `fk_api_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "DONE: table api_tokens created.\n";
}
