<?php
session_start();
require_once "config.php";

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_type"] !== 'driver'){
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if(!$data) {
    echo json_encode(['error' => 'No data received']);
    exit;
}

try {
    $driver_id = $_SESSION["id"];
    $driver_name = $_SESSION["full_name"];
    
    // Extract data
    $assignment_id = $data['assignment_id'] ?? null;
    $route_name = $data['route_name'] ?? 'Daily Route';
    $barangay = $data['barangay'] ?? 'Cebu City';
    $zones = $data['zones'] ?? '';
    $start_point = $data['start_point'] ?? 'Start Location';
    $end_point = $data['end_point'] ?? 'End Location';
    $distance_km = $data['distance_km'] ?? 0;
    $estimated_time_minutes = $data['estimated_time_minutes'] ?? 0;
    $actual_time_minutes = $data['actual_time_minutes'] ?? 0;
    $total_stops = $data['total_stops'] ?? 0;
    $completed_stops = $data['completed_stops'] ?? 0;
    $total_weight = $data['total_weight'] ?? 0;
    $collections_count = $data['collections_count'] ?? 0;
    $driver_rating = $data['driver_rating'] ?? 4.5;
    $notes = $data['notes'] ?? 'Completed via driver app';
    
    // Calculate collection amount (₱5 per kg)
    $total_amount = $total_weight * 5;
    
    // Save to routes_history
    $sql = "INSERT INTO routes_history (
        assignment_id, driver_id, route_name, barangay, zones,
        start_point, end_point, distance_km, estimated_time_minutes,
        actual_time_minutes, total_stops, completed_stops, total_weight,
        collections_count, driver_rating, notes, completed_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "issssssddiiiddds",
        $assignment_id, $driver_id, $route_name, $barangay, $zones,
        $start_point, $end_point, $distance_km, $estimated_time_minutes,
        $actual_time_minutes, $total_stops, $completed_stops, $total_weight,
        $collections_count, $driver_rating, $notes
    );
    
    if(mysqli_stmt_execute($stmt)) {
        $history_id = mysqli_insert_id($link);
        
        // Update driver_daily_assignments status
        if($assignment_id) {
            $updateAssignment = "UPDATE driver_daily_assignments SET 
                status = 'completed',
                completed_at = NOW(),
                completed_stops = ?,
                actual_time_minutes = ?,
                collections_weight = ?
                WHERE id = ?";
            
            $updateStmt = mysqli_prepare($link, $updateAssignment);
            mysqli_stmt_bind_param($updateStmt, "iidi",
                $completed_stops,
                $actual_time_minutes,
                $total_weight,
                $assignment_id
            );
            mysqli_stmt_execute($updateStmt);
            mysqli_stmt_close($updateStmt);
        }
        
        // Update pickup_schedules if applicable
        if($assignment_id) {
            $updatePickup = "UPDATE pickup_schedules ps
                JOIN pickup_assignments pa ON ps.id = pa.pickup_id
                JOIN driver_daily_assignments da ON pa.driver_daily_assignment_id = da.id
                SET ps.status = 'completed',
                    ps.completed_at = NOW()
                WHERE da.id = ?";
            
            $pickupStmt = mysqli_prepare($link, $updatePickup);
            mysqli_stmt_bind_param($pickupStmt, "i", $assignment_id);
            mysqli_stmt_execute($pickupStmt);
            mysqli_stmt_close($pickupStmt);
        }
        
        // Update driver_profile statistics
        $updateProfile = "UPDATE driver_profiles SET 
            total_collections = total_collections + ?,
            total_weight = total_weight + ?,
            updated_at = NOW()
            WHERE driver_id = ?";
        
        $profileStmt = mysqli_prepare($link, $updateProfile);
        mysqli_stmt_bind_param($profileStmt, "ddi", $collections_count, $total_weight, $driver_id);
        mysqli_stmt_execute($profileStmt);
        mysqli_stmt_close($profileStmt);
        
        // Update all waypoints for this assignment as collected
        if($assignment_id) {
            $updateWaypoints = "UPDATE route_waypoints SET 
                status = 'collected',
                collected_at = NOW(),
                weight_kg = ROUND(RAND() * 25 + 15, 2)
                WHERE assignment_id = ? AND status != 'collected'";
            
            $waypointsStmt = mysqli_prepare($link, $updateWaypoints);
            mysqli_stmt_bind_param($waypointsStmt, "i", $assignment_id);
            mysqli_stmt_execute($waypointsStmt);
            mysqli_stmt_close($waypointsStmt);
        }
        
        // Store in session for immediate access
        $_SESSION['completed_routes'][] = [
            'id' => $history_id,
            'route_name' => $route_name,
            'barangay' => $barangay,
            'start_point' => $start_point,
            'end_point' => $end_point,
            'distance_km' => $distance_km,
            'estimated_time' => $estimated_time_minutes . ' min',
            'actual_time' => $actual_time_minutes . ' min',
            'collections_count' => $collections_count,
            'total_weight' => $total_weight,
            'total_amount' => $total_amount,
            'completed_stops' => $completed_stops,
            'total_stops' => $total_stops,
            'status' => 'completed',
            'type' => 'route',
            'completed_at' => date('Y-m-d H:i:s'),
            'driver_rating' => $driver_rating
        ];
        
        echo json_encode([
            'success' => true,
            'message' => 'Route saved to history',
            'history_id' => $history_id,
            'total_amount' => $total_amount
        ]);
    } else {
        throw new Exception(mysqli_error($link));
    }
    
} catch(Exception $e) {
    error_log("Error saving route history: " . $e->getMessage());
    echo json_encode(['error' => 'Failed to save route history: ' . $e->getMessage()]);
}
?>