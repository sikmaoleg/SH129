document.addEventListener('DOMContentLoaded', function () {
  // Мобильное меню (публичная часть)
  var toggle = document.getElementById('navToggle');
  var menu   = document.getElementById('mainMenu');
  if (toggle && menu) {
    toggle.addEventListener('click', function () {
      var open = menu.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', String(open));
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
