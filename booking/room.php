<?php
require_once __DIR__ . '/../includes/functions.php';

$room_id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM rooms WHERE id = ? AND is_active = 1');
$stmt->execute([$room_id]);
$room = $stmt->fetch();

if (!$room) {
    http_response_code(404);
    $page_title = 'Room not found';
    include __DIR__ . '/../includes/header.php';
    echo '<div class="empty-state"><h3>Room not found</h3><p><a href="/index.php">Back to rooms</a></p></div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$user = current_user();

// Block external/guest access to internal-only rooms
if (!$room['is_public'] && (!$user || ($user['user_type'] !== 'faculty' && $user['role'] !== 'admin'))) {
    http_response_code(403);
    $page_title = 'Restricted';
    include __DIR__ . '/../includes/header.php';
    echo '<div class="empty-state"><h3>This room is for internal use only</h3><p><a href="/index.php">Back to rooms</a></p></div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    csrf_check();
    $date = $_POST['date'] ?? '';
    $start = $_POST['start_time'] ?? '';
    $end = $_POST['end_time'] ?? '';
    $purpose = trim($_POST['purpose'] ?? '');
    $attendees = (int)($_POST['attendees'] ?? 1);

    if (!$date || !$start || !$end) {
        $errors[] = 'Please choose a date and time slot.';
    } elseif ($start >= $end) {
        $errors[] = 'End time must be after start time.';
    } elseif ($date < date('Y-m-d')) {
        $errors[] = 'You cannot book a date in the past.';
    } elseif ($attendees > $room['capacity']) {
        $errors[] = 'Attendee count exceeds room capacity (' . $room['capacity'] . ').';
    }

    if (!$errors && !room_is_available($pdo, $room_id, $date, $start, $end)) {
        $errors[] = 'That slot was just taken by someone else. Please pick another.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare(
            'INSERT INTO bookings (room_id, user_id, booking_date, start_time, end_time, purpose, attendees, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, "pending")'
        );
        $stmt->execute([$room_id, $user['id'], $date, $start, $end, $purpose, $attendees]);
        flash_set('Booking request submitted. Awaiting approval.', 'success');
        redirect('/booking/my_bookings.php');
    }
}

$page_title = $room['name'];
include __DIR__ . '/../includes/header.php';
?>

<div style="padding: 28px 0 60px;">
  <a href="/index.php" class="text-muted" style="font-size:0.85rem;">&larr; All rooms</a>

  <?php if ($room['image_url']): ?>
    <img src="<?= e($room['image_url']) ?>" alt="<?= e($room['name']) ?>" style="width:100%; height:280px; object-fit:cover; border-radius:4px; margin-top:16px; border:1px solid var(--line);">
  <?php endif; ?>
  <div class="plate-head" style="margin-top:<?= $room['image_url'] ? '0' : '16px' ?>; border-radius:<?= $room['image_url'] ? '0' : '4px 4px 0 0' ?>;">
    <span class="room-num">RM-<?= str_pad($room['id'], 3, '0', STR_PAD_LEFT) ?></span>
    <span class="status-light available"><span class="dot"></span>Bookable</span>
  </div>
  <div class="panel" style="border-radius:0 0 4px 4px; margin-top:0;">
    <h1 class="mt-0"><?= e($room['name']) ?></h1>
    <div class="room-meta" style="margin-bottom:10px;">
      <span>📍 <?= e($room['location']) ?></span>
      <span>👥 Capacity <?= (int)$room['capacity'] ?></span>
    </div>
    <p><?= nl2br(e($room['description'])) ?></p>
    <?php if ($room['amenities']): ?>
      <div class="amenity-tags">
        <?php foreach (explode(',', $room['amenities']) as $a): ?>
          <span class="tag"><?= e(trim($a)) ?></span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="panel" style="margin-top:20px;">
    <h2>Book this room</h2>

    <?php if (!$user): ?>
      <p class="text-muted">Please <a href="/login_register/login.php" style="text-decoration:underline;">log in</a> or <a href="/login_register/register.php" style="text-decoration:underline;">sign up</a> to make a booking.</p>
    <?php else: ?>
      <?php foreach ($errors as $err): ?>
        <div class="flash error"><?= e($err) ?></div>
      <?php endforeach; ?>

      <div class="field" style="max-width:220px; margin-bottom:16px;">
        <label for="date-picker">Date</label>
        <input type="date" id="date-picker" min="<?= date('Y-m-d') ?>" value="<?= e($_POST['date'] ?? date('Y-m-d')) ?>">
      </div>

      <div id="slot-container">
        <label style="font-size:0.76rem; font-family: var(--font-mono); text-transform:uppercase; letter-spacing:0.05em; color:var(--ink-soft);">Available slots (click a start, then an end)</label>
        <div class="slot-grid" id="slot-grid"><!-- populated by JS --></div>
      </div>

      <form method="post" class="form-grid" style="margin-top:20px; max-width:480px;" id="booking-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="date" id="input-date">
        <input type="hidden" name="start_time" id="input-start">
        <input type="hidden" name="end_time" id="input-end">

        <div class="field">
          <label for="attendees">Number of attendees</label>
          <input type="number" id="attendees" name="attendees" min="1" max="<?= (int)$room['capacity'] ?>" value="1" required>
        </div>
        <div class="field">
          <label for="purpose">Purpose (optional)</label>
          <textarea id="purpose" name="purpose" rows="3" placeholder="e.g. Weekly sync, project discussion..."><?= e($_POST['purpose'] ?? '') ?></textarea>
        </div>
        <div id="selection-summary" class="text-muted" style="font-size:0.85rem;">No time selected yet.</div>
        <button type="submit" class="btn" id="submit-booking" disabled>Request booking</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<script>
  window.ROOM_ID = <?= (int)$room['id'] ?>;
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
