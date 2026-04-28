<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
Auth::require();

$slug = (string)($_GET['slug'] ?? '');
$errors = [];
$notice = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::csrfCheck($_POST['_csrf'] ?? null)) {
        $errors[] = 'Sitzung abgelaufen. Bitte neu laden.';
    } else {
        $slug = (string)($_POST['slug'] ?? '');
        $title = trim((string)($_POST['title'] ?? ''));
        $html = (string)($_POST['content_html'] ?? '');
        if (!Pages::get($slug))         $errors[] = 'Unbekannte Seite.';
        if ($title === '')              $errors[] = 'Titel darf nicht leer sein.';
        if (mb_strlen($html) > 100000)  $errors[] = 'Inhalt zu groß (max. 100k Zeichen).';
        if (!$errors) {
            Pages::save($slug, $title, sanitize_page_html($html));
            Audit::log('page.update', 'page', $slug, ['title' => $title, 'length' => mb_strlen($html)]);
            $notice = 'Gespeichert.';
        }
    }
}

$page = $slug !== '' ? Pages::get($slug) : null;
$pages = Pages::all();
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Inhalte verwalten</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
<link rel="stylesheet" href="../assets/style.css">
<style>
  :root{
    --c-primary: <?= e(cfg('colors.primary')) ?>;
    --c-primary-dk: <?= e(cfg('colors.primary_dk')) ?>;
    --c-accent: <?= e(cfg('colors.accent')) ?>;
    --c-bg: <?= e(cfg('colors.bg')) ?>;
    --c-bg-soft: <?= e(cfg('colors.bg_soft')) ?>;
    --c-surface: <?= e(cfg('colors.surface')) ?>;
    --c-text: <?= e(cfg('colors.text')) ?>;
    --c-text-soft: <?= e(cfg('colors.text_soft')) ?>;
    --c-danger: <?= e(cfg('colors.danger')) ?>;
    --c-success: <?= e(cfg('colors.success')) ?>;
  }
</style>
</head>
<body>
<?php $nav = 'pages'; include __DIR__ . '/_topbar.php'; ?>

<main class="container container-wide">
  <h1>Inhalte</h1>

  <?php if ($notice): ?><div class="alert alert-success"><?= e($notice) ?></div><?php endif ?>
  <?php if ($errors): ?>
    <div class="alert"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach ?></ul></div>
  <?php endif ?>

  <?php if (!$page): ?>
    <div class="card table-wrap" style="margin-bottom:1.5rem">
      <table class="tbl tbl-cards">
        <thead><tr><th>Seite</th><th>Zuletzt aktualisiert</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($pages as $p): ?>
            <tr>
              <td data-label="Seite"><strong><?= e($p['title']) ?></strong> <span class="muted">/<?= e($p['slug']) ?>.php</span></td>
              <td data-label="Aktualisiert"><?= e(date('d.m.Y H:i', (int)$p['updated_at'])) ?></td>
              <td data-label="">
                <a class="btn btn-sm btn-primary" href="?slug=<?= e($p['slug']) ?>">Bearbeiten</a>
                <a class="btn btn-sm btn-ghost" href="../<?= e($p['slug']) ?>.php" target="_blank" rel="noopener">Vorschau</a>
              </td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p class="breadcrumb"><a href="pages.php">← Inhalte-Übersicht</a></p>
    <div class="card form">
      <form method="post" autocomplete="off">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="slug" value="<?= e($page['slug']) ?>">

        <label class="field">
          <span>Titel</span>
          <input type="text" name="title" required maxlength="120" value="<?= e($page['title']) ?>">
        </label>

        <label class="field">
          <span>Inhalt</span>
          <div class="editor-wrap">
            <div class="editor-toolbar" id="editor-toolbar" role="toolbar" aria-label="Format">
              <button type="button" data-cmd="bold" title="Fett (Strg+B)"><b>B</b></button>
              <button type="button" data-cmd="italic" title="Kursiv (Strg+I)"><i>I</i></button>
              <button type="button" data-cmd="underline" title="Unterstreichen"><u>U</u></button>
              <span class="sep"></span>
              <button type="button" data-cmd="formatBlock" data-val="H2" title="Überschrift 2">H2</button>
              <button type="button" data-cmd="formatBlock" data-val="H3" title="Überschrift 3">H3</button>
              <button type="button" data-cmd="formatBlock" data-val="P" title="Absatz">¶</button>
              <span class="sep"></span>
              <button type="button" data-cmd="insertUnorderedList" title="Aufzählung">• Liste</button>
              <button type="button" data-cmd="insertOrderedList" title="Nummeriert">1. Liste</button>
              <span class="sep"></span>
              <button type="button" data-cmd="createLink" title="Link einfügen">🔗 Link</button>
              <button type="button" data-cmd="removeFormat" title="Formatierung entfernen">⨯ Format</button>
              <span class="sep"></span>
              <button type="button" data-cmd="undo" title="Rückgängig">↶</button>
              <button type="button" data-cmd="redo" title="Wiederholen">↷</button>
              <span class="sep"></span>
              <button type="button" data-cmd="toggleHtml" title="HTML-Quelltext bearbeiten" class="toggle-html">&lt; &gt; HTML</button>
            </div>
            <div class="editor" id="editor" contenteditable="true" spellcheck="true" lang="de"></div>
            <textarea name="content_html" id="content_html" hidden><?= e($page['content_html']) ?></textarea>
          </div>
          <small class="hint">Erlaubt: Absätze, Überschriften (H2/H3), Listen, Fett/Kursiv, Links. Andere Tags werden beim Speichern entfernt.</small>
        </label>

        <div class="actions">
          <button class="btn btn-primary" type="submit">Speichern</button>
          <a class="btn btn-ghost" href="../<?= e($page['slug']) ?>.php" target="_blank" rel="noopener">Vorschau</a>
        </div>
      </form>
    </div>
  <?php endif ?>
</main>
<script src="../assets/admin.js" defer></script>
<?php if ($page): ?>
<script src="../assets/editor.js" defer></script>
<?php endif ?>
</body>
</html>
