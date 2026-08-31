<?php
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

$room_id = (int)($_GET['room_id'] ?? 0);
$date = $_GET['date'] ?? '';

if (!$room_id || !$date) {
    http_response_code(400);
    echo json_encode(['error' => 'room_id and date are required']);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT start_time, end_time FROM bookings
     WHERE room_id = ? AND booking_date = ? AND status IN ('pending','approved')
     ORDER BY start_time"
);
$stmt->execute([$room_id, $date]);
$booked = $stmt->fetchAll();

echo json_encode(['booked' => $booked]);
