(function () {
  var shell = document.querySelector('.app-shell');
  var toggle = document.querySelector('.sidebar-toggle');
  var overlay = document.querySelector('.sidebar-overlay');

  if (!shell || !toggle || !overlay) {
    return;
  }

  function closeSidebar() {
    shell.classList.remove('sidebar-open');
  }

  toggle.addEventListener('click', function () {
    shell.classList.toggle('sidebar-open');
  });

  overlay.addEventListener('click', closeSidebar);

  document.querySelectorAll('.sidebar-nav a').forEach(function (link) {
    link.addEventListener('click', closeSidebar);
  });
})();
