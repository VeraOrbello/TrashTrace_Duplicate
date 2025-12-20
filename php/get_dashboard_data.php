<?php
require_once "config.php";

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_type"] !== 'admin'){
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_GET['user_id'] ?? $_SESSION["id"];
$user_barangay = $_GET['barangay'] ?? ($_SESSION["barangay"] ?? '');

$pending_applications = 0;
$sql = "SELECT COUNT(*) as count FROM worker_applications wa JOIN users u ON wa.user_id = u.id WHERE (wa.status = 'pending' OR LOWER(TRIM(u.user_type)) LIKE '%pending%') AND LOWER(TRIM(u.barangay)) = LOWER(TRIM(:barangay))";
if($stmt = $pdo->prepare($sql)){
    $stmt->bindParam(':barangay', $user_barangay, PDO::PARAM_STR);
    if($stmt->execute()){
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $pending_applications = $row['count'];
    }
    $stmt->closeCursor();
}

$today = date('Y-m-d');
$todays_pickups = [
    'total' => 0,
    'scheduled' => 0,
    'completed' => 0,
    'delayed' => 0
];

$sql = "SELECT COUNT(*) as count FROM pickup_schedules WHERE schedule_date = :today";
if($stmt = $pdo->prepare($sql)){
    $stmt->bindParam(":today", $today, PDO::PARAM_STR);
    if($stmt->execute()){
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $todays_pickups['total'] = $row['count'];
        $todays_pickups['scheduled'] = $row['count'];
    }
    $stmt->closeCursor();
}

$sql = "SELECT COUNT(*) as count FROM pickup_schedules WHERE schedule_date = :today AND status = 'Completed'";
if($stmt = $pdo->prepare($sql)){
    $stmt->bindParam(":today", $today, PDO::PARAM_STR);
    if($stmt->execute()){
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $todays_pickups['completed'] = $row['count'];
    }
    $stmt->closeCursor();
}

$sql = "SELECT COUNT(*) as count FROM pickup_schedules WHERE schedule_date = :today AND status = 'Delayed'";
if($stmt = $pdo->prepare($sql)){
    $stmt->bindParam(":today", $today, PDO::PARAM_STR);
    if($stmt->execute()){
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $todays_pickups['delayed'] = $row['count'];
    }
    $stmt->closeCursor();
}

$last_check = date('Y-m-d H:i:s', strtotime('-30 seconds'));
$new_notifications = [];
$sql = "SELECT * FROM notifications 
        WHERE (barangay = :barangay OR barangay IS NULL) 
        AND created_at > :last_check 
        ORDER BY created_at DESC 
        LIMIT 3";
if($stmt = $pdo->prepare($sql)){
    $stmt->bindParam(":barangay", $user_barangay, PDO::PARAM_STR);
    $stmt->bindParam(":last_check", $last_check, PDO::PARAM_STR);
    if($stmt->execute()){
        $new_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    $stmt->closeCursor();
}

echo json_encode([
    'success' => true,
    'pending_applications' => $pending_applications,
    'todays_pickups' => $todays_pickups,
    'new_notifications' => $new_notifications,
    'last_updated' => date('Y-m-d H:i:s')
]);
?>