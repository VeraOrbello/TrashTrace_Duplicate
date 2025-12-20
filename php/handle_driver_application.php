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
$application_id = $input['id'] ?? 0;
$action = $input['action'] ?? '';

if(!$application_id || !in_array($action, ['approve', 'reject'])){
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

if($action == 'approve'){
    // Get driver application details
    $sql = "SELECT da.*, u.id as user_id
            FROM driver_applications da
            JOIN users u ON da.user_id = u.id
            WHERE da.id = ? AND u.barangay = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$application_id, $user_barangay]);
    $driver_app = $stmt->fetch(PDO::FETCH_ASSOC);

    if($driver_app){
        // Update user to driver
        $sql = "UPDATE users SET user_type = 'driver' WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$driver_app['user_id']]);

        // Create driver profile
        $sql = "INSERT INTO driver_profiles (driver_id, license_number, vehicle_type, vehicle_plate, status)
                VALUES (?, ?, ?, ?, 'active')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $driver_app['user_id'],
            $driver_app['license_number'],
            $driver_app['vehicle_type'],
            $driver_app['vehicle_plate']
        ]);

        // Update application status
        $sql = "UPDATE driver_applications SET status = 'approved', reviewed_at = NOW(), reviewed_by = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$_SESSION['id'], $application_id]);

        echo json_encode(['success' => true, 'status' => 'approved']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Application not found']);
    }
} else if($action == 'reject'){
    // Get driver application details to reset user type
    $sql = "SELECT da.user_id FROM driver_applications da WHERE da.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$application_id]);
    $driver_app = $stmt->fetch(PDO::FETCH_ASSOC);

    if($driver_app){
        // Reset user to regular user
        $sql = "UPDATE users SET user_type = 'user' WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$driver_app['user_id']]);
    }

    // Update application status
    $sql = "UPDATE driver_applications SET status = 'rejected', reviewed_at = NOW(), reviewed_by = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_SESSION['id'], $application_id]);

    echo json_encode(['success' => true, 'status' => 'rejected']);
}
?>
