<?php
require_once "config.php";

$user_ok = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
if(!$user_ok){
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_barangay = $_SESSION['barangay'] ?? '';
$sql = "SELECT COUNT(*) as count FROM worker_applications wa JOIN users u ON wa.user_id = u.id WHERE wa.status = 'pending' AND LOWER(TRIM(u.barangay)) = LOWER(TRIM(:barangay))";
$count = 0;

if($stmt = $pdo->prepare($sql)){
    $stmt->bindParam(':barangay', $user_barangay, PDO::PARAM_STR);
    if($stmt->execute()){
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = $row['count'];
    }
    $stmt->closeCursor();
}

echo json_encode(['success' => true, 'count' => $count]);
?>