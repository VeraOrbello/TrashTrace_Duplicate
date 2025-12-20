<?php
require_once "config.php";

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

// Only admins can access this page
if($_SESSION["user_type"] !== 'admin'){
    header("location: dashboard.php");
    exit;
}

// Fetch all pending driver applications
$sql = "SELECT da.*, u.full_name, u.email, u.mobile_number, u.city, u.barangay 
        FROM driver_applications da 
        JOIN users u ON da.user_id = u.id 
        WHERE da.status = 'pending'
        ORDER BY da.application_date DESC";

$applications = [];
if($stmt = $pdo->prepare($sql)){
    if($stmt->execute()){
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    $stmt->closeCursor();
}

// Handle approval/rejection
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])){
    $application_id = $_POST['application_id'] ?? 0;
    $action = $_POST['action']; // 'approve' or 'reject'
    
    if($action == 'approve'){
        // Get application details
        $sql = "SELECT da.* FROM driver_applications da WHERE da.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$application_id]);
        $app = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($app){
            // Update user to driver
            $sql = "UPDATE users SET user_type = 'driver' WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$app['user_id']]);
            
            // Create driver profile
            $sql = "INSERT INTO driver_profiles (driver_id, license_number, vehicle_type, vehicle_plate, status) 
                    VALUES (?, ?, ?, ?, 'active')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $app['user_id'],
                $app['license_number'],
                $app['vehicle_type'],
                $app['vehicle_plate']
            ]);
            
            // Update application status
            $sql = "UPDATE driver_applications SET status = 'approved', reviewed_at = NOW(), reviewed_by = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$_SESSION['id'], $application_id]);
            
            $success_message = "Driver application approved successfully!";
        }
    } else if($action == 'reject'){
        $sql = "UPDATE driver_applications SET status = 'rejected', reviewed_at = NOW(), reviewed_by = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$_SESSION['id'], $application_id]);
        
        $success_message = "Driver application rejected.";
    }
    
    header("location: driver_applications.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Applications - TrashTrace</title>
    <link rel="stylesheet" href="css/barangay_applications.css">
    <link href="node_modules/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <!-- Similar to barangay_applications.php but for drivers -->
</body>
</html>