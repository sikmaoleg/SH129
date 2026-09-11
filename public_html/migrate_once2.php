<?php
require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

$hasCol = fetchValue(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'coordinator_notes'"
);
if (!$hasCol) {
    db()->exec("ALTER TABLE users ADD COLUMN coordinator_notes TEXT NULL AFTER about");
    echo "OK: добавлена колонка users.coordinator_notes\n";
} else {
    echo "SKIP: колонка users.coordinator_notes уже есть\n";
}

echo "DONE\n";
