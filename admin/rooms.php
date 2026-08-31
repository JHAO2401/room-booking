<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_active') {
    csrf_check();
    $id = (int)$_POST['room_id'];
    $stmt = $pdo->prepare('UPDATE rooms SET is_active = NOT is_active WHERE id = ?');
    $stmt->execute([$id]);
    flash_set('Room updated.', 'success');
    redirect('/admin/rooms.php');
}

$rooms = $pdo->query('SELECT * FROM rooms ORDER BY is_active DESC, name')->fetchAll();

$page_title = 'Manage Rooms';
include __DIR__ . '/../includes/header.php';
?>

<div class="dash-header">
  <h1 class="mb-0">Manage rooms</h1>
  <div class="flex gap-8">
    <a href="/admin/dashboard.php" class="btn btn-outline">&larr; Dashboard</a>
    <a href="/admin/room_form.php" class="btn">+ Add room</a>
  </div>
</div>

<div class="panel" style="padding:0;">
  <table>
    <thead>
      <tr><th></th><th>Room</th><th>Location</th><th>Capacity</th><th>Visibility</th><th>Status</th><th></th></tr>
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
          <td><strong><?= e($r['name']) ?></strong></td>
          <td><?= e($r['location']) ?></td>
          <td><?= (int)$r['capacity'] ?></td>
          <td><?= $r['is_public'] ? 'Public' : 'Internal only' ?></td>
          <td><span class="status-pill <?= $r['is_active'] ? 'approved' : 'cancelled' ?>"><?= $r['is_active'] ? 'Active' : 'Disabled' ?></span></td>
          <td>
            <div class="flex gap-8">
              <a href="/admin/room_form.php?id=<?= $r['id'] ?>" class="btn btn-outline btn-small">Edit</a>
              <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="toggle_active">
                <input type="hidden" name="room_id" value="<?= $r['id'] ?>">
                <button type="submit" class="btn btn-small <?= $r['is_active'] ? 'btn-danger' : 'btn-approve' ?>">
                  <?= $r['is_active'] ? 'Disable' : 'Enable' ?>
                </button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
