<?php
require_once "../config.php";

header('Content-Type: application/json');

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

if($_SESSION["user_type"] !== 'admin'){
    echo json_encode(['success' => false, 'error' => 'Not authorized']);
    exit;
}

$user_barangay = $_SESSION["barangay"] ?? '';

$input = json_decode(file_get_contents('php://input'), true);
$user_id = $input['id'] ?? 0;
$action = $input['action'] ?? '';

if(!$user_id || !in_array($action, ['approve', 'reject'])){
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

if($action == 'approve'){
    // Update user to admin
    $sql = "UPDATE users SET user_type = 'admin' WHERE id = ? AND barangay = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $user_barangay]);

    if($stmt->rowCount() > 0){
        echo json_encode(['success' => true, 'status' => 'approved']);
    } else {
        echo json_encode(['success' => false, 'error' => 'User not found or not in your barangay']);
    }
} else if($action == 'reject'){
    // Update user back to regular user
    $sql = "UPDATE users SET user_type = 'user' WHERE id = ? AND barangay = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $user_barangay]);

    if($stmt->rowCount() > 0){
        echo json_encode(['success' => true, 'status' => 'rejected']);
    } else {
        echo json_encode(['success' => false, 'error' => 'User not found or not in your barangay']);
    }
}
?>
