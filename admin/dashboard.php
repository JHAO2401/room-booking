<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$stats = [];
$stats['rooms'] = $pdo->query("SELECT COUNT(*) FROM rooms WHERE is_active = 1")->fetchColumn();
$stats['pending'] = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'")->fetchColumn();
$stats['approved_upcoming'] = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'approved' AND booking_date >= CURDATE()")->fetchColumn();
$stats['users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

$stmt = $pdo->query(
    "SELECT b.*, r.name AS room_name, u.name AS user_name, u.email
     FROM bookings b
     JOIN rooms r ON r.id = b.room_id
     JOIN users u ON u.id = b.user_id
     WHERE b.status = 'pending'
     ORDER BY b.created_at ASC
     LIMIT 8"
);
$pending = $stmt->fetchAll();

$rooms = $pdo->query("SELECT * FROM rooms WHERE is_active = 1 ORDER BY name")->fetchAll();

$page_title = 'Admin Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="dash-header">
  <h1 class="mb-0">Admin dashboard</h1>
</div>

<div class="stat-row">
  <div class="stat-box"><div class="num"><?= $stats['rooms'] ?></div><div class="label">Active rooms</div></div>
  <div class="stat-box"><div class="num"><?= $stats['pending'] ?></div><div class="label">Pending requests</div></div>
  <div class="stat-box"><div class="num"><?= $stats['approved_upcoming'] ?></div><div class="label">Upcoming approved</div></div>
  <div class="stat-box"><div class="num"><?= $stats['users'] ?></div><div class="label">Registered users</div></div>
</div>

<div class="flex between items-center" style="margin-top:8px;">
  <h2 class="mb-0">Current rooms</h2>
  <a href="/admin/rooms.php" class="btn btn-outline btn-small">Manage rooms &rarr;</a>
</div>
<?php if (!$rooms): ?>
  <div class="empty-state"><p>No active rooms yet.</p></div>
<?php else: ?>
  <div class="panel" style="padding:0; margin-top:12px;">
    <table>
      <thead>
        <tr><th></th><th>Room</th><th>Location</th><th>Capacity</th><th>Visibility</th></tr>
      </thead>
      <tbody>
        <?php foreach ($rooms as $r): ?>
          <tr>
            <td>
              <?php if ($r['image_url']): ?>
                <img src="<?= e($r['image_url']) ?>" alt="" style="width:52px; height:40px; object-fit:cover; border-radius:3px; border:1px solid var(--line);">
              <?php else: ?>
                <span class="text-muted" style="font-size:0.75rem;">—</span>
              <?php endif; ?>
            </td>
            <td><a href="/admin/room_form.php?id=<?= $r['id'] ?>"><strong><?= e($r['name']) ?></strong></a></td>
            <td><?= e($r['location']) ?></td>
            <td><?= (int)$r['capacity'] ?></td>
            <td><?= $r['is_public'] ? 'Public' : 'Internal only' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<h2 style="margin-top:32px;">Awaiting approval</h2>
<?php if (!$pending): ?>
  <div class="empty-state"><p>No pending requests. All caught up.</p></div>
<?php else: ?>
  <div class="panel" style="padding:0;">
    <table>
      <thead>
        <tr><th>Room</th><th>Requested by</th><th>Date</th><th>Time</th><th>Purpose</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($pending as $b): ?>
          <tr>
            <td><?= e($b['room_name']) ?></td>
            <td><?= e($b['user_name']) ?><br><span class="text-muted" style="font-size:0.8rem;"><?= e($b['email']) ?></span></td>
            <td><?= e($b['booking_date']) ?></td>
            <td><?= substr($b['start_time'],0,5) ?>–<?= substr($b['end_time'],0,5) ?></td>
            <td><?= e($b['purpose'] ?: '—') ?></td>
            <td>
              <div class="flex gap-8">
                <form method="post" action="/admin/bookings.php">
                  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="approve">
                  <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                  <button type="submit" class="btn btn-approve btn-small">Approve</button>
                </form>
                <form method="post" action="/admin/bookings.php">
                  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="reject">
                  <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-small">Reject</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
