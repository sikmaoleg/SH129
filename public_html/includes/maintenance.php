<?php
/**
 * Режим технических работ: сайт закрыт для посетителей, сотрудники (админы и разработчики)
 * продолжают им пользоваться. Включается в «Разработчик → Настройки» или миграцией.
 * Команды cron (CLI) и страницы входа не закрываются.
 */

function maintenanceGate(): void
{
    // Читаем напрямую: миграция могла закрыть сайт уже в этом запросе, а кэш настроек старый
    if (PHP_SAPI === 'cli' || (string)fetchValue("SELECT `value` FROM settings WHERE `key` = 'maintenance_mode'") !== '1') {
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
        ? 'Срочное дело? Напишите отделению: <a href="mailto:' . e($email) . '">' . e($email) . '</a>'
        : '';
    $fonts = e(url('assets/css/fonts.css'));
    $html = <<<'HTML'
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<meta name="robots" content="noindex, nofollow">
<title>Окно штаба: Молодая Гвардия Щёлково</title>
<link rel="stylesheet" href="__FONTS__">
<style>
:root{--paper:#F1F3F9;--surface:#FAFBFE;--ink:#0B1233;--ink-2:#454F70;--line:#D3D9E8;--accent:#2A45F0;--hi:#F2B45A}
@media (prefers-color-scheme: dark){:root{--paper:#070B1F;--surface:#0C1230;--ink:#E9EDFA;--ink-2:#A9B2D0;--line:#242D55;--accent:#5B73FF;--hi:#F2B45A}}
*{box-sizing:border-box}
html,body{margin:0;min-height:100%}
body{background:var(--paper);color:var(--ink);font:400 17px/1.55 'Onest',system-ui,-apple-system,'Segoe UI',sans-serif;-webkit-font-smoothing:antialiased;display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;padding:32px 20px}
.mt-caption{font-family:'Alumni Sans',sans-serif;font-weight:800;text-transform:uppercase;font-size:20px;letter-spacing:.03em;color:var(--ink-2);margin:0 0 14px;width:min(100%,760px);transform:skewY(-3deg);transform-origin:0 100%}
.mt-glass{position:relative;width:min(100%,760px);min-height:540px;background:linear-gradient(120deg,rgba(255,255,255,.55) 0%,rgba(255,255,255,0) 40%),var(--surface);border:2px solid var(--ink);box-shadow:12px 12px 0 var(--ink);overflow:hidden}
@media (prefers-color-scheme: dark){.mt-glass{background:linear-gradient(120deg,rgba(255,255,255,.08) 0%,rgba(255,255,255,0) 40%),var(--surface)}}
.mt-glass::before{content:"";position:absolute;inset:12px;border:1px dashed var(--line);pointer-events:none;z-index:1}
.mt-content{position:relative;padding:clamp(30px,6vw,64px)}
.mt-code{font-family:'Alumni Sans',sans-serif;font-weight:900;font-size:clamp(120px,22vw,220px);line-height:.78;color:var(--accent);margin:0;transform:skewY(-6deg);transform-origin:0 100%}
.mt-tag{display:inline-block;margin-top:12px;padding:6px 12px;background:var(--hi);color:#221600;border:2px solid var(--ink);font-size:14px;font-weight:700;transform:rotate(-2deg)}
h1{font-family:'Alumni Sans',sans-serif;font-weight:900;text-transform:uppercase;font-size:clamp(46px,8vw,84px);line-height:.88;margin:22px 0 16px;transform:skewY(-4deg);transform-origin:0 100%}
.mt-lead{font-size:clamp(17px,2vw,19px);color:var(--ink-2);max-width:46ch;margin:0}
.mt-note{margin:16px 0 0;font-size:15px;color:var(--ink-2)}
.mt-note a{color:var(--accent);font-weight:600;position:relative;z-index:3}
.mt-actions{margin-top:26px;position:relative;z-index:3}
.mt-btn{display:inline-flex;align-items:center;max-width:100%;min-height:50px;padding:14px 24px;text-wrap:balance;text-align:center;line-height:1.25;background:var(--ink);color:var(--paper);font:600 16px/1 'Onest',system-ui,sans-serif;border:0;cursor:pointer;transition:background-color .2s,color .2s}
.mt-btn:hover{background:var(--accent);color:#F7F8FF}
.mt-btn:focus-visible,.mt-mini:focus-visible{outline:3px solid var(--accent);outline-offset:3px}
.mt-dust{position:absolute;inset:0;z-index:2;touch-action:none;cursor:crosshair;transition:opacity .9s ease}
.mt-dust.is-gone{opacity:0;pointer-events:none}
.mt-trash{position:absolute;z-index:3;width:64px;height:64px;margin:-32px 0 0 -32px;padding:0;border:0;background:none;color:var(--ink);cursor:grab;touch-action:none;transform:rotate(var(--rot,0deg));transition:translate .25s ease,opacity .35s ease,scale .35s ease}
.mt-trash.is-drag{cursor:grabbing;transition:none;z-index:5}
.mt-trash.is-out{opacity:0;scale:.3;pointer-events:none}
.mt-trash:focus-visible{outline:3px solid var(--accent);outline-offset:4px}
.mt-trash svg,.mt-bin svg{width:100%;height:100%;display:block;overflow:visible}
.mt-bin{position:absolute;top:16px;right:16px;z-index:3;width:70px;height:70px;color:var(--ink);pointer-events:none;transition:transform .2s ease,color .2s ease,opacity .6s ease}
.mt-bin.is-hot{transform:scale(1.15) rotate(-4deg);color:var(--accent)}
.mt-bin.is-hit{transform:scale(.92)}
.mt-bin.is-gone{opacity:0}
.mt-hud{width:min(100%,760px);display:flex;flex-wrap:wrap;gap:12px 20px;align-items:center;justify-content:space-between;margin-top:16px;font-size:15px;color:var(--ink-2)}
.mt-score{display:flex;align-items:baseline;gap:8px}
.mt-pct{font-family:'Alumni Sans',sans-serif;font-weight:900;font-size:34px;line-height:1;color:var(--ink)}
.mt-hint{margin:0;max-width:42ch}
.mt-mini{height:40px;padding:0 16px;background:transparent;border:2px solid var(--ink);color:var(--ink);font:600 14px/1 'Onest',system-ui,sans-serif;cursor:pointer}
.mt-mini:hover:not(:disabled){background:var(--ink);color:var(--paper)}
.mt-mini:disabled{opacity:.4;cursor:default}
.mt-status{width:min(100%,760px);min-height:24px;margin:10px 0 0;font-weight:600;color:var(--accent);font-size:15px}
@media (max-width:640px){.mt-glass{min-height:600px}.mt-code{font-size:128px}.mt-bin{width:54px;height:54px;top:12px;right:12px}}
@media (prefers-reduced-motion: reduce){.mt-dust,.mt-trash,.mt-bin{transition:none}}
</style>
</head>
<body>
<p class="mt-caption">Окно штаба, второй этаж</p>

<main class="mt-glass" id="mtGlass">
  <div class="mt-content">
    <p class="mt-code">503</p>
    <span class="mt-tag">Ошибка 129: слишком много добрых дел одновременно</span>
    <h1>Сайт ушёл на субботник</h1>
    <p class="mt-lead">Пока мы наводим порядок в штабе: перебираем листовки, чиним то, что скрипит, и переписываем базу. Заглядывайте чуть позже, мы всё вернём на место.</p>
    <p class="mt-note">__CONTACT__</p>
    <div class="mt-actions"><button class="mt-btn" type="button" onclick="location.reload()">Проверить, не вернулись ли мы</button></div>
  </div>
  <canvas class="mt-dust" id="mtDust" aria-hidden="true"></canvas>
</main>

<div class="mt-hud">
  <div class="mt-score"><span class="mt-pct" id="mtPct">0</span><span>% протёрто</span></div>
  <p class="mt-hint" id="mtHint">Выбросьте мусор в корзину и протрите окно, чтобы прочитать.</p>
  <button class="mt-mini" type="button" id="mtWipeAll">Протереть всё</button>
</div>
<p class="mt-status" id="mtStatus" role="status" aria-live="polite"></p>

<script>
(function () {
  var glass = document.getElementById('mtGlass');
  var cv = document.getElementById('mtDust');
  if (!glass || !cv || !cv.getContext) { return; }
  var ctx = cv.getContext('2d');
  var COLS = 22, ROWS = 14, W = 0, H = 0;
  var cells = [], touched = 0, cleared = false, drawing = false, last = null;
  var trashLeft = 0, dragging = null, dragMoved = false, sx = 0, sy = 0, bin = null;
  var pctEl = document.getElementById('mtPct');
  var hint = document.getElementById('mtHint');
  var status = document.getElementById('mtStatus');
  var all = document.getElementById('mtWipeAll');

  var SHAPES = [
    '<svg viewBox="0 0 64 64" aria-hidden="true"><path d="M10 38C4 28 12 14 26 16C30 6 48 6 52 18C60 24 58 42 48 50C42 58 22 58 14 50C12 47 11 42 10 38Z" style="fill:var(--surface)" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round"/><path d="M22 28l7 7M36 24l-3 9M40 40l8-5M18 44l6-3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
    '<svg viewBox="0 0 64 64" aria-hidden="true"><path d="M8 12L56 6L50 58L10 52Z" style="fill:var(--hi)" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round"/><path d="M16 22h26M16 30h30M16 38h18M16 46h22" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" opacity=".7"/></svg>',
    '<svg viewBox="0 0 64 64" aria-hidden="true"><rect x="18" y="8" width="28" height="48" rx="5" style="fill:var(--accent)" stroke="currentColor" stroke-width="2.5"/><path d="M18 18h28M18 46h28" fill="none" stroke="currentColor" stroke-width="2.5"/></svg>'
  ];
  var BIN = '<svg viewBox="0 0 64 64" aria-hidden="true"><path d="M12 18h40" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/><path d="M24 18v-6h16v6" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/><path d="M16 18l3 36h26l3-36" style="fill:var(--surface)" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/><path d="M27 26v20M37 26v20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>';
  var SPOTS = [[10, 20], [42, 14], [80, 24], [18, 62], [52, 58], [76, 60]];

  function rnd(a, b) { return a + Math.random() * (b - a); }

  function paint() {
    var dpr = cv.width / W;
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.clearRect(0, 0, cv.width, cv.height);
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    for (var i = 0; i < 16; i++) {
      var x = rnd(0, W), y = rnd(0, H), R = rnd(70, 190);
      var g = ctx.createRadialGradient(x, y, 0, x, y, R);
      g.addColorStop(0, 'rgba(120,128,150,0.36)');
      g.addColorStop(1, 'rgba(120,128,150,0)');
      ctx.fillStyle = g;
      ctx.fillRect(x - R, y - R, R * 2, R * 2);
    }
    var n = Math.round(W * H / 60);
    for (var j = 0; j < n; j++) {
      ctx.fillStyle = 'rgba(85,93,120,' + rnd(0.2, 0.8).toFixed(2) + ')';
      var s = rnd(0.5, 2.2);
      ctx.fillRect(rnd(0, W), rnd(0, H), s, s);
    }
  }

  function layout() {
    var r = glass.getBoundingClientRect();
    W = r.width; H = r.height;
    var dpr = Math.min(window.devicePixelRatio || 1, 2);
    cv.width = Math.round(W * dpr);
    cv.height = Math.round(H * dpr);
    cv.style.width = W + 'px';
    cv.style.height = H + 'px';
    cells = new Array(COLS * ROWS).fill(false);
    touched = 0;
    if (!cleared) { paint(); }
    report();
  }

  function mark(x, y) {
    var c = Math.min(COLS - 1, Math.max(0, Math.floor(x / W * COLS)));
    var r = Math.min(ROWS - 1, Math.max(0, Math.floor(y / H * ROWS)));
    var k = r * COLS + c;
    if (!cells[k]) { cells[k] = true; touched++; }
  }

  function dab(x, y) {
    ctx.globalCompositeOperation = 'destination-out';
    ctx.beginPath();
    ctx.arc(x, y, 26, 0, Math.PI * 2);
    ctx.fill();
    ctx.globalCompositeOperation = 'source-over';
    mark(x, y);
  }

  function stroke(a, b) {
    var dx = b.x - a.x, dy = b.y - a.y;
    var steps = Math.max(1, Math.ceil(Math.sqrt(dx * dx + dy * dy) / 6));
    for (var i = 0; i <= steps; i++) {
      var t = i / steps;
      dab(a.x + dx * t + rnd(-1.5, 1.5), a.y + dy * t + rnd(-1.5, 1.5));
    }
  }

  function pos(e) {
    var r = cv.getBoundingClientRect();
    return { x: e.clientX - r.left, y: e.clientY - r.top };
  }

  function overBin(x, y) {
    if (!bin) { return false; }
    var r = bin.getBoundingClientRect();
    return x >= r.left - 8 && x <= r.right + 8 && y >= r.top - 8 && y <= r.bottom + 8;
  }

  function report() {
    var pct = Math.min(100, Math.round(touched / (COLS * ROWS) * 100));
    pctEl.textContent = String(pct);
    if (cleared) { return; }
    if (pct >= 70 && trashLeft === 0) { reveal(false); return; }
    hint.textContent = trashLeft > 0
      ? 'Выбросьте мусор в корзину и протрите окно, чтобы прочитать.'
      : 'Протрите окно пальцем или мышкой, чтобы прочитать.';
  }

  function reveal(byButton) {
    if (cleared) { return; }
    cleared = true;
    cv.classList.add('is-gone');
    glass.querySelectorAll('.mt-trash').forEach(function (el) {
      el.classList.add('is-out');
      setTimeout(function () { el.remove(); }, 400);
    });
    trashLeft = 0;
    if (bin) { bin.classList.add('is-gone'); }
    hint.textContent = 'Вот теперь видно всё.';
    pctEl.textContent = '100';
    status.textContent = byButton ? 'Окно протёрто целиком, мусор вынесен.' : 'Чисто! Теперь всё видно.';
    all.disabled = true;
  }

  function discard(el) {
    if (el.dataset.gone) { return; }
    el.dataset.gone = '1';
    el.classList.add('is-out');
    trashLeft--;
    bin.classList.add('is-hit');
    setTimeout(function () { bin.classList.remove('is-hit'); el.remove(); }, 260);
    status.textContent = trashLeft > 0
      ? 'Мусор в корзине. Осталось: ' + trashLeft + '.'
      : 'Двор чист. Теперь протрите стекло.';
    report();
  }

  function makeTrash(i) {
    var kind = i % 3;
    var spot = SPOTS[i];
    var el = document.createElement('button');
    el.type = 'button';
    el.className = 'mt-trash';
    el.innerHTML = SHAPES[kind];
    el.setAttribute('aria-label', 'Кусок мусора: перетащите его в корзину или нажмите, чтобы выбросить');
    el.style.left = (spot[0] + rnd(-3, 3)) + '%';
    el.style.top = (spot[1] + rnd(-3, 3)) + '%';
    el.style.setProperty('--rot', rnd(-25, 25).toFixed(1) + 'deg');
    glass.appendChild(el);

    el.addEventListener('pointerdown', function (e) {
      if (cleared || dragging) { return; }
      dragging = el; dragMoved = false;
      sx = e.clientX; sy = e.clientY;
      try { el.setPointerCapture(e.pointerId); } catch (err) { /* не критично */ }
      el.classList.add('is-drag');
      e.preventDefault();
    });
    el.addEventListener('pointermove', function (e) {
      if (dragging !== el) { return; }
      var dx = e.clientX - sx, dy = e.clientY - sy;
      if (Math.abs(dx) + Math.abs(dy) > 6) { dragMoved = true; }
      el.style.translate = dx + 'px ' + dy + 'px';
      bin.classList.toggle('is-hot', overBin(e.clientX, e.clientY));
    });
    function drop(e) {
      if (dragging !== el) { return; }
      dragging = null;
      el.classList.remove('is-drag');
      bin.classList.remove('is-hot');
      if (cleared) { return; }
      if (!dragMoved || overBin(e.clientX, e.clientY)) {
        discard(el);
      } else {
        el.style.translate = '';
      }
    }
    el.addEventListener('pointerup', drop);
    el.addEventListener('pointercancel', function () {
      if (dragging !== el) { return; }
      dragging = null;
      el.classList.remove('is-drag');
      el.style.translate = '';
      bin.classList.remove('is-hot');
    });
    // Клавиатура: Enter или пробел выбрасывают кусок (у такого клика detail = 0)
    el.addEventListener('click', function (e) {
      if (e.detail === 0) { discard(el); }
    });
  }

  function initTrash() {
    bin = document.createElement('div');
    bin.className = 'mt-bin';
    bin.setAttribute('aria-hidden', 'true');
    bin.innerHTML = BIN;
    glass.appendChild(bin);
    for (var i = 0; i < SPOTS.length; i++) { makeTrash(i); }
    trashLeft = SPOTS.length;
    report();
  }

  function startDrawing(e) {
    if (cleared) { return; }
    drawing = true;
    try { cv.setPointerCapture(e.pointerId); } catch (err) { /* не критично */ }
    last = pos(e);
    stroke(last, last);
    report();
  }
  cv.addEventListener('pointerdown', startDrawing);
  cv.addEventListener('pointermove', function (e) {
    if (!drawing || cleared) { return; }
    var p = pos(e);
    stroke(last, p);
    last = p;
    report();
  });
  function end() { drawing = false; last = null; }
  cv.addEventListener('pointerup', end);
  cv.addEventListener('pointercancel', end);

  all.addEventListener('click', function () { reveal(true); });

  var timer = null;
  window.addEventListener('resize', function () {
    clearTimeout(timer);
    timer = setTimeout(layout, 150);
  });
  window.addEventListener('load', layout);
  initTrash();
  layout();
})();
</script>
</body>
</html>

HTML;
    return strtr($html, ['__FONTS__' => $fonts, '__CONTACT__' => $contact]);
}
