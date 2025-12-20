<?php
require_once "config.php";

session_start();
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION["id"];
$user_barangay = $_SESSION["barangay"] ?? '';


$sql = "SELECT COUNT(*) as new_count FROM notifications 
        WHERE (user_id = :user_id OR (user_id IS NULL AND barangay = :barangay))
        AND is_read = 0";
        
if($stmt = $pdo->prepare($sql)){
    $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    $stmt->bindParam(":barangay", $user_barangay, PDO::PARAM_STR);
    
    if($stmt->execute()){
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $new_count = $result['new_count'] ?? 0;
    }
    $stmt->closeCursor();
}

echo json_encode([
    'success' => true,
    'new_count' => $new_count,
    'current_time' => date('Y-m-d H:i:s')
]);
?>