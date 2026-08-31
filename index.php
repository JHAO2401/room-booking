<?php
require_once __DIR__ . '/includes/functions.php';

$user = current_user();

// Visibility rule: internal-only rooms are hidden from guests and from
// logged-in public/external accounts.
$show_internal_only = $user && ($user['role'] === 'admin' || $user['user_type'] === 'faculty');

$search = trim($_GET['q'] ?? '');
$min_capacity = $_GET['capacity'] ?? '';

$sql = 'SELECT * FROM rooms WHERE is_active = 1';
$params = [];

if (!$show_internal_only) {
    $sql .= ' AND is_public = 1';
}
if ($search !== '') {
    $sql .= ' AND (name LIKE ? OR location LIKE ? OR amenities LIKE ?)';
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($min_capacity !== '') {
    $sql .= ' AND capacity >= ?';
    $params[] = (int)$min_capacity;
}
$sql .= ' ORDER BY name';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rooms = $stmt->fetchAll();

// Figure out "right now" occupancy so cards can show a live status light.
$today = date('Y-m-d');
$now = date('H:i:s');
$occupied_stmt = $pdo->prepare(
    "SELECT room_id FROM bookings
     WHERE booking_date = ? AND status = 'approved'
       AND start_time <= ? AND end_time > ?"
);
$occupied_stmt->execute([$today, $now, $now]);
$occupied_now = array_column($occupied_stmt->fetchAll(), 'room_id');

$page_title = 'Rooms';
include __DIR__ . '/includes/header.php';
?>

<section class="hero">
  <div class="eyebrow">01 — Find a room</div>
  <h1>Book a discussion room in a few clicks.</h1>
  <p class="lede">Browse real-time availability, pick a free slot, and your booking goes straight to the room owner for approval.</p>
</section>

<form method="get" class="filter-bar">
  <div class="field" style="flex:1; min-width:200px;">
    <label for="q">Search</label>
    <input type="text" id="q" name="q" placeholder="Room name, location, amenity..." value="<?= e($search) ?>">
  </div>
  <div class="field">
    <label for="capacity">Min. capacity</label>
    <input type="number" id="capacity" name="capacity" min="1" style="width:110px;" value="<?= e($min_capacity) ?>">
  </div>
  <button type="submit" class="btn">Filter</button>
  <?php if ($search !== '' || $min_capacity !== ''): ?>
    <a href="/index.php" class="btn btn-outline">Clear</a>
  <?php endif; ?>
</form>

<?php if (!$rooms): ?>
  <div class="empty-state">
    <h3>No rooms match your search</h3>
    <p>Try clearing your filters.</p>
  </div>
<?php else: ?>
  <div class="room-grid">
    <?php foreach ($rooms as $room): ?>
      <?php $is_occupied = in_array($room['id'], $occupied_now); ?>
      <div class="room-card">
        <?php if ($room['image_url']): ?>
          <img src="<?= e($room['image_url']) ?>" alt="<?= e($room['name']) ?>" class="thumb">
        <?php endif; ?>
        <div class="plate-head">
          <span class="room-num">RM-<?= str_pad($room['id'], 3, '0', STR_PAD_LEFT) ?></span>
          <span class="status-light <?= $is_occupied ? 'occupied' : 'available' ?>">
            <span class="dot"></span><?= $is_occupied ? 'In use now' : 'Free now' ?>
          </span>
        </div>
        <div class="body">
          <h3><?= e($room['name']) ?></h3>
          <div class="room-meta">
            <span>📍 <?= e($room['location']) ?></span>
            <span>👥 <?= (int)$room['capacity'] ?></span>
          </div>
          <p class="text-muted" style="font-size:0.9rem;"><?= e($room['description']) ?></p>
          <?php if ($room['amenities']): ?>
            <div class="amenity-tags">
              <?php foreach (explode(',', $room['amenities']) as $a): ?>
                <span class="tag"><?= e(trim($a)) ?></span>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <?php if (!$room['is_public']): ?>
            <span class="tag" style="border-color: var(--brass); color: var(--brass);">Internal only</span>
          <?php endif; ?>
        </div>
        <div class="footer">
          <?php if ($user && $user['role'] === 'admin'): ?>
            <a href="/admin/room_form.php?id=<?= $room['id'] ?>" class="btn btn-outline btn-block btn-small">Manage room</a>
          <?php else: ?>
            <a href="/booking/room.php?id=<?= $room['id'] ?>" class="btn btn-block btn-small">View &amp; book</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
