(() => {
  // Filter + row-click for index.php
  const q  = document.getElementById('filter');
  const fs = document.getElementById('filterStatus');
  const list = document.getElementById('appList');
  if (q && fs && list) {
    const rows  = list.querySelectorAll('tbody tr');
    const empty = document.getElementById('emptyHint');

    const apply = () => {
      const term   = q.value.trim().toLowerCase();
      const status = fs.value;
      let visible = 0;
      rows.forEach(r => {
        const text  = (r.dataset.search || '');
        const match = (!term || text.includes(term))
                   && (!status || r.dataset.status === status);
        r.classList.toggle('row-hidden', !match);
        if (match) visible++;
      });
      if (empty) empty.classList.toggle('row-hidden', visible !== 0);
    };
    q.addEventListener('input', apply);
    fs.addEventListener('change', apply);

    rows.forEach(r => {
      const href = r.dataset.href;
      if (!href) return;
      r.style.cursor = 'pointer';
      r.addEventListener('click', e => {
        if (e.target.closest('a, button, form')) return;
        window.location = href;
      });
    });
  }

  // data-confirm handler for forms
  document.querySelectorAll('form[data-confirm]').forEach(form => {
    form.addEventListener('submit', e => {
      const msg = form.dataset.confirm;
      if (msg && !confirm(msg)) e.preventDefault();
    });
  });
})();
