<?php
declare(strict_types=1);
require_once __DIR__ . '/_api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiError('Метод не поддерживается.', 405);
}

apiSuccess(['item' => [
    'orgName'          => setting('org_name', 'Молодая Гвардия · Щёлково'),
    'orgAddress'       => setting('org_address', 'Московская область, г. Щёлково'),
    'orgEmail'         => setting('org_email', 'info@example.ru'),
    'orgVk'            => setting('org_vk') ?: null,
    'orgTg'            => setting('org_tg') ?: null,
    'orgLeaderName'    => setting('org_leader_name') ?: null,
    'orgLeaderPhone'   => setting('org_leader_phone') ?: null,
    'registrationOpen' => setting('registration_open', '1') === '1',
]]);
