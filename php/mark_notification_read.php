<?php
require_once "config.php";
session_start();

header('Content-Type: application/json');

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$notification_id = $_POST['notification_id'] ?? 0;
$user_id = $_SESSION["id"];
$user_barangay = $_SESSION["barangay"] ?? '';
$user_zone = $_SESSION["zone"] ?? '';

if(empty($notification_id) || $notification_id == 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid notification ID']);
    exit;
}

try {
    
    $update_sql = "UPDATE notifications 
                   SET is_read = 1, 
                       read_at = NOW() 
                   WHERE id = :id 
                   AND (user_id = :user_id OR barangay = :barangay OR zone = :zone)";
    
    $update_stmt = $pdo->prepare($update_sql);
    $update_stmt->bindParam(":id", $notification_id, PDO::PARAM_INT);
    $update_stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    $update_stmt->bindParam(":barangay", $user_barangay, PDO::PARAM_STR);
    $update_stmt->bindParam(":zone", $user_zone, PDO::PARAM_STR);
    $update_stmt->execute();
    
   
    $count_sql = "SELECT COUNT(*) as unread_count 
                  FROM notifications 
                  WHERE (user_id = :user_id OR barangay = :barangay OR zone = :zone)
                  AND is_read = 0";
    
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    $count_stmt->bindParam(":barangay", $user_barangay, PDO::PARAM_STR);
    $count_stmt->bindParam(":zone", $user_zone, PDO::PARAM_STR);
    $count_stmt->execute();
    
    $unread_data = $count_stmt->fetch(PDO::FETCH_ASSOC);
    $unread_count = $unread_data['unread_count'] ?? 0;
    
    echo json_encode([
        'success' => true,
        'message' => 'Notification marked as read',
        'unread_count' => $unread_count
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
}