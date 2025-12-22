<?php
require_once "config.php";

header('Content-Type: application/json');

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

if($_SESSION["user_type"] !== 'driver'){
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$driver_id = $_SESSION["id"];

// Get JSON input
$data = json_decode(file_get_contents('php://input'), true);

if(!$data) {
    echo json_encode(['success' => false, 'message' => 'No data received']);
    exit;
}

$assignment_id = $data['assignment_id'] ?? null;
$status = $data['status'] ?? null;
$assignment_type = $data['assignment_type'] ?? 'assignment';

if(!$assignment_id || !$status) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit;
}

try {
    // Use the sync system to update
    require_once "sync_driver_assignments.php";
    
    // Prepare data for the sync function
    $_POST = [
        'action' => 'update_assignment_status',
        'assignment_id' => $assignment_id,
        'status' => $status,
        'assignment_type' => $assignment_type,
        'driver_id' => $driver_id
    ];
    
    // Call the sync function
    updateAssignmentStatus();
    
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>