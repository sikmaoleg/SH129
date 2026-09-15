<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/telegram.php';
requireDev();

$panelSection = 'dev';
$panelTitle   = 'Telegram';
$activeItem   = 'telegram';

// Секрет для запуска синхронизации по URL — генерируем один раз, если его ещё нет.
if (setting('telegram_sync_secret') === '') {
    setSetting('telegram_sync_secret', bin2hex(random_bytes(20)));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $token   = trim((string)($_POST['telegram_bot_token'] ?? ''));
        $channel = trim((string)($_POST['telegram_channel'] ?? ''));
        setSetting('telegram_bot_token', $token);
        setSetting('telegram_channel', $channel);
        logAction('telegram_settings_update', 'settings');
        flash('success', 'Настройки сохранены.');
    } elseif ($action === 'check') {
        $token = setting('telegram_bot_token');
        if ($token === '') {
            flash('error', 'Сначала укажите токен бота.');
        } else {
            try {
                $me = tgApiRequest($token, 'getMe', []);
                flash('success', 'Бот на связи: @' . ($me['username'] ?? '?') . '. Не забудьте добавить его администратором канала.');
            } catch (Throwable $e) {
                flash('error', 'Не удалось подключиться: ' . $e->getMessage());
            }
        }
    } elseif ($action === 'sync') {
        try {
            $result = runTelegramSync();
            flash('success', "Синхронизация выполнена. Импортировано: {$result['imported']}, пропущено: {$result['skipped']}.");
        } catch (Throwable $e) {
            flash('error', 'Ошибка синхронизации: ' . $e->getMessage());
        }
    } elseif ($action === 'reset_secret') {
        setSetting('telegram_sync_secret', bin2hex(random_bytes(20)));
        flash('info', 'Секретный токен обновлён — старая ссылка для cron больше не работает.');
    }
    redirect('dev/telegram.php');
}

$cronUrl    = url('cron/telegram_sync.php') . '?token=' . setting('telegram_sync_secret');
$scriptPath = realpath(__DIR__ . '/../cron/telegram_sync.php') ?: (__DIR__ . '/../cron/telegram_sync.php');
$draftsFromTg = (int)fetchValue("SELECT COUNT(*) FROM news WHERE tg_message_id IS NOT NULL AND status = 'draft'");

require __DIR__ . '/../includes/panel_header.php';
?>

<div class="grid-2">
  <div class="card">
    <div class="card-head"><div><h2>Подключение канала</h2><p>Бот должен быть добавлен <b>администратором</b> вашего Telegram-канала.</p></div></div>
    <div class="card-body">
      <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="save">
        <div class="field">
          <label for="telegram_bot_token">Токен бота</label>
          <input type="password" id="telegram_bot_token" name="telegram_bot_token" value="<?= e(setting('telegram_bot_token')) ?>" placeholder="123456:AA...">
          <div class="hint">Получите у <a href="https://t.me/BotFather" target="_blank" rel="noopener">@BotFather</a> командой /newbot.</div>
        </div>
        <div class="field">
          <label for="telegram_channel">Канал</label>
          <input type="text" id="telegram_channel" name="telegram_channel" value="<?= e(setting('telegram_channel')) ?>" placeholder="@mger_schelkovo">
          <div class="hint">Публичное имя канала (@username) или числовой chat_id для закрытого канала.</div>
        </div>
        <button type="submit" class="btn btn-primary">Сохранить</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><div><h2>Как это работает</h2></div></div>
    <div class="card-body">
      <ol style="margin:0;padding-left:20px;font-size:.9rem;color:var(--ink);display:flex;flex-direction:column;gap:8px;">
        <li>Создайте бота через <a href="https://t.me/BotFather" target="_blank" rel="noopener">@BotFather</a> и скопируйте токен.</li>
        <li>Добавьте бота в канал — с правами <b>администратора</b> (иначе он не увидит посты).</li>
        <li>Впишите токен и канал слева, нажмите «Проверить подключение».</li>
        <li>Настройте регулярный запуск синхронизации (см. блок ниже) — раз в 10–15 минут достаточно.</li>
        <li>Новые посты канала попадают в «Новости» черновиками — откройте и опубликуйте нужные.</li>
      </ol>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <div><h2>Синхронизация</h2><p>Последний запуск: <?= setting('telegram_last_sync_at') ? e(ruDate(setting('telegram_last_sync_at'), true)) : 'ещё не запускалась' ?><?= setting('telegram_last_sync_result') ? ' — ' . e(setting('telegram_last_sync_result')) : '' ?></p></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <form method="post" style="margin:0;">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="check">
        <button type="submit" class="btn btn-outline btn-sm">Проверить подключение</button>
      </form>
      <form method="post" style="margin:0;">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="sync">
        <button type="submit" class="btn btn-primary btn-sm">Синхронизировать сейчас</button>
      </form>
    </div>
  </div>
  <div class="card-body">
    <p style="font-size:.9rem;color:var(--muted);margin-top:0;">Чтобы посты подтягивались сами, добавьте регулярный запуск — либо cron-заданием на хостинге (если панель хостинга это умеет):</p>
    <div class="hint mono" style="background:var(--blue-050);padding:10px 14px;border-radius:var(--radius-sm);word-break:break-all;">php <?= e($scriptPath) ?></div>
    <p style="font-size:.9rem;color:var(--muted);">...либо через внешний сервис вроде cron-job.org, который раз в 10–15 минут открывает эту ссылку (содержит секретный токен — никому её не передавайте):</p>
    <div class="hint mono" style="background:var(--blue-050);padding:10px 14px;border-radius:var(--radius-sm);word-break:break-all;"><?= e($cronUrl) ?></div>
    <form method="post" style="margin-top:12px;">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="reset_secret">
      <button type="submit" class="btn btn-outline btn-sm" data-confirm="Старая ссылка перестанет работать. Продолжить?">Сгенерировать новую ссылку</button>
    </form>
  </div>
</div>

<?php if ($draftsFromTg > 0): ?>
<div class="card">
  <div class="card-head"><div><h2>Ждут публикации</h2><p>Черновики, созданные из Telegram и ещё не опубликованные.</p></div></div>
  <div class="card-body">
    <p><b><?= $draftsFromTg ?></b> <?= plural($draftsFromTg, 'черновик', 'черновика', 'черновиков') ?> — <a href="<?= url('admin/news.php') ?>">открыть «Новости»</a>.</p>
  </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/panel_footer.php'; ?>
