<?php
/**
 * One-off helper: run this from the command line to generate a password
 * hash you can paste into the `users` table for the admin account.
 *
 *   php database/generate_admin_hash.php "YourStrongPassword123"
 */
if ($argc < 2) {
    echo "Usage: php generate_admin_hash.php <password>\n";
    exit(1);
}
echo password_hash($argv[1], PASSWORD_DEFAULT) . "\n";
