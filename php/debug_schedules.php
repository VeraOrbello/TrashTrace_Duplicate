<?php
require_once "../config.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$user_barangay = $_SESSION['barangay'] ?? null;
$user_zone = $_SESSION['zone'] ?? null;
$month = $_GET['month'] ?? date('Y-m');

$result = [];

$result['session'] = [
    'loggedin' => $_SESSION['loggedin'] ?? false,
    'barangay_raw' => $user_barangay,
    'zone_raw' => $user_zone,
    'barangay_norm' => $user_barangay !== null ? mb_strtolower(trim($user_barangay)) : null,
    'zone_norm' => $user_zone !== null ? mb_strtolower(trim($user_zone)) : null,
    'month' => $month
];

try {
    $queries = [];

    $sql1 = "SELECT * FROM pickup_schedules WHERE barangay = :barangay AND DATE_FORMAT(schedule_date, '%Y-%m') = :month ORDER BY schedule_date";
    $stmt1 = $pdo->prepare($sql1);
    $stmt1->bindParam(':barangay', $user_barangay);
    $stmt1->bindParam(':month', $month);
    $stmt1->execute();
    $queries['exact_match'] = $stmt1->fetchAll(PDO::FETCH_ASSOC);

    $barangay_norm = $user_barangay !== null ? mb_strtolower(trim($user_barangay)) : null;
    $sql2 = "SELECT * FROM pickup_schedules WHERE LOWER(TRIM(barangay)) = :barangay AND DATE_FORMAT(schedule_date, '%Y-%m') = :month ORDER BY schedule_date";
    $stmt2 = $pdo->prepare($sql2);
    $stmt2->bindParam(':barangay', $barangay_norm);
    $stmt2->bindParam(':month', $month);
    $stmt2->execute();
    $queries['normalized_match'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    $like_param = $user_barangay !== null ? '%' . $user_barangay . '%' : null;
    $sql3 = "SELECT * FROM pickup_schedules WHERE barangay LIKE :barangay AND DATE_FORMAT(schedule_date, '%Y-%m') = :month ORDER BY schedule_date";
    $stmt3 = $pdo->prepare($sql3);
    $stmt3->bindParam(':barangay', $like_param);
    $stmt3->bindParam(':month', $month);
    $stmt3->execute();
    $queries['like_match'] = $stmt3->fetchAll(PDO::FETCH_ASSOC);

    $sql4 = "SELECT id, barangay, zone, schedule_date, created_at FROM pickup_schedules ORDER BY created_at DESC LIMIT 20";
    $stmt4 = $pdo->query($sql4);
    $recent = $stmt4->fetchAll(PDO::FETCH_ASSOC);

    $result['queries'] = $queries;
    $result['recent'] = $recent;
} catch (Exception $e) {
    $result['error'] = $e->getMessage();
}

echo json_encode($result, JSON_PRETTY_PRINT);

exit;
