<?php
require_once __DIR__ . '/includes/bootstrap.php';
header('Content-Type: text/plain; charset=UTF-8');

echo "admin/users.php считает: status IN ('approved','blocked')\n";
echo "  всего: " . fetchValue("SELECT COUNT(*) FROM users WHERE status IN ('approved','blocked')") . "\n\n";

echo "admin/index.php считает: status='approved' AND role='volunteer'\n";
echo "  всего: " . fetchValue("SELECT COUNT(*) FROM users WHERE status='approved' AND role='volunteer'") . "\n\n";

echo "Разбивка всех approved/blocked по роли и статусу:\n";
$rows = fetchAll("SELECT role, status, COUNT(*) AS n FROM users WHERE status IN ('approved','blocked') GROUP BY role, status ORDER BY role, status");
foreach ($rows as $r) {
    echo "  {$r['role']} / {$r['status']}: {$r['n']}\n";
}
