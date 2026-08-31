<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $booking_id = (int)($_POST['booking_id'] ?? 0);
    $redirect_qs = $_POST['redirect_qs'] ?? '';

    if (in_array($action, ['approve', 'reject']) && $booking_id) {
        $new_status = $action === 'approve' ? 'approved' : 'rejected';

        // Guard: don't approve into a slot that's already approved for the same room/time
        if ($new_status === 'approved') {
            $b = $pdo->prepare('SELECT * FROM bookings WHERE id = ?');
            $b->execute([$booking_id]);
            $booking = $b->fetch();
            if ($booking && !room_is_available($pdo, $booking['room_id'], $booking['booking_date'], $booking['start_time'], $booking['end_time'], $booking_id)) {
                flash_set('Cannot approve — this slot now conflicts with another approved booking.', 'error');
                redirect('/admin/bookings.php' . $redirect_qs);
            }
        }

        $stmt = $pdo->prepare('UPDATE bookings SET status = ? WHERE id = ?');
        $stmt->execute([$new_status, $booking_id]);
        flash_set('Booking ' . $new_status . '.', 'success');
    } elseif ($action === 'delete' && $booking_id) {
        $stmt = $pdo->prepare('DELETE FROM bookings WHERE id = ?');
        $stmt->execute([$booking_id]);
        flash_set('Booking record deleted.', 'success');
    }
    redirect('/admin/bookings.php' . $redirect_qs);
}

$status_filter = $_GET['status'] ?? 'all';
$search = trim($_GET['q'] ?? '');
$per_page = 20;
$page = max(1, (int)($_GET['p'] ?? 1));

$where = [];
$params = [];
if ($status_filter !== 'all') {
    $where[] = 'b.status = ?';
    $params[] = $status_filter;
}
if ($search !== '') {
    $where[] = '(r.name LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR b.booking_date LIKE ?)';
    $like = "%$search%";
    array_push($params, $like, $like, $like, $like);
}
$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$base_join = "FROM bookings b JOIN rooms r ON r.id = b.room_id JOIN users u ON u.id = b.user_id $where_sql";

$count_stmt = $pdo->prepare("SELECT COUNT(*) $base_join");
$count_stmt->execute($params);
$total = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$sql = "SELECT b.*, r.name AS room_name, u.name AS user_name, u.email $base_join
        ORDER BY b.booking_date DESC, b.start_time DESC
        LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// Build the query string used to return to this exact filtered/paged view
function build_qs($overrides = []) {
    $q = array_merge($_GET, $overrides);
    $q = array_filter($q, fn($v) => $v !== '' && $v !== null);
    return $q ? ('?' . http_build_query($q)) : '';
}

$page_title = 'All Bookings';
include __DIR__ . '/../includes/header.php';
?>

<div class="dash-header">
  <h1 class="mb-0">All bookings</h1>
</div>

<div class="filter-bar">
  <?php foreach (['all','pending','approved','rejected','cancelled'] as $s): ?>
    <a href="<?= e(build_qs(['status' => $s, 'p' => 1])) ?>" class="btn btn-small <?= $status_filter === $s ? '' : 'btn-outline' ?>"><?= ucfirst($s) ?></a>
  <?php endforeach; ?>
  <form method="get" class="field" style="flex:1; min-width:200px; margin-left:auto;">
    <input type="hidden" name="status" value="<?= e($status_filter) ?>">
    <label for="q">Search room, name, email, or date</label>
    <input type="text" id="q" name="q" placeholder="e.g. Boardroom, jane@..., 2026-08-12" value="<?= e($search) ?>">
  </form>
</div>

<?php if (!$bookings): ?>
  <div class="empty-state"><p>No bookings found<?= $search !== '' ? ' for "' . e($search) . '"' : '' ?>.</p></div>
<?php else: ?>
<p class="text-muted" style="font-size:0.85rem;"><?= $total ?> total booking<?= $total === 1 ? '' : 's' ?> &middot; page <?= $page ?> of <?= $total_pages ?></p>
<div class="panel" style="padding:0;">
  <table>
    <thead>
      <tr><th>Room</th><th>Requested by</th><th>Date</th><th>Time</th><th>Status</th><th></th></tr>
    </thead>
    <tbody>
      <?php $redirect_qs = build_qs(); ?>
      <?php foreach ($bookings as $b): ?>
        <tr>
          <td><?= e($b['room_name']) ?></td>
          <td><?= e($b['user_name']) ?><br><span class="text-muted" style="font-size:0.8rem;"><?= e($b['email']) ?></span></td>
          <td><?= e($b['booking_date']) ?></td>
          <td><?= substr($b['start_time'],0,5) ?>–<?= substr($b['end_time'],0,5) ?></td>
          <td><span class="status-pill <?= e($b['status']) ?>"><?= e($b['status']) ?></span></td>
          <td>
            <div class="flex gap-8">
              <?php if ($b['status'] === 'pending'): ?>
                <form method="post">
                  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="approve">
                  <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                  <input type="hidden" name="redirect_qs" value="<?= e($redirect_qs) ?>">
                  <button type="submit" class="btn btn-approve btn-small">Approve</button>
                </form>
                <form method="post">
                  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="reject">
                  <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                  <input type="hidden" name="redirect_qs" value="<?= e($redirect_qs) ?>">
                  <button type="submit" class="btn btn-danger btn-small">Reject</button>
                </form>
              <?php endif; ?>
              <form method="post" onsubmit="return confirm('Permanently delete this booking record? This cannot be undone.');">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                <input type="hidden" name="redirect_qs" value="<?= e($redirect_qs) ?>">
                <button type="submit" class="btn btn-outline btn-small" title="Delete record">Delete</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if ($total_pages > 1): ?>
  <div class="flex gap-8 items-center" style="margin-top:16px; justify-content:center;">
    <a href="<?= e(build_qs(['p' => max(1, $page - 1)])) ?>" class="btn btn-outline btn-small" <?= $page <= 1 ? 'aria-disabled="true" style="pointer-events:none;opacity:0.4;"' : '' ?>>&larr; Prev</a>
    <span class="text-muted" style="font-size:0.85rem;">Page <?= $page ?> of <?= $total_pages ?></span>
    <a href="<?= e(build_qs(['p' => min($total_pages, $page + 1)])) ?>" class="btn btn-outline btn-small" <?= $page >= $total_pages ? 'aria-disabled="true" style="pointer-events:none;opacity:0.4;"' : '' ?>>Next &rarr;</a>
  </div>
<?php endif; ?>

<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
