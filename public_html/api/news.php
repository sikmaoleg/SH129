<?php
declare(strict_types=1);
require_once __DIR__ . '/_api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiError('Метод не поддерживается.', 405);
}

$id = (int)($_GET['id'] ?? 0);

if ($id) {
    $n = fetchOne("SELECT * FROM news WHERE id = ? AND status='published' AND published_at <= NOW()", [$id]);
    if (!$n) {
        apiError('Новость не найдена.', 404);
    }
    $images = fetchAll('SELECT image FROM news_images WHERE news_id = ? ORDER BY sort ASC, id ASC', [$id]);
    apiSuccess(['item' => [
        'id'          => (int)$n['id'],
        'title'       => $n['title'],
        'excerpt'     => $n['excerpt'],
        'body'        => $n['body'],
        'cover'       => $n['cover'] ? url('uploads/news/' . $n['cover']) : null,
        'images'      => array_map(fn($i) => url($i['image']), $images),
        'publishedAt' => $n['published_at'],
    ]]);
}

$rows = fetchAll(
    "SELECT id, title, excerpt, cover, published_at
     FROM news WHERE status = 'published' AND published_at <= NOW()
     ORDER BY published_at DESC LIMIT 50"
);
apiSuccess(['items' => array_map(fn($n) => [
    'id'          => (int)$n['id'],
    'title'       => $n['title'],
    'excerpt'     => $n['excerpt'],
    'cover'       => $n['cover'] ? url('uploads/news/' . $n['cover']) : null,
    'publishedAt' => $n['published_at'],
], $rows)]);
