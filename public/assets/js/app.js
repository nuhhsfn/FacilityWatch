(function () {
  'use strict';

  // Menu mobile
  var toggle = document.querySelector('[data-nav-toggle]');
  var links = document.querySelector('[data-nav-links]');
  if (toggle && links) {
    toggle.addEventListener('click', function () {
      var open = links.classList.toggle('open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  // Toast otomatis hilang
  document.querySelectorAll('.toast').forEach(function (t) {
    setTimeout(function () {
      t.classList.add('hide');
      setTimeout(function () { t.remove(); }, 350);
    }, 4000);
  });

  // Modal
  function openModal(id) {
    var m = document.getElementById(id);
    if (m) { m.classList.add('open'); var f = m.querySelector('input:not([type=hidden]),select,textarea'); if (f) f.focus(); }
  }
  function closeModal(m) { m.classList.remove('open'); }

  document.addEventListener('click', function (ev) {
    var openBtn = ev.target.closest('[data-modal-open]');
    if (openBtn) {
      var modal = document.getElementById(openBtn.dataset.modalOpen);
      if (modal) {
        var form = modal.querySelector('form');
        var title = modal.querySelector('[data-modal-title]');
        var data = openBtn.dataset.edit ? JSON.parse(openBtn.dataset.edit) : null;
        if (form) {
          form.reset();
          form.querySelectorAll('[name]').forEach(function (el) {
            if (el.name === '_csrf' || el.name === 'action') return;
            if (data && Object.prototype.hasOwnProperty.call(data, el.name) && el.type !== 'file') {
              el.value = data[el.name] === null ? '' : data[el.name];
            }
          });
          var act = form.querySelector('[name=action]');
          if (act) act.value = data ? 'update' : 'create';
          var idf = form.querySelector('[name=id]');
          if (idf) idf.value = data ? data.id : '';
        }
        if (title) title.textContent = data ? title.dataset.edit : title.dataset.create;
        openModal(openBtn.dataset.modalOpen);
      }
      return;
    }
    if (ev.target.closest('[data-modal-close]') || ev.target.classList.contains('modal')) {
      var m = ev.target.closest('.modal');
      if (m) closeModal(m);
    }
  });
  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape') document.querySelectorAll('.modal.open').forEach(closeModal);
  });

  // Konfirmasi hapus
  document.addEventListener('submit', function (ev) {
    var msg = ev.target.dataset.confirm;
    if (msg && !window.confirm(msg)) ev.preventDefault();
  });

  // Auto-buka modal jika ?new=1
  if (/[?&]new=1/.test(location.search)) {
    var first = document.querySelector('[data-modal-open][data-auto]');
    if (first) first.click();
  }

  // Filter dropdown aset di form tiket
  var filter = document.querySelector('[data-asset-filter]');
  var select = document.querySelector('[data-asset-select]');
  if (filter && select) {
    filter.addEventListener('input', function () {
      var q = filter.value.toLowerCase();
      select.querySelectorAll('option').forEach(function (o) {
        if (!o.value) return;
        o.hidden = q && o.textContent.toLowerCase().indexOf(q) === -1;
      });
      select.querySelectorAll('optgroup').forEach(function (g) {
        var any = Array.prototype.some.call(g.querySelectorAll('option'), function (o) { return !o.hidden; });
        g.hidden = !any;
      });
    });
  }

  // Pratinjau nama file upload
  document.querySelectorAll('input[type=file][data-preview]').forEach(function (inp) {
    inp.addEventListener('change', function () {
      var img = document.getElementById(inp.dataset.preview);
      if (!img || !inp.files[0]) return;
      img.src = URL.createObjectURL(inp.files[0]);
      img.hidden = false;
    });
  });
})();
