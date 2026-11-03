<?php
/** @var string $title */
/** @var string $active */
$title  = $title ?? 'FacilityWatch';
$active = $active ?? '';
$user   = current_user();
$nav = [
    'home'      => ['/', 'Beranda'],
    'dashboard' => ['/dashboard.php', 'Dashboard'],
    'rooms'     => ['/rooms.php', 'Ruangan'],
    'assets'    => ['/assets.php', 'Aset'],
    'tickets'   => ['/tickets.php', 'Tiket'],
];
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> · FacilityWatch</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="<?= e($bodyClass ?? '') ?>">

<header class="nav-wrap">
  <nav class="nav" aria-label="Navigasi utama">
    <a href="/" class="brand" aria-label="FacilityWatch">
      <svg width="28" height="28" viewBox="0 0 28 28" aria-hidden="true">
        <?php
        // logo titik-titik melingkar
        foreach ([[14,4],[7,7],[21,7],[4,14],[24,14],[7,21],[21,21],[14,24],[14,14]] as [$x,$y]) {
            echo '<circle cx="' . $x . '" cy="' . $y . '" r="2" fill="#0b0b0b"/>';
        }
        ?>
      </svg>
    </a>
    <button class="nav-toggle" type="button" aria-label="Menu" aria-expanded="false" data-nav-toggle>
      <span></span><span></span>
    </button>
    <div class="nav-links" data-nav-links>
      <?php foreach ($nav as $key => [$href, $label]): ?>
        <a href="<?= $href ?>" class="<?= $active === $key ? 'active' : '' ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
      <?php if ($user): ?>
        <form method="post" action="/logout.php" class="inline-form">
          <?= csrf_field() ?>
          <button class="btn btn-dark btn-sm" type="submit">Keluar</button>
        </form>
      <?php else: ?>
        <a href="/login.php" class="btn btn-dark btn-sm">Masuk Admin</a>
      <?php endif; ?>
    </div>
  </nav>
</header>

<div class="toasts" id="toasts">
  <?php foreach (pull_flash() as $f): ?>
    <div class="toast toast-<?= e($f['type']) ?>" role="status"><?= e($f['message']) ?></div>
  <?php endforeach; ?>
</div>
