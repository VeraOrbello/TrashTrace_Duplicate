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
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if(!$user_id){
    echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
    exit;
}

// Get admin application details (user with admin_pending status)
$sql = "SELECT u.*, wa.id_number, wa.contact_number, wa.city, wa.barangay, wa.zone,
               wa.experience_years, wa.availability, wa.vehicle_access, wa.submitted_at
        FROM users u
        LEFT JOIN worker_applications wa ON u.id = wa.user_id
        WHERE u.id = ? AND u.user_type = 'admin_pending' AND u.barangay = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id, $user_barangay]);
$application = $stmt->fetch(PDO::FETCH_ASSOC);

if($application){
    echo json_encode([
        'success' => true,
        'application' => $application
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Application not found']);
}
?>
