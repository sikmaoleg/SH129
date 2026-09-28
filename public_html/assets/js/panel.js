/* Панели: тема, меню, поиск Ctrl+K, выдвижные формы, отметки участия, графики и мелкие помощники страниц */
(function () {
  'use strict';
  var root = document.documentElement;
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var plural = function (n, a, b, c) { n = Math.abs(n); var m10 = n % 10, m100 = n % 100; if (m10 === 1 && m100 !== 11) return a; if (m10 >= 2 && m10 <= 4 && (m100 < 12 || m100 > 14)) return b; return c; };

  /* ---------- Тема ---------- */
  var themeBtn = $('#themeBtn');
  if (themeBtn) themeBtn.addEventListener('click', function () {
    var dark = root.dataset.theme ? root.dataset.theme === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
    root.dataset.theme = dark ? 'light' : 'dark';
    try { localStorage.setItem('mg-theme', root.dataset.theme); } catch (e) {}
  });

  /* ---------- Боковое меню на телефоне ---------- */
  var side = $('#side'), scrim = $('#scrim'), menuBtn = $('#menuBtn');
  function openSide() { side.classList.add('open'); scrim.hidden = false; menuBtn.setAttribute('aria-expanded', 'true'); }
  function closeSide() { side.classList.remove('open'); scrim.hidden = true; if (menuBtn) menuBtn.setAttribute('aria-expanded', 'false'); }
  if (menuBtn && side) {
    menuBtn.addEventListener('click', openSide);
    $('#sideClose').addEventListener('click', closeSide);
    scrim.addEventListener('click', closeSide);
  }

  /* ---------- Меню «Создать» ---------- */
  var createBtn = $('#createBtn'), createMenu = $('#createMenu');
  if (createBtn && createMenu) {
    createBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      createMenu.hidden = !createMenu.hidden;
      createBtn.setAttribute('aria-expanded', String(!createMenu.hidden));
      if (!createMenu.hidden) createMenu.querySelector('a').focus();
    });
    document.addEventListener('click', function (e) { if (!createMenu.hidden && !createMenu.contains(e.target)) { createMenu.hidden = true; createBtn.setAttribute('aria-expanded', 'false'); } });
  }

  /* ---------- Уведомление внизу экрана ---------- */
  function toast(msg) {
    var box = $('#toasts'); if (!box) return;
    var t = document.createElement('div');
    t.className = 'toast';
    t.textContent = msg;
    box.appendChild(t);
    setTimeout(function () { t.remove(); }, 4200);
  }

  /* ---------- Подтверждение опасных действий ---------- */
  document.addEventListener('click', function (ev) {
    var el = ev.target.closest('[data-confirm]');
    if (el && !window.confirm(el.getAttribute('data-confirm'))) { ev.preventDefault(); ev.stopImmediatePropagation(); }
  }, true);

  /* ---------- Копирование в буфер ---------- */
  document.addEventListener('click', function (ev) {
    var el = ev.target.closest('[data-copy]');
    if (!el) return;
    var text = el.getAttribute('data-copy');
    var ok = function () { toast('Скопировано'); };
    if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(text).then(ok).catch(function () { window.prompt('Скопируйте:', text); });
    else window.prompt('Скопируйте:', text);
  });

  /* ---------- Выдвижные панели с формами ---------- */
  var drawerOpener = null;
  function openDrawer(d) {
    if (!d) return;
    drawerOpener = document.activeElement;
    d.hidden = false;
    document.body.style.overflow = 'hidden';
    var f = d.querySelector('input:not([type=hidden]),select,textarea,[data-close-drawer]');
    if (f) f.focus();
  }
  function closeDrawer(d) {
    d.hidden = true;
    document.body.style.overflow = '';
    if (drawerOpener && drawerOpener.focus) drawerOpener.focus();
    if (d.hasAttribute('data-close-url')) history.replaceState(null, '', d.getAttribute('data-close-url'));
  }
  document.addEventListener('click', function (e) {
    var op = e.target.closest('[data-drawer-open]');
    if (op) { e.preventDefault(); openDrawer(document.getElementById(op.getAttribute('data-drawer-open'))); return; }
    var cl = e.target.closest('[data-close-drawer]');
    if (cl) { e.preventDefault(); closeDrawer(cl.closest('.drawer')); return; }
    if (e.target.classList && e.target.classList.contains('drawer')) closeDrawer(e.target);
  });
  $$('.drawer:not([hidden])').forEach(function (d) { document.body.style.overflow = 'hidden'; });

  /* ---------- Поиск по панели: Ctrl+K или «/» ---------- */
  var pal = $('#palette'), palIn = $('#paletteInput'), palList = $('#paletteList');
  var palData = { items: [], search: '' };
  try { palData = JSON.parse($('#paletteData').textContent); } catch (e) {}
  var palItems = [], palAt = 0;
  function palFilter(q) {
    q = q.trim().toLowerCase();
    var list = palData.items.filter(function (x) { return !q || (x.t + ' ' + x.s).toLowerCase().indexOf(q) !== -1; });
    if (!q) list = list.filter(function (x) { return x.g === 'Действия'; }).concat(list.filter(function (x) { return x.g !== 'Действия'; }).slice(0, 7));
    list = list.slice(0, 13);
    if (q && palData.search) list.push({ g: 'Волонтёры', t: 'Найти «' + q + '» среди волонтёров', s: '', i: palData.searchIcon, u: palData.search + '?q=' + encodeURIComponent(q) });
    return list.slice(0, 14);
  }
  function palDraw() {
    palItems = palFilter(palIn.value);
    palAt = Math.min(palAt, Math.max(0, palItems.length - 1));
    palList.textContent = '';
    var g = '';
    if (!palItems.length) { var em = document.createElement('p'); em.textContent = 'Ничего не нашлось'; palList.appendChild(em); return; }
    palItems.forEach(function (x, i) {
      if (x.g !== g) { var h = document.createElement('p'); h.textContent = x.g; palList.appendChild(h); g = x.g; }
      var a = document.createElement('a');
      a.href = x.u; a.setAttribute('role', 'option'); a.dataset.pi = i;
      a.className = i === palAt ? 'on' : '';
      a.setAttribute('aria-selected', i === palAt ? 'true' : 'false');
      a.insertAdjacentHTML('afterbegin', x.i || '');
      var t = document.createElement('span'); t.textContent = x.t; a.appendChild(t);
      if (x.s) { var s = document.createElement('small'); s.textContent = x.s; a.appendChild(s); }
      palList.appendChild(a);
    });
  }
  function openPal() { pal.hidden = false; palIn.value = ''; palAt = 0; palDraw(); palIn.focus(); }
  function closePal() { pal.hidden = true; }
  if (pal) {
    $('#searchBtn').addEventListener('click', openPal);
    palIn.addEventListener('input', function () { palAt = 0; palDraw(); });
    pal.addEventListener('click', function (e) { if (e.target === pal) closePal(); });
  }
  document.addEventListener('keydown', function (e) {
    var typing = /INPUT|TEXTAREA|SELECT/.test((document.activeElement || {}).tagName || '');
    if (pal && ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k' || (e.key === '/' && !typing))) { e.preventDefault(); openPal(); return; }
    if (pal && !pal.hidden) {
      if (e.key === 'Escape') closePal();
      if (e.key === 'ArrowDown') { e.preventDefault(); palAt = Math.min(palItems.length - 1, palAt + 1); palDraw(); }
      if (e.key === 'ArrowUp') { e.preventDefault(); palAt = Math.max(0, palAt - 1); palDraw(); }
      if (e.key === 'Enter' && palItems[palAt]) { e.preventDefault(); window.location.href = palItems[palAt].u; }
      return;
    }
    if (e.key === 'Escape') {
      var d = $('.drawer:not([hidden])');
      if (d) closeDrawer(d); else if (side && side.classList.contains('open')) closeSide();
      if (createMenu) createMenu.hidden = true;
    }
  });

  /* ---------- Кнопки-подсказки заполняют поле (причины отказа, начислений) ---------- */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-fill]');
    if (!b) return;
    var target = document.getElementById(b.getAttribute('data-fill-target'));
    if (!target) return;
    target.value = b.getAttribute('data-fill');
    target.dispatchEvent(new Event('input', { bubbles: true }));
    $$('[data-fill-target="' + b.getAttribute('data-fill-target') + '"]').forEach(function (x) { x.classList.toggle('on', x === b); });
  });

  /* ---------- Отметки участия: переключатели и итог внизу ---------- */
  var attForm = $('#attForm');
  if (attForm) {
    var reward = +attForm.getAttribute('data-reward') || 0;
    var sum = $('#attSum'), stats = $('#attStats');
    var updAtt = function () {
      var rows = $$('.att-row', attForm), c = { attended: 0, no_show: 0, cancelled: 0, registered: 0 };
      rows.forEach(function (r) { var ch = r.querySelector('input:checked'); c[ch ? ch.value : 'registered']++; });
      if (stats) {
        stats.querySelector('[data-c="attended"]').textContent = c.attended;
        stats.querySelector('[data-c="no_show"]').textContent = c.no_show;
        stats.querySelector('[data-c="registered"]').textContent = c.registered;
      }
      if (sum) sum.textContent = c.attended
        ? 'Участие примем у ' + c.attended + ' ' + plural(c.attended, 'человека', 'человек', 'человек') + (reward ? ', каждому +' + reward + ' ' + plural(reward, 'балл', 'балла', 'баллов') : '') + (c.registered ? '. Без отметки: ' + c.registered : '')
        : 'Отметь, кто пришёл. Баллы начислятся при сохранении.';
    };
    attForm.addEventListener('change', updAtt);
    $$('[data-bulk-status]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var v = btn.getAttribute('data-bulk-status');
        $$('input[type=radio][value="' + v + '"]', attForm).forEach(function (r) { r.checked = true; });
        updAtt();
      });
    });
    updAtt();
  }

  /* ---------- Мероприятия: повтор, список будущих дат ---------- */
  var rep = $('#repeat_enable');
  if (rep) {
    var MON = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
    var updRep = function () {
      var box = $('#repeatFields'), note = $('#repeatNote'), date = $('#date');
      box.hidden = !rep.checked; note.hidden = !rep.checked;
      if (!rep.checked || !date.value) return;
      var freq = $('#repeat_freq').value, n = Math.max(2, Math.min(24, +$('#repeat_count').value || 2));
      var base = new Date(date.value + 'T12:00:00'), out = [];
      for (var i = 0; i < n; i++) {
        var x = new Date(base);
        if (freq === 'monthly') x.setMonth(x.getMonth() + i); else x.setDate(x.getDate() + (freq === 'biweekly' ? 14 : 7) * i);
        out.push(x.getDate() + ' ' + MON[x.getMonth()]);
      }
      note.textContent = 'Будет создано ' + out.length + ' ' + plural(out.length, 'мероприятие', 'мероприятия', 'мероприятий') + ': ' + out.join(', ') + '.';
    };
    ['#repeat_enable', '#repeat_freq', '#repeat_count', '#date'].forEach(function (s) { var el = $(s); if (el) { el.addEventListener('input', updRep); el.addEventListener('change', updRep); } });
    updRep();
  }

  /* ---------- Быстрый фильтр строк (волонтёры, списки) ---------- */
  $$('[data-filter-input]').forEach(function (inp) {
    var scope = document.getElementById(inp.getAttribute('data-filter-input'));
    if (!scope) return;
    var rows = $$('[data-filter-text]', scope), empty = document.getElementById(inp.getAttribute('data-filter-empty'));
    var run = function () {
      var q = inp.value.trim().toLowerCase(), role = scope.getAttribute('data-role') || 'all', shown = 0;
      rows.forEach(function (r) {
        var ok = (!q || r.getAttribute('data-filter-text').indexOf(q) !== -1) && (role === 'all' || (' ' + r.getAttribute('data-role') + ' ').indexOf(' ' + role + ' ') !== -1);
        r.hidden = !ok; if (ok) shown++;
      });
      if (empty) empty.hidden = shown > 0;
    };
    inp.addEventListener('input', run);
    $$('[data-role-filter]').forEach(function (b) {
      b.addEventListener('click', function () {
        scope.setAttribute('data-role', b.getAttribute('data-role-filter'));
        $$('[data-role-filter]').forEach(function (x) { x.classList.toggle('on', x === b); });
        run();
      });
    });
    run();
  });

  /* ---------- Кликабельные строки таблиц ---------- */
  document.addEventListener('click', function (e) {
    var tr = e.target.closest('tr[data-href]');
    if (!tr || e.target.closest('a,button,input,select,label,form')) return;
    window.location.href = tr.getAttribute('data-href');
  });

  /* ---------- Начисление баллов: быстрые суммы, выбор людей, итог ---------- */
  var pForm = $('#pointsForm');
  if (pForm) {
    var pts = $('#points'), reason = $('#reason'), summary = $('#pointsSummary'), go = $('#pointsGo');
    var updPts = function () {
      var picked = $$('.check-list input:checked', pForm), v = +pts.value || 0;
      $$('.check-list-item', pForm).forEach(function (l) { l.classList.toggle('is-checked', l.querySelector('input').checked); });
      $$('[data-amount]', pForm).forEach(function (b) { b.classList.toggle('on', +b.getAttribute('data-amount') === v); });
      var names = picked.map(function (c) { return c.getAttribute('data-name'); });
      $('#pickedCount').textContent = picked.length ? 'Выбрано: ' + picked.length : '';
      summary.textContent = picked.length && v
        ? (v > 0 ? 'Начислим +' : 'Спишем −') + Math.abs(v) + ' ' + plural(v, 'балл', 'балла', 'баллов') + ' ' + (picked.length === 1 ? 'волонтёру' : picked.length + ' ' + plural(picked.length, 'волонтёру', 'волонтёрам', 'волонтёрам')) + ': ' + names.slice(0, 6).join(', ') + (names.length > 6 ? ' и ещё ' + (names.length - 6) : '')
        : 'Выбери, кому и сколько начислить.';
      go.disabled = !(picked.length && v && reason.value.trim());
    };
    pForm.addEventListener('change', updPts);
    pForm.addEventListener('input', updPts);
    $$('[data-amount]', pForm).forEach(function (b) { b.addEventListener('click', function () { pts.value = b.getAttribute('data-amount'); updPts(); }); });
    updPts();
  }

  /* ---------- Доска почёта: превью как на сайте ---------- */
  var hSel = $('#honor_user'), hNote = $('#honor_note');
  if (hSel && hNote) {
    var updHonor = function () {
      var opt = hSel.options[hSel.selectedIndex], on = +hSel.value > 0;
      $('#honorShown').hidden = !on; $('#honorHidden').hidden = on;
      if (on) {
        $('#honorName').textContent = opt.getAttribute('data-name');
        $('#honorIni').textContent = opt.getAttribute('data-ini');
        var ava = $('#honorAva'), src = opt.getAttribute('data-ava');
        ava.hidden = !src; if (src) ava.src = src;
        $('#honorIni').hidden = !!src;
      }
      $('#honorText').textContent = hNote.value;
      $('#honorCount').textContent = hNote.value.length + ' из ' + (hNote.maxLength > 0 ? hNote.maxLength : 255) + ' символов';
    };
    hSel.addEventListener('change', updHonor); hSel.addEventListener('input', updHonor); hNote.addEventListener('input', updHonor); updHonor();
  }

  /* ---------- Новость: заголовок в превью карточки ---------- */
  var nTitle = $('#title'), pvT = $('#pvT');
  if (nTitle && pvT) nTitle.addEventListener('input', function () { pvT.textContent = nTitle.value.trim() || 'Без заголовка'; });

  /* ---------- Фото на главной: имя выбранного файла и подсветка зоны ---------- */
  var heroFile = $('#heroFile'), heroName = $('#heroFileName'), drop = $('#drop');
  if (heroFile && heroName) heroFile.addEventListener('change', function () { heroName.textContent = heroFile.files && heroFile.files[0] ? heroFile.files[0].name : ''; });
  if (drop) {
    ['dragenter', 'dragover'].forEach(function (t) { drop.addEventListener(t, function () { drop.classList.add('over'); }); });
    ['dragleave', 'drop'].forEach(function (t) { drop.addEventListener(t, function () { drop.classList.remove('over'); }); });
  }

  /* ---------- Фото на главной: перетаскивание для порядка ---------- */
  var slides = $('#slides');
  if (slides && slides.getAttribute('data-reorder-url')) {
    var dragEl = null;
    $$('.slide', slides).forEach(function (s) {
      s.addEventListener('dragstart', function () { dragEl = s; s.classList.add('dragging'); });
      s.addEventListener('dragend', function () { s.classList.remove('dragging'); $$('.slide', slides).forEach(function (x) { x.classList.remove('over'); }); });
      s.addEventListener('dragover', function (e) { e.preventDefault(); if (s !== dragEl) s.classList.add('over'); });
      s.addEventListener('dragleave', function () { s.classList.remove('over'); });
      s.addEventListener('drop', function (e) {
        e.preventDefault();
        if (!dragEl || dragEl === s) return;
        var all = $$('.slide', slides);
        if (all.indexOf(dragEl) < all.indexOf(s)) s.after(dragEl); else s.before(dragEl);
        $$('.slide', slides).forEach(function (x, i) { x.querySelector('.slide-n').textContent = i + 1; });
        var body = new FormData();
        body.append('_csrf', slides.getAttribute('data-csrf'));
        body.append('action', 'reorder');
        $$('.slide', slides).forEach(function (x) { body.append('order[]', x.getAttribute('data-id')); });
        fetch(slides.getAttribute('data-reorder-url'), { method: 'POST', body: body, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
          .then(function (r) { toast(r.ok ? 'Порядок фото сохранён' : 'Не удалось сохранить порядок, обнови страницу'); })
          .catch(function () { toast('Не удалось сохранить порядок, обнови страницу'); });
      });
    });
  }

  /* ---------- Настройки: уровни таблицей и напоминание о несохранённом ---------- */
  var setForm = $('#settingsForm');
  if (setForm) {
    var dirty = $('#dirty');
    var syncLevels = function () {
      var rows = $$('.lv-row:not(.lv-headrow)', setForm);
      $('#level_thresholds').value = rows.map(function (r) { return r.querySelector('[data-lv="min"]').value.trim(); }).join(',');
      $('#level_names').value = rows.map(function (r) { return r.querySelector('[data-lv="name"]').value.trim(); }).join(',');
    };
    $$('[data-stat-toggle]', setForm).forEach(function (t) {
      var inp = document.getElementById(t.getAttribute('data-stat-toggle'));
      var upd = function () { inp.disabled = !t.checked; if (!t.checked) inp.value = '0'; else if (inp.value === '0') inp.value = inp.getAttribute('data-last') || ''; };
      t.addEventListener('change', upd);
    });
    var lvBox = $('#lvRows');
    var addLv = $('[data-lv-add]'), delLv = $('[data-lv-del]');
    var lvButtons = function () { var n = $$('.lv-row:not(.lv-headrow)', lvBox).length; if (delLv) delLv.disabled = n <= 1; };
    if (addLv) addLv.addEventListener('click', function () {
      var rows = $$('.lv-row:not(.lv-headrow)', lvBox), last = rows[rows.length - 1], row = last.cloneNode(true), n = rows.length + 1;
      row.querySelector('.lv-n').textContent = n;
      var nm = row.querySelector('[data-lv="name"]'), mn = row.querySelector('[data-lv="min"]');
      nm.value = ''; nm.setAttribute('aria-label', 'Название уровня ' + n);
      mn.readOnly = false; mn.value = (+last.querySelector('[data-lv="min"]').value || 0) + 100; mn.setAttribute('aria-label', 'Порог уровня ' + n);
      lvBox.appendChild(row); nm.focus(); syncLevels(); lvButtons(); if (dirty) dirty.hidden = false;
    });
    if (delLv) delLv.addEventListener('click', function () {
      var rows = $$('.lv-row:not(.lv-headrow)', lvBox); if (rows.length > 1) rows[rows.length - 1].remove();
      syncLevels(); lvButtons(); if (dirty) dirty.hidden = false;
    });
    if (lvBox) lvButtons();
    var reg = $('#registration_toggle');
    if (reg) reg.addEventListener('change', function () { $('#registration_open').value = reg.checked ? '1' : '0'; });
    setForm.addEventListener('input', function () { if (dirty) dirty.hidden = false; syncLevels(); });
    setForm.addEventListener('change', function () { if (dirty) dirty.hidden = false; syncLevels(); });
    setForm.addEventListener('submit', function () {
      syncLevels();
      $$('[data-stat-toggle]', setForm).forEach(function (t) { var inp = document.getElementById(t.getAttribute('data-stat-toggle')); inp.disabled = false; if (!t.checked) inp.value = '0'; });
      if (dirty) dirty.hidden = true;
    });
    var navLinks = $$('.set-nav a');
    if ('IntersectionObserver' in window && navLinks.length) {
      var io = new IntersectionObserver(function (es) {
        es.forEach(function (en) { if (en.isIntersecting) navLinks.forEach(function (x) { x.classList.toggle('on', x.getAttribute('href') === '#' + en.target.id); }); });
      }, { rootMargin: '-30% 0px -60% 0px' });
      $$('.set-sec', setForm).forEach(function (s) { io.observe(s); });
    }
    window.addEventListener('beforeunload', function (e) { if (dirty && !dirty.hidden) { e.preventDefault(); e.returnValue = ''; } });
  }

  /* ---------- Показать и скрыть секрет ---------- */
  $$('[data-reveal-input]').forEach(function (b) {
    b.addEventListener('click', function () { var i = document.getElementById(b.getAttribute('data-reveal-input')); i.type = i.type === 'password' ? 'text' : 'password'; });
  });

  /* ---------- Графики аналитики: столбцы и линия с подсказкой при наведении ---------- */
  var tip = $('#tip');
  function showTip(x, y, value, label) {
    tip.textContent = '';
    var b = document.createElement('b'); b.textContent = value;
    var s = document.createElement('span'); s.textContent = label;
    tip.append(b, s); tip.hidden = false;
    var w = tip.offsetWidth, h = tip.offsetHeight;
    tip.style.left = Math.min(window.innerWidth - w - 8, Math.max(8, x - w / 2)) + 'px';
    tip.style.top = Math.max(8, y - h - 14) + 'px';
  }
  function hideTip() { if (tip) tip.hidden = true; }
  var niceMax = function (m) { var st = [2, 4, 5, 10, 20, 25, 50, 100]; for (var i = 0; i < st.length; i++) if (m <= st[i]) return st[i]; return Math.ceil(m / 100) * 100; };
  var niceStep = function (m) { var st = [1, 2, 5, 10, 20, 25, 50, 100]; for (var i = 0; i < st.length; i++) if (m / st[i] <= 5) return st[i]; return Math.ceil(m / 5); };
  var NS = 'http://www.w3.org/2000/svg';
  function el(name, attrs, text) { var n = document.createElementNS(NS, name); for (var k in attrs) n.setAttribute(k, attrs[k]); if (text != null) n.textContent = text; return n; }
  $$('[data-chart]').forEach(function (box) {
    var cfg; try { cfg = JSON.parse(box.getAttribute('data-chart')); } catch (e) { return; }
    var labels = cfg.labels, values = cfg.values, kind = cfg.kind, unit = cfg.unit || ['', '', ''];
    var fmt = function (v) { return v + ' ' + plural(v, unit[0], unit[1], unit[2]); };
    var W = 600, H = 240, L = 34, R = 18, T = 22, B = 30, pw = W - L - R, ph = H - T - B;
    var max = niceMax(Math.max.apply(null, values.concat([1]))), step = niceStep(max);
    var svg = el('svg', { viewBox: '0 0 ' + W + ' ' + H, role: 'img', 'aria-label': cfg.title || '' });
    for (var v = 0; v <= max; v += step) {
      var y = T + ph - ph * v / max;
      svg.appendChild(el('line', { class: 'gl', x1: L, x2: W - R, y1: y, y2: y }));
      svg.appendChild(el('text', { class: 'ax', x: L - 8, y: y + 4, 'text-anchor': 'end' }, v));
    }
    var X, Y = function (val) { return T + ph - ph * val / max; };
    var hits = [];
    if (kind === 'bar') {
      var band = pw / values.length, bw = Math.min(24, band * 0.5);
      values.forEach(function (val, i) {
        var x = L + band * i + (band - bw) / 2, h = ph * val / max, y = T + ph - h, r = Math.min(4, h);
        if (h > 0) svg.appendChild(el('path', { class: 'bar', d: 'M' + x + ',' + (T + ph) + ' V' + (y + r) + ' Q' + x + ',' + y + ' ' + (x + r) + ',' + y + ' H' + (x + bw - r) + ' Q' + (x + bw) + ',' + y + ' ' + (x + bw) + ',' + (y + r) + ' V' + (T + ph) + ' Z' }));
        svg.appendChild(el('text', { class: 'vl', x: x + bw / 2, y: y - 7, 'text-anchor': 'middle' }, val));
        svg.appendChild(el('text', { class: 'ax', x: x + bw / 2, y: H - 8, 'text-anchor': 'middle' }, labels[i]));
        hits.push(el('rect', { class: 'hit', x: L + band * i, y: T, width: band, height: ph + B, tabindex: 0, 'data-i': i, 'aria-label': labels[i] + ': ' + fmt(val) }));
      });
    } else {
      X = function (i) { return L + pw * i / Math.max(1, values.length - 1); };
      var pts = values.map(function (val, i) { return X(i) + ',' + Y(val); });
      svg.appendChild(el('path', { class: 'area', d: 'M' + X(0) + ',' + (T + ph) + ' L' + pts.join(' L') + ' L' + X(values.length - 1) + ',' + (T + ph) + ' Z' }));
      svg.appendChild(el('polyline', { class: 'ln', points: pts.join(' ') }));
      var last = values.length - 1;
      svg.appendChild(el('circle', { class: 'mk', cx: X(last), cy: Y(values[last]), r: 5 }));
      svg.appendChild(el('text', { class: 'vl', x: X(last), y: Y(values[last]) - 12, 'text-anchor': 'middle' }, values[last]));
      labels.forEach(function (l, i) { svg.appendChild(el('text', { class: 'ax', x: X(i), y: H - 8, 'text-anchor': 'middle' }, l)); });
      var seg = pw / Math.max(1, values.length - 1);
      values.forEach(function (val, i) { hits.push(el('rect', { class: 'hit', x: X(i) - seg / 2, y: T, width: seg, height: ph + B, tabindex: 0, 'data-i': i, 'aria-label': labels[i] + ': ' + fmt(val) })); });
      var xh = el('line', { class: 'xh', x1: 0, x2: 0, y1: T, y2: T + ph, style: 'display:none' }), mk = el('circle', { class: 'mk', r: 5, style: 'display:none' });
      svg.appendChild(xh); svg.appendChild(mk);
    }
    svg.appendChild(el('line', { class: 'gl', x1: L, x2: W - R, y1: T + ph, y2: T + ph, style: 'stroke:var(--ink-3)' }));
    hits.forEach(function (h) { svg.appendChild(h); });
    box.appendChild(svg);
    var pick = function (hEl) {
      var i = +hEl.getAttribute('data-i'), sr = svg.getBoundingClientRect(), sx = sr.width / W;
      if (kind === 'bar') {
        $$('.bar', svg).forEach(function (b, j) { b.classList.toggle('dim', j !== i); });
        var rr = hEl.getBoundingClientRect();
        showTip(rr.left + rr.width / 2, sr.top + Y(values[i]) * sx, fmt(values[i]), cfg.full[i]);
      } else {
        var cx = X(i); xh.style.display = ''; xh.setAttribute('x1', cx); xh.setAttribute('x2', cx);
        mk.style.display = ''; mk.setAttribute('cx', cx); mk.setAttribute('cy', Y(values[i]));
        showTip(sr.left + cx * sx, sr.top + Y(values[i]) * sx, fmt(values[i]), cfg.full[i]);
      }
    };
    var clear = function () { hideTip(); $$('.bar', svg).forEach(function (b) { b.classList.remove('dim'); }); if (xh) { xh.style.display = 'none'; mk.style.display = 'none'; } };
    hits.forEach(function (h) { h.addEventListener('pointerenter', function () { pick(h); }); h.addEventListener('focus', function () { pick(h); }); h.addEventListener('pointerleave', clear); h.addEventListener('blur', clear); });
  });
  $$('[data-toggle-table]').forEach(function (b) {
    b.addEventListener('click', function () {
      var t = document.getElementById(b.getAttribute('data-toggle-table'));
      t.hidden = !t.hidden; b.textContent = t.hidden ? 'Таблицей' : 'Скрыть таблицу';
    });
  });
  $$('[data-hb]').forEach(function (b) {
    var s = function () { var r = b.getBoundingClientRect(); showTip(r.right, r.top, b.getAttribute('data-hb'), b.getAttribute('data-hbl')); };
    b.addEventListener('pointerenter', s); b.addEventListener('focus', s); b.addEventListener('pointerleave', hideTip); b.addEventListener('blur', hideTip);
  });
})();
