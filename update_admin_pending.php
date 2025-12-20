<?php
require_once "config.php";

try {
    $sql = "ALTER TABLE users MODIFY COLUMN user_type ENUM('user','driver_pending','driver','admin_pending','admin') DEFAULT 'user'";
    $pdo->exec($sql);
    echo "Successfully updated user_type enum to include 'admin_pending'.\n";
} catch(PDOException $e) {
    echo "Error updating database: " . $e->getMessage() . "\n";
}
?>
