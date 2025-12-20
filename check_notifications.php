<?php
require_once 'config.php';

$query = 'SELECT COUNT(*) as total, COUNT(CASE WHEN user_id IS NULL THEN 1 END) as barangay_notifications, COUNT(CASE WHEN user_id IS NOT NULL THEN 1 END) as user_notifications FROM notifications';
$result = $link->query($query);

if ($result) {
    $row = $result->fetch_assoc();
    echo 'Total notifications: ' . $row['total'] . PHP_EOL;
    echo 'Barangay notifications (user_id IS NULL): ' . $row['barangay_notifications'] . PHP_EOL;
    echo 'User notifications (user_id IS NOT NULL): ' . $row['user_notifications'] . PHP_EOL;

    // Also check recent notifications
    $recent_query = 'SELECT id, title, user_id, barangay, created_at FROM notifications ORDER BY created_at DESC LIMIT 5';
    $recent_result = $link->query($recent_query);
    if ($recent_result && $recent_result->num_rows > 0) {
        echo PHP_EOL . 'Recent notifications:' . PHP_EOL;
        while ($row = $recent_result->fetch_assoc()) {
            echo '- ID: ' . $row['id'] . ', Title: ' . $row['title'] . ', User ID: ' . ($row['user_id'] ?? 'NULL') . ', Barangay: ' . ($row['barangay'] ?? 'NULL') . ', Created: ' . $row['created_at'] . PHP_EOL;
        }
    }
} else {
    echo 'No notifications table or query failed' . PHP_EOL;
}
?>
