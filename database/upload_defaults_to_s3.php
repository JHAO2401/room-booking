<?php
/**
 * One-off helper: uploads the 4 bundled default room photos (in
 * images/rooms/) to your configured S3 bucket, and prints the SQL you
 * need to run to point your rooms at the new S3 URLs.
 *
 * Usage (run this ON the EC2 instance, so it can use the IAM role):
 *   S3_BUCKET=your-bucket-name AWS_REGION=us-east-1 php database/upload_defaults_to_s3.php
 *
 * Requires S3_BUCKET (and optionally AWS_REGION) to be set — either as
 * env vars for this one command, or already set in your Apache/EC2
 * environment (in which case you can just run `php database/upload_defaults_to_s3.php`).
 */

require_once __DIR__ . '/../includes/s3.php';

if (!s3_is_enabled()) {
    echo "S3_BUCKET is not set. Example:\n";
    echo "  S3_BUCKET=my-bucket AWS_REGION=us-east-1 php database/upload_defaults_to_s3.php\n";
    exit(1);
}

$images = [
    'Discussion Room A' => 'discussion_room_a.jpg',
    'Discussion Room B' => 'discussion_room_b.jpg',
    'Boardroom'         => 'boardroom.jpg',
    'Focus Pod 1'       => 'focus_pod_1.jpg',
];

$mime_by_ext = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

echo "-- Run this SQL after the uploads below finish, to point your rooms at S3:\n\n";

foreach ($images as $room_name => $filename) {
    $local_path = __DIR__ . '/../images/rooms/' . $filename;
    if (!file_exists($local_path)) {
        fwrite(STDERR, "Skipping $filename — not found in images/rooms/\n");
        continue;
    }
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $mime = $mime_by_ext[$ext] ?? 'application/octet-stream';

    $url = s3_upload_file($local_path, 'rooms/' . $filename, $mime);

    if ($url) {
        fwrite(STDERR, "Uploaded $filename -> $url\n");
        $safe_name = addslashes($room_name);
        $safe_url = addslashes($url);
        echo "UPDATE room_booking_db.rooms SET image_url = '$safe_url' WHERE name = '$safe_name';\n";
    } else {
        fwrite(STDERR, "FAILED to upload $filename — check your IAM role has s3:PutObject on this bucket.\n");
    }
}
