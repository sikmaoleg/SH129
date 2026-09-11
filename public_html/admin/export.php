<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();

/** Строка CSV для Excel: точка с запятой, BOM для кириллицы */
function csvOut(string $filename, array $rows): never
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM — иначе Excel показывает кракозябры
    foreach ($rows as $row) {
        fputcsv($out, $row, ';', '"', '');
    }
    fclose($out);
    exit;
}

$type = $_GET['type'] ?? '';

if ($type === 'users') {
    $search = trim((string)($_GET['q'] ?? ''));
    $params = [];
    $where  = "status IN ('approved','blocked')";
    if ($search !== '') {
        $where .= " AND (last_name LIKE ? OR first_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
        $like = '%' . $search . '%';
        $params = [$like, $like, $like, $like];
    }
    $users = fetchAll("SELECT * FROM users WHERE $where ORDER BY last_name ASC", $params);

    $rows = [['Фамилия', 'Имя', 'Отчество', 'Почта', 'Телефон', 'Роль', 'Позиция', 'Статус', 'Очки', 'Часы', 'Дата рождения', 'В движении с']];
    foreach ($users as $u) {
        $rows[] = [
            $u['last_name'], $u['first_name'], $u['middle_name'] ?? '',
            $u['email'], $u['phone'] ?? '',
            match ($u['role']) { 'dev' => 'разработчик', 'admin' => 'администратор', default => 'волонтёр' },
            positionLabel($u['position']),
            $u['status'] === 'blocked' ? 'заблокирован' : 'активен',
            (int)$u['points'], (float)$u['hours'],
            $u['birth_date'] ?? '', $u['created_at'],
        ];
    }
    csvOut('volontery.csv', $rows);
}

if ($type === 'event') {
    $eventId = (int)($_GET['event_id'] ?? 0);
    $event = fetchOne('SELECT * FROM events WHERE id = ?', [$eventId]);
    if (!$event) {
        flash('error', 'Мероприятие не найдено.');
        redirect('admin/events.php');
    }
    $regs = fetchAll(
        "SELECT r.*, u.last_name, u.first_name, u.phone, u.email
         FROM event_registrations r JOIN users u ON u.id = r.user_id
         WHERE r.event_id = ? ORDER BY u.last_name ASC", [$eventId]
    );
    $statusLabel = ['registered' => 'записан', 'attended' => 'участие принято', 'no_show' => 'не пришёл', 'cancelled' => 'отменено'];

    $rows = [['Фамилия', 'Имя', 'Телефон', 'Почта', 'Статус', 'Часы', 'Начислено очков']];
    foreach ($regs as $r) {
        $rows[] = [
            $r['last_name'], $r['first_name'], $r['phone'] ?? '', $r['email'],
            $statusLabel[$r['status']] ?? $r['status'],
            (float)$r['hours'], (int)$r['points_awarded'],
        ];
    }
    $slug = mb_substr(preg_replace('/[^a-zA-Zа-яА-Я0-9]+/u', '_', $event['title']), 0, 40);
    csvOut('meropriyatie_' . $slug . '.csv', $rows);
}

if ($type === 'summary') {
    $from = $_GET['from'] ?? date('Y-m-01');
    $to   = $_GET['to']   ?? date('Y-m-d');
    if (!strtotime($from) || !strtotime($to)) {
        flash('error', 'Некорректный период.');
        redirect('admin/analytics.php');
    }
    $toEnd = date('Y-m-d', strtotime($to)) . ' 23:59:59';

    $newUsers = fetchAll(
        "SELECT last_name, first_name, email, created_at FROM users
         WHERE status='approved' AND role='volunteer' AND created_at BETWEEN ? AND ?
         ORDER BY created_at ASC", [$from, $toEnd]
    );
    $eventsInRange = fetchAll(
        "SELECT e.title, e.starts_at,
                (SELECT COUNT(*) FROM event_registrations r WHERE r.event_id=e.id AND r.status='attended') AS attended,
                (SELECT COUNT(*) FROM event_registrations r WHERE r.event_id=e.id AND r.status<>'cancelled') AS registered
         FROM events e WHERE e.starts_at BETWEEN ? AND ? ORDER BY e.starts_at ASC", [$from, $toEnd]
    );
    $pointsSum = (float)fetchValue(
        "SELECT COALESCE(SUM(points),0) FROM point_transactions WHERE points > 0 AND created_at BETWEEN ? AND ?",
        [$from, $toEnd]
    );
    $hoursSum = (float)fetchValue(
        "SELECT COALESCE(SUM(r.hours),0) FROM event_registrations r JOIN events e ON e.id = r.event_id
         WHERE r.status='attended' AND e.starts_at BETWEEN ? AND ?",
        [$from, $toEnd]
    );

    $rows = [
        ['Сводка за период', $from . ' — ' . $to],
        [],
        ['Новых волонтёров', count($newUsers)],
        ['Мероприятий в периоде', count($eventsInRange)],
        ['Начислено очков', (int)$pointsSum],
        ['Отработано часов', $hoursSum],
        [],
        ['Новые волонтёры'],
        ['Фамилия', 'Имя', 'Почта', 'Дата'],
    ];
    foreach ($newUsers as $u) {
        $rows[] = [$u['last_name'], $u['first_name'], $u['email'], $u['created_at']];
    }
    $rows[] = [];
    $rows[] = ['Мероприятия'];
    $rows[] = ['Название', 'Дата', 'Записано', 'Пришло'];
    foreach ($eventsInRange as $ev) {
        $rows[] = [$ev['title'], $ev['starts_at'], (int)$ev['registered'], (int)$ev['attended']];
    }
    csvOut('svodka_' . $from . '_' . $to . '.csv', $rows);
}

flash('error', 'Не указан тип экспорта.');
redirect('admin/index.php');
