<?php
require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: text/plain; charset=UTF-8');

$exists = fetchValue(
    "SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hero_slides'"
);

if ((int)$exists > 0) {
    echo "OK: table hero_slides already exists.\n";
} else {
    db()->exec("
        CREATE TABLE `hero_slides` (
          `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `image`      VARCHAR(190) NOT NULL,
          `caption`    VARCHAR(190)     NULL,
          `sort`       INT NOT NULL DEFAULT 0,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uniq_image` (`image`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "DONE: hero_slides table created.\n";
}

$seeded = (int)fetchValue('SELECT COUNT(*) FROM hero_slides');
if ($seeded > 0) {
    echo "OK: hero_slides already has {$seeded} row(s).\n";
} else {
    q("INSERT IGNORE INTO hero_slides (image,caption,sort) VALUES
        ('assets/img/team.webp','Команда отделения',10),
        ('assets/img/march.webp','Городское шествие',20),
        ('assets/img/creative.webp','Съёмка команды',30),
        ('assets/img/rink.webp','Спортивное мероприятие',40),
        ('assets/img/cake.webp','День рождения отделения',50),
        ('assets/img/rain.webp','Работаем в любую погоду',60)");
    echo "DONE: hero_slides seeded with the current 6 photos.\n";
}
