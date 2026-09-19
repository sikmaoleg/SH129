<?php
declare(strict_types=1);
require_once __DIR__ . '/_api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiError('Метод не поддерживается.', 405);
}

$heroPhotos = fetchAll('SELECT image, caption FROM hero_slides ORDER BY sort ASC, id ASC');

$news = fetchAll(
    "SELECT id, title, excerpt, cover, published_at
     FROM news WHERE status = 'published' AND published_at <= NOW()
     ORDER BY published_at DESC LIMIT 3"
);

$events = fetchAll(
    "SELECT e.*, d.title AS direction_title,
            (SELECT COUNT(*) FROM event_registrations r
              WHERE r.event_id = e.id AND r.status <> 'cancelled') AS taken
     FROM events e
     LEFT JOIN directions d ON d.id = e.direction_id
     WHERE e.status = 'published' AND e.starts_at >= NOW()
     ORDER BY e.starts_at ASC LIMIT 4"
);

$statVolunteers = (int)setting('stat_volunteers');
if ($statVolunteers <= 0) {
    $statVolunteers = (int)fetchValue("SELECT COUNT(*) FROM users WHERE status = 'approved' AND role = 'volunteer'");
}
$statEvents = (int)setting('stat_events');
if ($statEvents <= 0) {
    $statEvents = (int)fetchValue("SELECT COUNT(*) FROM events WHERE status IN ('published','finished')");
}
$statHours = (int)setting('stat_hours');
if ($statHours <= 0) {
    $statHours = (int)fetchValue('SELECT COALESCE(SUM(hours),0) FROM users');
}

$honorId = (int)setting('honor_user_id');
$honor   = $honorId > 0 ? fetchOne("SELECT * FROM users WHERE id = ? AND status = 'approved'", [$honorId]) : null;

apiSuccess([
    'heroPhotos' => array_map(fn($s) => ['image' => url($s['image']), 'caption' => $s['caption']], $heroPhotos),
    'news'       => array_map(fn($n) => [
        'id' => (int)$n['id'], 'title' => $n['title'], 'excerpt' => $n['excerpt'],
        'cover' => $n['cover'] ? url('uploads/news/' . $n['cover']) : null, 'publishedAt' => $n['published_at'],
    ], $news),
    'events'     => array_map(fn($ev) => [
        'id' => (int)$ev['id'], 'title' => $ev['title'], 'startsAt' => $ev['starts_at'],
        'location' => $ev['location'], 'directionTitle' => $ev['direction_title'],
        'capacity' => (int)$ev['capacity'],
        'freeSlots' => (int)$ev['capacity'] > 0 ? max(0, (int)$ev['capacity'] - (int)$ev['taken']) : null,
    ], $events),
    'stats'      => ['volunteers' => $statVolunteers, 'events' => $statEvents, 'hours' => $statHours],
    'honor'      => $honor ? [
        'id' => (int)$honor['id'], 'name' => trim($honor['last_name'] . ' ' . $honor['first_name']),
        'avatar' => $honor['avatar'] ? url('uploads/avatars/' . $honor['avatar']) : null,
        'note' => setting('honor_note') ?: null,
    ] : null,
]);
