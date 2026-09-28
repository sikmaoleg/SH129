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
            flash('error', 'Укажи название достижения.');
        } elseif (mb_strlen($icon) > 16) {
            flash('error', 'Значок должен быть коротким: цифра, буква или эмодзи.');
        } else {
            $code = 'custom_' . bin2hex(random_bytes(6));
            q('INSERT INTO badges (code, title, description, icon) VALUES (?,?,?,?)',
              [$code, $title, $desc !== '' ? $desc : null, $icon !== '' ? $icon : null]);
            logAction('badge_create', 'badge', (int)db()->lastInsertId(), $title);
            flash('success', 'Достижение создано.');
        }
    } elseif ($action === 'award') {
        $badgeId = (int)($_POST['badge_id'] ?? 0);
        $userId  = (int)($_POST['user_id'] ?? 0);
        $badge = $badgeId ? fetchOne('SELECT * FROM badges WHERE id = ?', [$badgeId]) : null;
        $user  = $userId ? fetchOne("SELECT id, first_name, last_name FROM users WHERE id = ? AND status = 'approved'", [$userId]) : null;
        if (!$badge || !$user) {
            flash('error', 'Выбери достижение и волонтёра.');
        } else {
            $st = q('INSERT IGNORE INTO user_badges (user_id, badge_id, awarded_by) VALUES (?,?,?)', [$userId, $badgeId, (int)$me['id']]);
            $who = $user['first_name'] . ' ' . $user['last_name'];
            if ($st->rowCount() > 0) {
                logAction('badge_award', 'user', $userId, (string)$badgeId);
                flash('success', 'Достижение «' . $badge['title'] . '» выдано: ' . $who . '.');
            } else {
                flash('info', 'Достижение «' . $badge['title'] . '» уже есть у волонтёра: ' . $who . '.');
            }
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

$people = fetchAll("SELECT id, first_name, last_name FROM users WHERE status = 'approved' ORDER BY last_name ASC, first_name ASC");
$openNew = isset($_GET['new']);
$panelLead = 'Значки за особые заслуги. Волонтёры видят их в личном кабинете и в приложении.';
$panelActions = '<button class="btn btn-accent" type="button" data-drawer-open="badgeDrawer">' . icon('plus') . 'Новое достижение</button>';
$glyph = fn($b) => ($b['icon'] !== '' && $b['icon'] !== null) ? e($b['icon']) : icon('badge-check');

require __DIR__ . '/../includes/panel_header.php';
?>

<div class="badges">
  <?php foreach ($badges as $b): $n = (int)$b['holders']; ?>
    <article class="bdg">
      <span class="bdg-ico"><?= $glyph($b) ?></span>
      <div><h3><?= e($b['title']) ?></h3><p><?= e($b['description'] ?: 'Без описания') ?></p></div>
      <div class="bdg-f">
        <span><?= $n ? 'Выдано ' . $n . ' ' . plural($n, 'раз', 'раза', 'раз') : 'Ещё никому не выдано' ?></span>
        <div class="row" style="gap:4px">
          <button class="btn btn-line btn-sm" type="button" data-drawer-open="awardDrawer" data-fill="<?= (int)$b['id'] ?>" data-fill-target="award_badge"><?= icon('medal') ?>Выдать</button>
          <form method="post" style="margin:0">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="badge_id" value="<?= (int)$b['id'] ?>">
            <button type="submit" class="menu-dots" aria-label="Удалить «<?= e($b['title']) ?>»" data-confirm="Удалить достижение «<?= e($b['title']) ?>»? Оно пропадёт у всех, кому выдано."><?= icon('trash') ?></button>
          </form>
        </div>
      </div>
    </article>
  <?php endforeach; ?>
  <button class="bdg new" type="button" data-drawer-open="badgeDrawer"><?= icon('plus') ?><b>Придумать новое</b><span class="muted">Название, значок и за что выдаётся</span></button>
</div>

<div class="drawer" id="badgeDrawer" <?= $openNew ? '' : 'hidden' ?> data-close-url="<?= url('admin/badges.php') ?>">
  <form method="post" class="drawer-panel" role="dialog" aria-modal="true" aria-labelledby="bdT">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="create">
    <div class="drawer-head"><div><p class="drawer-kicker">Достижение</p><h2 class="drawer-title" id="bdT">Новое достижение</h2></div><button class="icon-btn" type="button" data-close-drawer aria-label="Закрыть"><?= icon('x') ?></button></div>
    <div class="drawer-body">
      <div class="field"><label for="b_title">Название</label><input class="input" type="text" id="b_title" name="title" maxlength="120" required placeholder="Например: Организатор года"></div>
      <div class="field">
        <label for="b_icon">Значок</label>
        <div class="row" style="flex-wrap:wrap">
          <input class="input" type="text" id="b_icon" name="icon" maxlength="4" placeholder="1" style="width:90px;text-align:center;font-weight:700">
          <div class="reasons"><?php foreach (['1', '5', '10', '24', 'N', '★'] as $g): ?><button type="button" data-fill="<?= e($g) ?>" data-fill-target="b_icon"><?= e($g) ?></button><?php endforeach; ?></div>
        </div>
        <span class="hint">До четырёх символов: цифра, буква или эмодзи. Так значок выглядит и в приложении.</span>
      </div>
      <div class="field"><label for="b_desc">За что выдаётся</label><textarea class="textarea" id="b_desc" name="description" maxlength="255" style="min-height:90px" placeholder="Одним предложением"></textarea></div>
    </div>
    <div class="drawer-foot"><button class="btn btn-ghost" type="button" data-close-drawer>Отмена</button><button class="btn btn-accent" type="submit"><?= icon('plus') ?>Создать</button></div>
  </form>
</div>

<div class="drawer" id="awardDrawer" hidden>
  <form method="post" class="drawer-panel" role="dialog" aria-modal="true" aria-labelledby="awT">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="award">
    <div class="drawer-head"><div><p class="drawer-kicker">Достижение</p><h2 class="drawer-title" id="awT">Выдать</h2></div><button class="icon-btn" type="button" data-close-drawer aria-label="Закрыть"><?= icon('x') ?></button></div>
    <div class="drawer-body">
      <div class="field"><label for="award_badge">Достижение</label>
        <select class="select" id="award_badge" name="badge_id" required>
          <?php foreach ($badges as $b): ?><option value="<?= (int)$b['id'] ?>"><?= e($b['title']) ?></option><?php endforeach; ?>
        </select></div>
      <div class="field"><label for="award_user">Кому</label>
        <select class="select" id="award_user" name="user_id" required>
          <option value="">Выбери волонтёра</option>
          <?php foreach ($people as $pp): ?><option value="<?= (int)$pp['id'] ?>"><?= e($pp['last_name'] . ' ' . $pp['first_name']) ?></option><?php endforeach; ?>
        </select>
        <span class="hint">Снять достижение можно в карточке волонтёра.</span></div>
    </div>
    <div class="drawer-foot"><button class="btn btn-ghost" type="button" data-close-drawer>Отмена</button><button class="btn btn-accent" type="submit"><?= icon('medal') ?>Выдать</button></div>
  </form>
</div>

<?php require __DIR__ . '/../includes/panel_footer.php'; ?>
