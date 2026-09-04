<?php
require_once __DIR__ . '/functions.php';
$user = current_user();
$flash = flash_get();
$current = basename($_SERVER['PHP_SELF']);

// Auto-detect an uploaded logo / hero background, if the site owner has
// dropped one into /images/logo or /images/backgrounds. Falls back to the
// plain text/letter styling when nothing is there — no code change needed
// to "activate" a logo, just add the file.
function find_first_image($dir) {
    foreach (['png', 'jpg', 'jpeg', 'svg', 'webp'] as $ext) {
        $matches = glob(__DIR__ . "/../images/$dir/*.$ext");
        if ($matches) {
            return '/images/' . $dir . '/' . basename($matches[0]);
        }
    }
    return null;
}
$site_logo = find_first_image('logo');
$site_background = find_first_image('backgrounds');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($page_title) ? e($page_title) . ' — RoomPlate' : 'RoomPlate — Discussion Room Booking' ?></title>
<link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
<link rel="icon" type="image/png" href="/images/logo/logo.png" />
</head>
<body<?= $site_background ? ' style="--hero-bg:url(\'' . e($site_background) . '\');"' : '' ?>>
<header class="site-header">
  <div class="container">
    <a href="/index.php" class="brand">
      <?php if ($site_logo): ?>
        <img src="<?= e($site_logo) ?>" alt="RoomPlate logo" class="brand-logo">
      <?php else: ?>
        <span class="plate">RP</span> RoomPlate
      <?php endif; ?>
    </a>
    <nav class="main-nav">
      <a href="/index.php" class="<?= $current === 'index.php' ? 'active' : '' ?>">Rooms</a>
      <?php if ($user): ?>
        <?php if ($user['role'] !== 'admin'): ?>
          <a href="/booking/my_bookings.php" class="<?= $current === 'my_bookings.php' ? 'active' : '' ?>">My Bookings</a>
        <?php else: ?>
          <span class="nav-divider">|</span>
          <a href="/admin/dashboard.php" class="<?= $current === 'dashboard.php' ? 'active' : '' ?>">Dashboard</a>
          <a href="/admin/rooms.php" class="<?= $current === 'rooms.php' || $current === 'room_form.php' ? 'active' : '' ?>">Manage Rooms</a>
          <a href="/admin/bookings.php" class="<?= $current === 'bookings.php' ? 'active' : '' ?>">Manage Bookings</a>
        <?php endif; ?>
      <?php endif; ?>
    </nav>
    <div class="nav-user">
      <?php if ($user): ?>
        <span class="badge-role"><?= e($user['role'] === 'admin' ? 'admin' : $user['user_type']) ?></span>
        <span class="text-muted"><?= e($user['name']) ?></span>
        <a href="/login_register/logout.php" class="btn btn-outline btn-small">Log out</a>
      <?php else: ?>
        <a href="/login_register/login.php" class="btn btn-outline btn-small">Log in</a>
        <a href="/login_register/register.php" class="btn btn-small">Sign up</a>
      <?php endif; ?>
    </div>
  </div>
</header>
<main>
<div class="container">
<?php if ($flash): ?>
  <div class="flash <?= e($flash['type']) ?>" style="margin-top:24px;"><?= e($flash['msg']) ?></div>
<?php endif; ?>
