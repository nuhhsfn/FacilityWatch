/* FacilityWatch - JS ringan untuk semua halaman (tanpa framework) */
(() => {
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];

  // 1. Dialog tambah/edit (dipanggil dari tombol di halaman)
  window.openForm = (id, d) => {
    const dlg = document.getElementById(id), f = $('form', dlg);
    f.reset();
    f.elements['_id'].value = d ? d.id : '';
    $('[data-title]', dlg).textContent = (d ? 'Edit ' : 'Tambah ') + dlg.dataset.name;
    if (d) for (const k in d) { const el = f.elements[k]; if (el && el.type !== 'file') el.value = d[k] ?? ''; }
    dlg.showModal();
  };
  $$('dialog').forEach(d => d.addEventListener('click', e => { if (e.target === d) d.close(); })); // klik latar = tutup
  $$('[data-autoopen]').forEach(el => openForm(el.dataset.autoopen));

  // 2. Notifikasi: hilang otomatis / klik untuk menutup
  $$('.toast').forEach(t => { t.onclick = () => t.remove(); setTimeout(() => t.remove(), 4500); });

  // 3. Tabel responsif: label kolom dari header, supaya tampil sebagai kartu di HP
  $$('table.rtable').forEach(tb => {
    const heads = $$('thead th', tb).map(h => h.textContent.trim());
    $$('tbody tr', tb).forEach(tr => $$('td', tr).forEach((td, i) => td.dataset.label = heads[i] || ''));
  });

  // 4. Filter otomatis: dropdown langsung memfilter, pencarian menunggu berhenti mengetik
  $$('form[data-filter]').forEach(f => {
    f.addEventListener('change', e => { if (e.target.tagName === 'SELECT') f.submit(); });
    const s = $('input[name=search]', f);
    if (!s) return;
    let t;
    s.addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => { sessionStorage.fwFocus = '1'; f.submit(); }, 600); });
    if (sessionStorage.fwFocus) { sessionStorage.removeItem('fwFocus'); s.focus(); s.setSelectionRange(s.value.length, s.value.length); }
  });

  // 5. Chip filter: geser otomatis ke chip yang aktif (berguna di HP)
  $$('.chips').forEach(c => { const a = $('.is-active', c); if (a) c.scrollLeft = a.offsetLeft - 16; });

  // 6. Navigasi bawah (HP): sembunyi saat scroll ke bawah, muncul saat scroll ke atas
  const nav = $('#bnav'); let y = scrollY;
  addEventListener('scroll', () => { const n = scrollY; if (nav) nav.classList.toggle('hide', n > y && n > 80); y = n; }, { passive: true });

  // 7. Form tiket: cari aset di dropdown, penghitung karakter, pratinjau foto
  $$('[data-select-search]').forEach(inp => {
    const sel = $(inp.dataset.selectSearch), copy = sel.cloneNode(true);
    inp.addEventListener('input', () => {
      const q = inp.value.toLowerCase(), keep = sel.value;
      sel.innerHTML = '<option value="">— Pilih aset —</option>';
      $$('optgroup', copy).forEach(g => {
        const m = $$('option', g).filter(o => o.textContent.toLowerCase().includes(q));
        if (!m.length) return;
        const ng = g.cloneNode(false); m.forEach(o => ng.append(o.cloneNode(true))); sel.append(ng);
      });
      sel.value = keep;
    });
  });
  $$('textarea[data-counter]').forEach(t => {
    const out = $(t.dataset.counter), u = () => out.textContent = t.value.trim().length + ' karakter (minimal 10)';
    t.addEventListener('input', u); u();
  });
  $$('input[type=file][data-preview]').forEach(i => i.addEventListener('change', () => {
    const img = $(i.dataset.preview), f = i.files[0];
    if (f) { img.src = URL.createObjectURL(f); img.classList.remove('hidden'); } else img.classList.add('hidden');
  }));

  // 8. Grafik dashboard: menyesuaikan ukuran layar
  if (window.Chart && window.FW) {
    const small = () => innerWidth < 640;
    const bar = new Chart($('#cBar'), { type: 'bar',
      data: { labels: Object.keys(FW.status), datasets: [{ data: Object.values(FW.status), borderRadius: 10, backgroundColor: ['#93c5fd', '#c4b5fd', '#fdba74', '#86efac', '#d1d5db'] }] },
      options: { maintainAspectRatio: false, plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 }, grid: { display: false } }, x: { grid: { display: false }, ticks: { maxRotation: small() ? 60 : 0 } } } } });
    const pie = new Chart($('#cPie'), { type: 'pie',
      data: { labels: Object.keys(FW.cond), datasets: [{ data: Object.values(FW.cond), borderWidth: 0, backgroundColor: ['#86efac', '#fde047', '#fca5a5'] }] },
      options: { maintainAspectRatio: false, plugins: { legend: { position: small() ? 'bottom' : 'right' } } } });
    addEventListener('resize', () => {
      pie.options.plugins.legend.position = small() ? 'bottom' : 'right';
      bar.options.scales.x.ticks.maxRotation = small() ? 60 : 0;
      pie.update(); bar.update();
    });
  }
})();
