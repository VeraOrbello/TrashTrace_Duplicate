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

// Get filter parameters
$filter_status = $_GET['status'] ?? 'all';
$filter_date = $_GET['date'] ?? 'all';
$filter_waste = $_GET['waste'] ?? 'all';
$filter_type = $_GET['type'] ?? 'all'; // collection or route
$search = $_GET['search'] ?? '';

// Fetch collections history
$collections_history = [];

try {
    $sql = "SELECT 
                c.*, 
                u.full_name as customer_name,
                u.phone as customer_phone,
                a.zone_name,
                a.area,
                'collection' as type
            FROM collections c
            LEFT JOIN users u ON c.customer_id = u.id
            LEFT JOIN assignments a ON c.assignment_id = a.id
            WHERE c.driver_id = ?";
    
    $params = [$driver_id];
    $types = "i";
    
    // Apply filters
    if($filter_status !== 'all') {
        $sql .= " AND c.status = ?";
        $params[] = $filter_status;
        $types .= "s";
    }
    
    if($filter_waste !== 'all') {
        $sql .= " AND c.waste_type = ?";
        $params[] = $filter_waste;
        $types .= "s";
    }
    
    if($filter_type === 'collection') {
        $sql .= " AND 1=1"; // Just continue with collections query
    }
    
    if($filter_date !== 'all') {
        $today = date('Y-m-d');
        if($filter_date === 'today') {
            $sql .= " AND DATE(c.collection_date) = ?";
            $params[] = $today;
            $types .= "s";
        } elseif($filter_date === 'week') {
            $week_ago = date('Y-m-d', strtotime('-7 days'));
            $sql .= " AND DATE(c.collection_date) >= ?";
            $params[] = $week_ago;
            $types .= "s";
        } elseif($filter_date === 'month') {
            $month_ago = date('Y-m-d', strtotime('-30 days'));
            $sql .= " AND DATE(c.collection_date) >= ?";
            $params[] = $month_ago;
            $types .= "s";
        }
    }
    
    if(!empty($search)) {
        $sql .= " AND (u.full_name LIKE ? OR c.pickup_address LIKE ? OR c.waste_type LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= "sss";
    }
    
    $sql .= " ORDER BY c.collection_date DESC";
    
    if($stmt = mysqli_prepare($link, $sql)){
        if(!empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        if(mysqli_stmt_execute($stmt)){
            $result = mysqli_stmt_get_result($stmt);
            while($row = mysqli_fetch_assoc($result)){
                $collections_history[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
    }
    
} catch(Exception $e) {
    error_log("Collections history error: " . $e->getMessage());
    // Generate sample data for demo
    $collections_history = generateSampleCollections();
}

// Fetch completed routes history from routes_history table
$routes_history = [];

try {
    if($filter_type === 'all' || $filter_type === 'route') {
        $sql = "SELECT 
                    rh.*,
                    dda.barangay,
                    dda.zones,
                    'route' as type
                FROM routes_history rh
                LEFT JOIN driver_daily_assignments dda ON rh.assignment_id = dda.id
                WHERE rh.driver_id = ?";
        
        $params = [$driver_id];
        $types = "i";
        
        // Apply filters for routes
        if($filter_date !== 'all') {
            $today = date('Y-m-d');
            if($filter_date === 'today') {
                $sql .= " AND DATE(rh.completed_at) = ?";
                $params[] = $today;
                $types .= "s";
            } elseif($filter_date === 'week') {
                $week_ago = date('Y-m-d', strtotime('-7 days'));
                $sql .= " AND DATE(rh.completed_at) >= ?";
                $params[] = $week_ago;
                $types .= "s";
            } elseif($filter_date === 'month') {
                $month_ago = date('Y-m-d', strtotime('-30 days'));
                $sql .= " AND DATE(rh.completed_at) >= ?";
                $params[] = $month_ago;
                $types .= "s";
            }
        }
        
        if(!empty($search)) {
            $sql .= " AND (rh.route_name LIKE ? OR rh.barangay LIKE ? OR rh.start_point LIKE ?)";
            $search_term = "%$search%";
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
            $types .= "sss";
        }
        
        $sql .= " ORDER BY rh.completed_at DESC";
        
        if($stmt = mysqli_prepare($link, $sql)){
            if(!empty($params)) {
                mysqli_stmt_bind_param($stmt, $types, ...$params);
            }
            if(mysqli_stmt_execute($stmt)){
                $result = mysqli_stmt_get_result($stmt);
                while($row = mysqli_fetch_assoc($result)){
                    // Format the data to match your existing structure
                    $formatted_row = [
                        'id' => $row['id'],
                        'route_name' => $row['route_name'] ?: 'Route #' . $row['id'],
                        'completed_at' => $row['completed_at'],
                        'start_point' => $row['start_point'] ?: 'Start Location',
                        'end_point' => $row['end_point'] ?: 'End Location',
                        'distance_km' => $row['distance_km'] ?: '0.0',
                        'estimated_time_minutes' => $row['estimated_time_minutes'] ?: '0',
                        'actual_time_minutes' => $row['actual_time_minutes'] ?: '0',
                        'total_stops' => $row['total_stops'] ?: 0,
                        'completed_stops' => $row['completed_stops'] ?: 0,
                        'total_weight' => $row['total_weight'] ?: 0.00,
                        'collections_count' => $row['collections_count'] ?: 0,
                        'total_amount' => ($row['total_weight'] ?: 0) * 5, // ₱5 per kg
                        'zone_name' => $row['zones'] ?: 'Zone A',
                        'area' => $row['barangay'] ?: 'Cebu City',
                        'barangay' => $row['barangay'] ?: 'Cebu City',
                        'zones' => $row['zones'] ?: '',
                        'notes' => $row['notes'] ?: '',
                        'driver_rating' => $row['driver_rating'] ?: 0.00,
                        'status' => 'completed',
                        'type' => 'route'
                    ];
                    
                    $routes_history[] = $formatted_row;
                }
            }
            mysqli_stmt_close($stmt);
        }
    }
} catch(Exception $e) {
    error_log("Routes history error: " . $e->getMessage());
    
    // Fallback 1: Check session data
    if(isset($_SESSION['completed_routes']) && !empty($_SESSION['completed_routes'])) {
        $routes_history = $_SESSION['completed_routes'];
        // Convert session data to proper format
        foreach($routes_history as &$route) {
            $route['type'] = 'route';
            $route['barangay'] = $route['barangay'] ?? 'Cebu City';
            $route['zones'] = $route['zone_name'] ?? '';
            $route['total_stops'] = $route['collections_count'] ?? 0;
            $route['completed_stops'] = $route['collections_count'] ?? 0;
        }
    } else {
        // Fallback 2: Generate sample data
        $routes_history = generateSampleRoutes();
    }
}

// Combine both histories for display
$history_data = [];
if($filter_type === 'all') {
    $history_data = array_merge($collections_history, $routes_history);
} elseif($filter_type === 'collection') {
    $history_data = $collections_history;
} elseif($filter_type === 'route') {
    $history_data = $routes_history;
}

// Sort by date (newest first)
usort($history_data, function($a, $b) {
    $dateA = $a['type'] === 'collection' ? ($a['collection_date'] ?? '') : ($a['completed_at'] ?? '');
    $dateB = $b['type'] === 'collection' ? ($b['collection_date'] ?? '') : ($b['completed_at'] ?? '');
    return strtotime($dateB) - strtotime($dateA);
});

function generateSampleCollections() {
    $sample_data = [];
    $statuses = ['completed', 'cancelled', 'pending'];
    $waste_types = ['Plastic', 'Paper', 'Glass', 'Metal', 'Organic', 'E-waste'];
    $customers = ['Juan Dela Cruz', 'Maria Santos', 'Pedro Reyes', 'Ana Lopez', 'James Wilson'];
    $areas = ['Lahug, Cebu', 'Apas, Cebu', 'IT Park, Cebu', 'Mabolo, Cebu'];
    
    for($i = 1; $i <= 15; $i++) {
        $weight = rand(10, 100);
        $amount = $weight * 5;
        $days_ago = rand(0, 90);
        
        $sample_data[] = [
            'id' => $i,
            'collection_date' => date('Y-m-d H:i:s', strtotime("-$days_ago days")),
            'customer_name' => $customers[array_rand($customers)],
            'customer_phone' => '09' . rand(100000000, 999999999),
            'pickup_address' => $areas[array_rand($areas)],
            'waste_type' => $waste_types[array_rand($waste_types)],
            'weight_kg' => $weight,
            'payment_amount' => $amount,
            'status' => $statuses[array_rand($statuses)],
            'zone_name' => 'Zone ' . chr(65 + ($i % 5)),
            'area' => $areas[array_rand($areas)],
            'notes' => $i % 3 == 0 ? 'Special handling required' : 'No issues',
            'type' => 'collection'
        ];
    }
    
    return $sample_data;
}

function generateSampleRoutes() {
    $sample_data = [];
    $route_names = ['Barangay Lahug Morning Route', 'Apas Residential Route', 'Downtown Collection Route', 
                   'IT Park Commercial Route', 'Mabolo Residential Collection'];
    $areas = ['Lahug, Cebu', 'Apas, Cebu', 'IT Park, Cebu', 'Mabolo, Cebu', 'Downtown Cebu'];
    
    for($i = 1; $i <= 10; $i++) {
        $days_ago = rand(0, 90);
        $collections_count = rand(8, 25);
        $total_weight = rand(150, 800);
        $total_amount = $total_weight * 5;
        
        $sample_data[] = [
            'id' => $i + 1000,
            'route_name' => $route_names[array_rand($route_names)],
            'completed_at' => date('Y-m-d H:i:s', strtotime("-$days_ago days")),
            'start_point' => $areas[array_rand($areas)],
            'end_point' => $areas[array_rand($areas)],
            'distance_km' => rand(5, 15) . '.' . rand(0, 9),
            'estimated_time' => rand(90, 240) . ' min',
            'collections_count' => $collections_count,
            'total_weight' => $total_weight,
            'total_amount' => $total_amount,
            'zone_name' => 'Zone ' . chr(65 + ($i % 5)),
            'area' => $areas[array_rand($areas)],
            'barangay' => $areas[array_rand($areas)],
            'zones' => 'Zone ' . chr(65 + ($i % 5)),
            'status' => 'completed',
            'type' => 'route',
            'total_stops' => $collections_count,
            'completed_stops' => $collections_count,
            'estimated_time_minutes' => rand(90, 240),
            'actual_time_minutes' => rand(85, 235),
            'driver_rating' => rand(35, 50) / 10 // 3.5 to 5.0
        ];
    }
    
    return $sample_data;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History - TrashTrace Driver</title>
    <link href="../node_modules/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Consolidated CSS -->
    <link rel="stylesheet" href="../css/driver/master-styles.css">
    
    <style>
        /* Additional styles matching routes.php design */
        .history-container {
            display: flex;
            flex-direction: column;
            gap: 30px;
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
        
        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            padding: 0 24px;
            padding-bottom: 20px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 200px;
            flex: 1;
        }
        
        .filter-group label {
            font-size: 0.9rem;
            color: #666;
            font-weight: 500;
            font-family: 'Montserrat', sans-serif;
        }
        
        .filter-select {
            padding: 12px 15px;
            border: 1px solid #e8f5e9;
            border-radius: 12px;
            background: white;
            color: #333;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            font-family: 'Montserrat', sans-serif;
            width: 100%;
        }
        
        .filter-select:focus {
            outline: none;
            border-color: #4caf50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }
        
        .search-box {
            position: relative;
            margin: 0 24px 20px 24px;
        }
        
        .search-box input {
            width: 100%;
            padding: 12px 20px 12px 45px;
            border: 1px solid #e8f5e9;
            border-radius: 12px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: #f8fdf9;
            font-family: 'Montserrat', sans-serif;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: #4caf50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
            background: white;
        }
        
        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            padding: 24px;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 20px;
            animation: fadeInUp 0.6s ease;
            backdrop-filter: blur(20px);
            position: relative;
            z-index: 2;
        }
        
        .stat-card::before {
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
        
        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
            border-color: rgba(76, 175, 80, 0.3);
        }
        
        .stat-content {
            display: flex;
            align-items: center;
            gap: 1.2rem;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        
        .icon-collections {
            background: linear-gradient(135deg, #4caf50, #2e7d32);
        }
        
        .icon-routes {
            background: linear-gradient(135deg, #2196f3, #1976d2);
        }
        
        .icon-weight {
            background: linear-gradient(135deg, #ff9800, #f57c00);
        }
        
        .icon-earnings {
            background: linear-gradient(135deg, #9c27b0, #7b1fa2);
        }
        
        .stat-info h3 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 0.2rem;
            line-height: 1;
            font-family: 'Montserrat', sans-serif;
        }
        
        .stat-info p {
            color: #666;
            font-size: 0.9rem;
            font-weight: 500;
            font-family: 'Montserrat', sans-serif;
        }
        
        .table-container {
            overflow-x: auto;
            border-radius: 12px;
            margin: 0 24px 24px 24px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }
        
        thead {
            background: linear-gradient(135deg, #e8f5e9, #f1f8e9);
        }
        
        th {
            padding: 16px;
            text-align: left;
            color: #2e7d32;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #c8e6c9;
            font-family: 'Montserrat', sans-serif;
        }
        
        td {
            padding: 16px;
            border-bottom: 1px solid #e8f5e9;
            color: #555;
            font-family: 'Montserrat', sans-serif;
            vertical-align: middle;
        }
        
        tbody tr {
            transition: all 0.3s ease;
        }
        
        tbody tr:hover {
            background: #f8fdf9;
            transform: translateX(5px);
        }
        
        .history-type {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .type-collection {
            background: rgba(76, 175, 80, 0.1);
            color: #2e7d32;
            border: 1px solid rgba(76, 175, 80, 0.2);
        }
        
        .type-route {
            background: rgba(33, 150, 243, 0.1);
            color: #1976d2;
            border: 1px solid rgba(33, 150, 243, 0.2);
        }
        
        .type-collection i,
        .type-route i {
            font-size: 0.7rem;
        }
        
        .route-details {
            font-size: 0.9rem;
            color: #666;
        }
        
        .route-details div {
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .route-details i {
            color: #4caf50;
            width: 16px;
        }
        
        .history-stats {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .stat-item {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
        }
        
        .stat-label {
            color: #666;
        }
        
        .stat-value {
            color: #2c3e50;
            font-weight: 500;
        }
        
        .history-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        
        .history-icon.route {
            background: linear-gradient(135deg, #2196f3, #0d47a1);
            color: white;
        }
        
        .history-icon.collection {
            background: linear-gradient(135deg, #4caf50, #2e7d32);
            color: white;
        }
        
        .history-meta {
            display: flex;
            gap: 10px;
            font-size: 0.8rem;
            color: #999;
            margin-top: 4px;
        }
        
        .history-meta span {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .amount-display {
            text-align: right;
        }
        
        .amount {
            font-weight: 600;
            color: #2e7d32;
            font-size: 1.1rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .btn-action {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
            font-family: 'Montserrat', sans-serif;
            font-weight: 500;
        }
        
        .btn-view {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .btn-view:hover {
            background: #bbdefb;
            transform: translateY(-2px);
        }
        
        .btn-view-route {
            background: rgba(76, 175, 80, 0.1);
            color: #2e7d32;
            border: 1px solid rgba(76, 175, 80, 0.3);
        }
        
        .btn-view-route:hover {
            background: rgba(76, 175, 80, 0.2);
        }
        
        .btn-view-notes {
            background: rgba(255, 193, 7, 0.1);
            color: #ff9800;
            border: 1px solid rgba(255, 193, 7, 0.3);
        }
        
        .btn-view-notes:hover {
            background: rgba(255, 193, 7, 0.2);
        }
        
        .btn-print {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .btn-print:hover {
            background: #c8e6c9;
            transform: translateY(-2px);
        }
        
        .btn-export {
            background: #fff3e0;
            color: #ef6c00;
        }
        
        .btn-export:hover {
            background: #ffe0b2;
            transform: translateY(-2px);
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin: 0 24px 24px 24px;
            padding-top: 24px;
            border-top: 2px solid #f0f7f3;
        }
        
        .page-btn {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            border: 1px solid #e8f5e9;
            background: white;
            color: #666;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .page-btn:hover {
            background: #f0f9f4;
            color: #2e7d32;
            border-color: #c8e6c9;
            transform: translateY(-2px);
        }
        
        .page-btn.active {
            background: linear-gradient(135deg, #4caf50, #2e7d32);
            color: white;
            border-color: #2e7d32;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
            font-family: 'Montserrat', sans-serif;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #e0e0e0;
        }
        
        .empty-state h3 {
            color: #666;
            margin-bottom: 0.5rem;
            font-family: 'Montserrat', sans-serif;
        }
        
        .empty-state p {
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
            font-family: 'Montserrat', sans-serif;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            font-family: 'Montserrat', sans-serif;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4CAF50, #2E7D32);
            color: white;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76, 175, 80, 0.4);
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid #ddd;
            color: #666;
        }
        
        .btn-outline:hover {
            border-color: #4CAF50;
            color: #4CAF50;
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #2196F3, #0D47A1);
            color: white;
            box-shadow: 0 4px 15px rgba(33, 150, 243, 0.3);
        }
        
        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(33, 150, 243, 0.4);
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            padding: 0 24px;
            padding-bottom: 20px;
        }
        
        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-completed {
            background: rgba(76, 175, 80, 0.1);
            color: #2e7d32;
            border: 1px solid rgba(76, 175, 80, 0.3);
        }
        
        .status-pending {
            background: rgba(255, 193, 7, 0.1);
            color: #ff9800;
            border: 1px solid rgba(255, 193, 7, 0.3);
        }
        
        .status-cancelled {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
            border: 1px solid rgba(244, 67, 54, 0.3);
        }
        
        /* Modal Overlay */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            animation: fadeIn 0.3s ease;
        }
        
        .modal-content {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
            overflow: hidden;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
        }
        
        .modal-header {
            padding: 24px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #f8fdf9, #f0f9f4);
        }
        
        .modal-header h3 {
            margin: 0;
            color: #2c3e50;
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 2rem;
            cursor: pointer;
            color: #999;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }
        
        .close-modal:hover {
            color: #f44336;
            background: rgba(0, 0, 0, 0.05);
        }
        
        .modal-body {
            padding: 24px;
            overflow-y: auto;
            flex: 1;
        }
        
        .modal-footer {
            padding: 20px 24px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            background: #f9f9f9;
        }
        
        .route-info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .info-item {
            padding: 15px;
            background: #f8fdf9;
            border-radius: 12px;
            border: 1px solid #e8f5e9;
        }
        
        .info-item.full-width {
            grid-column: 1 / -1;
        }
        
        .info-label {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .info-label i {
            width: 16px;
        }
        
        .info-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .notes-content {
            padding: 15px;
            background: #f8fdf9;
            border-radius: 12px;
            border: 1px solid #e8f5e9;
            font-size: 0.95rem;
            line-height: 1.5;
            color: #555;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @media (max-width: 768px) {
            .filters {
                flex-direction: column;
            }
            
            .filter-group {
                min-width: 100%;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .table-container {
                margin: 0 15px 15px 15px;
            }
            
            th, td {
                padding: 12px 8px;
                font-size: 0.85rem;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 5px;
            }
            
            .btn-action {
                padding: 6px 10px;
                font-size: 0.8rem;
            }
            
            .route-info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Updated Navbar matching routes.php -->
        <header class="dashboard-header">
            <!-- Grid Background Pattern -->
            <div class="grid-background-nav"></div>
            
            <div class="header-content">
                <a href="../driver_dashboard.php" class="logo">
                    <i class="fas fa-recycle"></i>
                    <span>Trash<span style="font-weight: 700;">Trace</span></span>
                </a>
                
                <button class="mobile-menu-toggle" id="mobileMenuToggle">
                    <i class="fas fa-bars"></i>
                </button>
                
                <nav id="mainNav">
                    <div class="nav-container">
                        <ul>
                            <li><a href="../driver_dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                            <li><a href="assignments.php" class="nav-link"><i class="fas fa-tasks"></i> <span>Assignments</span></a></li>
                            <li><a href="routes.php" class="nav-link"><i class="fas fa-route"></i> <span>Routes</span></a></li>
                            <li><a href="collections.php" class="nav-link"><i class="fas fa-trash"></i> <span>Collections</span></a></li>
                            <li><a href="earnings.php" class="nav-link"><i class="fas fa-money-bill-wave"></i> <span>Earnings</span></a></li>
                            <li><a href="history.php" class="nav-link active"><i class="fas fa-history"></i> <span>History</span></a></li>
                            <li><a href="profile.php" class="nav-link"><i class="fas fa-user"></i> <span>Profile</span></a></li>
                        </ul>
                    </div>
                </nav>
                
                <div class="user-menu">
                    <div class="user-info" onclick="window.location.href='profile.php'">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($driver_name, 0, 1)); ?>
                        </div>
                        <div class="user-details">
                            <span class="user-name"><?php echo htmlspecialchars($driver_name); ?></span>
                            <span class="user-id">ID: #<?php echo str_pad($driver_id, 4, '0', STR_PAD_LEFT); ?></span>
                        </div>
                    </div>
                    <a href="../logout.php" class="btn-logout">
                        <i class="fas fa-sign-out-alt"></i>
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
                        <i class="fas fa-history"></i>
                        Collection & Route History
                    </h1>
                    <p class="page-subtitle">View your complete history of collections and routes with detailed analytics.</p>
                </div>
                
                <div class="history-container">
                    <!-- Filters -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3><i class="fas fa-filter"></i> Filters & Search</h3>
                        </div>
                        
                        <form method="GET" action="history.php" id="filterForm">
                            <div class="filters">
                                <div class="filter-group">
                                    <label for="type">History Type</label>
                                    <select name="type" id="type" class="filter-select">
                                        <option value="all" <?php echo $filter_type == 'all' ? 'selected' : ''; ?>>All Types</option>
                                        <option value="collection" <?php echo $filter_type == 'collection' ? 'selected' : ''; ?>>Collections Only</option>
                                        <option value="route" <?php echo $filter_type == 'route' ? 'selected' : ''; ?>>Routes Only</option>
                                    </select>
                                </div>
                                
                                <div class="filter-group">
                                    <label for="status">Status</label>
                                    <select name="status" id="status" class="filter-select">
                                        <option value="all" <?php echo $filter_status == 'all' ? 'selected' : ''; ?>>All Status</option>
                                        <option value="completed" <?php echo $filter_status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="cancelled" <?php echo $filter_status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                                
                                <div class="filter-group">
                                    <label for="date">Date Range</label>
                                    <select name="date" id="date" class="filter-select">
                                        <option value="all" <?php echo $filter_date == 'all' ? 'selected' : ''; ?>>All Time</option>
                                        <option value="today" <?php echo $filter_date == 'today' ? 'selected' : ''; ?>>Today</option>
                                        <option value="week" <?php echo $filter_date == 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                                        <option value="month" <?php echo $filter_date == 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                                    </select>
                                </div>
                                
                                <div class="filter-group">
                                    <label for="waste">Waste Type</label>
                                    <select name="waste" id="waste" class="filter-select">
                                        <option value="all" <?php echo $filter_waste == 'all' ? 'selected' : ''; ?>>All Types</option>
                                        <option value="plastic" <?php echo $filter_waste == 'plastic' ? 'selected' : ''; ?>>Plastic</option>
                                        <option value="paper" <?php echo $filter_waste == 'paper' ? 'selected' : ''; ?>>Paper</option>
                                        <option value="glass" <?php echo $filter_waste == 'glass' ? 'selected' : ''; ?>>Glass</option>
                                        <option value="metal" <?php echo $filter_waste == 'metal' ? 'selected' : ''; ?>>Metal</option>
                                        <option value="organic" <?php echo $filter_waste == 'organic' ? 'selected' : ''; ?>>Organic</option>
                                        <option value="e-waste" <?php echo $filter_waste == 'e-waste' ? 'selected' : ''; ?>>E-waste</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" name="search" id="search" placeholder="Search by customer, route name, or address..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            
                            <div class="filter-buttons">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Apply Filters
                                </button>
                                <button type="button" id="resetFilters" class="btn btn-outline">
                                    <i class="fas fa-redo"></i> Reset
                                </button>
                                <button type="button" id="exportHistory" class="btn btn-secondary">
                                    <i class="fas fa-download"></i> Export History
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Statistics -->
                    <div class="stats-grid">
                        <?php
                        $total_collections = count($collections_history);
                        $total_routes = count($routes_history);
                        $total_weight = array_sum(array_column($collections_history, 'weight_kg'));
                        $total_amount = array_sum(array_column($collections_history, 'payment_amount')) + 
                                      array_sum(array_column($routes_history, 'total_amount'));
                        ?>
                        
                        <div class="stat-card">
                            <div class="stat-content">
                                <div class="stat-icon icon-collections">
                                    <i class="fas fa-trash-alt"></i>
                                </div>
                                <div class="stat-info">
                                    <h3><?php echo $total_collections; ?></h3>
                                    <p>Total Collections</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-content">
                                <div class="stat-icon icon-routes">
                                    <i class="fas fa-route"></i>
                                </div>
                                <div class="stat-info">
                                    <h3><?php echo $total_routes; ?></h3>
                                    <p>Completed Routes</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-content">
                                <div class="stat-icon icon-weight">
                                    <i class="fas fa-weight-hanging"></i>
                                </div>
                                <div class="stat-info">
                                    <h3><?php echo number_format($total_weight, 0); ?> kg</h3>
                                    <p>Total Weight</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-content">
                                <div class="stat-icon icon-earnings">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                                <div class="stat-info">
                                    <h3>₱<?php echo number_format($total_amount, 2); ?></h3>
                                    <p>Total Earnings</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- History Table -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3><i class="fas fa-list"></i> History Records (<?php echo count($history_data); ?>)</h3>
                        </div>
                        
                        <?php if(empty($history_data)): ?>
                            <div class="empty-state">
                                <i class="fas fa-history"></i>
                                <h3>No History Found</h3>
                                <p>No records match your current filters.</p>
                                <button onclick="window.location.href='history.php'" class="btn btn-primary">
                                    <i class="fas fa-redo"></i> Clear Filters
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="table-container">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Type</th>
                                            <th>Date & Time</th>
                                            <th>Details</th>
                                            <th>Zone/Area</th>
                                            <th>Metrics</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($history_data as $item): ?>
                                            <?php if($item['type'] === 'route'): ?>
                                                <!-- Route History Row -->
                                                <tr>
                                                    <td>
                                                        <div style="display: flex; align-items: center; gap: 10px;">
                                                            <div class="history-icon route">
                                                                <i class="fas fa-route"></i>
                                                            </div>
                                                            <div>
                                                                <strong><?php echo htmlspecialchars($item['route_name']); ?></strong>
                                                                <div class="history-meta">
                                                                    <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($item['completed_at'])); ?></span>
                                                                    <span><i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($item['completed_at'])); ?></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="route-details">
                                                            <div><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($item['start_point']); ?></div>
                                                            <div><i class="fas fa-flag-checkered"></i> <?php echo htmlspecialchars($item['end_point']); ?></div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="history-stats">
                                                            <div class="stat-item">
                                                                <span class="stat-label">Distance:</span>
                                                                <span class="stat-value"><?php echo $item['distance_km']; ?> km</span>
                                                            </div>
                                                            <div class="stat-item">
                                                                <span class="stat-label">Time:</span>
                                                                <span class="stat-value"><?php echo $item['actual_time_minutes']; ?> min</span>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="history-stats">
                                                            <div class="stat-item">
                                                                <span class="stat-label">Stops:</span>
                                                                <span class="stat-value"><?php echo $item['completed_stops']; ?>/<?php echo $item['total_stops']; ?></span>
                                                            </div>
                                                            <div class="stat-item">
                                                                <span class="stat-label">Weight:</span>
                                                                <span class="stat-value"><?php echo number_format($item['total_weight'], 2); ?> kg</span>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="amount-display">
                                                            <span class="amount">₱<?php echo number_format($item['total_amount'] ?? ($item['total_weight'] * 5), 2); ?></span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="status-badge status-completed">
                                                            <i class="fas fa-check-circle"></i> Completed
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="action-buttons">
                                                            <button class="btn-action btn-view-route view-route" 
                                                                    data-route-id="<?php echo $item['id']; ?>"
                                                                    data-route-name="<?php echo htmlspecialchars($item['route_name']); ?>"
                                                                    data-distance="<?php echo $item['distance_km']; ?>"
                                                                    data-time="<?php echo $item['actual_time_minutes']; ?>"
                                                                    data-stops="<?php echo $item['completed_stops']; ?>/<?php echo $item['total_stops']; ?>"
                                                                    data-weight="<?php echo number_format($item['total_weight'], 2); ?> kg"
                                                                    data-amount="₱<?php echo number_format($item['total_amount'] ?? ($item['total_weight'] * 5), 2); ?>"
                                                                    data-barangay="<?php echo htmlspecialchars($item['barangay']); ?>"
                                                                    data-zones="<?php echo htmlspecialchars($item['zones']); ?>"
                                                                    data-completed="<?php echo date('F j, Y, g:i A', strtotime($item['completed_at'])); ?>">
                                                                <i class="fas fa-eye"></i> View
                                                            </button>
                                                            <?php if($item['notes']): ?>
                                                                <button class="btn-action btn-view-notes view-notes" data-notes="<?php echo htmlspecialchars($item['notes']); ?>">
                                                                    <i class="fas fa-sticky-note"></i> Notes
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <!-- Collection History Row -->
                                                <tr>
                                                    <td>
                                                        <span class="history-type type-collection">
                                                            <i class="fas fa-trash-alt"></i> Collection
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div style="font-weight: 500;">
                                                            <?php echo date('M d, Y', strtotime($item['collection_date'])); ?>
                                                        </div>
                                                        <div style="font-size: 0.8rem; color: #999;">
                                                            <?php echo date('h:i A', strtotime($item['collection_date'])); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div style="font-weight: 500;"><?php echo htmlspecialchars($item['customer_name']); ?></div>
                                                        <div style="font-size: 0.8rem; color: #999;">
                                                            <?php echo htmlspecialchars($item['waste_type']); ?> • 
                                                            <?php echo htmlspecialchars($item['customer_phone']); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div style="font-weight: 500;"><?php echo htmlspecialchars($item['zone_name'] ?? 'N/A'); ?></div>
                                                        <div style="font-size: 0.8rem; color: #999;"><?php echo htmlspecialchars($item['area'] ?? ''); ?></div>
                                                    </td>
                                                    <td>
                                                        <div style="font-weight: 600; color: #2e7d32;">
                                                            <?php echo number_format($item['weight_kg'], 1); ?> kg
                                                        </div>
                                                        <div style="font-size: 0.8rem; color: #ff9800;">
                                                            ₱<?php echo number_format($item['payment_amount'], 2); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="status-badge status-<?php echo htmlspecialchars($item['status']); ?>">
                                                            <?php echo ucfirst($item['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="action-buttons">
                                                            <button class="btn-action btn-view view-details" 
                                                                    data-id="<?php echo $item['id']; ?>" 
                                                                    data-type="<?php echo $item['type']; ?>">
                                                                <i class="fas fa-eye"></i> View
                                                            </button>
                                                            <?php if($item['status'] == 'completed'): ?>
                                                                <button class="btn-action btn-print print-receipt" 
                                                                        data-id="<?php echo $item['id']; ?>" 
                                                                        data-type="<?php echo $item['type']; ?>">
                                                                    <i class="fas fa-print"></i> Print
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <div class="pagination">
                                <button class="page-btn"><i class="fas fa-chevron-left"></i></button>
                                <button class="page-btn active">1</button>
                                <button class="page-btn">2</button>
                                <button class="page-btn">3</button>
                                <button class="page-btn">4</button>
                                <button class="page-btn">5</button>
                                <button class="page-btn"><i class="fas fa-chevron-right"></i></button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const mainNav = document.getElementById('mainNav');
            
            if(mobileMenuToggle && mainNav) {
                mobileMenuToggle.addEventListener('click', function(e) {
                    e.stopPropagation();
                    mainNav.classList.toggle('active');
                });
                
                // Close menu when clicking outside
                document.addEventListener('click', function(event) {
                    if(window.innerWidth <= 900) {
                        if(!mainNav.contains(event.target) && !mobileMenuToggle.contains(event.target)) {
                            mainNav.classList.remove('active');
                        }
                    }
                });
            }
            
            // Reset Filters
            document.getElementById('resetFilters').addEventListener('click', function() {
                window.location.href = 'history.php';
            });
            
            // Export History
            document.getElementById('exportHistory').addEventListener('click', function() {
                const form = document.getElementById('filterForm');
                const exportBtn = this;
                
                // Show loading state
                const originalHTML = exportBtn.innerHTML;
                exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exporting...';
                exportBtn.disabled = true;
                
                // Submit form to export endpoint
                setTimeout(() => {
                    const exportForm = document.createElement('form');
                    exportForm.method = 'POST';
                    exportForm.action = 'export_history.php';
                    exportForm.target = '_blank';
                    
                    // Add all filter parameters
                    const params = new URLSearchParams(new FormData(form));
                    params.forEach((value, key) => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = key;
                        input.value = value;
                        exportForm.appendChild(input);
                    });
                    
                    // Add driver ID
                    const driverInput = document.createElement('input');
                    driverInput.type = 'hidden';
                    driverInput.name = 'driver_id';
                    driverInput.value = '<?php echo $driver_id; ?>';
                    exportForm.appendChild(driverInput);
                    
                    document.body.appendChild(exportForm);
                    exportForm.submit();
                    document.body.removeChild(exportForm);
                    
                    // Reset button
                    setTimeout(() => {
                        exportBtn.innerHTML = originalHTML;
                        exportBtn.disabled = false;
                    }, 1000);
                }, 500);
            });
            
            // View collection details
            document.querySelectorAll('.view-details').forEach(button => {
                button.addEventListener('click', function() {
                    const itemId = this.getAttribute('data-id');
                    const itemType = this.getAttribute('data-type');
                    
                    // Show loading state
                    const modal = createModal();
                    modal.querySelector('.modal-body').innerHTML = `
                        <div style="text-align: center; padding: 40px 0;">
                            <div class="loader" style="width: 40px; height: 40px; border: 3px solid #f3f3f3; border-top: 3px solid #4caf50; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto;"></div>
                            <p>Loading details...</p>
                        </div>
                        <style>
                            @keyframes spin {
                                0% { transform: rotate(0deg); }
                                100% { transform: rotate(360deg); }
                            }
                        </style>
                    `;
                    
                    // Show modal
                    document.body.appendChild(modal);
                    
                    // Simulate API call with different content for collection vs route
                    setTimeout(() => {
                        if(itemType === 'collection') {
                            modal.querySelector('.modal-body').innerHTML = getCollectionDetails(itemId);
                        } else {
                            modal.querySelector('.modal-body').innerHTML = getCollectionDetails(itemId);
                        }
                    }, 500);
                });
            });
            
            // View route details
            document.addEventListener('click', function(e) {
                if(e.target.closest('.view-route')) {
                    const btn = e.target.closest('.view-route');
                    const routeData = {
                        name: btn.dataset.routeName,
                        distance: btn.dataset.distance,
                        time: btn.dataset.time,
                        stops: btn.dataset.stops,
                        weight: btn.dataset.weight,
                        amount: btn.dataset.amount,
                        barangay: btn.dataset.barangay,
                        zones: btn.dataset.zones,
                        completed: btn.dataset.completed
                    };
                    
                    showRouteDetailsModal(routeData);
                }
                
                if(e.target.closest('.view-notes')) {
                    const btn = e.target.closest('.view-notes');
                    showNotesModal(btn.dataset.notes);
                }
            });
            
            // Print Receipt
            document.querySelectorAll('.print-receipt').forEach(button => {
                button.addEventListener('click', function() {
                    const itemId = this.getAttribute('data-id');
                    const itemType = this.getAttribute('data-type');
                    
                    if(confirm(`Print ${itemType === 'collection' ? 'receipt' : 'route summary'} for ID: ${itemId}?`)) {
                        window.open(`receipt.php?type=${itemType}&id=${itemId}`, '_blank');
                    }
                });
            });
            
            // Auto-submit form on filter change
            document.getElementById('type').addEventListener('change', function() {
                document.getElementById('filterForm').submit();
            });
            
            document.getElementById('status').addEventListener('change', function() {
                document.getElementById('filterForm').submit();
            });
            
            document.getElementById('date').addEventListener('change', function() {
                document.getElementById('filterForm').submit();
            });
            
            document.getElementById('waste').addEventListener('change', function() {
                document.getElementById('filterForm').submit();
            });
            
            // Add search debounce
            let searchTimeout;
            document.getElementById('search').addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    document.getElementById('filterForm').submit();
                }, 500);
            });
        });
        
        function createModal() {
            const modal = document.createElement('div');
            modal.className = 'modal-overlay';
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 800px;">
                    <div class="modal-header">
                        <h3><i class="fas fa-info-circle"></i> Details</h3>
                        <button class="close-modal">&times;</button>
                    </div>
                    <div class="modal-body"></div>
                    <div class="modal-footer">
                        <button class="btn btn-outline close-modal">Close</button>
                    </div>
                </div>
            `;
            
            // Close modal events
            modal.querySelectorAll('.close-modal').forEach(btn => {
                btn.addEventListener('click', () => modal.remove());
            });
            
            modal.addEventListener('click', (e) => {
                if(e.target === modal) modal.remove();
            });
            
            return modal;
        }
        
        function getCollectionDetails(id) {
            return `
                <div style="display: flex; flex-direction: column; gap: 20px;">
                    <!-- Header -->
                    <div style="background: #f8fdf9; padding: 20px; border-radius: 12px; border-left: 4px solid #4caf50;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div>
                                <div style="font-size: 1.2rem; font-weight: 600; color: #2c3e50; margin-bottom: 5px;">Collection #${String(id).padStart(6, '0')}</div>
                                <div style="font-size: 0.9rem; color: #666;">Completed on ${new Date().toLocaleDateString()}</div>
                            </div>
                            <span class="status-badge status-completed">Completed</span>
                        </div>
                    </div>
                    
                    <!-- Details Grid -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <div style="font-size: 0.85rem; color: #666; margin-bottom: 5px;">Customer</div>
                            <div style="font-weight: 600; color: #333;">Juan Dela Cruz</div>
                            <div style="font-size: 0.85rem; color: #999;">09123456789</div>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #666; margin-bottom: 5px;">Zone</div>
                            <div style="font-weight: 600; color: #333;">Zone A - Lahug</div>
                            <div style="font-size: 0.85rem; color: #999;">Barangay Lahug, Cebu City</div>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #666; margin-bottom: 5px;">Waste Type</div>
                            <div>
                                <span style="display: inline-block; padding: 4px 12px; background: #e8f5e9; color: #2e7d32; border-radius: 20px; font-size: 0.85rem;">Plastic</span>
                            </div>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #666; margin-bottom: 5px;">Weight</div>
                            <div style="font-weight: 600; color: #2e7d32; font-size: 1.2rem;">45.5 kg</div>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #666; margin-bottom: 5px;">Amount</div>
                            <div style="font-weight: 600; color: #ff9800; font-size: 1.2rem;">₱227.50</div>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #666; margin-bottom: 5px;">Payment Method</div>
                            <div style="font-weight: 600; color: #333;">Cash</div>
                        </div>
                    </div>
                    
                    <!-- Notes -->
                    <div>
                        <div style="font-size: 0.9rem; color: #666; margin-bottom: 10px; font-weight: 500;">Notes</div>
                        <div style="background: #f8fdf9; padding: 15px; border-radius: 8px; border: 1px solid #e8f5e9;">
                            <p style="margin: 0; color: #555; font-size: 0.95rem;">Collection completed successfully. Customer had pre-sorted recyclables. No issues encountered.</p>
                        </div>
                    </div>
                    
                    <!-- Driver Actions -->
                    <div>
                        <div style="font-size: 0.9rem; color: #666; margin-bottom: 10px; font-weight: 500;">Driver Actions</div>
                        <div style="background: #f0f7f3; padding: 12px; border-radius: 8px; font-size: 0.85rem; color: #666;">
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px;">
                                <i class="fas fa-user-check" style="color: #4caf50;"></i>
                                <span>Driver verified waste type and weight</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px;">
                                <i class="fas fa-camera" style="color: #2196f3;"></i>
                                <span>Photo of collected waste was taken</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-receipt" style="color: #ff9800;"></i>
                                <span>Digital receipt issued to customer</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Buttons -->
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button class="btn btn-primary" style="flex: 1;" onclick="printDetails(${id}, 'collection')">
                            <i class="fas fa-print"></i> Print Receipt
                        </button>
                        <button class="btn btn-secondary" style="flex: 1;" onclick="sendReceipt(${id}, 'collection')">
                            <i class="fas fa-paper-plane"></i> Send to Customer
                        </button>
                    </div>
                </div>
            `;
        }
        
        function showRouteDetailsModal(route) {
            const modal = document.createElement('div');
            modal.className = 'modal-overlay';
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 800px;">
                    <div class="modal-header">
                        <h3><i class="fas fa-route"></i> Route Details</h3>
                        <button class="close-modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div style="display: flex; flex-direction: column; gap: 20px;">
                            <!-- Header -->
                            <div style="background: #f8fdf9; padding: 20px; border-radius: 12px; border-left: 4px solid #2196f3;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                    <div>
                                        <div style="font-size: 1.2rem; font-weight: 600; color: #2c3e50; margin-bottom: 5px;">${route.name}</div>
                                        <div style="font-size: 0.9rem; color: #666;">Completed on ${route.completed}</div>
                                    </div>
                                    <span class="status-badge status-completed">Completed</span>
                                </div>
                            </div>
                            
                            <!-- Route Stats -->
                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                                <div style="padding: 15px; background: #f8fdf9; border-radius: 12px; text-align: center;">
                                    <div style="font-size: 1.5rem; font-weight: 700; color: #2e7d32; margin-bottom: 5px;">${route.distance}</div>
                                    <div style="font-size: 0.85rem; color: #666; text-transform: uppercase; letter-spacing: 0.5px;">Distance</div>
                                </div>
                                <div style="padding: 15px; background: #f8fdf9; border-radius: 12px; text-align: center;">
                                    <div style="font-size: 1.5rem; font-weight: 700; color: #2e7d32; margin-bottom: 5px;">${route.time}</div>
                                    <div style="font-size: 0.85rem; color: #666; text-transform: uppercase; letter-spacing: 0.5px;">Duration</div>
                                </div>
                                <div style="padding: 15px; background: #f8fdf9; border-radius: 12px; text-align: center;">
                                    <div style="font-size: 1.5rem; font-weight: 700; color: #2e7d32; margin-bottom: 5px;">${route.stops}</div>
                                    <div style="font-size: 0.85rem; color: #666; text-transform: uppercase; letter-spacing: 0.5px;">Stops</div>
                                </div>
                                <div style="padding: 15px; background: #f8fdf9; border-radius: 12px; text-align: center;">
                                    <div style="font-size: 1.5rem; font-weight: 700; color: #2e7d32; margin-bottom: 5px;">${route.amount}</div>
                                    <div style="font-size: 0.85rem; color: #666; text-transform: uppercase; letter-spacing: 0.5px;">Total</div>
                                </div>
                            </div>
                            
                            <!-- Details Grid -->
                            <div class="route-info-grid">
                                <div class="info-item">
                                    <div class="info-label"><i class="fas fa-signature"></i> Route Name</div>
                                    <div class="info-value">${route.name}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label"><i class="fas fa-road"></i> Distance</div>
                                    <div class="info-value">${route.distance}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label"><i class="fas fa-clock"></i> Duration</div>
                                    <div class="info-value">${route.time}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label"><i class="fas fa-trash-alt"></i> Stops Completed</div>
                                    <div class="info-value">${route.stops}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label"><i class="fas fa-weight"></i> Total Weight</div>
                                    <div class="info-value">${route.weight}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label"><i class="fas fa-money-bill-wave"></i> Total Earnings</div>
                                    <div class="info-value" style="color: #4caf50; font-weight: 600;">${route.amount}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label"><i class="fas fa-map-marked-alt"></i> Barangay</div>
                                    <div class="info-value">${route.barangay}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label"><i class="fas fa-layer-group"></i> Zones</div>
                                    <div class="info-value">${route.zones}</div>
                                </div>
                                <div class="info-item full-width">
                                    <div class="info-label"><i class="fas fa-calendar-check"></i> Completed On</div>
                                    <div class="info-value">${route.completed}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline close-modal">Close</button>
                        <button class="btn btn-primary" onclick="printRouteDetails('${route.name}')">
                            <i class="fas fa-print"></i> Print Summary
                        </button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // Close modal events
            modal.querySelectorAll('.close-modal').forEach(btn => {
                btn.addEventListener('click', () => modal.remove());
            });
            
            modal.addEventListener('click', (e) => {
                if(e.target === modal) modal.remove();
            });
        }
        
        function showNotesModal(notes) {
            const modal = document.createElement('div');
            modal.className = 'modal-overlay';
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 500px;">
                    <div class="modal-header">
                        <h3><i class="fas fa-sticky-note"></i> Route Notes</h3>
                        <button class="close-modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="notes-content">
                            <p>${notes}</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline close-modal">Close</button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            modal.querySelectorAll('.close-modal').forEach(btn => {
                btn.addEventListener('click', () => modal.remove());
            });
            
            modal.addEventListener('click', (e) => {
                if(e.target === modal) modal.remove();
            });
        }
        
        function printDetails(id, type) {
            alert(`Printing ${type === 'collection' ? 'receipt' : 'route summary'} for ID: ${id}`);
            // In a real application, this would open a print dialog with formatted content
        }
        
        function printRouteDetails(routeName) {
            alert(`Printing route summary for: ${routeName}`);
            // In a real application, this would open a print dialog
        }
        
        function sendReceipt(id, type) {
            alert(`Sending ${type === 'collection' ? 'receipt' : 'summary'} to customer for ID: ${id}`);
            // In a real application, this would send via email or SMS
        }
    </script>
</body>
</html> 