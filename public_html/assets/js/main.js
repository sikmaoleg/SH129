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

  // Лайтбокс: увеличенный просмотр фото по клику, с переключением между фото группы
  var lightbox = document.getElementById('lightbox');
  if (lightbox) {
    var lbImg   = document.getElementById('lightboxImg');
    var lbPrev  = document.getElementById('lightboxPrev');
    var lbNext  = document.getElementById('lightboxNext');
    var lbItems = [];
    var lbIndex = 0;

    var show = function (i) {
      lbIndex = (i + lbItems.length) % lbItems.length;
      lbImg.src = lbItems[lbIndex].getAttribute('href');
      var multi = lbItems.length > 1;
      lbPrev.classList.toggle('lightbox-nav-hidden', !multi);
      lbNext.classList.toggle('lightbox-nav-hidden', !multi);
    };

    var open = function (items, index) {
      lbItems = items;
      show(index);
      lightbox.classList.add('is-open');
      document.body.style.overflow = 'hidden';
    };

    var close = function () {
      lightbox.classList.remove('is-open');
      document.body.style.overflow = '';
    };

    document.querySelectorAll('[data-lightbox-group]').forEach(function (group) {
      var items = Array.prototype.slice.call(group.querySelectorAll('.lightbox-trigger'));
      items.forEach(function (link, idx) {
        link.addEventListener('click', function (ev) {
          ev.preventDefault();
          open(items, idx);
        });
      });
    });

    lbPrev.addEventListener('click', function () { show(lbIndex - 1); });
    lbNext.addEventListener('click', function () { show(lbIndex + 1); });
    document.getElementById('lightboxClose').addEventListener('click', close);
    lightbox.addEventListener('click', function (ev) {
      if (ev.target === lightbox) close();
    });
    document.addEventListener('keydown', function (ev) {
      if (!lightbox.classList.contains('is-open')) return;
      if (ev.key === 'Escape') close();
      if (ev.key === 'ArrowLeft') show(lbIndex - 1);
      if (ev.key === 'ArrowRight') show(lbIndex + 1);
    });
  }
});
