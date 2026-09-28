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
            flash('error', 'Сначала укажи токен бота.');
        } else {
            try {
                $me = tgApiRequest($token, 'getMe', []);
                flash('success', 'Бот на связи: @' . ($me['username'] ?? '?') . '. Не забудь добавить его администратором канала.');
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
        flash('info', 'Секретная ссылка обновлена, старая больше не работает.');
    }
    redirect('dev/telegram.php');
}

$cronUrl    = url('cron/telegram_sync.php') . '?token=' . setting('telegram_sync_secret');
$scriptPath = realpath(__DIR__ . '/../cron/telegram_sync.php') ?: (__DIR__ . '/../cron/telegram_sync.php');
$draftsFromTg = (int)fetchValue("SELECT COUNT(*) FROM news WHERE tg_message_id IS NOT NULL AND status = 'draft'");

$tgNews    = (int)fetchValue('SELECT COUNT(*) FROM news WHERE tg_message_id IS NOT NULL');
$connected = setting('telegram_bot_token') !== '' && setting('telegram_channel') !== '';
$lastSync  = setting('telegram_last_sync_at');
$panelLead = 'Посты из канала сами попадают в новости сайта и сразу публикуются. Здесь подключение и расписание.';
$panelActions = '<form method="post" style="margin:0">' . csrfField() . '<input type="hidden" name="action" value="check"><button type="submit" class="btn btn-line">' . icon('badge-check') . 'Проверить бота</button></form>'
    . '<form method="post" style="margin:0">' . csrfField() . '<input type="hidden" name="action" value="sync"><button type="submit" class="btn btn-accent">' . icon('refresh') . 'Синхронизировать</button></form>';

require __DIR__ . '/../includes/panel_header.php';
?>

<section class="card">
  <div class="card-b tg-status">
    <span class="tg-logo"><?= icon('telegram-brand') ?></span>
    <div>
      <div class="row" style="flex-wrap:wrap;gap:10px">
        <b style="font-size:17px"><?= setting('telegram_channel') !== '' ? e(setting('telegram_channel')) : 'Канал не указан' ?></b>
        <?= $connected ? '<span class="chip ok">' . icon('check') . 'Подключено</span>' : '<span class="chip warn">' . icon('alert') . 'Не настроено</span>' ?>
      </div>
      <p class="muted" style="font-size:14px;margin-top:4px">Последний запуск: <?= $lastSync ? e(ruDate($lastSync, true)) : 'ещё не было' ?><?= setting('telegram_last_sync_result') ? ', ' . e(setting('telegram_last_sync_result')) : '' ?></p>
    </div>
  </div>
  <div class="stat-inline">
    <div><b class="num"><?= $tgNews ?></b><span><?= plural($tgNews, 'новость', 'новости', 'новостей') ?> из канала</span></div>
    <div><b class="num"><?= $draftsFromTg ?></b><span><?= plural($draftsFromTg, 'черновик ждёт', 'черновика ждут', 'черновиков ждут') ?> публикации</span></div>
    <div><b><?= $lastSync ? e(date('H:i', strtotime($lastSync))) : 'нет' ?></b><span>время последней синхронизации</span></div>
  </div>
</section>

<div class="grid g2" style="margin-top:18px">
  <section class="card">
    <div class="card-h"><div><h2>Подключение</h2><p>Бот должен быть администратором канала</p></div></div>
    <form method="post" class="card-b stack">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save">
      <div class="field">
        <label for="telegram_bot_token">Токен бота</label>
        <div class="row" style="gap:8px">
          <input class="input mono" type="password" id="telegram_bot_token" name="telegram_bot_token" value="<?= e(setting('telegram_bot_token')) ?>" placeholder="123456:AA..." autocomplete="off" style="flex:1">
          <button class="icon-btn" type="button" data-reveal-input="telegram_bot_token" aria-label="Показать или скрыть токен"><?= icon('eye') ?></button>
        </div>
        <span class="hint">Выдаёт <a href="https://t.me/BotFather" target="_blank" rel="noopener">@BotFather</a> по команде /newbot.</span>
      </div>
      <div class="field">
        <label for="telegram_channel">Канал</label>
        <input class="input" type="text" id="telegram_channel" name="telegram_channel" value="<?= e(setting('telegram_channel')) ?>" placeholder="@mger_schelkovo">
        <span class="hint">Имя публичного канала через @ или числовой chat_id закрытого.</span>
      </div>
      <div><button type="submit" class="btn btn-accent">Сохранить</button></div>
    </form>
  </section>

  <section class="card">
    <div class="card-h"><div><h2>Как подключить</h2></div></div>
    <div class="card-b">
      <ol class="steps">
        <li><span>Создай бота через <a href="https://t.me/BotFather" target="_blank" rel="noopener">@BotFather</a> и скопируй токен.</span></li>
        <li><span>Добавь бота в канал с правами администратора, иначе он не увидит посты.</span></li>
        <li><span>Впиши токен и канал, сохрани и нажми «Проверить бота».</span></li>
        <li><span>Настрой автозапуск ниже: раз в 10 или 15 минут достаточно.</span></li>
      </ol>
    </div>
  </section>
</div>

<section class="card" style="margin-top:18px">
  <div class="card-h"><div><h2>Автозапуск</h2><p>Один из двух способов на выбор</p></div></div>
  <div class="card-b stack">
    <div class="field">
      <span class="lbl">Задание cron на хостинге</span>
      <div class="codebox"><span>php <?= e($scriptPath) ?></span><button class="btn btn-line btn-sm" type="button" data-copy="php <?= e($scriptPath) ?>"><?= icon('copy') ?>Копировать</button></div>
    </div>
    <div class="field">
      <span class="lbl">Ссылка для внешнего сервиса, например cron-job.org</span>
      <div class="codebox"><span><?= e($cronUrl) ?></span><button class="btn btn-line btn-sm" type="button" data-copy="<?= e($cronUrl) ?>"><?= icon('copy') ?>Копировать</button></div>
      <span class="hint">В ссылке секретный ключ, не публикуй её.</span>
    </div>
  </div>
  <div class="card-f" style="justify-content:flex-start">
    <form method="post" style="margin:0">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="reset_secret">
      <button type="submit" class="btn btn-ghost btn-sm" data-confirm="Старая ссылка перестанет работать. Продолжить?"><?= icon('key') ?>Выпустить новую ссылку</button>
    </form>
  </div>
</section>

<?php require __DIR__ . '/../includes/panel_footer.php'; ?>
