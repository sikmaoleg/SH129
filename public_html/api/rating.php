<?php
declare(strict_types=1);
require_once __DIR__ . '/_api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiError('Метод не поддерживается.', 405);
}

$me = apiRequireUser();
$period = ($_GET['period'] ?? 'all') === 'month' ? 'month' : 'all';

if ($period === 'month') {
    $rows = fetchAll(
        "SELECT u.id, u.last_name, u.first_name, u.avatar,
                COALESCE(SUM(t.points),0) AS pts
         FROM users u
         LEFT JOIN point_transactions t
                ON t.user_id = u.id
               AND t.created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
         WHERE u.status='approved' AND u.role='volunteer'
         GROUP BY u.id, u.last_name, u.first_name, u.avatar
         ORDER BY pts DESC, u.last_name ASC
         LIMIT 100"
    );
} else {
    $rows = fetchAll(
        "SELECT id, last_name, first_name, avatar, points AS pts
         FROM users WHERE status='approved' AND role='volunteer'
         ORDER BY points DESC, last_name ASC LIMIT 100"
    );
}

$items = [];
foreach ($rows as $i => $r) {
    $items[] = [
        'place'    => $i + 1,
        'id'       => (int)$r['id'],
        'name'     => trim($r['last_name'] . ' ' . $r['first_name']),
        'avatar'   => $r['avatar'] ? url('uploads/avatars/' . $r['avatar']) : null,
        'points'   => (int)$r['pts'],
        'levelName'=> levelFor((int)$r['pts'])['current']['name'],
        'isMe'     => (int)$r['id'] === (int)$me['id'],
    ];
}

apiSuccess(['items' => $items, 'levels' => levels()]);
