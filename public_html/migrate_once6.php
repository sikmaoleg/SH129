<?php
require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: text/plain; charset=UTF-8');

$exists = fetchValue(
    "SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'news_images'"
);

if ((int)$exists > 0) {
    echo "OK: table news_images already exists.\n";
} else {
    db()->exec(
        "CREATE TABLE `news_images` (
           `id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
           `news_id` INT UNSIGNED NOT NULL,
           `image`   VARCHAR(190) NOT NULL,
           `sort`    INT NOT NULL DEFAULT 0,
           PRIMARY KEY (`id`),
           KEY `idx_news` (`news_id`),
           CONSTRAINT `fk_news_images_news` FOREIGN KEY (`news_id`) REFERENCES `news` (`id`) ON DELETE CASCADE
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "DONE: table news_images created.\n";
}
