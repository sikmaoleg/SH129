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
});
</script>
</body>
</html>
