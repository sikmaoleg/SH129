<?php
declare(strict_types=1);
require_once __DIR__ . '/_api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiError('Метод не поддерживается.', 405);
}

$me = apiRequireUser();

$rows = fetchAll(
    "SELECT r.status, r.points_awarded, r.comment,
            e.id, e.title, e.starts_at, e.location, d.title AS direction_title
     FROM event_registrations r
     JOIN events e ON e.id = r.event_id
     LEFT JOIN directions d ON d.id = e.direction_id
     WHERE r.user_id = ?
     ORDER BY e.starts_at DESC", [(int)$me['id']]
);

$map = fn($r) => [
    'id'             => (int)$r['id'],
    'title'          => $r['title'],
    'startsAt'       => $r['starts_at'],
    'location'       => $r['location'],
    'directionTitle' => $r['direction_title'],
    'status'         => $r['status'],
    'pointsAwarded'  => (int)$r['points_awarded'],
    'comment'        => $r['comment'],
];

$upcoming = array_values(array_filter($rows, fn($r) => strtotime($r['starts_at']) >= time() && $r['status'] === 'registered'));
$past     = array_values(array_filter($rows, fn($r) => strtotime($r['starts_at']) < time() || $r['status'] !== 'registered'));

apiSuccess([
    'upcoming' => array_map($map, $upcoming),
    'past'     => array_map($map, $past),
]);
