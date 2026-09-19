<?php
declare(strict_types=1);
require_once __DIR__ . '/_api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiError('Метод не поддерживается.', 405);
}

$me = apiRequireUser();

$lvl = levelFor((int)$me['points']);

$myPlace = (int)fetchValue(
    "SELECT COUNT(*) + 1 FROM users WHERE status='approved' AND role='volunteer' AND points > ?", [(int)$me['points']]
);
$totalVolunteers = (int)fetchValue("SELECT COUNT(*) FROM users WHERE status='approved' AND role='volunteer'");

$attended = (int)fetchValue(
    "SELECT COUNT(*) FROM event_registrations WHERE user_id = ? AND status = 'attended'", [(int)$me['id']]
);

$upcoming = fetchAll(
    "SELECT e.id, e.title, e.starts_at, e.location
     FROM event_registrations r JOIN events e ON e.id = r.event_id
     WHERE r.user_id = ? AND r.status = 'registered' AND e.starts_at >= NOW()
     ORDER BY e.starts_at ASC LIMIT 5", [(int)$me['id']]
);

$openEvents = fetchAll(
    "SELECT e.id, e.title, e.starts_at, e.location
     FROM events e
     WHERE e.status='published' AND e.starts_at >= NOW()
       AND e.id NOT IN (SELECT event_id FROM event_registrations WHERE user_id = ? AND status <> 'cancelled')
     ORDER BY e.starts_at ASC LIMIT 4", [(int)$me['id']]
);

$history = fetchAll(
    "SELECT points, reason, created_at FROM point_transactions
     WHERE user_id = ? ORDER BY created_at DESC LIMIT 6", [(int)$me['id']]
);

$badgesEarned = (int)fetchValue('SELECT COUNT(*) FROM user_badges WHERE user_id = ?', [(int)$me['id']]);

$mapEvent = fn($e) => ['id' => (int)$e['id'], 'title' => $e['title'], 'startsAt' => $e['starts_at'], 'location' => $e['location']];

apiSuccess([
    'level'           => $lvl,
    'myPlace'         => $myPlace,
    'totalVolunteers' => $totalVolunteers,
    'attended'        => $attended,
    'badgesEarned'    => $badgesEarned,
    'upcoming'        => array_map($mapEvent, $upcoming),
    'openEvents'      => array_map($mapEvent, $openEvents),
    'history'         => array_map(fn($h) => [
        'points'    => (int)$h['points'],
        'reason'    => $h['reason'],
        'createdAt' => $h['created_at'],
    ], $history),
]);
