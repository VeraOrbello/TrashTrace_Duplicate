<?php
require_once "config.php";

header('Content-Type: application/json');

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION["id"];
$user_barangay = $_SESSION["barangay"] ?? '';
$page = $_GET['page'] ?? 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$notifications = [];

$sql = "SELECT * FROM notifications WHERE user_id = :user_id OR (user_id IS NULL AND barangay = :barangay) ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
if($stmt = $pdo->prepare($sql)){
    $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    $stmt->bindParam(":barangay", $user_barangay, PDO::PARAM_STR);
    $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
    $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
    
    if($stmt->execute()){
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($stmt);
}

echo json_encode(['notifications' => $notifications]);
unset($pdo);
?>