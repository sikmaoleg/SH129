document.addEventListener('DOMContentLoaded', function () {
  // Мобильное меню (публичная часть)
  var toggle = document.getElementById('navToggle');
  var menu   = document.getElementById('mainMenu');
  if (toggle && menu) {
    var setOpen = function (open) {
      menu.classList.toggle('is-open', open);
      toggle.setAttribute('aria-expanded', String(open));
    };

    toggle.addEventListener('click', function (ev) {
      ev.stopPropagation();
      setOpen(!menu.classList.contains('is-open'));
    });

    // Тап мимо меню закрывает его — иначе на телефоне из него не выйти без выбора пункта
    document.addEventListener('click', function (ev) {
      if (menu.classList.contains('is-open') && !menu.contains(ev.target) && ev.target !== toggle) {
        setOpen(false);
      }
    });

    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && menu.classList.contains('is-open')) {
        setOpen(false);
        toggle.focus();
      }
    });
  }

  // Слайдер фото в hero на главной
  var slider = document.getElementById('heroSlider');
  if (slider) {
    var slides = slider.querySelectorAll('.hero-slide');
    var dots   = slider.querySelectorAll('.hero-slider-dots button');
    var current = 0;
    var timer = null;
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var show = function (i) {
      current = (i + slides.length) % slides.length;
      slides.forEach(function (s, idx) { s.classList.toggle('is-active', idx === current); });
      dots.forEach(function (d, idx) { d.classList.toggle('is-active', idx === current); });
    };
    var next = function () { show(current + 1); };

    var start = function () {
      if (reduceMotion || slides.length < 2) return;
      stop();
      timer = setInterval(next, 4500);
    };
    var stop = function () { if (timer) { clearInterval(timer); timer = null; } };

    dots.forEach(function (d, idx) {
      d.addEventListener('click', function () { show(idx); start(); });
    });
    slider.addEventListener('mouseenter', stop);
    slider.addEventListener('mouseleave', start);
    slider.addEventListener('focusin', stop);
    slider.addEventListener('focusout', start);

    // Свайп для тач-экранов
    var touchX = null;
    slider.addEventListener('touchstart', function (ev) { touchX = ev.touches[0].clientX; stop(); }, { passive: true });
    slider.addEventListener('touchend', function (ev) {
      if (touchX === null) return;
      var dx = ev.changedTouches[0].clientX - touchX;
      if (Math.abs(dx) > 40) show(current + (dx < 0 ? 1 : -1));
      touchX = null;
      start();
    }, { passive: true });

    start();
  }

  // Кнопка «скопировать ссылку» в блоке «Поделиться»
  document.querySelectorAll('[data-copy-link]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var url = btn.getAttribute('data-copy-link');
      var done = function () {
        btn.classList.add('is-copied');
        setTimeout(function () { btn.classList.remove('is-copied'); }, 1600);
      };
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(url).then(done).catch(function () { window.prompt('Скопируйте ссылку:', url); });
      } else {
        window.prompt('Скопируйте ссылку:', url);
      }
    });
  });

  // Подтверждение опасных действий
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (ev) {
      if (!window.confirm(el.getAttribute('data-confirm'))) {
        ev.preventDefault();
      }
    });
  });
});
