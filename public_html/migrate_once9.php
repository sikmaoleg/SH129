<?php
require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: text/plain; charset=UTF-8');

$current = fetchValue("SELECT value FROM settings WHERE `key` = 'org_map_coords'");

if ($current) {
    echo "OK: org_map_coords already set to '{$current}'.\n";
} else {
    setSetting('org_map_coords', '55.919256,37.994084');
    echo "DONE: org_map_coords set to '55.919256,37.994084'.\n";
}
