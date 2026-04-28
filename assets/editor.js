(() => {
  const editor = document.getElementById('editor');
  const target = document.getElementById('content_html');
  const toolbar = document.getElementById('editor-toolbar');
  if (!editor || !target || !toolbar) return;

  // Initialwert vom Hidden-Field in den editierbaren Bereich übernehmen
  editor.innerHTML = target.value;

  const exec = (cmd, val = null) => {
    editor.focus();
    document.execCommand(cmd, false, val);
    sync();
  };

  const sync = () => { target.value = editor.innerHTML; };

  toolbar.addEventListener('click', e => {
    const btn = e.target.closest('button[data-cmd]');
    if (!btn) return;
    e.preventDefault();
    const cmd = btn.dataset.cmd;
    const val = btn.dataset.val || null;

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

  // Plain-Text einfügen (kein Word-Garbage)
  editor.addEventListener('paste', e => {
    e.preventDefault();
    const text = (e.clipboardData || window.clipboardData).getData('text/plain');
    document.execCommand('insertText', false, text);
  });

  editor.addEventListener('input', sync);
  editor.closest('form')?.addEventListener('submit', sync);
})();
