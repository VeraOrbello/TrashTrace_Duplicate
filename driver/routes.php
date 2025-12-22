<?php
session_start();
require_once "../config.php";

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: ../login.php");
    exit;
}

if($_SESSION["user_type"] !== 'driver'){
    header("location: ../dashboard.php");
    exit;
}

$driver_id = $_SESSION["id"];
$driver_name = $_SESSION["full_name"];

// Get driver's location (for demo, use Cebu City coordinates)
$default_lat = 10.3157;
$default_lng = 123.8854;

// Initialize or restore route progress from session
if(!isset($_SESSION['route_progress'])) {
    $_SESSION['route_progress'] = [];
}

// Initialize completed routes in session
if(!isset($_SESSION['completed_routes'])) {
    $_SESSION['completed_routes'] = [];
}

// Check for completed route parameter and update session
if(isset($_GET['completed'])) {
    $route_id = $_GET['completed'];
    
    // If this route isn't already marked as completed in session, mark it
    $is_already_completed = false;
    foreach($_SESSION['completed_routes'] as $route) {
        if($route['id'] == $route_id) {
            $is_already_completed = true;
            break;
        }
    }
    
    if(!$is_already_completed) {
        // This would normally come from database, but for demo, create sample data
        $_SESSION['completed_routes'][] = [
            'id' => $route_id,
            'route_name' => "Route #" . $route_id,
            'start_point' => "Start Location",
            'end_point' => "End Location",
            'distance_km' => rand(5, 15) . '.' . rand(0, 9),
            'estimated_time' => rand(90, 240) . ' min',
            'collections_count' => rand(8, 25),
            'total_weight' => rand(150, 800),
            'total_amount' => rand(150, 800) * 5,
            'zone_name' => 'Zone ' . chr(65 + ($route_id % 5)),
            'area' => 'Cebu City',
            'status' => 'completed',
            'type' => 'route',
            'completed_at' => date('Y-m-d H:i:s')
        ];
    }
}
// Handle route completion
if(isset($_POST['complete_route']) && isset($_POST['route_id'])) {
    $route_id = $_POST['route_id'];
    $route_data = json_decode($_POST['route_data'], true);
    
    // Store completed route in session
    $completed_route = [
        'id' => $route_id,
        'route_name' => $route_data['name'] ?? 'Unknown Route',
        'start_point' => $route_data['start'] ?? 'Unknown Start',
        'end_point' => $route_data['end'] ?? 'Unknown End',
        'distance_km' => str_replace(' km', '', $route_data['distance'] ?? '0'),
        'estimated_time' => $route_data['estimatedTime'] ?? '0 min',
        'collections_count' => $route_data['stops'] ?? 0,
        'total_weight' => rand(150, 800),
        'total_amount' => rand(150, 800) * 5,
        'zone_name' => 'Zone ' . chr(65 + ($route_id % 5)),
        'area' => 'Cebu City',
        'status' => 'completed',
        'type' => 'route',
        'completed_at' => date('Y-m-d H:i:s'),
        'driver_id' => $driver_id,
        'driver_name' => $driver_name
    ];
    
    $_SESSION['completed_routes'][] = $completed_route;
    
    // Save to database for permanent storage
    try {
        $sql = "INSERT INTO routes_history (route_id, route_name, start_point, end_point, distance, estimated_time, stops_count, driver_id, driver_name, completed_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        // Create variables for the bind parameters
        $route_name = $route_data['name'] ?? 'Unknown Route';
        $start_point = $route_data['start'] ?? 'Unknown Start';
        $end_point = $route_data['end'] ?? 'Unknown End';
        $distance = $route_data['distance'] ?? '0 km';
        $estimated_time = $route_data['estimatedTime'] ?? '0 min';
        $stops_count = $route_data['stops'] ?? 0;
        $completed_at = date('Y-m-d H:i:s');
        
        if($stmt = mysqli_prepare($link, $sql)){
            mysqli_stmt_bind_param($stmt, "isssssiiss", 
                $route_id,
                $route_name,
                $start_point,
                $end_point,
                $distance,
                $estimated_time,
                $stops_count,
                $driver_id,
                $driver_name,
                $completed_at
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    } catch(Exception $e) {
        error_log("Error saving route to history: " . $e->getMessage());
    }
    
    // Redirect to avoid form resubmission
    header("Location: routes.php?completed=" . $route_id);
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Routes - TrashTrace</title>
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
          crossorigin=""/>
    
    <!-- Leaflet Routing Machine CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css"/>
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Consolidated CSS -->
    <link rel="stylesheet" href="../css/driver/master-styles.css">
    
    <style>
        /* Additional styles for routes page */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 400px 1fr;
            gap: 30px;
            margin-top: 30px;
        }
        
        @media (max-width: 1200px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .dashboard-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(0, 0, 0, 0.05);
            backdrop-filter: blur(20px);
            position: relative;
            z-index: 2;
        }
        
        .dashboard-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
            z-index: -1;
            border-radius: 20px;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.12);
        }
        
        .card-header {
            padding: 24px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, rgba(248, 253, 249, 0.8), rgba(240, 255, 244, 0.6));
        }
        
        .card-header h3 {
            margin: 0;
            color: #2c3e50;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
        }
        
        .card-header i {
            color: #2e7d32;
            font-size: 1.4rem;
        }
        
        .route-list {
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .route-item {
            background: linear-gradient(135deg, rgba(248, 253, 249, 0.8), rgba(240, 255, 244, 0.6));
            padding: 20px;
            border-radius: 16px;
            border-left: 4px solid #4caf50;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 1px solid rgba(232, 245, 233, 0.5);
            position: relative;
            overflow: hidden;
        }
        
        .route-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(76, 175, 80, 0.1), rgba(46, 125, 50, 0.05));
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 1;
        }
        
        .route-item:hover {
            transform: translateX(5px);
            border-color: #4caf50;
            box-shadow: 0 8px 20px rgba(76, 175, 80, 0.1);
        }
        
        .route-item:hover::before {
            opacity: 1;
        }
        
        .route-item.active {
            background: linear-gradient(135deg, rgba(232, 245, 233, 0.9), rgba(241, 248, 233, 0.8));
            border-left-color: #2e7d32;
            box-shadow: 0 8px 25px rgba(46, 125, 50, 0.15);
        }
        
        .route-item.active::before {
            opacity: 1;
        }
        
        .route-content {
            position: relative;
            z-index: 2;
        }
        
        .route-item h4 {
            color: #2c3e50;
            margin-bottom: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            line-height: 1.4;
        }
        
        .route-details {
            display: flex;
            justify-content: space-between;
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 8px;
        }
        
        .route-details i {
            color: #4caf50;
            margin-right: 6px;
            width: 16px;
        }
        
        .route-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .route-status {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            backdrop-filter: blur(10px);
        }
        
        .status-pending {
            background: rgba(255, 243, 224, 0.8);
            color: #ef6c00;
            border: 1px solid rgba(239, 108, 0, 0.2);
        }
        
        .status-active {
            background: rgba(232, 245, 233, 0.8);
            color: #2e7d32;
            border: 1px solid rgba(76, 175, 80, 0.2);
        }
        
        .status-completed {
            background: rgba(245, 245, 245, 0.8);
            color: #666;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        .controls-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            padding: 20px;
        }
        
        .btn {
            padding: 14px 20px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 0.95rem;
            backdrop-filter: blur(10px);
            white-space: nowrap;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4caf50, #2e7d32);
            color: white;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #43a047, #1b5e20);
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(76, 175, 80, 0.3);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, rgba(33, 150, 243, 0.9), rgba(25, 118, 210, 0.9));
            color: white;
        }
        
        .btn-secondary:hover {
            background: linear-gradient(135deg, #1976d2, #1565c0);
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(33, 150, 243, 0.3);
        }
        
        .btn-outline {
            background: rgba(255, 255, 255, 0.8);
            border: 2px solid #4caf50;
            color: #2e7d32;
        }
        
        .btn-outline:hover {
            background: #4caf50;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(76, 175, 80, 0.2);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            padding: 24px;
        }
        
        .stat-item {
            background: linear-gradient(135deg, rgba(248, 253, 249, 0.8), rgba(240, 255, 244, 0.6));
            padding: 20px;
            border-radius: 16px;
            text-align: center;
            border: 1px solid rgba(232, 245, 233, 0.5);
            transition: all 0.3s ease;
        }
        
        .stat-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #2e7d32;
            margin-bottom: 8px;
        }
        
        .stat-label {
            font-size: 0.85rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 500;
        }
        
        .map-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            height: 500px;
            position: relative;
            border: 1px solid rgba(0, 0, 0, 0.05);
            backdrop-filter: blur(20px);
        }
        
        #map {
            width: 100%;
            height: 100%;
            border-radius: 20px;
        }
        
        .map-actions-container {
            margin-top: 30px;
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
        }
        
        .route-details-horizontal {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            padding: 24px;
        }
        
        .detail-item-horizontal {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            padding: 20px;
            background: linear-gradient(135deg, rgba(248, 253, 249, 0.8), rgba(240, 255, 244, 0.6));
            border-radius: 16px;
            border: 1px solid rgba(232, 245, 233, 0.5);
            transition: all 0.3s ease;
        }
        
        .detail-item-horizontal:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(76, 175, 80, 0.1);
        }
        
        .detail-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #e8f5e9, #f1f8e9);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            color: #2e7d32;
            font-size: 1.5rem;
        }
        
        .detail-content-horizontal h4 {
            color: #666;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .detail-content-horizontal p {
            color: #2c3e50;
            font-size: 1.1rem;
            font-weight: 600;
            line-height: 1.4;
        }
        
        .notification-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, #ff4757, #ff3838);
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 600;
            z-index: 1000;
            cursor: pointer;
            border: 2px solid white;
            box-shadow: 0 4px 15px rgba(255, 71, 87, 0.4);
            animation: pulse 2s infinite;
            backdrop-filter: blur(10px);
        }
        
        @keyframes pulse {
            0% {
                transform: scale(1);
                box-shadow: 0 4px 15px rgba(255, 71, 87, 0.4);
            }
            50% {
                transform: scale(1.1);
                box-shadow: 0 6px 20px rgba(255, 71, 87, 0.6);
            }
            100% {
                transform: scale(1);
                box-shadow: 0 4px 15px rgba(255, 71, 87, 0.4);
            }
        }
        
        .notification-panel {
            position: absolute;
            top: 50px;
            right: 20px;
            width: 350px;
            border-radius: 20px;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            display: none;
            overflow: hidden;
            border: 1px solid rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.95);
        }
        
        .notification-header {
            padding: 20px;
            background: linear-gradient(135deg, #2e7d32, #4caf50);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .notification-header h4 {
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .notification-list {
            max-height: 400px;
            overflow-y: auto;
            padding: 20px;
        }
        
        .notification-item {
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 10px;
            background: rgba(248, 253, 249, 0.8);
            border: 1px solid rgba(232, 245, 233, 0.5);
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            backdrop-filter: blur(10px);
        }
        
        .notification-item:hover {
            background: rgba(240, 255, 244, 0.9);
            transform: translateX(5px);
            border-color: rgba(76, 175, 80, 0.3);
        }
        
        .notification-item.unread {
            background: rgba(232, 245, 233, 0.9);
            border-left: 4px solid #4caf50;
        }
        
        .notification-item.unread::before {
            content: '';
            position: absolute;
            top: 12px;
            left: -8px;
            width: 8px;
            height: 8px;
            background: #4caf50;
            border-radius: 50%;
        }
        
        .notification-time {
            font-size: 0.8rem;
            color: #888;
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .close-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }
        
        .close-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }
        
        .live-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: rgba(232, 245, 233, 0.9);
            border-radius: 20px;
            font-size: 0.85rem;
            color: #2e7d32;
            font-weight: 500;
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1000;
            border: 1px solid rgba(200, 230, 201, 0.5);
            backdrop-filter: blur(10px);
        }
        
        .live-dot {
            width: 8px;
            height: 8px;
            background: #4caf50;
            border-radius: 50%;
            animation: livePulse 1.5s infinite;
        }
        
        @keyframes livePulse {
            0%, 100% {
                opacity: 1;
                transform: scale(1);
            }
            50% {
                opacity: 0.7;
                transform: scale(1.2);
            }
        }
        
        .route-progress-section {
            margin-top: 30px;
        }
        
        .progress-container {
            margin-bottom: 15px;
        }
        
        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            color: #666;
            font-size: 0.9rem;
        }
        
        .progress-bar {
            height: 10px;
            background: rgba(224, 224, 224, 0.5);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4caf50, #2e7d32);
            border-radius: 10px;
            transition: width 0.5s ease;
            position: relative;
            overflow: hidden;
        }
        
        .progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, 
                transparent, 
                rgba(255, 255, 255, 0.4), 
                transparent);
            animation: shimmer 2s infinite;
        }
        
        @keyframes shimmer {
            0% {
                transform: translateX(-100%);
            }
            100% {
                transform: translateX(100%);
            }
        }
        
        /* Map marker styles */
        .user-marker {
            filter: drop-shadow(0 4px 12px rgba(46, 125, 50, 0.4));
        }
        
        .waypoint-marker {
            filter: drop-shadow(0 2px 8px rgba(0, 0, 0, 0.2));
        }
        
        /* Custom scrollbar */
        .route-list::-webkit-scrollbar {
            width: 6px;
        }
        .route-list::-webkit-scrollbar-track {
            background: rgba(241, 241, 241, 0.5);
            border-radius: 10px;
        }
        .route-list::-webkit-scrollbar-thumb {
            background: rgba(200, 230, 201, 0.8);
            border-radius: 10px;
        }
        .route-list::-webkit-scrollbar-thumb:hover {
            background: rgba(76, 175, 80, 0.8);
        }

        @media (max-width: 1200px) {
            .map-container {
                height: 450px;
            }
            
            .map-actions-container {
                grid-template-columns: 1fr;
            }
            
            .controls-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .route-details-horizontal {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .dashboard-grid {
                gap: 20px;
            }
            
            .controls-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .route-details-horizontal {
                grid-template-columns: 1fr;
            }
            
            .notification-panel {
                width: 90vw;
                right: 5vw;
            }
            
            .map-container {
                height: 400px;
            }
        }
        
        .toast-notification {
            border-left: 4px solid;
            animation: slideInRight 0.3s ease-out;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
        
        /* Main content enhancements */
        .main-content {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 30px;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-title {
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 2.2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .page-subtitle {
            color: #666;
            font-size: 1.1rem;
            max-width: 600px;
            line-height: 1.5;
        }
        
        /* Truck animation path */
        .truck-path {
            stroke-dasharray: 10, 10;
            animation: moveTruck 20s linear infinite;
        }
        
        @keyframes moveTruck {
            0% {
                stroke-dashoffset: 1000;
            }
            100% {
                stroke-dashoffset: 0;
            }
        }
        
        /* Success toast */
        .success-toast {
            background: linear-gradient(135deg, #4caf50, #2e7d32);
            color: white;
            padding: 15px 20px;
            border-radius: 12px;
            position: fixed;
            top: 100px;
            right: 30px;
            z-index: 9999;
            box-shadow: 0 10px 30px rgba(76, 175, 80, 0.3);
            animation: slideInRight 0.3s ease-out;
            display: flex;
            align-items: center;
            gap: 12px;
            max-width: 350px;
        }
        
        .success-toast i {
            font-size: 1.5rem;
        }
        
        /* Route completion modal */
        .completion-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .completion-content {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 500px;
            width: 90%;
            text-align: center;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
            animation: scaleIn 0.3s ease;
        }
        
        @keyframes scaleIn {
            from { transform: scale(0.9); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        
        .completion-icon {
            font-size: 4rem;
            color: #4caf50;
            margin-bottom: 20px;
        }
        
        .completion-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin: 25px 0;
        }
        
        .completion-stat {
            padding: 15px;
            background: #f8fdf9;
            border-radius: 12px;
        }
        
        .completion-stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2e7d32;
            margin-bottom: 5px;
        }
        
        .completion-stat-label {
            font-size: 0.9rem;
            color: #666;
        }
        
        /* Real-time tracking panel */
        .tracking-panel {
            position: absolute;
            bottom: 80px;
            left: 20px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            max-width: 300px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        .tracking-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .tracking-header i {
            color: #4caf50;
            font-size: 1.2rem;
        }
        
        .tracking-header h4 {
            margin: 0;
            color: #2c3e50;
            font-size: 1.1rem;
        }
        
        .tracking-info {
            font-size: 0.9rem;
            color: #666;
            line-height: 1.5;
        }
        
        .tracking-info strong {
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php
// Shared header/navbar
$current_page = basename($_SERVER['PHP_SELF']);
$user_type = $_SESSION['user_type'] ?? '';
$current_dir = dirname($_SERVER['PHP_SELF']);
$is_driver_page = strpos($current_dir, '/driver') !== false || $current_page === 'driver_dashboard.php';
$base_path = '/TrashTrace_Duplicate/';
?>
<header class="dashboard-header">
    <div class="grid-background-nav"></div>

    <div class="header-content">
        <?php if($user_type === 'driver'): ?>
            <a href="<?php echo $base_path; ?>driver_dashboard.php" class="logo">
                <i class="fas fa-recycle"></i>
                <span>TrashTrace Driver</span>
            </a>
            <button class="mobile-menu-toggle" id="mobileMenuToggle">
                <i class="fas fa-bars"></i>
            </button>
            <nav id="mainNav">
                <div class="nav-container">
                    <ul>
        <?php else: ?>
            <div class="logo">
                <img src="assets/images/trashtrace logo green.png" alt="TrashTrace Logo" class="logo-img">
                <?php if($user_type === 'admin'): ?>
                <span class="portal-label">Admin Portal</span>
                <?php endif; ?>
            </div>
            <nav class="main-nav">
                <ul>
        <?php endif; ?>
                <?php if($user_type === 'admin'): ?>
                    <li><a href="barangay_dashboard.php" class="nav-link <?php echo $current_page === 'barangay_dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-th-large"></i>
                        <span>Dashboard</span>
                    </a></li>
                    <li><a href="barangay_schedule.php" class="nav-link <?php echo $current_page === 'barangay_schedule.php' ? 'active' : ''; ?>">
                        <i class="far fa-calendar"></i>
                        <span>Schedule</span>
                    </a></li>
                    <li><a href="barangay_applications.php" class="nav-link <?php echo $current_page === 'barangay_applications.php' ? 'active' : ''; ?>">
                        <i class="far fa-file-alt"></i>
                        <span>Applications</span>
                    </a></li>
                    <li><a href="barangay_notifications.php" class="nav-link <?php echo $current_page === 'barangay_notifications.php' ? 'active' : ''; ?>">
                        <i class="far fa-bell"></i>
                        <span>Notifications</span>
                    </a></li>
                    <li><a href="barangay_reports.php" class="nav-link <?php echo $current_page === 'barangay_reports.php' ? 'active' : ''; ?>">
                        <i class="far fa-chart-bar"></i>
                        <span>Reports</span>
                    </a></li>
                <?php elseif($user_type === 'driver'): ?>
                    <li><a href="<?php echo $base_path; ?>driver_dashboard.php" class="nav-link <?php echo $current_page === 'driver_dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>

                    </a></li>
                    <li><a href="<?php echo $base_path; ?>driver/assignments.php" class="nav-link <?php echo $current_page === 'assignments.php' ? 'active' : ''; ?>">
                        <i class="fas fa-tasks"></i>
                        <span>Assignments</span>
                    </a></li>
                    <li><a href="<?php echo $base_path; ?>driver/routes.php" class="nav-link <?php echo $current_page === 'routes.php' ? 'active' : ''; ?>">
                        <i class="fas fa-route"></i>
                        <span>Routes</span>
                    </a></li>
                    <li><a href="<?php echo $base_path; ?>driver/collections.php" class="nav-link <?php echo $current_page === 'collections.php' ? 'active' : ''; ?>">
                        <i class="fas fa-trash"></i>
                        <span>Collections</span>
                    </a></li>
                    <li><a href="<?php echo $base_path; ?>driver/earnings.php" class="nav-link <?php echo $current_page === 'earnings.php' ? 'active' : ''; ?>">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Earnings</span>
                    </a></li>
                    <li><a href="<?php echo $base_path; ?>driver/profile.php" class="nav-link <?php echo $current_page === 'profile.php' ? 'active' : ''; ?>">
                        <i class="fas fa-user"></i>
                        <span>Profile</span>
                    </a></li>
                    <li><a href="<?php echo $base_path; ?>driver/history.php" class="nav-link <?php echo $current_page === 'history.php' ? 'active' : ''; ?>">
                        <i class="fas fa-history"></i>
                        <span>History</span>
                    </a></li>
                <?php else: ?>
                    <li><a href="dashboard.php" class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-th-large"></i>
                        <span>Dashboard</span>
                    </a></li>
                    <li><a href="res_schedule.php" class="nav-link <?php echo $current_page === 'res_schedule.php' ? 'active' : ''; ?>">
                        <i class="far fa-calendar"></i>
                        <span>Schedule</span>
                    </a></li>
                    <li><a href="res_notif.php" class="nav-link <?php echo $current_page === 'res_notif.php' ? 'active' : ''; ?>">
                        <i class="far fa-bell"></i>
                        <span>Notifications</span>
                    </a></li>
                    <li><a href="res_profile.php" class="nav-link <?php echo $current_page === 'res_profile.php' ? 'active' : ''; ?>">
                        <i class="far fa-user"></i>
                        <span>Profile</span>
                    </a></li>
                <?php endif; ?>
            </ul>
        </nav>
        <div class="header-actions">
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
</header>
        
        <main class="dashboard-main">
            <!-- Grid Background Pattern -->
            <div class="grid-background"></div>
            
            <div class="container">
                <div class="page-header">
                    <h1 class="page-title">
                        <i class="fas fa-route"></i>
                        Driver Routes - Live Demo
                    </h1>
                    <p class="page-subtitle">Track real-time truck movement, navigate routes, and automatically sync completed routes to history.</p>
                </div>
                
                <?php if(isset($_GET['completed'])): ?>
                    <div class="success-toast" id="completionToast">
                        <i class="fas fa-check-circle"></i>
                        <div>
                            <strong>Route Completed!</strong>
                            <p>Route #<?php echo $_GET['completed']; ?> has been saved to your history.</p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="dashboard-grid">
                    <!-- Sidebar with routes and statistics -->
                    <div class="sidebar">
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h3><i class="fas fa-map-marked-alt"></i> Available Routes</h3>
                            </div>
                            <div class="route-list" id="routeList">
                                <!-- Routes will be populated by JavaScript -->
                            </div>
                        </div>
                        
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h3><i class="fas fa-chart-line"></i> Route Statistics</h3>
                            </div>
                            <div class="stats-grid" id="routeStats">
                                <!-- Stats will be populated by JavaScript -->
                            </div>
                        </div>
                    </div>
                    
                    <!-- Map section with controls below -->
                    <div class="main-content">
                        <div class="map-container">
                            <div id="map"></div>
                            <div class="tracking-panel" id="trackingPanel">
                                <div class="tracking-header">
                                    <i class="fas fa-satellite-dish"></i>
                                    <h4>Live Tracking Active</h4>
                                </div>
                                <div class="tracking-info">
                                    <p><strong>Truck ID:</strong> TT-<?php echo str_pad($driver_id, 4, '0', STR_PAD_LEFT); ?></p>
                                    <p><strong>Driver:</strong> <?php echo $driver_name; ?></p>
                                    <p><strong>Status:</strong> <span id="trackingStatus">Ready</span></p>
                                    <p><strong>Speed:</strong> <span id="trackingSpeed">0 km/h</span></p>
                                </div>
                            </div>
                            <div class="notification-badge" id="notificationBadge">3</div>
                            <div class="notification-panel" id="notificationPanel">
                                <div class="notification-header">
                                    <h4><i class="fas fa-bell"></i> Notifications</h4>
                                    <button class="close-btn" id="closeNotifications">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <div class="notification-list" id="notificationList">
                                    <!-- Notifications will be added here -->
                                </div>
                            </div>
                            <div class="live-indicator">
                                <div class="live-dot"></div>
                                <span>Live Truck Tracking</span>
                            </div>
                        </div>
                        
                        <!-- Navigation Controls and Details moved below map -->
                        <div class="map-actions-container">
                            <!-- Navigation Controls -->
                            <div class="dashboard-card">
                                <div class="card-header">
                                    <h3><i class="fas fa-cogs"></i> Navigation Controls</h3>
                                </div>
                                <div class="controls-grid">
                                    <button class="btn btn-primary" id="startNavigation">
                                        <i class="fas fa-play-circle"></i> Start Navigation
                                    </button>
                                    <button class="btn btn-secondary" id="pauseNavigation">
                                        <i class="fas fa-pause-circle"></i> Pause
                                    </button>
                                    <button class="btn btn-outline" id="completeRoute">
                                        <i class="fas fa-check-circle"></i> Complete Route
                                    </button>
                                    <button class="btn btn-outline" id="reportIssue">
                                        <i class="fas fa-exclamation-triangle"></i> Report Issue
                                    </button>
                                </div>
                                <div class="route-progress">
                                    <div class="progress-container">
                                        <div class="progress-label">
                                            <span>Route Progress</span>
                                            <span id="progressPercentage">0%</span>
                                        </div>
                                        <div class="progress-bar">
                                            <div class="progress-fill" id="progressFill" style="width: 0%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Horizontal Route Details -->
                            <div class="dashboard-card">
                                <div class="card-header">
                                    <h3><i class="fas fa-info-circle"></i> Route Details</h3>
                                </div>
                                <div class="route-details-horizontal" id="routeDetailsHorizontal">
                                    <div class="detail-item-horizontal">
                                        <div class="detail-icon">
                                            <i class="fas fa-map-pin"></i>
                                        </div>
                                        <div class="detail-content-horizontal">
                                            <h4>Start Point</h4>
                                            <p id="horizontalStartPoint">Select a route</p>
                                        </div>
                                    </div>
                                    <div class="detail-item-horizontal">
                                        <div class="detail-icon">
                                            <i class="fas fa-flag-checkered"></i>
                                        </div>
                                        <div class="detail-content-horizontal">
                                            <h4>End Point</h4>
                                            <p id="horizontalEndPoint">Select a route</p>
                                        </div>
                                    </div>
                                    <div class="detail-item-horizontal">
                                        <div class="detail-icon">
                                            <i class="fas fa-road"></i>
                                        </div>
                                        <div class="detail-content-horizontal">
                                            <h4>Distance</h4>
                                            <p id="horizontalRouteDistance">-</p>
                                        </div>
                                    </div>
                                    <div class="detail-item-horizontal">
                                        <div class="detail-icon">
                                            <i class="fas fa-clock"></i>
                                        </div>
                                        <div class="detail-content-horizontal">
                                            <h4>Estimated Time</h4>
                                            <p id="horizontalRouteTime">-</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        
        <!-- Route Completion Form -->
        <form id="routeCompletionForm" method="POST" style="display: none;">
            <input type="hidden" name="complete_route" value="1">
            <input type="hidden" name="route_id" id="completeRouteId">
            <input type="hidden" name="route_data" id="completeRouteData">
        </form>
    </div>
    
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
            crossorigin=""></script>
    
    <!-- Leaflet Routing Machine -->
    <script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>
    
    <!-- Socket.io for real-time updates -->
    <script src="https://cdn.socket.io/4.5.4/socket.io.min.js"></script>
    
    <script>
        // Initialize variables
        let map;
        let currentRoute = null;
        let routingControl = null;
        let userMarker = null;
        let truckMarker = null;
        let watchId = null;
        let socket = null;
        let notifications = [];
        let selectedRoute = null;
        let routeProgress = 0;
        let isNavigating = false;
        let truckInterval = null;
        let truckPosition = null;
        let truckSpeed = 0;
        let truckDirection = 0;
        let currentWaypointIndex = 0;
        let truckPath = null;
        let routePath = null;
        let routeWaypoints = [];
        let realRouteCoordinates = [];
        
        // Cebu City coordinates
        const CEBU_COORDS = [10.3157, 123.8854];
        
        // Demo routes with realistic waypoints for Cebu City - FIXED FORMAT
        const routes = [
            {
                id: 1,
                name: "Barangay Lahug Morning Route",
                start: "Cebu City Hall",
                end: "Lahug Market",
                stops: 15,
                distance: "8.5 km",
                estimatedTime: "2 hours 15 min",
                status: "pending",
                progress: <?php echo isset($_SESSION['route_progress'][1]) ? $_SESSION['route_progress'][1] : 0; ?>,
                waypoints: [
                    {lat: 10.3157, lng: 123.8854, name: "Cebu City Hall"}, // Start
                    {lat: 10.3190, lng: 123.8880, name: "Osmena Blvd"},
                    {lat: 10.3220, lng: 123.8910, name: "Gorordo Ave"},
                    {lat: 10.3245, lng: 123.8943, name: "Lahug Area"},
                    {lat: 10.3270, lng: 123.8970, name: "Salinas Drive"},
                    {lat: 10.3298, lng: 123.9021, name: "JY Square"},
                    {lat: 10.3320, lng: 123.9050, name: "IT Park"},
                    {lat: 10.3342, lng: 123.9087, name: "Lahug Market"} // End
                ]
            },
            {
                id: 2,
                name: "Apas Residential Route",
                start: "IT Park",
                end: "Apas Terminal",
                stops: 22,
                distance: "12.3 km",
                estimatedTime: "3 hours 30 min",
                status: "pending",
                progress: <?php echo isset($_SESSION['route_progress'][2]) ? $_SESSION['route_progress'][2] : 0; ?>,
                waypoints: [
                    {lat: 10.3347, lng: 123.9054, name: "IT Park"},
                    {lat: 10.3320, lng: 123.9020, name: "AS Fortuna"},
                    {lat: 10.3295, lng: 123.8990, name: "Mango Ave"},
                    {lat: 10.3289, lng: 123.8987, name: "Apas Zone 1"},
                    {lat: 10.3260, lng: 123.8960, name: "Capitol Area"},
                    {lat: 10.3240, lng: 123.8935, name: "Escario St"},
                    {lat: 10.3223, lng: 123.8912, name: "Apas Zone 2"},
                    {lat: 10.3200, lng: 123.8880, name: "Jones Ave"},
                    {lat: 10.3180, lng: 123.8860, name: "Colon St"},
                    {lat: 10.3168, lng: 123.8845, name: "Apas Terminal"}
                ]
            },
            {
                id: 3,
                name: "Downtown Collection Route",
                start: "Carbon Market",
                end: "Colon Street",
                stops: 18,
                distance: "6.8 km",
                estimatedTime: "2 hours",
                status: "pending",
                progress: <?php echo isset($_SESSION['route_progress'][3]) ? $_SESSION['route_progress'][3] : 0; ?>,
                waypoints: [
                    {lat: 10.2939, lng: 123.9025, name: "Carbon Market"},
                    {lat: 10.2955, lng: 123.9010, name: "P. Gullas St"},
                    {lat: 10.2970, lng: 123.8995, name: "Manalili St"},
                    {lat: 10.2987, lng: 123.8983, name: "Downtown Area 1"},
                    {lat: 10.3005, lng: 123.8965, name: "Borromeo St"},
                    {lat: 10.3021, lng: 123.8947, name: "Downtown Area 2"},
                    {lat: 10.3035, lng: 123.8930, name: "Sanciangko St"},
                    {lat: 10.3054, lng: 123.8912, name: "Colon Street"}
                ]
            }
        ];
        
        // Sample notifications
        const sampleNotifications = [
            {
                id: 1,
                title: "Live Demo Activated",
                message: "Real-time truck tracking is now active. Select a route to start navigation.",
                time: "Just now",
                type: "info",
                read: false
            },
            {
                id: 2,
                title: "System Ready",
                message: "All systems are operational. GPS tracking is active.",
                time: "2 min ago",
                type: "success",
                read: false
            },
            {
                id: 3,
                title: "Demo Tip",
                message: "Complete a route to see it automatically saved to your history.",
                time: "5 min ago",
                type: "info",
                read: false
            }
        ];
        
        // Initialize map
        function initMap() {
            map = L.map('map').setView(CEBU_COORDS, 14);
            
            // Add OpenStreetMap tiles with custom styling
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19
            }).addTo(map);
            
            // Add user location marker with custom icon
            userMarker = L.marker(CEBU_COORDS, {
                icon: L.divIcon({
                    className: 'user-marker',
                    html: '<div style="background: linear-gradient(135deg, #4caf50, #2e7d32); width: 32px; height: 32px; border-radius: 50%; border: 3px solid white; box-shadow: 0 4px 12px rgba(46, 125, 50, 0.4); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 14px;">D</div>',
                    iconSize: [32, 32],
                    iconAnchor: [16, 16]
                })
            }).addTo(map);
            
            // Bind popup to user marker
            userMarker.bindPopup(`
                <div style="padding: 10px; max-width: 200px;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                        <div style="width: 30px; height: 30px; background: linear-gradient(135deg, #4caf50, #2e7d32); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">D</div>
                        <div>
                            <strong style="color: #2c3e50;"><?php echo $driver_name; ?></strong><br>
                            <small style="color: #666;">Driver ID: #<?php echo str_pad($driver_id, 4, '0', STR_PAD_LEFT); ?></small>
                        </div>
                    </div>
                    <p style="margin: 0; color: #666; font-size: 12px; padding: 8px; background: #f8fdf9; border-radius: 6px;">
                        <i class="fas fa-map-marker-alt" style="color: #4caf50;"></i> Currently at Cebu City
                    </p>
                </div>
            `).openPopup();
            
            // Load routes
            loadRoutes();
            
            // Load notifications
            loadNotifications();
            
            // Initialize WebSocket connection
            initWebSocket();
            
            // Start location tracking
            startLocationTracking();
            
            // Update progress bar
            updateProgressBar();
            
            // Auto-hide success toast after 5 seconds
            const toast = document.getElementById('completionToast');
            if (toast) {
                setTimeout(() => {
                    toast.style.animation = 'slideOutRight 0.3s ease-out';
                    setTimeout(() => toast.remove(), 300);
                }, 5000);
            }
        }
        
        // Load routes to sidebar
        function loadRoutes() {
            const routeList = document.getElementById('routeList');
            const routeStats = document.getElementById('routeStats');
            
            // Clear existing content
            routeList.innerHTML = '';
            routeStats.innerHTML = '';
            
            // Add routes to list
            routes.forEach(route => {
                // Check session for saved progress
                const savedProgress = <?php echo json_encode($_SESSION['route_progress']); ?>;
                if(savedProgress && savedProgress[route.id]) {
                    route.progress = savedProgress[route.id];
                }
                
                const routeItem = document.createElement('div');
                routeItem.className = `route-item ${route.status === 'active' ? 'active' : ''}`;
                routeItem.innerHTML = `
                    <div class="route-content">
                        <h4>${route.name}</h4>
                        <div class="route-details">
                            <span><i class="fas fa-map-marker-alt"></i> ${route.start}</span>
                            <span><i class="fas fa-flag-checkered"></i> ${route.end}</span>
                        </div>
                        <div class="route-details">
                            <span><i class="fas fa-road"></i> ${route.distance}</span>
                            <span><i class="fas fa-clock"></i> ${route.estimatedTime}</span>
                        </div>
                        <div class="route-meta">
                            <span><i class="fas fa-trash-alt"></i> ${route.stops} stops</span>
                            <span class="route-status status-${route.status}">${route.status}</span>
                        </div>
                    </div>
                `;
                
                routeItem.addEventListener('click', () => selectRoute(route));
                routeList.appendChild(routeItem);
            });
            
            // Update statistics
            const totalRoutes = routes.length;
            const activeRoutes = routes.filter(r => r.status === 'active').length;
            const completedRoutes = routes.filter(r => r.status === 'completed').length;
            const totalStops = routes.reduce((sum, r) => sum + parseInt(r.stops), 0);
            
            routeStats.innerHTML = `
                <div class="stat-item">
                    <div class="stat-value">${totalRoutes}</div>
                    <div class="stat-label">Total Routes</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">${activeRoutes}</div>
                    <div class="stat-label">Active</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">${completedRoutes}</div>
                    <div class="stat-label">Completed</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">${totalStops}</div>
                    <div class="stat-label">Total Stops</div>
                </div>
            `;
        }
        
        // Select a route
        function selectRoute(route) {
            selectedRoute = route;
            routeProgress = route.progress;
            
            // Update UI
            document.querySelectorAll('.route-item').forEach(item => {
                item.classList.remove('active');
            });
            event.currentTarget.classList.add('active');
            
            // Update route details
            updateRouteDetails(route);
            
            // Update progress bar
            updateProgressBar();
            
            // Plot route on map using real road routing
            plotRoute(route.waypoints);
            
            // Send notification
            addNotification(`Route selected: ${route.name}`, 'info');
        }
        
        // Update route details panel
        function updateRouteDetails(route) {
            document.getElementById('horizontalStartPoint').textContent = route.start;
            document.getElementById('horizontalEndPoint').textContent = route.end;
            document.getElementById('horizontalRouteDistance').textContent = route.distance;
            document.getElementById('horizontalRouteTime').textContent = route.estimatedTime;
        }
        
        // Update progress bar
        function updateProgressBar() {
            const progressFill = document.getElementById('progressFill');
            const progressPercentage = document.getElementById('progressPercentage');
            
            progressFill.style.width = `${routeProgress}%`;
            progressPercentage.textContent = `${routeProgress}%`;
        }
        
        // Plot route using Leaflet Routing Machine
        function plotRoute(waypoints) {
            // Clear previous route
            if (routingControl) {
                map.removeControl(routingControl);
            }
            if (truckPath) {
                map.removeLayer(truckPath);
            }
            if (truckMarker) {
                map.removeLayer(truckMarker);
            }
            if (routePath) {
                map.removeLayer(routePath);
            }
            
            // Convert waypoints to LatLng objects
            const latLngWaypoints = waypoints.map(wp => L.latLng(wp.lat, wp.lng));
            routeWaypoints = latLngWaypoints;
            
            // Create routing control with real road routing
            routingControl = L.Routing.control({
                waypoints: latLngWaypoints,
                routeWhileDragging: false,
                showAlternatives: false,
                fitSelectedRoutes: true,
                show: false,
                createMarker: function(i, wp) {
                    let iconHtml;
                    let className = 'waypoint-marker';
                    
                    if (i === 0) {
                        iconHtml = '<i class="fas fa-play" style="color: #4caf50;"></i>';
                        className += ' start';
                    } else if (i === waypoints.length - 1) {
                        iconHtml = '<i class="fas fa-flag-checkered" style="color: #2e7d32;"></i>';
                        className += ' end';
                    } else {
                        iconHtml = '<i class="fas fa-circle" style="color: #2196f3;"></i>';
                        className += ' stop';
                    }
                    
                    return L.marker(wp.latLng, {
                        icon: L.divIcon({
                            className: className,
                            html: `<div style="background: white; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">${iconHtml}</div>`,
                            iconSize: [28, 28],
                            iconAnchor: [14, 14]
                        })
                    });
                },
                lineOptions: {
                    styles: [
                        {
                            color: '#4caf50',
                            opacity: 0.8,
                            weight: 5
                        }
                    ]
                },
                router: L.Routing.osrmv1({
                    serviceUrl: 'https://router.project-osrm.org/route/v1'
                })
            }).addTo(map);
            
            // Add event listener for route changes
            routingControl.on('routesfound', function(e) {
                const routes = e.routes;
                if (routes && routes.length > 0) {
                    const route = routes[0];
                    const summary = route.summary;
                    
                    // Store route coordinates for truck simulation
                    realRouteCoordinates = route.coordinates;
                    
                    // Create a smooth path for visualization
                    routePath = L.polyline(route.coordinates, {
                        color: '#4caf50',
                        weight: 4,
                        opacity: 0.6,
                        dashArray: '10, 10'
                    }).addTo(map);
                    
                    // Update route details with real calculations
                    if (selectedRoute) {
                        selectedRoute.distance = (summary.totalDistance / 1000).toFixed(1) + ' km';
                        selectedRoute.estimatedTime = Math.round(summary.totalTime / 60) + ' min';
                        updateRouteDetails(selectedRoute);
                    }
                    
                    addNotification(`Route calculated: ${(summary.totalDistance / 1000).toFixed(1)} km, ${Math.round(summary.totalTime / 60)} min`, 'success');
                    
                    // If navigation was previously active, restore truck position
                    if (isNavigating && selectedRoute.progress > 0) {
                        restoreTruckPosition();
                    }
                }
            });
            
            routingControl.on('routingerror', function(e) {
                console.error('Routing error:', e.error);
                addNotification('Could not calculate route. Using straight line path.', 'warning');
                
                // Fallback: create straight line path
                realRouteCoordinates = latLngWaypoints.map(wp => [wp.lat, wp.lng]);
                routePath = L.polyline(realRouteCoordinates, {
                    color: '#4caf50',
                    weight: 4,
                    opacity: 0.6,
                    dashArray: '10, 10'
                }).addTo(map);
                
                // Calculate approximate distance
                const distance = calculateStraightLineDistance(latLngWaypoints);
                selectedRoute.distance = distance.toFixed(1) + ' km';
                selectedRoute.estimatedTime = Math.round(distance * 10) + ' min';
                updateRouteDetails(selectedRoute);
            });
        }
        
        // Calculate straight line distance as fallback
        function calculateStraightLineDistance(waypoints) {
            let totalDistance = 0;
            for (let i = 1; i < waypoints.length; i++) {
                const prev = waypoints[i-1];
                const curr = waypoints[i];
                const distance = prev.distanceTo(curr) / 1000; // Convert to km
                totalDistance += distance;
            }
            return totalDistance;
        }
        
        // Restore truck position based on saved progress
        function restoreTruckPosition() {
            if (!selectedRoute || !realRouteCoordinates || realRouteCoordinates.length === 0) return;
            
            // Calculate position based on progress percentage
            const totalPoints = realRouteCoordinates.length;
            const progressIndex = Math.floor((selectedRoute.progress / 100) * totalPoints);
            const targetIndex = Math.min(progressIndex, totalPoints - 1);
            
            // Get position from route coordinates
            const position = realRouteCoordinates[targetIndex];
            truckPosition = [position.lat, position.lng];
            
            // Create or update truck marker
            if (!truckMarker) {
                createTruckMarker();
            }
            truckMarker.setLatLng(truckPosition);
            
            // Update progress
            routeProgress = selectedRoute.progress;
            updateProgressBar();
            
            // Update tracking status
            document.getElementById('trackingStatus').textContent = 'Paused';
            document.getElementById('trackingStatus').style.color = '#ff9800';
            
            addNotification(`Restored to ${routeProgress}% progress`, 'info');
        }
        
        // Create truck marker
        function createTruckMarker() {
            truckMarker = L.marker(truckPosition || CEBU_COORDS, {
                icon: L.divIcon({
                    className: 'truck-marker',
                    html: `<div style="background: linear-gradient(135deg, #ff9800, #f57c00); width: 40px; height: 40px; border-radius: 8px; border: 3px solid white; box-shadow: 0 4px 12px rgba(255, 152, 0, 0.4); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 16px; transform: rotate(45deg);">T</div>`,
                    iconSize: [40, 40],
                    iconAnchor: [20, 20]
                })
            }).addTo(map);
        }
        
        // Start truck simulation along the route
        function startTruckSimulation() {
            if (truckInterval) {
                clearInterval(truckInterval);
            }
            
            if (!truckMarker) {
                createTruckMarker();
            }
            
            if (!realRouteCoordinates || realRouteCoordinates.length === 0) {
                addNotification('Route not calculated yet. Please wait.', 'warning');
                return;
            }
            
            // Determine starting position based on progress
            let currentIndex = 0;
            if (routeProgress > 0) {
                currentIndex = Math.floor((routeProgress / 100) * (realRouteCoordinates.length - 1));
                currentIndex = Math.min(currentIndex, realRouteCoordinates.length - 2);
            }
            
            let segmentProgress = 0;
            const totalSegments = realRouteCoordinates.length - 1;
            
            // Start moving truck
            truckInterval = setInterval(() => {
                if (!isNavigating || currentIndex >= totalSegments) {
                    return;
                }
                
                // Get current segment
                const startPoint = realRouteCoordinates[currentIndex];
                const endPoint = realRouteCoordinates[currentIndex + 1];
                
                // Calculate intermediate position
                const lat = startPoint.lat + (endPoint.lat - startPoint.lat) * segmentProgress;
                const lng = startPoint.lng + (endPoint.lng - startPoint.lng) * segmentProgress;
                
                truckPosition = [lat, lng];
                truckMarker.setLatLng(truckPosition);
                
                // Update speed (based on road type - slower in dense areas)
                truckSpeed = Math.floor(Math.random() * 20) + 30; // 30-50 km/h
                document.getElementById('trackingSpeed').textContent = `${truckSpeed} km/h`;
                
                // Update segment progress
                segmentProgress += 0.01; // Move 1% along segment
                
                // Check if reached next point
                if (segmentProgress >= 1) {
                    segmentProgress = 0;
                    currentIndex++;
                    
                    // Update waypoint index for UI
                    currentWaypointIndex = Math.min(currentIndex, routeWaypoints.length - 1);
                    
                    if (currentIndex < totalSegments) {
                        addNotification(`Reached point ${currentIndex + 1}/${totalSegments + 1}`, 'info');
                    }
                }
                
                // Update overall progress
                routeProgress = Math.min(100, ((currentIndex + segmentProgress) / totalSegments) * 100);
                selectedRoute.progress = routeProgress;
                
                // Save progress to session
                saveRouteProgress();
                
                updateProgressBar();
                
                // Pan map to follow truck
                map.panTo(truckPosition);
                
                // Check if completed
                if (currentIndex >= totalSegments && segmentProgress >= 1) {
                    completeTruckJourney();
                }
                
            }, 100); // Update every 100ms
            
            addNotification('Truck simulation started', 'success');
            document.getElementById('trackingStatus').textContent = 'Moving';
            document.getElementById('trackingStatus').style.color = '#4caf50';
        }
        
        // Save route progress to session via AJAX
        function saveRouteProgress() {
            if (!selectedRoute) return;
            
            // Save to session via AJAX
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'save_progress.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.send(`route_id=${selectedRoute.id}&progress=${routeProgress}`);
        }
        
        // Stop truck simulation
        function stopTruckSimulation() {
            if (truckInterval) {
                clearInterval(truckInterval);
                truckInterval = null;
            }
            document.getElementById('trackingStatus').textContent = 'Stopped';
            document.getElementById('trackingStatus').style.color = '#f44336';
            truckSpeed = 0;
            document.getElementById('trackingSpeed').textContent = '0 km/h';
        }
        
        // Complete truck journey
        function completeTruckJourney() {
            stopTruckSimulation();
            selectedRoute.status = 'completed';
            selectedRoute.progress = 100;
            
            // Update UI
            updateRouteDetails(selectedRoute);
            updateProgressBar();
            loadRoutes();
            
            addNotification('Route completed!', 'success');
            document.getElementById('trackingStatus').textContent = 'Completed';
            document.getElementById('trackingStatus').style.color = '#2e7d32';
            
            // Auto-complete the route
            completeCurrentRoute();
        }
        
        // Initialize WebSocket for real-time updates
        function initWebSocket() {
            // Connect to WebSocket server
            try {
                socket = io('http://localhost:3000', {
                    transports: ['websocket'],
                    reconnection: true,
                    reconnectionAttempts: 5,
                    reconnectionDelay: 1000
                });
                
                socket.on('connect', () => {
                    console.log('Connected to WebSocket server');
                    addNotification('Connected to real-time server', 'success');
                });
                
                socket.on('disconnect', () => {
                    console.log('Disconnected from WebSocket server');
                    addNotification('Disconnected from server', 'warning');
                });
                
                socket.on('driver_update', (data) => {
                    console.log('Driver update received:', data);
                    if (data.driver_id === <?php echo $driver_id; ?>) {
                        // Update driver position if provided
                        if (data.position) {
                            userMarker.setLatLng([data.position.lat, data.position.lng]);
                            map.panTo([data.position.lat, data.position.lng]);
                        }
                        
                        // Add notification
                        if (data.message) {
                            addNotification(data.message, data.type || 'info');
                        }
                    }
                });
                
                socket.on('route_update', (data) => {
                    console.log('Route update received:', data);
                    addNotification(`Route update: ${data.message}`, data.type || 'info');
                    
                    // Update route if needed
                    if (selectedRoute && selectedRoute.id === data.route_id) {
                        selectedRoute.status = data.status;
                        selectedRoute.progress = data.progress || 0;
                        updateRouteDetails(selectedRoute);
                        updateProgressBar();
                        loadRoutes();
                    }
                });
                
                socket.on('notification', (data) => {
                    console.log('Notification received:', data);
                    addNotification(data.message, data.type || 'info');
                });
            } catch (error) {
                console.log('WebSocket connection failed, using demo mode:', error);
                addNotification('Running in demo mode. Real-time features simulated.', 'info');
            }
        }
        
        // Start location tracking
        function startLocationTracking() {
            if ('geolocation' in navigator) {
                watchId = navigator.geolocation.watchPosition(
                    (position) => {
                        const { latitude, longitude } = position.coords;
                        
                        // Update user marker position
                        userMarker.setLatLng([latitude, longitude]);
                        
                        // Update user marker popup
                        userMarker.setPopupContent(`
                            <div style="padding: 10px; max-width: 200px;">
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                    <div style="width: 30px; height: 30px; background: linear-gradient(135deg, #4caf50, #2e7d32); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">D</div>
                                    <div>
                                        <strong style="color: #2c3e50;"><?php echo $driver_name; ?></strong><br>
                                        <small style="color: #666;">Driver ID: #<?php echo str_pad($driver_id, 4, '0', STR_PAD_LEFT); ?></small>
                                    </div>
                                </div>
                                <p style="margin: 0; color: #666; font-size: 12px; padding: 8px; background: #f8fdf9; border-radius: 6px;">
                                    <i class="fas fa-map-marker-alt" style="color: #4caf50;"></i> ${latitude.toFixed(4)}, ${longitude.toFixed(4)}
                                </p>
                            </div>
                        `);
                        
                        // Send position update to server
                        if (socket && socket.connected) {
                            socket.emit('driver_position', {
                                driver_id: <?php echo $driver_id; ?>,
                                position: { lat: latitude, lng: longitude },
                                timestamp: new Date().toISOString()
                            });
                        }
                    },
                    (error) => {
                        console.error('Geolocation error:', error);
                        addNotification('Using simulated GPS for demo', 'info');
                    },
                    {
                        enableHighAccuracy: true,
                        maximumAge: 30000,
                        timeout: 27000
                    }
                );
            } else {
                addNotification('Geolocation not supported, using demo mode', 'info');
            }
        }
        
        // Load notifications
        function loadNotifications() {
            notifications = [...sampleNotifications];
            updateNotificationList();
            updateNotificationBadge();
        }
        
        // Add notification
        function addNotification(message, type = 'info', title = 'System Notification') {
            const notification = {
                id: Date.now(),
                title: title,
                message: message,
                type: type,
                time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
                read: false
            };
            
            notifications.unshift(notification);
            
            // Update UI
            updateNotificationList();
            updateNotificationBadge();
            
            // Show desktop notification if allowed
            if (Notification.permission === 'granted') {
                new Notification('TrashTrace Driver', {
                    body: message,
                    icon: '/favicon.ico'
                });
            }
            
            // Show toast notification
            showToastNotification(notification);
        }
        
        // Show toast notification
        function showToastNotification(notification) {
            const toast = document.createElement('div');
            toast.className = 'toast-notification';
            toast.style.cssText = `
                position: fixed;
                top: 100px;
                right: 30px;
                background: rgba(255, 255, 255, 0.95);
                padding: 15px 20px;
                border-radius: 12px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.15);
                z-index: 9999;
                max-width: 300px;
                border-left: 4px solid ${getNotificationColor(notification.type)};
                animation: slideInRight 0.3s ease-out;
                cursor: pointer;
                border: 1px solid rgba(0,0,0,0.1);
                backdrop-filter: blur(10px);
            `;
            
            toast.innerHTML = `
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                    <div style="width: 24px; height: 24px; background: ${getNotificationColor(notification.type)}; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 12px;">
                        ${getNotificationIcon(notification.type)}
                    </div>
                    <strong style="color: #2c3e50; font-size: 14px;">${notification.title}</strong>
                </div>
                <p style="margin: 0; color: #666; font-size: 13px; line-height: 1.4;">${notification.message}</p>
                <small style="color: #999; font-size: 11px; display: block; margin-top: 5px;">${notification.time}</small>
            `;
            
            document.body.appendChild(toast);
            
            toast.addEventListener('click', () => {
                toast.style.animation = 'slideOutRight 0.3s ease-out';
                setTimeout(() => toast.remove(), 300);
            });
            
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.style.animation = 'slideOutRight 0.3s ease-out';
                    setTimeout(() => toast.remove(), 300);
                }
            }, 5000);
        }
        
        function getNotificationColor(type) {
            switch(type) {
                case 'success': return '#4caf50';
                case 'warning': return '#ff9800';
                case 'error': return '#f44336';
                default: return '#2196f3';
            }
        }
        
        function getNotificationIcon(type) {
            switch(type) {
                case 'success': return '✓';
                case 'warning': return '!';
                case 'error': return '×';
                default: return 'i';
            }
        }
        
        // Update notification list
        function updateNotificationList() {
            const list = document.getElementById('notificationList');
            list.innerHTML = '';
            
            if (notifications.length === 0) {
                list.innerHTML = '<div style="padding: 40px 20px; text-align: center; color: #999;">No notifications</div>';
                return;
            }
            
            notifications.slice(0, 10).forEach(notification => {
                const item = document.createElement('div');
                item.className = `notification-item ${notification.read ? '' : 'unread'}`;
                item.innerHTML = `
                    <div style="display: flex; align-items: flex-start; gap: 12px;">
                        <div style="width: 36px; height: 36px; background: ${getNotificationColor(notification.type)}20; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: ${getNotificationColor(notification.type)}; font-size: 14px; font-weight: bold;">
                            ${getNotificationIcon(notification.type)}
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: 600; color: #2c3e50; margin-bottom: 4px;">${notification.title}</div>
                            <div style="color: #666; font-size: 13px; margin-bottom: 4px;">${notification.message}</div>
                            <div class="notification-time">
                                <i class="fas fa-clock" style="font-size: 11px;"></i> ${notification.time}
                            </div>
                        </div>
                    </div>
                `;
                
                item.addEventListener('click', () => {
                    notification.read = true;
                    updateNotificationList();
                    updateNotificationBadge();
                });
                
                list.appendChild(item);
            });
        }
        
        // Update notification badge
        function updateNotificationBadge() {
            const unreadCount = notifications.filter(n => !n.read).length;
            const badge = document.getElementById('notificationBadge');
            badge.textContent = unreadCount;
            
            if (unreadCount === 0) {
                badge.style.display = 'none';
            } else {
                badge.style.display = 'flex';
            }
        }
        
        // Request notification permission
        function requestNotificationPermission() {
            if ('Notification' in window) {
                Notification.requestPermission().then(permission => {
                    if (permission === 'granted') {
                        addNotification('Notifications enabled', 'success');
                    }
                });
            }
        }
        
        // Show completion modal
        function showCompletionModal(route) {
            const modal = document.createElement('div');
            modal.className = 'completion-modal';
            modal.innerHTML = `
                <div class="completion-content">
                    <div class="completion-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h2>Route Completed!</h2>
                    <p>${route.name} has been successfully completed and saved to your history.</p>
                    
                    <div class="completion-stats">
                        <div class="completion-stat">
                            <div class="completion-stat-value">${route.distance}</div>
                            <div class="completion-stat-label">Distance</div>
                        </div>
                        <div class="completion-stat">
                            <div class="completion-stat-value">${route.estimatedTime}</div>
                            <div class="completion-stat-label">Time</div>
                        </div>
                        <div class="completion-stat">
                            <div class="completion-stat-value">${route.stops}</div>
                            <div class="completion-stat-label">Stops</div>
                        </div>
                        <div class="completion-stat">
                            <div class="completion-stat-value">100%</div>
                            <div class="completion-stat-label">Progress</div>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 25px;">
                        <button class="btn btn-primary" id="viewHistoryBtn" style="flex: 1;">
                            <i class="fas fa-history"></i> View in History
                        </button>
                        <button class="btn btn-outline" id="closeModalBtn" style="flex: 1;">
                            <i class="fas fa-times"></i> Close
                        </button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // Event listeners for modal buttons
            document.getElementById('viewHistoryBtn').addEventListener('click', () => {
                window.location.href = 'history.php';
            });
            
            document.getElementById('closeModalBtn').addEventListener('click', () => {
                modal.remove();
            });
            
            // Close modal when clicking outside
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.remove();
                }
            });
        }
        
        // Complete route and save to history
        function completeCurrentRoute() {
            if (!selectedRoute) {
                addNotification('Please select a route first', 'warning');
                return;
            }
            
            // Update route status
            selectedRoute.status = 'completed';
            selectedRoute.progress = 100;
            
            // Stop truck simulation
            stopTruckSimulation();
            isNavigating = false;
            
            // Update UI
            updateRouteDetails(selectedRoute);
            updateProgressBar();
            loadRoutes();
            
            // Prepare data for form submission
            document.getElementById('completeRouteId').value = selectedRoute.id;
            document.getElementById('completeRouteData').value = JSON.stringify(selectedRoute);
            
            // Submit form to save to history
            document.getElementById('routeCompletionForm').submit();
        }
        
        // Event Listeners
        document.getElementById('startNavigation').addEventListener('click', () => {
            if (selectedRoute) {
                if (selectedRoute.status === 'completed') {
                    addNotification('This route has already been completed', 'warning');
                    return;
                }
                
                selectedRoute.status = 'active';
                isNavigating = true;
                
                // Update UI
                updateRouteDetails(selectedRoute);
                loadRoutes();
                
                // Start or continue truck simulation
                startTruckSimulation();
                
                addNotification('Navigation started for ' + selectedRoute.name, 'success');
                
                if (socket && socket.connected) {
                    socket.emit('navigation_started', {
                        route_id: selectedRoute.id,
                        driver_id: <?php echo $driver_id; ?>
                    });
                }
            } else {
                addNotification('Please select a route first', 'warning');
            }
        });
        
        document.getElementById('pauseNavigation').addEventListener('click', () => {
            if (selectedRoute && selectedRoute.status === 'active') {
                isNavigating = !isNavigating;
                
                if (isNavigating) {
                    addNotification('Navigation resumed', 'success');
                    document.getElementById('trackingStatus').textContent = 'Moving';
                    document.getElementById('trackingStatus').style.color = '#4caf50';
                } else {
                    addNotification('Navigation paused', 'warning');
                    stopTruckSimulation();
                }
                
                if (socket && socket.connected) {
                    socket.emit('navigation_paused', {
                        route_id: selectedRoute.id,
                        driver_id: <?php echo $driver_id; ?>
                    });
                }
            } else {
                addNotification('No active navigation to pause', 'warning');
            }
        });
        
        document.getElementById('completeRoute').addEventListener('click', completeCurrentRoute);
        
        document.getElementById('reportIssue').addEventListener('click', () => {
            const issue = prompt('Please describe the issue:');
            if (issue) {
                addNotification('Issue reported: ' + issue, 'warning');
                
                if (socket && socket.connected) {
                    socket.emit('issue_reported', {
                        driver_id: <?php echo $driver_id; ?>,
                        issue: issue,
                        route_id: selectedRoute ? selectedRoute.id : null
                    });
                }
            }
        });
        
        document.getElementById('notificationBadge').addEventListener('click', () => {
            const panel = document.getElementById('notificationPanel');
            panel.style.display = panel.style.display === 'block' ? 'none' : 'block';
            
            // Mark all as read when opening
            if (panel.style.display === 'block') {
                notifications.forEach(n => n.read = true);
                updateNotificationList();
                updateNotificationBadge();
            }
        });
        
        document.getElementById('closeNotifications').addEventListener('click', () => {
            document.getElementById('notificationPanel').style.display = 'none';
        });
        
        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', () => {
            initMap();
            requestNotificationPermission();
            
            // Demo: Auto-select first route
            setTimeout(() => {
                if (routes.length > 0) {
                    // Simulate click on first route
                    const firstRoute = document.querySelector('.route-item');
                    if (firstRoute) {
                        firstRoute.click();
                    }
                }
            }, 1000);
        });
        
        // Clean up on page unload
        window.addEventListener('beforeunload', () => {
            if (watchId) {
                navigator.geolocation.clearWatch(watchId);
            }
            
            if (truckInterval) {
                clearInterval(truckInterval);
            }
            
            if (socket) {
                socket.disconnect();
            }
        });
    </script>
</body>
</html>