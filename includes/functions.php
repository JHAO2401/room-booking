<?php
session_start();
require_once __DIR__ . '/../config/db.php';

/** ---------- Auth helpers ---------- */

function current_user() {
    return $_SESSION['user'] ?? null;
}

function require_login() {
    if (!current_user()) {
        header('Location: /login_register/login.php');
        exit;
    }
}

function require_admin() {
    require_login();
    if (current_user()['role'] !== 'admin') {
        http_response_code(403);
        die('Forbidden: admin access only.');
    }
}

function is_admin() {
    $u = current_user();
    return $u && $u['role'] === 'admin';
}

/** ---------- Small utilities ---------- */

function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect($path) {
    header('Location: ' . $path);
    exit;
}

function flash_set($msg, $type = 'success') {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

function flash_get() {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_check() {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }
}

/** ---------- Image upload ---------- */

// Handles an uploaded room photo from $_FILES['room_image'].
// Returns [success(bool), value(string|null), error(string|null)].
// `value` is the new /images/rooms/... path, or null if no file was
// uploaded (caller should then keep the existing image_url as-is).
function handle_room_image_upload($field, $upload_dir_fs, $upload_dir_url) {
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return [true, null, null]; // nothing uploaded — not an error
    }

    $file = $_FILES[$field];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [false, null, 'Upload failed. Please try again.'];
    }

    $max_bytes = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $max_bytes) {
        return [false, null, 'Image is too large (max 5MB).'];
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowed[$mime])) {
        return [false, null, 'Please upload a JPG, PNG, or WEBP image.'];
    }

    if (!is_dir($upload_dir_fs)) {
        mkdir($upload_dir_fs, 0755, true);
    }

    $filename = 'room_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    $dest = rtrim($upload_dir_fs, '/') . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return [false, null, 'Could not save the uploaded image.'];
    }

    return [true, rtrim($upload_dir_url, '/') . '/' . $filename, null];
}

/** ---------- Domain helpers ---------- */

// Checks whether a room is free for the given date/time range.
// Optionally excludes a booking id (useful when editing).
function room_is_available(PDO $pdo, $room_id, $date, $start, $end, $exclude_booking_id = null) {
    $sql = "SELECT COUNT(*) FROM bookings
            WHERE room_id = :room_id
              AND booking_date = :date
              AND status IN ('pending','approved')
              AND start_time < :end
              AND end_time > :start";
    $params = [
        ':room_id' => $room_id,
        ':date'    => $date,
        ':start'   => $start,
        ':end'     => $end,
    ];
    if ($exclude_booking_id) {
        $sql .= " AND id != :exclude_id";
        $params[':exclude_id'] = $exclude_booking_id;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn() == 0;
}
