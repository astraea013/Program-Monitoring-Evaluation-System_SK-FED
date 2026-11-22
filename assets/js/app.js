// Small client-side behaviour. Everything works without JavaScript except
// the menus, modals, dark mode toggle and camera-based QR scanning.
(function () {
  'use strict';

  // Profile dropdown
  document.querySelectorAll('[data-menu-toggle]').forEach(function (btn) {
    var menu = btn.parentElement.querySelector('.profile-dropdown');
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      var open = menu.hidden;
      menu.hidden = !open;
      btn.setAttribute('aria-expanded', String(open));
    });
    document.addEventListener('click', function (e) {
      if (!menu.hidden && !menu.contains(e.target)) {
        menu.hidden = true;
        btn.setAttribute('aria-expanded', 'false');
      }
    });
  });

  // Mobile navigation
  document.querySelectorAll('[data-nav-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      document.body.classList.toggle('nav-open');
    });
  });
  document.addEventListener('click', function (e) {
    if (document.body.classList.contains('nav-open') && !e.target.closest('.nav-rail')) {
      document.body.classList.remove('nav-open');
    }
  });

  // Dark mode (per-browser preference only)
  document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var dark = document.documentElement.classList.toggle('dark-mode');
      try { localStorage.setItem('skmes-theme', dark ? 'dark' : 'light'); } catch (err) { /* storage blocked */ }
    });
  });

  // Modals: <button data-open="dialog-id"> opens <dialog id="dialog-id">.
  // data-fill='{"field":"value"}' copies values into the dialog's inputs.
  document.querySelectorAll('[data-open]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      var dlg = document.getElementById(btn.getAttribute('data-open'));
      if (!dlg) return;
      var fill = btn.getAttribute('data-fill');
      if (fill) {
        var data = JSON.parse(fill);
        Object.keys(data).forEach(function (k) {
          dlg.querySelectorAll('[name="' + k + '"], [data-text="' + k + '"]').forEach(function (el) {
            if (el.matches('[data-text]')) { el.textContent = data[k]; }
            else if (el.type === 'checkbox') { el.checked = !!data[k] && data[k] !== '0'; }
            else { el.value = data[k]; }
          });
        });
      }
      dlg.showModal();
    });
  });
  document.querySelectorAll('dialog[data-autoopen]').forEach(function (dlg) { dlg.showModal(); });
  document.querySelectorAll('dialog [data-close]').forEach(function (btn) {
    btn.addEventListener('click', function () { btn.closest('dialog').close(); });
  });
  document.querySelectorAll('dialog.modal').forEach(function (dlg) {
    dlg.addEventListener('click', function (e) { if (e.target === dlg) dlg.close(); });
  });

  // Confirm before destructive actions: <form data-confirm="...">
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (!window.confirm(f.getAttribute('data-confirm'))) e.preventDefault();
    });
  });

  // Auto-submit filter selects
  document.querySelectorAll('[data-autosubmit]').forEach(function (el) {
    el.addEventListener('change', function () { el.form.submit(); });
  });

  // Print buttons
  document.querySelectorAll('[data-print]').forEach(function (btn) {
    btn.addEventListener('click', function () { window.print(); });
  });
})();
