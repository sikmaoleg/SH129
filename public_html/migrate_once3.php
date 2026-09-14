<?php
require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: text/plain; charset=UTF-8');

$exists = fetchValue(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'mger_joined_at'"
);

if ((int)$exists > 0) {
    echo "OK: column users.mger_joined_at already exists.\n";
} else {
    db()->exec("ALTER TABLE users ADD COLUMN mger_joined_at DATE NULL AFTER coordinator_notes");
    echo "DONE: users.mger_joined_at added.\n";
}
