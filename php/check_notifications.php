<?php
require_once "config.php";
session_start();

header('Content-Type: application/json');

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION["id"];
$user_barangay = $_SESSION["barangay"] ?? '';
$user_zone = $_SESSION["zone"] ?? '';
$last_check = $_POST['last_check'] ?? date('Y-m-d H:i:s', strtotime('-1 hour'));

try {
    $sql = "SELECT n.* 
            FROM notifications n
            WHERE (n.user_id = :user_id OR n.barangay = :barangay OR n.zone = :zone)
            AND n.created_at > :last_check
            ORDER BY n.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    $stmt->bindParam(":barangay", $user_barangay, PDO::PARAM_STR);
    $stmt->bindParam(":zone", $user_zone, PDO::PARAM_STR);
    $stmt->bindParam(":last_check", $last_check, PDO::PARAM_STR);
    $stmt->execute();
    
    $new_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
        'new_notifications' => $new_notifications,
        'unread_count' => $unread_count,
        'last_check' => date('Y-m-d H:i:s')
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
}
