<?php
require_once __DIR__ . '/includes/bootstrap.php';
header('Content-Type: text/plain; charset=UTF-8');

global $config;
echo "config charset: " . ($config['db']['charset'] ?? '(not set)') . "\n\n";

foreach (['character_set_client', 'character_set_connection', 'character_set_results', 'collation_connection'] as $var) {
    $row = fetchOne("SHOW VARIABLES LIKE '$var'");
    echo $var . ': ' . ($row['Value'] ?? '?') . "\n";
}

echo "\nnews table columns:\n";
$cols = fetchAll("SHOW FULL COLUMNS FROM news");
foreach ($cols as $c) {
    if (in_array($c['Field'], ['title', 'excerpt', 'body'], true)) {
        echo "  {$c['Field']}: {$c['Collation']}\n";
    }
}

echo "\ntest insert with emoji as a bound parameter:\n";
try {
    db()->beginTransaction();
    q("INSERT INTO news (title, excerpt, body, status, published_at, tg_message_id) VALUES (?,?,?,?,?,?)",
      ['Тест эмодзи 🎉', 'кратко', 'текст', 'draft', date('Y-m-d H:i:s'), 999999999]);
    echo "OK: insert with emoji succeeded\n";
    db()->rollBack();
} catch (Throwable $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    echo "FAILED: " . $e->getMessage() . "\n";
}
