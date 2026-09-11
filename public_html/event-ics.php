<?php
require_once __DIR__ . '/includes/bootstrap.php';

$id = (int)($_GET['id'] ?? 0);
$ev = fetchOne("SELECT * FROM events WHERE id = ? AND status IN ('published','finished')", [$id]);
if (!$ev) {
    http_response_code(404);
    exit('Мероприятие не найдено.');
}

/** Экранирование текстовых полей .ics: перевод строк и спецсимволы */
function icsEscape(string $s): string
{
    return str_replace(["\\", "\n", ",", ";"], ["\\\\", "\\n", "\\,", "\\;"], $s);
}

$start = new DateTime($ev['starts_at'], new DateTimeZone(date_default_timezone_get()));
$end   = (clone $start)->modify('+2 hours'); // длительность в базе не хранится — берём типовые 2 часа
$startUtc = (clone $start)->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
$endUtc   = (clone $end)->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
$stamp    = (new DateTime('now', new DateTimeZone('UTC')))->format('Ymd\THis\Z');

global $config;
$desc = trim((string)$ev['description']);
if ($ev['location']) {
    $desc .= ($desc !== '' ? "\n\n" : '') . 'Место: ' . $ev['location'];
}
$desc .= "\n\n" . url('event.php?id=' . $id);

$lines = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//Molodaya Gvardiya Schelkovo//Events//RU',
    'CALSCALE:GREGORIAN',
    'BEGIN:VEVENT',
    'UID:event-' . $id . '@' . parse_url($config['base_url'] ?? '', PHP_URL_HOST),
    'DTSTAMP:' . $stamp,
    'DTSTART:' . $startUtc,
    'DTEND:' . $endUtc,
    'SUMMARY:' . icsEscape($ev['title']),
    'DESCRIPTION:' . icsEscape($desc),
];
if ($ev['location']) {
    $lines[] = 'LOCATION:' . icsEscape($ev['location']);
}
$lines[] = 'END:VEVENT';
$lines[] = 'END:VCALENDAR';

$slug = mb_substr(preg_replace('/[^a-zA-Zа-яА-Я0-9]+/u', '_', $ev['title']), 0, 40);

header('Content-Type: text/calendar; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $slug . '.ics"');
echo implode("\r\n", $lines) . "\r\n";
