<?php
require_once "config.php";
session_start();

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$notification_id = $data['notification_id'] ?? '';

if(empty($notification_id)) {
    echo json_encode(['success' => false, 'message' => 'No notification ID']);
    exit;
}

// Update notification as read
$sql = "UPDATE notifications SET is_read = 1 WHERE id = :id";
if($stmt = $pdo->prepare($sql)){
    $stmt->bindParam(":id", $notification_id, PDO::PARAM_INT);
    
    if($stmt->execute()){
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    
    $stmt->closeCursor();
}
?>