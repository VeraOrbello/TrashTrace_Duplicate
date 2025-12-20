<?php
require_once "../config.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo json_encode([]);
    exit;
}

$user_barangay = $_SESSION["barangay"] ?? '';
$user_zone = $_SESSION["zone"] ?? '';
$user_type = $_SESSION["user_type"] ?? 'user';
$month = isset($_GET['month']) && $_GET['month'] !== '' ? $_GET['month'] : date('Y-m');
$requested_barangay = (isset($_GET['barangay']) && $_GET['barangay'] !== '') ? $_GET['barangay'] : $user_barangay;
$requested_zone = (isset($_GET['zone']) && $_GET['zone'] !== '') ? $_GET['zone'] : $user_zone;

$barangay_to_use = !empty($requested_barangay) ? $requested_barangay : $user_barangay;
$zone_to_use = !empty($requested_zone) ? $requested_zone : $user_zone;

if (empty($barangay_to_use)) {
    $user_id = $_SESSION['id'] ?? null;
    if ($user_id) {
        try {
            $uSql = "SELECT barangay, zone FROM users WHERE id = :id LIMIT 1";
            $uStmt = $pdo->prepare($uSql);
            $uStmt->bindParam(':id', $user_id, PDO::PARAM_INT);
            $uStmt->execute();
            $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);
            if ($uRow && !empty($uRow['barangay'])) {
                $barangay_to_use = $uRow['barangay'];
                $zone_to_use = $uRow['zone'] ?? $zone_to_use;
            }
        } catch (Exception $e) {
            error_log('User lookup failed in get_schedules.php: ' . $e->getMessage());
        }
    }

    if (empty($barangay_to_use)) {
        echo json_encode([]);
        exit;
    }
}

try {
        $barangay_param = mb_strtolower(trim($barangay_to_use));

        $sql = "SELECT * FROM pickup_schedules 
            WHERE LOWER(TRIM(barangay)) = :barangay 
            AND DATE_FORMAT(schedule_date, '%Y-%m') = :month 
            ORDER BY schedule_date";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(":barangay", $barangay_param);
        $stmt->bindParam(":month", $month);

    $stmt->execute();
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($schedules);
} catch (Exception $e) {
    error_log("Error in get_schedules.php: " . $e->getMessage());
    echo json_encode([]);
}

exit;
?>