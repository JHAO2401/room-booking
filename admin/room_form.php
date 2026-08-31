<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$room = null;
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM rooms WHERE id = ?');
    $stmt->execute([$id]);
    $room = $stmt->fetch();
    if (!$room) { flash_set('Room not found.', 'error'); redirect('/admin/rooms.php'); }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim($_POST['name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $capacity = (int)($_POST['capacity'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $amenities = trim($_POST['amenities'] ?? '');
    $is_public = isset($_POST['is_public']) ? 1 : 0;
    $remove_image = isset($_POST['remove_image']);

    if ($name === '' || $location === '' || $capacity < 1) {
        $errors[] = 'Please fill in name, location, and a valid capacity.';
    }

    // Handle an optional photo upload (JPG/PNG/WEBP, max 5MB)
    [$upload_ok, $new_image_url, $upload_err] = handle_room_image_upload(
        'room_image',
        __DIR__ . '/../images/rooms',
        '/images/rooms'
    );
    if (!$upload_ok) {
        $errors[] = $upload_err;
    }

    if (!$errors) {
        $image_url = $room['image_url'] ?? null;
        if ($new_image_url) {
            $image_url = $new_image_url;
        } elseif ($remove_image) {
            $image_url = null;
        }

        try {
            if ($room) {
                $stmt = $pdo->prepare('UPDATE rooms SET name=?, location=?, capacity=?, description=?, amenities=?, is_public=?, image_url=? WHERE id=?');
                $stmt->execute([$name, $location, $capacity, $description, $amenities, $is_public, $image_url, $room['id']]);
                flash_set('Room updated.', 'success');
            } else {
                $stmt = $pdo->prepare('INSERT INTO rooms (name, location, capacity, description, amenities, is_public, image_url, is_active) VALUES (?,?,?,?,?,?,?,1)');
                $stmt->execute([$name, $location, $capacity, $description, $amenities, $is_public, $image_url]);
                flash_set('Room created.', 'success');
            }
            redirect('/admin/rooms.php');
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $errors[] = 'A room named "' . $name . '" already exists. Please use a different name.';
            } else {
                throw $e;
            }
        }
    }
}

$page_title = $room ? 'Edit Room' : 'Add Room';
include __DIR__ . '/../includes/header.php';
?>

<div class="form-narrow panel" style="max-width:560px;">
  <h1><?= $room ? 'Edit room' : 'Add a new room' ?></h1>

  <?php foreach ($errors as $err): ?>
    <div class="flash error"><?= e($err) ?></div>
  <?php endforeach; ?>

  <form method="post" class="form-grid" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <div class="field">
      <label>Photo</label>
      <img id="photo-preview" src="<?= !empty($room['image_url']) ? e($room['image_url']) : '' ?>" alt="Room photo preview"
           style="width:100%; max-width:280px; height:160px; object-fit:cover; border-radius:4px; border:1px solid var(--line); margin-bottom:8px; <?= empty($room['image_url']) ? 'display:none;' : '' ?>">
      <?php if (!empty($room['image_url'])): ?>
        <label class="flex items-center gap-8" style="font-size:0.85rem; font-weight:400; margin-bottom:6px;">
          <input type="checkbox" name="remove_image" id="remove-image-check" style="width:auto;">
          Remove current photo
        </label>
      <?php endif; ?>
      <input type="file" name="room_image" id="room-image-input" accept="image/png,image/jpeg,image/webp">
      <span class="text-muted" style="font-size:0.78rem;">JPG, PNG or WEBP, max 5MB. <?= !empty($room['image_url']) ? 'Uploading a new photo replaces the current one.' : '' ?></span>
    </div>

    <div class="field">
      <label for="name">Room name</label>
      <input type="text" id="name" name="name" required value="<?= e($room['name'] ?? $_POST['name'] ?? '') ?>">
    </div>
    <div class="form-row-2">
      <div class="field">
        <label for="location">Location</label>
        <input type="text" id="location" name="location" required value="<?= e($room['location'] ?? $_POST['location'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="capacity">Capacity</label>
        <input type="number" id="capacity" name="capacity" min="1" required value="<?= e($room['capacity'] ?? $_POST['capacity'] ?? '') ?>">
      </div>
    </div>
    <div class="field">
      <label for="description">Description</label>
      <textarea id="description" name="description" rows="3"><?= e($room['description'] ?? $_POST['description'] ?? '') ?></textarea>
    </div>
    <div class="field">
      <label for="amenities">Amenities (comma-separated)</label>
      <input type="text" id="amenities" name="amenities" placeholder="Projector, Whiteboard, Wi-Fi" value="<?= e($room['amenities'] ?? $_POST['amenities'] ?? '') ?>">
    </div>
    <label class="flex items-center gap-8" style="font-size:0.9rem;">
      <input type="checkbox" name="is_public" style="width:auto;" <?= (isset($room['is_public']) ? $room['is_public'] : ($_POST['is_public'] ?? 1)) ? 'checked' : '' ?>>
      Bookable by external/public visitors
    </label>
    <button type="submit" class="btn btn-block"><?= $room ? 'Save changes' : 'Create room' ?></button>
  </form>
</div>

<script>
  // Live preview of the chosen photo before the form is submitted.
  (function () {
    const input = document.getElementById('room-image-input');
    const preview = document.getElementById('photo-preview');
    const removeCheck = document.getElementById('remove-image-check');
    if (!input || !preview) return;

    input.addEventListener('change', () => {
      const file = input.files && input.files[0];
      if (!file) return;
      preview.src = URL.createObjectURL(file);
      preview.style.display = 'block';
      if (removeCheck) removeCheck.checked = false;
    });
  })();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
