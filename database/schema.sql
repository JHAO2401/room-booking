-- Discussion Room Booking System — Database Schema
-- Run this against your MySQL instance (e.g. AWS RDS for MySQL).
--
-- IMPORTANT: "CREATE DATABASE/TABLE IF NOT EXISTS" does NOT reset or wipe
-- existing data. If you want a truly clean slate, run
-- `DROP DATABASE room_booking_db;` before this file. Otherwise this file
-- is safe to run repeatedly on top of existing data without duplicating
-- rooms ever again.

CREATE DATABASE IF NOT EXISTS room_booking_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE room_booking_db;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    user_type ENUM('student', 'faculty') NOT NULL DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    location VARCHAR(150) NOT NULL,
    capacity INT NOT NULL DEFAULT 4,
    description TEXT,
    amenities VARCHAR(255),
    image_url VARCHAR(255),
    is_public TINYINT(1) NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    user_id INT NOT NULL,
    booking_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    purpose VARCHAR(255),
    attendees INT DEFAULT 1,
    status ENUM('pending', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_room_date (room_id, booking_date)
) ENGINE=InnoDB;

-- STEP 1: clean up duplicate rooms FIRST (must happen before adding the
-- unique constraint below). Any bookings sitting on a duplicate row are
-- moved to the row being kept, so no booking history is lost.
UPDATE bookings b
JOIN rooms dup  ON b.room_id = dup.id
JOIN rooms keep ON keep.name = dup.name AND keep.id < dup.id
SET b.room_id = keep.id;

DELETE dup FROM rooms dup
JOIN rooms keep ON keep.name = dup.name AND keep.id < dup.id;

-- STEP 2: add the unique constraint so duplicates can never happen again.
-- Uses PREPARE/EXECUTE (not a stored procedure) so it also works on AWS
-- RDS without needing extra privileges.
SET @stmt := (
    SELECT IF(
        (SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rooms' AND INDEX_NAME = 'uniq_room_name') = 0,
        'ALTER TABLE rooms ADD UNIQUE KEY uniq_room_name (name)',
        'SELECT 1'
    )
);
PREPARE stmt FROM @stmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- STEP 3: seed data (safe to re-run — INSERT IGNORE skips rows that
-- already exist now that the unique constraint is in place)

-- Default admin account: email admin@gmail.com / password Admin@123
-- (this hash is for "Admin@123", NOT "admin123" — if you want the
--  password to actually be admin123, run on your EC2:
--    php database/generate_admin_hash.php "admin123"
--  and paste the new hash below instead.)
INSERT IGNORE INTO users (name, email, password_hash, role, user_type) VALUES
('Admin', 'admin@gmail.com', '$2y$10$o7a/a0t5lH59JGP1XsADsOomVK5W2xH91nEKrL05jqBlBrg..j7Yy', 'admin', 'faculty');

INSERT IGNORE INTO rooms (name, location, capacity, description, amenities, image_url, is_public, is_active) VALUES
('Discussion Room A', '2nd Floor, East Wing', 6, 'Cosy room for small team discussions.', 'Whiteboard,TV Screen,Wi-Fi', '/images/rooms/discussion_room_a.jpg', 1, 1),
('Discussion Room B', '2nd Floor, East Wing', 10, 'Mid-size room with a round table, good for workshops.', 'Projector,Whiteboard,Video Call,Wi-Fi', '/images/rooms/discussion_room_b.jpg', 1, 1),
('Boardroom', '5th Floor, West Wing', 16, 'Large boardroom for formal meetings and presentations.', 'Projector,Video Call,Sound System,Wi-Fi', '/images/rooms/boardroom.jpg', 0, 1),
('Focus Pod 1', '1st Floor, Lobby', 2, 'Small pod for 1-on-1 calls or quiet focus work.', 'Wi-Fi,Power Outlets', '/images/rooms/focus_pod_1.jpg', 1, 1);

-- STEP 4: fix any image paths missing the leading "/" (relative paths
-- break on any page not at the site root, e.g. /booking/room.php)
UPDATE rooms
SET image_url = CONCAT('/', image_url)
WHERE image_url IS NOT NULL AND image_url NOT LIKE '/%';