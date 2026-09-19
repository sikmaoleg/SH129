<?php
declare(strict_types=1);
require_once __DIR__ . '/_api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiError('Метод не поддерживается.', 405);
}

$me = apiRequireUser();

$all = fetchAll('SELECT * FROM badges ORDER BY id ASC');
$mine = [];
foreach (fetchAll('SELECT badge_id, awarded_at FROM user_badges WHERE user_id = ?', [(int)$me['id']]) as $r) {
    $mine[(int)$r['badge_id']] = $r['awarded_at'];
}

apiSuccess(['items' => array_map(function ($b) use ($mine) {
    $earned = isset($mine[(int)$b['id']]);
    return [
        'id'          => (int)$b['id'],
        'code'        => $b['code'],
        'title'       => $b['title'],
        'description' => $b['description'],
        'icon'        => $b['icon'],
        'earned'      => $earned,
        'awardedAt'   => $earned ? $mine[(int)$b['id']] : null,
    ];
}, $all)]);
