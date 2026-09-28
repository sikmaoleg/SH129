/* Публичная часть сайта: тема, меню, анимации главной, лента новостей, лайтбокс, анкета */
(function () {
  'use strict';
  var root = document.documentElement;
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  /* Тема: светлая / тёмная, выбор запоминается */
  var themeBtn = $('#themeBtn');
  if (themeBtn) {
    themeBtn.addEventListener('click', function () {
      var dark = root.dataset.theme ? root.dataset.theme === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
      root.dataset.theme = dark ? 'light' : 'dark';
      try { localStorage.setItem('mg-theme', root.dataset.theme); } catch (e) {}
    });
  }

  /* Мобильное меню */
  var menuBtn = $('#menuBtn');
  function setMenu(open) {
    root.classList.toggle('menu-open', open);
    if (menuBtn) {
      menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
      menuBtn.setAttribute('aria-label', open ? 'Закрыть меню' : 'Открыть меню');
    }
  }
  if (menuBtn) {
    menuBtn.addEventListener('click', function () { setMenu(!root.classList.contains('menu-open')); });
    $$('#menu a').forEach(function (a) { a.addEventListener('click', function () { setMenu(false); }); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && root.classList.contains('menu-open')) { setMenu(false); menuBtn.focus(); } });
  }

  /* На главной кнопка «Стать волонтёром» в шапке появляется, когда главная кнопка ушла из вида */
  var nav = $('#nav'), heroCta = $('#heroCta');
  if (nav && heroCta && 'IntersectionObserver' in window) {
    new IntersectionObserver(function (entries) {
      var e = entries[0];
      nav.classList.toggle('show-cta', !e.isIntersecting && e.boundingClientRect.top < 0);
    }).observe(heroCta);
  }

  /* Смена фото на первом экране: только пока экран виден */
  var frame = $('#heroFrame');
  if (frame && 'IntersectionObserver' in window) {
    var slides = $$('img', frame), current = 0, timer = null;
    var nextSlide = function () {
      var prev = slides[current];
      current = (current + 1) % slides.length;
      slides.forEach(function (s) { s.classList.remove('was-on'); });
      prev.classList.remove('is-on');
      prev.classList.add('was-on');
      slides[current].classList.add('is-on');
    };
    if (slides.length > 1) {
      new IntersectionObserver(function (entries) {
        var visible = entries[0].isIntersecting;
        if (visible && !timer && !reduce.matches) timer = setInterval(nextSlide, 4800);
        if (!visible && timer) { clearInterval(timer); timer = null; }
      }).observe(frame);
    }
  }

  /* Появление блоков при прокрутке и счётчики */
  function countUp(el) {
    var target = +el.dataset.count, textNode = el.firstChild;
    if (reduce.matches || !textNode || !target) return;
    var start = performance.now(), dur = 1500;
    (function tick(now) {
      var p = Math.min(1, (now - start) / dur), eased = 1 - Math.pow(1 - p, 3);
      textNode.nodeValue = Math.round(target * eased).toLocaleString('ru-RU');
      if (p < 1) requestAnimationFrame(tick);
    })(start);
  }
  var reveals = $$('[data-reveal]');
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (!e.isIntersecting) return;
        e.target.classList.add('in');
        $$('[data-count]', e.target).forEach(countUp);
        io.unobserve(e.target);
      });
    }, { threshold: 0.15, rootMargin: '0px 0px -6% 0px' });
    reveals.forEach(function (el) { io.observe(el); });
  } else {
    reveals.forEach(function (el) { el.classList.add('in'); });
  }

  /* Направления: раскрытие по наведению, фокусу и нажатию */
  var dirs = $$('.dir');
  var canHover = window.matchMedia('(hover: hover) and (min-width: 1024px)');
  var openDir = function (d) { dirs.forEach(function (x) { x.classList.toggle('is-open', x === d); x.setAttribute('aria-expanded', x === d ? 'true' : 'false'); }); };
  dirs.forEach(function (d) {
    d.addEventListener('mouseenter', function () { if (canHover.matches) openDir(d); });
    d.addEventListener('focus', function () { openDir(d); });
    d.addEventListener('click', function () { openDir(d); });
  });

  /* Лента новостей: стрелки, крайние положения выключают кнопку */
  var rail = $('#newsRail');
  if (rail) {
    var railBtns = $$('[data-rail]');
    railBtns.forEach(function (b) {
      b.addEventListener('click', function () {
        var card = $('.n-card:not(.n-lead)', rail);
        var step = (card ? card.getBoundingClientRect().width : 300) + 20;
        rail.scrollBy({ left: step * +b.dataset.rail, behavior: reduce.matches ? 'auto' : 'smooth' });
      });
    });
    if ('IntersectionObserver' in window && railBtns.length === 2) {
      var edges = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
          var idx = e.target === rail.firstElementChild ? 0 : 1;
          railBtns[idx].disabled = e.intersectionRatio > 0.98;
        });
      }, { root: rail, threshold: [0, 0.98, 1] });
      edges.observe(rail.firstElementChild);
      edges.observe($('.n-end', rail));
    }
  }

  /* Кнопка «скопировать ссылку» */
  $$('[data-copy-link]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var link = btn.getAttribute('data-copy-link');
      var done = function () { btn.classList.add('is-copied'); setTimeout(function () { btn.classList.remove('is-copied'); }, 1600); };
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(link).then(done).catch(function () { window.prompt('Скопируйте ссылку:', link); });
      } else {
        window.prompt('Скопируйте ссылку:', link);
      }
    });
  });

  /* Подтверждение опасных действий */
  $$('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (ev) { if (!window.confirm(el.getAttribute('data-confirm'))) ev.preventDefault(); });
  });

  /* Лайтбокс: ссылки .lightbox-trigger внутри [data-lightbox-group] */
  var lb = $('#lightbox');
  if (lb) {
    var lbImg = $('#lightboxImg'), lbCap = $('#lightboxCap'), lbPrev = $('#lightboxPrev'), lbNext = $('#lightboxNext');
    var items = [], at = 0, opener = null;
    var show = function (i) {
      at = (i + items.length) % items.length;
      var a = items[at], img = a.querySelector('img');
      lbImg.src = a.getAttribute('href');
      lbImg.alt = img ? img.alt : '';
      var cap = a.getAttribute('data-caption') || (img ? img.alt : '');
      lbCap.textContent = cap + (items.length > 1 ? '   ' + (at + 1) + ' из ' + items.length : '');
      lbPrev.hidden = lbNext.hidden = items.length < 2;
    };
    var close = function () { lb.hidden = true; document.body.style.overflow = ''; if (opener) opener.focus(); };
    $$('[data-lightbox-group]').forEach(function (group) {
      var links = $$('.lightbox-trigger', group);
      links.forEach(function (link, idx) {
        link.addEventListener('click', function (ev) {
          ev.preventDefault();
          items = links; opener = link; show(idx);
          lb.hidden = false; document.body.style.overflow = 'hidden';
          $('#lightboxClose').focus();
        });
      });
    });
    lbPrev.addEventListener('click', function () { show(at - 1); });
    lbNext.addEventListener('click', function () { show(at + 1); });
    $('#lightboxClose').addEventListener('click', close);
    lb.addEventListener('click', function (ev) { if (ev.target === lb) close(); });
    document.addEventListener('keydown', function (ev) {
      if (lb.hidden) return;
      if (ev.key === 'Escape') close();
      if (ev.key === 'ArrowLeft') show(at - 1);
      if (ev.key === 'ArrowRight') show(at + 1);
    });
    var touchX = null;
    lb.addEventListener('touchstart', function (ev) { touchX = ev.touches[0].clientX; }, { passive: true });
    lb.addEventListener('touchend', function (ev) {
      if (touchX === null) return;
      var dx = ev.changedTouches[0].clientX - touchX;
      if (Math.abs(dx) > 40 && items.length > 1) show(at + (dx < 0 ? 1 : -1));
      touchX = null;
    }, { passive: true });
  }

  /* Анкета: подсветка незаполненных полей до отправки */
  $$('form[data-validate]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      var firstBad = null;
      $$('.field', form).forEach(function (fl) {
        var inp = fl.querySelector('input,select,textarea');
        if (!inp) return;
        var bad = !inp.checkValidity();
        if (inp.name === 'password2' && form.password) bad = bad || inp.value !== form.password.value;
        fl.classList.toggle('bad', bad);
        inp.setAttribute('aria-invalid', bad ? 'true' : 'false');
        if (bad && !firstBad) firstBad = inp;
      });
      var agree = form.querySelector('input[name="agree"]'), agreeErr = form.querySelector('.err-agree');
      if (agree && agreeErr) {
        agreeErr.classList.toggle('show', !agree.checked);
        if (!agree.checked && !firstBad) firstBad = agree;
      }
      if (firstBad) { e.preventDefault(); firstBad.focus(); }
    });
    form.addEventListener('input', function (e) {
      var fl = e.target.closest('.field');
      if (fl && fl.classList.contains('bad') && e.target.checkValidity()) fl.classList.remove('bad');
    });
  });
})();
