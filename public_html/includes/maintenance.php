<?php
/**
 * Режим технических работ: сайт закрыт для посетителей, сотрудники (админы и разработчики)
 * продолжают им пользоваться. Включается в «Разработчик → Настройки» или миграцией.
 * Команды cron (CLI) и страницы входа не закрываются.
 */

function maintenanceGate(): void
{
    if (PHP_SAPI === 'cli' || setting('maintenance_mode') !== '1') {
        return;
    }
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    // Вход и выход открыты: по ним сотрудники попадают в панель и возвращают сайт
    if (str_ends_with($script, '/login.php') || str_ends_with($script, '/logout.php')) {
        return;
    }
    // Приложение: JSON без страницы, сотрудники через приложение тоже ждут
    if (str_contains($script, '/api/')) {
        http_response_code(503);
        header('Retry-After: 3600');
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => false, 'error' => 'Сайт на техническом обслуживании. Попробуйте позже.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (currentUser() && (isAdmin() || isDev())) {
        return;
    }
    http_response_code(503);
    header('Retry-After: 3600');
    header('Content-Type: text/html; charset=utf-8');
    echo maintenancePage();
    exit;
}

function maintenancePage(): string
{
    $email = trim(setting('org_email'));
    $contact = $email !== ''
        ? '<p class="mt-note">Срочное дело? Напишите отделению: <a href="mailto:' . e($email) . '">' . e($email) . '</a></p>'
        : '';
    $fonts = e(url('assets/css/fonts.css'));
    return <<<HTML
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Сайт на субботнике: Молодая Гвардия Щёлково</title>
<link rel="stylesheet" href="{$fonts}">
<style>
:root{--paper:#F1F3F9;--ink:#0B1233;--ink-2:#454F70;--accent:#2A45F0;--ink-inv:#F1F3F9}
@media (prefers-color-scheme: dark){:root{--paper:#070B1F;--ink:#E9EDFA;--ink-2:#A9B2D0;--accent:#5B73FF;--ink-inv:#070B1F}}
*{box-sizing:border-box}
html,body{margin:0;min-height:100%}
body{background:var(--paper);color:var(--ink);font:400 17px/1.55 'Onest',system-ui,-apple-system,'Segoe UI',sans-serif;display:grid;place-items:center;min-height:100vh;padding:28px 20px;-webkit-font-smoothing:antialiased}
main{max-width:640px;width:100%}
.mt-code{display:block;font-family:'Alumni Sans',sans-serif;font-weight:900;font-size:clamp(150px,34vw,280px);line-height:.78;color:var(--accent);transform:skewY(-6deg);transform-origin:0 100%;margin:0 0 4px 0;animation:mt-wobble 3.2s ease-in-out infinite}
.mt-tag{display:inline-block;padding:6px 12px;border:2px solid var(--ink);font-size:14px;font-weight:700;letter-spacing:.01em}
h1{font-family:'Alumni Sans',sans-serif;font-weight:900;text-transform:uppercase;font-size:clamp(46px,8vw,84px);line-height:.88;margin:22px 0 16px;transform:skewY(-4deg);transform-origin:0 100%}
.mt-lead{font-size:clamp(17px,2vw,20px);color:var(--ink-2);max-width:46ch;margin:0}
.mt-note{margin:18px 0 0;font-size:15px;color:var(--ink-2)}
.mt-note a{color:var(--accent);font-weight:600}
.mt-actions{margin-top:30px}
.mt-btn{display:inline-flex;align-items:center;height:50px;padding:0 24px;background:var(--ink);color:var(--ink-inv);font:600 16px/1 'Onest',system-ui,sans-serif;border:0;cursor:pointer;transition:background-color .2s,color .2s}
.mt-btn:hover{background:var(--accent);color:#F7F8FF}
.mt-btn:focus-visible{outline:3px solid var(--accent);outline-offset:3px}
@keyframes mt-wobble{0%,100%{transform:skewY(-6deg) rotate(0)}50%{transform:skewY(-6deg) rotate(-4deg)}}
@media (prefers-reduced-motion: reduce){.mt-code{animation:none}}
</style>
</head>
<body>
<main>
  <span class="mt-code" aria-hidden="true">503</span>
  <span class="mt-tag">Ошибка 129: слишком много добрых дел одновременно</span>
  <h1>Сайт ушёл на субботник</h1>
  <p class="mt-lead">Пока мы наводим порядок в штабе: перебираем листовки, чиним то, что скрипит, и заодно переписываем то, что давно просило ремонта. Мы вернёмся совсем скоро, заглядывайте чуть позже.</p>
  {$contact}
  <div class="mt-actions"><button class="mt-btn" type="button" onclick="location.reload()">Проверить, не вернулись ли мы</button></div>
</main>
</body>
</html>
HTML;
}
