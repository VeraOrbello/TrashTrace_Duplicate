<?php
require_once "config.php";

header('Content-Type: application/json');

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}


$input = json_decode(file_get_contents('php://input'), true);
$user_id = $input['user_id'] ?? null;
$barangay = $input['barangay'] ?? null;

if(!$user_id && isset($_SESSION['id'])){
    $user_id = $_SESSION['id'];
}

if(!$barangay && isset($_SESSION['barangay'])){
    $barangay = $_SESSION['barangay'];
}

try{
    if($user_id){
        $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = :user_id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->execute();
    }

    if($barangay){
        $sql2 = "UPDATE notifications SET is_read = 1 WHERE user_id IS NULL AND barangay = :barangay";
        $stmt2 = $pdo->prepare($sql2);
        $stmt2->bindParam(":barangay", $barangay, PDO::PARAM_STR);
        $stmt2->execute();
    }

    echo json_encode(['success' => true]);
} catch(PDOException $e){
    echo json_encode(['success' => false, 'error' => 'Database error', 'message' => $e->getMessage()]);
}

unset($pdo);
?>