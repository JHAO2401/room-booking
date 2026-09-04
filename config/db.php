<?php
/**
 * Database connection.
 * On AWS this typically points at an RDS for MySQL endpoint, e.g.
 *   DB_HOST = mydb.xxxxxxxx.ap-southeast-1.rds.amazonaws.com
 * For local/dev testing it can point at localhost.
 *
 * Values are read from environment variables when available (recommended
 * for production — set these in your EC2 / Elastic Beanstalk environment),
 * and fall back to the defaults below for quick local testing.
 */

$DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
$DB_NAME = getenv('DB_NAME') ?: 'room_booking_db';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '';
$DB_PORT = getenv('DB_PORT') ?: '3306';

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,  
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    die('Database connection failed. Please try again later.');
}
