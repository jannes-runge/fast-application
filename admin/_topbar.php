<?php
// Erwartet $nav: 'apps' | 'admins'
$nav = $nav ?? 'apps';
?>
<header class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="index.php">
      <img class="logo-sm" src="../<?= e(cfg('logo_path')) ?>" alt="">
      <strong><?= e(cfg('company_name')) ?></strong>
    </a>
    <span class="spacer"></span>
    <nav class="topbar-nav">
      <a href="index.php" class="<?= $nav === 'apps' ? 'active' : '' ?>">Bewerbungen</a>
      <a href="admins.php" class="<?= $nav === 'admins' ? 'active' : '' ?>">Admins</a>
      <a href="logout.php" class="logout">Abmelden</a>
    </nav>
  </div>
</header>
