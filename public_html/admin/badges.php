<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$me = requireAdmin();

$panelSection = 'admin';
$panelTitle   = 'Достижения';
$activeItem   = 'badges';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $title = trim((string)($_POST['title'] ?? ''));
        $desc  = trim((string)($_POST['description'] ?? ''));
        $icon  = trim((string)($_POST['icon'] ?? ''));

        if ($title === '') {
            flash('error', 'Укажите название достижения.');
        } elseif (mb_strlen($icon) > 16) {
            flash('error', 'Значок должен быть коротким — один-два символа/эмодзи.');
        } else {
            $code = 'custom_' . bin2hex(random_bytes(6));
            q('INSERT INTO badges (code, title, description, icon) VALUES (?,?,?,?)',
              [$code, $title, $desc !== '' ? $desc : null, $icon !== '' ? $icon : null]);
            logAction('badge_create', 'badge', (int)db()->lastInsertId(), $title);
            flash('success', 'Достижение создано.');
        }
    } elseif ($action === 'delete') {
        $badgeId = (int)($_POST['badge_id'] ?? 0);
        $badge = $badgeId ? fetchOne('SELECT * FROM badges WHERE id = ?', [$badgeId]) : null;
        if ($badge) {
            q('DELETE FROM badges WHERE id = ?', [$badgeId]);
            logAction('badge_delete', 'badge', $badgeId, $badge['title']);
            flash('info', 'Достижение удалено вместе со всеми выдачами.');
        } else {
            flash('error', 'Достижение не найдено.');
        }
    }
    redirect('admin/badges.php');
}

$badges = fetchAll(
    "SELECT b.*, (SELECT COUNT(*) FROM user_badges ub WHERE ub.badge_id = b.id) AS holders
     FROM badges b ORDER BY b.title ASC"
);

require __DIR__ . '/../includes/panel_header.php';
?>

<div class="grid-2">
  <div class="card">
    <div class="card-head"><div><h2>Новое достижение</h2><p>Придумайте название, описание и короткий значок (эмодзи или 1-2 буквы).</p></div></div>
    <div class="card-body">
      <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create">
        <div class="field-row">
          <div class="field">
            <label for="title">Название</label>
            <input type="text" id="title" name="title" maxlength="120" required placeholder="Например: Организатор года">
          </div>
          <div class="field">
            <label for="icon">Значок</label>
            <input type="text" id="icon" name="icon" maxlength="16" placeholder="🏆">
          </div>
        </div>
        <div class="field">
          <label for="description">Описание</label>
          <input type="text" id="description" name="description" maxlength="255" placeholder="За что выдаётся это достижение">
        </div>
        <button type="submit" class="btn btn-primary">Создать</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><div><h2>Как выдавать</h2></div></div>
    <div class="card-body">
      <p style="color:var(--muted);font-size:.9rem;">Созданное здесь достижение можно выдать любому волонтёру на его карточке — откройте «Волонтёры» → нужный человек → блок «Достижения».</p>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-head"><div><h2>Все достижения — <?= count($badges) ?></h2></div></div>
  <?php if ($badges): ?>
    <div class="card-body card-body-flush table-wrap">
      <table class="data cards">
        <thead><tr><th style="width:60px;">Значок</th><th>Название</th><th>Описание</th><th style="width:110px;">Выдано</th><th style="width:100px;">Действия</th></tr></thead>
        <tbody>
          <?php foreach ($badges as $b): ?>
            <tr>
              <td data-label="Значок" style="font-size:1.3rem;"><?= $b['icon'] !== '' && $b['icon'] !== null ? e($b['icon']) : icon('badge-check') ?></td>
              <td data-label="Название"><b><?= e($b['title']) ?></b></td>
              <td data-label="Описание" style="color:var(--muted);"><?= e($b['description'] ?: '—') ?></td>
              <td class="num" data-label="Выдано"><?= (int)$b['holders'] ?></td>
              <td data-label="Действия">
                <form method="post" style="margin:0;">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="badge_id" value="<?= (int)$b['id'] ?>">
                  <button type="submit" class="btn btn-outline btn-sm" data-confirm="Удалить достижение «<?= e($b['title']) ?>»? Оно будет снято у всех, кому выдано.">Удалить</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="empty"><b>Достижений пока нет</b>Создайте первое в форме выше.</div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/panel_footer.php'; ?>
