(() => {
  const editor   = document.getElementById('editor');
  const target   = document.getElementById('content_html');
  const toolbar  = document.getElementById('editor-toolbar');
  if (!editor || !target || !toolbar) return;

  // Initial: Hidden-Textarea -> contenteditable
  editor.innerHTML = target.value;

  let mode = 'visual'; // 'visual' | 'html'

  const sync = () => {
    if (mode === 'visual') target.value = editor.innerHTML;
    else                   target.value = target.value; // textarea selbst ist Quelle
  };

  const setMode = (next) => {
    if (next === mode) return;
    if (next === 'html') {
      // Visuell -> HTML: Editor-Inhalt in Textarea übernehmen, Textarea sichtbar
      target.value = editor.innerHTML;
      editor.style.display = 'none';
      target.hidden = false;
      target.classList.add('editor-html');
      // Format-Buttons disablen (außer Toggle)
      toolbar.querySelectorAll('button[data-cmd]').forEach(b => b.disabled = true);
    } else {
      // HTML -> Visuell: Textarea-Inhalt in Editor parsen
      editor.innerHTML = target.value;
      target.hidden = true;
      target.classList.remove('editor-html');
      editor.style.display = '';
      toolbar.querySelectorAll('button[data-cmd]').forEach(b => b.disabled = false);
    }
    mode = next;
    toggleBtn.classList.toggle('active', mode === 'html');
    toggleBtn.textContent = mode === 'html' ? '✓ HTML' : '< > HTML';
  };

  const exec = (cmd, val = null) => {
    if (mode !== 'visual') return;
    editor.focus();
    document.execCommand(cmd, false, val);
    sync();
  };

  const toggleBtn = toolbar.querySelector('button[data-cmd="toggleHtml"]');

  toolbar.addEventListener('click', e => {
    const btn = e.target.closest('button');
    if (!btn) return;
    e.preventDefault();
    const cmd = btn.dataset.cmd;
    const val = btn.dataset.val || null;

    if (cmd === 'toggleHtml') {
      setMode(mode === 'visual' ? 'html' : 'visual');
      return;
    }
    if (mode !== 'visual') return;

    if (cmd === 'createLink') {
      const url = prompt('Link-URL (https://… oder mailto:…):', 'https://');
      if (!url) return;
      exec('createLink', url);
    } else if (cmd === 'formatBlock') {
      exec('formatBlock', val);
    } else if (cmd === 'removeFormat') {
      exec('removeFormat');
      exec('unlink');
    } else {
      exec(cmd);
    }
  });

  // Paste im Visuell-Modus: als Plain-Text einfügen, damit kein Word-Müll kommt
  editor.addEventListener('paste', e => {
    if (mode !== 'visual') return;
    e.preventDefault();
    const text = (e.clipboardData || window.clipboardData).getData('text/plain');
    document.execCommand('insertText', false, text);
  });

  editor.addEventListener('input', sync);
  // Beim Submit immer den aktuellen Modus festschreiben
  editor.closest('form')?.addEventListener('submit', () => {
    if (mode === 'visual') target.value = editor.innerHTML;
    // im HTML-Modus ist target.value bereits aktuell
  });
})();
