<?php
require_once "config.php";

session_start();
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    echo json_encode(['success' => false]);
    exit;
}

$user_id = $_SESSION["id"];
$user_barangay = $_SESSION["barangay"] ?? '';

$notifications = [];
$sql = "SELECT * FROM notifications 
        WHERE (user_id = :user_id OR (user_id IS NULL AND barangay = :barangay)) 
        ORDER BY created_at DESC 
        LIMIT 20";
        
if($stmt = $pdo->prepare($sql)){
    $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    $stmt->bindParam(":barangay", $user_barangay, PDO::PARAM_STR);
    
    if($stmt->execute()){
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($stmt);
}

$unread_count = 0;
foreach($notifications as $notification) {
    if(!$notification['is_read']) {
        $unread_count++;
    }
}

echo json_encode([
    'success' => true,
    'notifications' => $notifications,
    'stats' => [
        'total' => count($notifications),
        'unread' => $unread_count,
        'read' => count($notifications) - $unread_count
    ]
]);
?>