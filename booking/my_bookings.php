<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    csrf_check();
    $booking_id = (int)$_POST['booking_id'];
    $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND user_id = ? AND status IN ('pending','approved')");
    $stmt->execute([$booking_id, $user['id']]);
    flash_set('Booking cancelled.', 'success');
    redirect('/booking/my_bookings.php');
}

$stmt = $pdo->prepare(
    "SELECT b.*, r.name AS room_name, r.location
     FROM bookings b JOIN rooms r ON r.id = b.room_id
     WHERE b.user_id = ?
     ORDER BY b.booking_date DESC, b.start_time DESC"
);
$stmt->execute([$user['id']]);
$bookings = $stmt->fetchAll();

$page_title = 'My Bookings';
include __DIR__ . '/../includes/header.php';
?>

<div class="dash-header">
  <h1 class="mb-0">My bookings</h1>
  <a href="/index.php" class="btn">+ New booking</a>
</div>

<?php if (!$bookings): ?>
  <div class="empty-state">
    <h3>No bookings yet</h3>
    <p>Once you request a room, it'll show up here.</p>
  </div>
<?php else: ?>
  <div class="panel" style="padding:0;">
    <table>
      <thead>
        <tr>
          <th>Room</th>
          <th>Date</th>
          <th>Time</th>
          <th>Attendees</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($bookings as $b): ?>
          <tr>
            <td><strong><?= e($b['room_name']) ?></strong><br><span class="text-muted" style="font-size:0.8rem;"><?= e($b['location']) ?></span></td>
            <td><?= e($b['booking_date']) ?></td>
            <td><?= substr($b['start_time'],0,5) ?> – <?= substr($b['end_time'],0,5) ?></td>
            <td><?= (int)$b['attendees'] ?></td>
            <td><span class="status-pill <?= e($b['status']) ?>"><?= e($b['status']) ?></span></td>
            <td>
              <?php if (in_array($b['status'], ['pending','approved'])): ?>
                <form method="post" onsubmit="return confirm('Cancel this booking?');">
                  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="cancel">
                  <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-small">Cancel</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
