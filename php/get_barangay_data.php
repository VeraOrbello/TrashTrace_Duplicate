<?php
require_once "config.php";

session_start();

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_barangay = $_SESSION["barangay"] ?? '';
$user_id = $_SESSION["id"] ?? 0;

$data = ['success' => true];

$today = date('Y-m-d');

$todays_stats = ['total' => 0, 'completed' => 0, 'delayed' => 0];

$sql = "SELECT COUNT(*) as count FROM pickup_schedules WHERE barangay = :barangay AND schedule_date = :today";
if($stmt = $pdo->prepare($sql)){
    $stmt->bindParam(":barangay", $user_barangay, PDO::PARAM_STR);
    $stmt->bindParam(":today", $today, PDO::PARAM_STR);
    if($stmt->execute()){
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $todays_stats['total'] = $row['count'];
    }
}

$sql = "SELECT COUNT(*) as count FROM pickup_schedules WHERE barangay = :barangay AND schedule_date = :today AND status = 'Completed'";
if($stmt = $pdo->prepare($sql)){
    $stmt->bindParam(":barangay", $user_barangay, PDO::PARAM_STR);
    $stmt->bindParam(":today", $today, PDO::PARAM_STR);
    if($stmt->execute()){
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $todays_stats['completed'] = $row['count'];
    }
}

$sql = "SELECT COUNT(*) as count FROM pickup_schedules WHERE barangay = :barangay AND schedule_date = :today AND status = 'Delayed'";
if($stmt = $pdo->prepare($sql)){
    $stmt->bindParam(":barangay", $user_barangay, PDO::PARAM_STR);
    $stmt->bindParam(":today", $today, PDO::PARAM_STR);
    if($stmt->execute()){
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $todays_stats['delayed'] = $row['count'];
    }
}

$data['todays_stats'] = $todays_stats;

$pending_sql = "SELECT COUNT(*) as count FROM worker_applications wa 
                JOIN users u ON wa.user_id = u.id 
                WHERE (wa.status = 'pending' OR LOWER(TRIM(u.user_type)) LIKE '%pending%') 
                AND LOWER(TRIM(u.barangay)) = LOWER(TRIM(:barangay))";

if($stmt = $pdo->prepare($pending_sql)){
    $stmt->bindParam(":barangay", $user_barangay, PDO::PARAM_STR);
    if($stmt->execute()){
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $data['pending_applications'] = $row['count'];
    }
}

$unread_sql = "SELECT COUNT(*) as count FROM notifications 
               WHERE (barangay = :barangay OR barangay IS NULL)
               AND is_read = 0";

if($stmt = $pdo->prepare($unread_sql)){
    $stmt->bindParam(":barangay", $user_barangay, PDO::PARAM_STR);
    if($stmt->execute()){
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $data['unread_count'] = $row['count'];
    }
}

$data['last_updated'] = date('Y-m-d H:i:s');

echo json_encode($data);
?>