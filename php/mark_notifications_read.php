<?php
require_once "config.php";

session_start();
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION["id"];
$action = $_POST['action'] ?? '';

if($action === 'mark_all') {
    $sql = "UPDATE notifications SET is_read = 1
            WHERE (user_id = :user_id OR (user_id IS NULL AND barangay = :barangay))
            AND is_read = 0";
    
    if($stmt = $pdo->prepare($sql)){
        $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->bindParam(":barangay", $_POST['barangay'], PDO::PARAM_STR);
        
        if($stmt->execute()){
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
    }
} elseif($action === 'mark_single' && isset($_POST['notification_id'])) {
    // Mark single notification as read
    $sql = "UPDATE notifications SET is_read = 1 WHERE id = :id";
    
    if($stmt = $pdo->prepare($sql)){
        $stmt->bindParam(":id", $_POST['notification_id'], PDO::PARAM_INT);
        
        if($stmt->execute()){
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
    }
}
?>