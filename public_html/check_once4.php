<?php
require_once __DIR__ . '/includes/bootstrap.php';
header('Content-Type: text/plain; charset=UTF-8');

echo "PHP date('Y-m-d H:i:s'): " . date('Y-m-d H:i:s') . "\n";
echo "PHP default timezone:    " . date_default_timezone_get() . "\n";
$row = fetchOne("SELECT NOW() AS mysql_now, @@session.time_zone AS session_tz, @@global.time_zone AS global_tz");
echo "MySQL NOW():              " . $row['mysql_now'] . "\n";
echo "MySQL session time_zone:  " . $row['session_tz'] . "\n";
echo "MySQL global time_zone:   " . $row['global_tz'] . "\n";

$diffSeconds = strtotime(date('Y-m-d H:i:s')) - strtotime($row['mysql_now']);
echo "\nDifference (PHP - MySQL): {$diffSeconds} seconds\n";
