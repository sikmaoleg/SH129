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

  // Подтверждение опасных действий
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (ev) {
      if (!window.confirm(el.getAttribute('data-confirm'))) {
        ev.preventDefault();
      }
    });
  });
});
