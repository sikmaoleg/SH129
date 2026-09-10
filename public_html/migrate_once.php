<?php
require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

// 1) Колонка position, если её ещё нет
$hasPosition = fetchValue(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'position'"
);
if (!$hasPosition) {
    db()->exec("ALTER TABLE users ADD COLUMN position ENUM('volunteer','activist','staff','local_staff','leader')
                NOT NULL DEFAULT 'volunteer' AFTER role");
    echo "OK: добавлена колонка users.position\n";
} else {
    echo "SKIP: колонка users.position уже есть\n";
}

// 2) Админ bestiy09 (пароль задаётся хешем — plaintext нигде не хранится)
$adminHash = '$2y$12$MlfDp1BJQq/qQP/wyXPH3enORlx5Jq7/pVVxSiFOlwRJUVuDzKQGO';
$existing = fetchOne('SELECT id FROM users WHERE email = ?', ['bestiy09']);
if (!$existing) {
    q('INSERT INTO users (email, password_hash, last_name, first_name, role, position, status)
       VALUES (?,?,?,?,\'admin\',\'volunteer\',\'approved\')', [
        'bestiy09',
        $adminHash,
        'Администратор',
        'Сайта',
    ]);
    echo "OK: создан администратор bestiy09\n";
} else {
    q("UPDATE users SET password_hash = ?, role = 'admin', status = 'approved' WHERE email = ?",
      [$adminHash, 'bestiy09']);
    echo "OK: обновлён существующий пользователь bestiy09 (пароль/роль/статус)\n";
}

// 3) Настройки сайта
$settings = [
    'org_leader_name'  => 'Гореликова Виктория Денисовна',
    'org_leader_phone' => '+7 985 711-19-83',
    'org_email'        => 'mger2a@yandex.ru',
    'org_vk'           => 'https://vk.ru/mgerschelkovo',
    'org_tg'           => 'https://t.me/mgerschelkovo',
    'stat_volunteers'  => '100',
    'stat_events'      => '500',
    'stat_hours'       => '1000',
];
foreach ($settings as $k => $v) {
    setSetting($k, $v);
    echo "OK: setting {$k} = {$v}\n";
}

echo "DONE\n";
