<?php
declare(strict_types=1);
require_once __DIR__ . '/_api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiError('Метод не поддерживается.', 405);
}

$order = ['leader', 'local_staff', 'staff', 'activist'];
$placeholders = implode(',', array_fill(0, count($order), '?'));
$members = fetchAll(
    "SELECT id, last_name, first_name, middle_name, position, avatar, vk, telegram
     FROM users WHERE status = 'approved' AND position IN ($placeholders)
     ORDER BY FIELD(position, $placeholders), last_name ASC",
    array_merge($order, $order)
);

apiSuccess(['items' => array_map(fn($m) => [
    'id'            => (int)$m['id'],
    'name'          => trim($m['last_name'] . ' ' . $m['first_name']),
    'position'      => $m['position'],
    'positionLabel' => positionLabel($m['position']),
    'avatar'        => $m['avatar'] ? url('uploads/avatars/' . $m['avatar']) : null,
    'vk'            => $m['vk'],
    'telegram'      => $m['telegram'],
], $members)]);
