    </div><!-- /.panel-body -->
  </div><!-- /.panel-main -->
</div><!-- /.panel -->

<script>
document.addEventListener('DOMContentLoaded', function () {
  var btn = document.getElementById('panelMenuBtn');
  var bar = document.getElementById('sidebar');
  if (btn && bar) {
    btn.addEventListener('click', function () {
      var open = bar.classList.toggle('is-open');
      btn.setAttribute('aria-expanded', String(open));
    });
  }
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (ev) {
      if (!window.confirm(el.getAttribute('data-confirm'))) ev.preventDefault();
    });
  });

  // Массовая простановка отметок участия — выставляет значение во всех
  // селектах формы, сохранение всё равно требует отдельного нажатия «Сохранить»
  document.querySelectorAll('[data-bulk-status]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var status = btn.getAttribute('data-bulk-status');
      var form = btn.closest('form');
      if (!form) return;
      form.querySelectorAll('select[name^="status["]').forEach(function (sel) {
        sel.value = status;
      });
    });
  });
});
</script>
</body>
</html>
