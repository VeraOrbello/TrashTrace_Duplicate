<?php
session_start();
require_once 'config.php';

echo "Session Debug Information:\n";
echo "========================\n\n";

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo "User is not logged in!\n";
    exit;
}

echo "User ID: " . ($_SESSION['user_id'] ?? 'NOT SET') . "\n";
echo "Username: " . ($_SESSION['username'] ?? 'NOT SET') . "\n";
echo "Full Name: " . ($_SESSION['full_name'] ?? 'NOT SET') . "\n";
echo "User Type: " . ($_SESSION['user_type'] ?? 'NOT SET') . "\n";
echo "Barangay: " . ($_SESSION['barangay'] ?? 'NOT SET') . "\n";
echo "Zone: " . ($_SESSION['zone'] ?? 'NOT SET') . "\n\n";

// Test the notification query with current session
$user_id = $_SESSION['user_id'] ?? 0;
$user_barangay = $_SESSION['barangay'] ?? '';

echo "Testing notification query:\n";
echo "User ID: $user_id\n";
echo "User Barangay: '$user_barangay'\n\n";

$query = "SELECT n.id, n.title, n.user_id, n.barangay, n.created_at
          FROM notifications n
          WHERE (n.user_id = ? OR (n.user_id IS NULL AND n.barangay = ?))
          ORDER BY n.created_at DESC LIMIT 5";

if($stmt = $link->prepare($query)){
    $stmt->bind_param("is", $user_id, $user_barangay);
    if($stmt->execute()){
        $result = $stmt->get_result();
        echo "Notifications found: " . $result->num_rows . "\n\n";
        if($result->num_rows > 0){
            echo "Recent notifications:\n";
            while($row = $result->fetch_assoc()){
                echo "- ID: {$row['id']}, Title: {$row['title']}, User ID: " . ($row['user_id'] ?? 'NULL') . ", Barangay: " . ($row['barangay'] ?? 'NULL') . "\n";
            }
        } else {
            echo "No notifications found for this user/barangay combination.\n";
        }
    } else {
        echo "Query execution failed.\n";
    }
} else {
    echo "Query preparation failed.\n";
}
?>
