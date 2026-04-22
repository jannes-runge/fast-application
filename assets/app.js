/* Leichte UX-Verbesserungen für das Bewerbungsformular. */
(() => {
  'use strict';

  const form = document.querySelector('form.form');
  if (!form) return;

  // Zeichenzähler für das Anschreiben
  const ta = form.querySelector('textarea[name="message"]');
  if (ta) {
    const max = parseInt(ta.getAttribute('maxlength') || '0', 10);
    const hint = ta.parentElement.querySelector('.hint');
    const update = () => {
      if (!hint) return;
      const left = max - ta.value.length;
      hint.textContent = `Noch ${left} Zeichen.`;
      hint.style.color = left < 100 ? 'var(--c-danger)' : '';
    };
    ta.addEventListener('input', update);
    update();
  }

  // Dateinamen anzeigen
  const fi = form.querySelector('input[type="file"]');
  if (fi) {
    const list = document.createElement('div');
    list.className = 'hint';
    fi.parentElement.appendChild(list);
    fi.addEventListener('change', () => {
      const names = Array.from(fi.files || []).map(f => `${f.name} (${(f.size/1024).toFixed(0)} KB)`);
      list.textContent = names.length ? names.join(' · ') : '';
    });
  }

  // Submit-Button während Sendens deaktivieren
  form.addEventListener('submit', () => {
    const btn = form.querySelector('button[type="submit"]');
    if (btn) {
      btn.disabled = true;
      btn.style.opacity = '.7';
      const span = btn.querySelector('span');
      if (span) span.textContent = 'Wird gesendet…';
    }
  });
})();
