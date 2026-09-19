<?php
declare(strict_types=1);
require_once __DIR__ . '/_api.php';

function apiEventCard(array $ev, ?int $myUserId): array
{
    $capacity = (int)$ev['capacity'];
    $taken    = (int)$ev['taken'];
    return [
        'id'             => (int)$ev['id'],
        'title'          => $ev['title'],
        'startsAt'       => $ev['starts_at'],
        'location'       => $ev['location'],
        'directionTitle' => $ev['direction_title'] ?? null,
        'capacity'       => $capacity,
        'freeSlots'      => $capacity > 0 ? max(0, $capacity - $taken) : null,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = (int)($_GET['id'] ?? 0);
    $me = apiUser();

    if ($id) {
        $ev = fetchOne(
            "SELECT e.*, d.title AS direction_title,
                    (SELECT COUNT(*) FROM event_registrations r WHERE r.event_id = e.id AND r.status <> 'cancelled') AS taken
             FROM events e LEFT JOIN directions d ON d.id = e.direction_id
             WHERE e.id = ? AND e.status IN ('published','finished')", [$id]
        );
        if (!$ev) {
            apiError('Мероприятие не найдено.', 404);
        }
        $myReg = $me ? fetchOne('SELECT status FROM event_registrations WHERE event_id = ? AND user_id = ?', [$id, (int)$me['id']]) : null;
        $card = apiEventCard($ev, $me ? (int)$me['id'] : null);
        $card['description'] = $ev['description'];
        $card['cover']       = $ev['cover'] ? url('uploads/events/' . $ev['cover']) : null;
        $card['isPast']      = strtotime($ev['starts_at']) < time();
        $card['myStatus']    = $myReg['status'] ?? null;
        apiSuccess(['item' => $card]);
    }

    $filter = ($_GET['filter'] ?? 'upcoming') === 'past' ? 'past' : 'upcoming';
    $where  = $filter === 'past'
        ? "e.status IN ('published','finished') AND e.starts_at < NOW()"
        : "e.status = 'published' AND e.starts_at >= NOW()";
    $order  = $filter === 'past' ? 'DESC' : 'ASC';

    $rows = fetchAll(
        "SELECT e.*, d.title AS direction_title,
                (SELECT COUNT(*) FROM event_registrations r WHERE r.event_id = e.id AND r.status <> 'cancelled') AS taken
         FROM events e LEFT JOIN directions d ON d.id = e.direction_id
         WHERE $where ORDER BY e.starts_at $order LIMIT 60"
    );
    apiSuccess(['items' => array_map(fn($ev) => apiEventCard($ev, null), $rows)]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $me = apiRequireUser();
    $in = apiInput();
    $id = (int)($in['id'] ?? 0);
    $action = $in['action'] ?? '';

    $ev = fetchOne(
        "SELECT e.*, (SELECT COUNT(*) FROM event_registrations r WHERE r.event_id = e.id AND r.status <> 'cancelled') AS taken
         FROM events e WHERE e.id = ? AND e.status IN ('published','finished')", [$id]
    );
    if (!$ev) {
        apiError('Мероприятие не найдено.', 404);
    }
    $isPast = strtotime($ev['starts_at']) < time();

    if ($action === 'signup') {
        $freeSlots = (int)$ev['capacity'] > 0 ? max(0, (int)$ev['capacity'] - (int)$ev['taken']) : null;
        $myReg = fetchOne('SELECT status FROM event_registrations WHERE event_id = ? AND user_id = ?', [$id, (int)$me['id']]);
        if ($isPast) {
            apiError('Мероприятие уже прошло.', 422);
        }
        if ($freeSlots !== null && $freeSlots <= 0 && (!$myReg || $myReg['status'] === 'cancelled')) {
            apiError('Свободных мест не осталось.', 422);
        }
        q("INSERT INTO event_registrations (event_id, user_id, status) VALUES (?,?,'registered')
           ON DUPLICATE KEY UPDATE status = 'registered'", [$id, (int)$me['id']]);
        logAction('event_signup', 'event', $id);
        apiSuccess(['message' => 'Вы записаны на мероприятие.']);
    }

    if ($action === 'cancel') {
        if ($isPast) {
            apiError('Мероприятие уже прошло — отменить запись нельзя.', 422);
        }
        q("UPDATE event_registrations SET status='cancelled' WHERE event_id = ? AND user_id = ? AND status='registered'",
          [$id, (int)$me['id']]);
        logAction('event_cancel', 'event', $id);
        apiSuccess(['message' => 'Запись отменена.']);
    }

    apiError('Неизвестное действие.', 404);
}

apiError('Метод не поддерживается.', 405);
