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
$filter_type = $_GET['type'] ?? 'all'; // New: collection or route
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

// Fetch completed routes history
$routes_history = [];

try {
    if($filter_type === 'all' || $filter_type === 'route') {
        $sql = "SELECT 
                    r.*,
                    'route' as type,
                    a.zone_name,
                    a.area,
                    COUNT(DISTINCT c.id) as collections_count,
                    SUM(c.weight_kg) as total_weight,
                    SUM(c.payment_amount) as total_amount
                FROM routes r
                LEFT JOIN assignments a ON r.assignment_id = a.id
                LEFT JOIN collections c ON c.assignment_id = a.id AND c.driver_id = ?
                WHERE r.driver_id = ? AND r.status = 'completed'";
        
        $params = [$driver_id, $driver_id];
        $types = "ii";
        
        if($filter_date !== 'all') {
            $today = date('Y-m-d');
            if($filter_date === 'today') {
                $sql .= " AND DATE(r.completed_at) = ?";
                $params[] = $today;
                $types .= "s";
            } elseif($filter_date === 'week') {
                $week_ago = date('Y-m-d', strtotime('-7 days'));
                $sql .= " AND DATE(r.completed_at) >= ?";
                $params[] = $week_ago;
                $types .= "s";
            } elseif($filter_date === 'month') {
                $month_ago = date('Y-m-d', strtotime('-30 days'));
                $sql .= " AND DATE(r.completed_at) >= ?";
                $params[] = $month_ago;
                $types .= "s";
            }
        }
        
        if(!empty($search)) {
            $sql .= " AND (r.route_name LIKE ? OR a.zone_name LIKE ?)";
            $search_term = "%$search%";
            $params[] = $search_term;
            $params[] = $search_term;
            $types .= "ss";
        }
        
        $sql .= " GROUP BY r.id ORDER BY r.completed_at DESC";
        
        if($stmt = mysqli_prepare($link, $sql)){
            if(!empty($params)) {
                mysqli_stmt_bind_param($stmt, $types, ...$params);
            }
            if(mysqli_stmt_execute($stmt)){
                $result = mysqli_stmt_get_result($stmt);
                while($row = mysqli_fetch_assoc($result)){
                    $routes_history[] = $row;
                }
            }
            mysqli_stmt_close($stmt);
        }
    }
} catch(Exception $e) {
    error_log("Routes history error: " . $e->getMessage());
    // Generate sample data for demo
    $routes_history = generateSampleRoutes();
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
    $dateA = $a['type'] === 'collection' ? $a['collection_date'] : $a['completed_at'];
    $dateB = $b['type'] === 'collection' ? $b['collection_date'] : $b['completed_at'];
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
            'id' => $i + 1000, // Different ID range for routes
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
            'status' => 'completed',
            'type' => 'route'
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
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1001;
            animation: fadeIn 0.3s ease;
            align-items: center;
            justify-content: center;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-content {
            position: relative;
            background: white;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            margin: 50px auto;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
            overflow-y: auto;
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
        
        .modal-close {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
            transition: color 0.3s ease;
            background: none;
            border: none;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        
        .modal-close:hover {
            color: #ff4757;
            background: rgba(0, 0, 0, 0.05);
        }
        
        #modal-title {
            color: #2c3e50;
            margin-bottom: 25px;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .route-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin: 20px 0;
        }
        
        .route-stat-item {
            padding: 15px;
            background: #f8fdf9;
            border-radius: 12px;
            text-align: center;
        }
        
        .route-stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2e7d32;
            margin-bottom: 5px;
        }
        
        .route-stat-label {
            font-size: 0.85rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
                                            <tr>
                                                <td>
                                                    <?php if($item['type'] === 'collection'): ?>
                                                        <span class="history-type type-collection">
                                                            <i class="fas fa-trash-alt"></i> Collection
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="history-type type-route">
                                                            <i class="fas fa-route"></i> Route
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div style="font-weight: 500;">
                                                        <?php 
                                                        $date = $item['type'] === 'collection' ? $item['collection_date'] : $item['completed_at'];
                                                        echo date('M d, Y', strtotime($date)); 
                                                        ?>
                                                    </div>
                                                    <div style="font-size: 0.8rem; color: #999;">
                                                        <?php echo date('h:i A', strtotime($date)); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if($item['type'] === 'collection'): ?>
                                                        <div style="font-weight: 500;"><?php echo htmlspecialchars($item['customer_name']); ?></div>
                                                        <div style="font-size: 0.8rem; color: #999;">
                                                            <?php echo htmlspecialchars($item['waste_type']); ?> • 
                                                            <?php echo htmlspecialchars($item['customer_phone']); ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <div style="font-weight: 500;"><?php echo htmlspecialchars($item['route_name']); ?></div>
                                                        <div style="font-size: 0.8rem; color: #999;">
                                                            <?php echo htmlspecialchars($item['start_point'] ?? 'N/A'); ?> → 
                                                            <?php echo htmlspecialchars($item['end_point'] ?? 'N/A'); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div style="font-weight: 500;"><?php echo htmlspecialchars($item['zone_name'] ?? 'N/A'); ?></div>
                                                    <div style="font-size: 0.8rem; color: #999;"><?php echo htmlspecialchars($item['area'] ?? ''); ?></div>
                                                </td>
                                                <td>
                                                    <?php if($item['type'] === 'collection'): ?>
                                                        <div style="font-weight: 600; color: #2e7d32;">
                                                            <?php echo number_format($item['weight_kg'], 1); ?> kg
                                                        </div>
                                                        <div style="font-size: 0.8rem; color: #ff9800;">
                                                            ₱<?php echo number_format($item['payment_amount'], 2); ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <div style="font-weight: 600; color: #2e7d32;">
                                                            <?php echo $item['collections_count']; ?> collections
                                                        </div>
                                                        <div style="font-size: 0.8rem; color: #2196f3;">
                                                            <?php echo $item['distance_km']; ?> km • <?php echo $item['estimated_time']; ?>
                                                        </div>
                                                    <?php endif; ?>
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
        
        <!-- Details Modal -->
        <div class="modal" id="detailsModal">
            <div class="modal-content">
                <button class="modal-close" id="closeModal">×</button>
                <h3 id="modal-title"><i class="fas fa-info-circle"></i> Details</h3>
                <div id="modalContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
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
            
            // View Details
            document.querySelectorAll('.view-details').forEach(button => {
                button.addEventListener('click', function() {
                    const itemId = this.getAttribute('data-id');
                    const itemType = this.getAttribute('data-type');
                    
                    // Show loading state
                    document.getElementById('modalContent').innerHTML = `
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
                    document.getElementById('detailsModal').style.display = 'flex';
                    
                    // Simulate API call with different content for collection vs route
                    setTimeout(() => {
                        if(itemType === 'collection') {
                            document.getElementById('modalContent').innerHTML = getCollectionDetails(itemId);
                        } else {
                            document.getElementById('modalContent').innerHTML = getRouteDetails(itemId);
                        }
                    }, 500);
                });
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
            
            // Close Modal
            document.getElementById('closeModal').addEventListener('click', function() {
                document.getElementById('detailsModal').style.display = 'none';
            });
            
            // Close modal when clicking outside
            document.getElementById('detailsModal').addEventListener('click', function(e) {
                if(e.target === this) {
                    this.style.display = 'none';
                }
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
                                <span class="waste-badge waste-plastic">Plastic</span>
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
        
        function getRouteDetails(id) {
            return `
                <div style="display: flex; flex-direction: column; gap: 20px;">
                    <!-- Header -->
                    <div style="background: #f8fdf9; padding: 20px; border-radius: 12px; border-left: 4px solid #2196f3;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div>
                                <div style="font-size: 1.2rem; font-weight: 600; color: #2c3e50; margin-bottom: 5px;">Route #${String(id).padStart(6, '0')}</div>
                                <div style="font-size: 0.9rem; color: #666;">Completed on ${new Date().toLocaleDateString()}</div>
                            </div>
                            <span class="status-badge status-completed">Completed</span>
                        </div>
                    </div>
                    
                    <!-- Route Stats -->
                    <div class="route-stats">
                        <div class="route-stat-item">
                            <div class="route-stat-value">8.5 km</div>
                            <div class="route-stat-label">Distance</div>
                        </div>
                        <div class="route-stat-item">
                            <div class="route-stat-value">2h 15m</div>
                            <div class="route-stat-label">Duration</div>
                        </div>
                        <div class="route-stat-item">
                            <div class="route-stat-value">15</div>
                            <div class="route-stat-label">Collections</div>
                        </div>
                        <div class="route-stat-item">
                            <div class="route-stat-value">₱1,275</div>
                            <div class="route-stat-label">Total</div>
                        </div>
                    </div>
                    
                    <!-- Details Grid -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <div style="font-size: 0.85rem; color: #666; margin-bottom: 5px;">Route Name</div>
                            <div style="font-weight: 600; color: #333;">Barangay Lahug Morning Route</div>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #666; margin-bottom: 5px;">Zone</div>
                            <div style="font-weight: 600; color: #333;">Zone A - Lahug</div>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #666; margin-bottom: 5px;">Start Point</div>
                            <div style="font-weight: 600; color: #333;">Cebu City Hall</div>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #666; margin-bottom: 5px;">End Point</div>
                            <div style="font-weight: 600; color: #333;">Lahug Market</div>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #666; margin-bottom: 5px;">Total Weight</div>
                            <div style="font-weight: 600; color: #2e7d32; font-size: 1.2rem;">255 kg</div>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #666; margin-bottom: 5px;">Driver</div>
                            <div style="font-weight: 600; color: #333;"><?php echo $driver_name; ?></div>
                        </div>
                    </div>
                    
                    <!-- Notes -->
                    <div>
                        <div style="font-size: 0.9rem; color: #666; margin-bottom: 10px; font-weight: 500;">Route Summary</div>
                        <div style="background: #f8fdf9; padding: 15px; border-radius: 8px; border: 1px solid #e8f5e9;">
                            <p style="margin: 0; color: #555; font-size: 0.95rem;">
                                Successfully completed morning collection route covering Barangay Lahug area. 
                                Collected mixed recyclables from 15 households and businesses. 
                                No major issues encountered during the route.
                            </p>
                        </div>
                    </div>
                    
                    <!-- Collection Breakdown -->
                    <div>
                        <div style="font-size: 0.9rem; color: #666; margin-bottom: 10px; font-weight: 500;">Collection Breakdown</div>
                        <div style="background: #f0f7f3; padding: 12px; border-radius: 8px; font-size: 0.85rem; color: #666;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                <span>Plastic</span>
                                <span style="font-weight: 600;">85 kg (₱425)</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                <span>Paper</span>
                                <span style="font-weight: 600;">75 kg (₱375)</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                <span>Glass</span>
                                <span style="font-weight: 600;">45 kg (₱225)</span>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span>Metal</span>
                                <span style="font-weight: 600;">50 kg (₱250)</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Buttons -->
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button class="btn btn-primary" style="flex: 1;" onclick="printDetails(${id}, 'route')">
                            <i class="fas fa-print"></i> Print Summary
                        </button>
                        <button class="btn btn-secondary" style="flex: 1;" onclick="exportRouteData(${id})">
                            <i class="fas fa-file-export"></i> Export Data
                        </button>
                    </div>
                </div>
            `;
        }
        
        function printDetails(id, type) {
            alert(`Printing ${type === 'collection' ? 'receipt' : 'route summary'} for ID: ${id}`);
            // In a real application, this would open a print dialog with formatted content
        }
        
        function sendReceipt(id, type) {
            alert(`Sending ${type === 'collection' ? 'receipt' : 'summary'} to customer for ID: ${id}`);
            // In a real application, this would send via email or SMS
        }
        
        function exportRouteData(id) {
            alert(`Exporting route data for ID: ${id}`);
            // In a real application, this would download a CSV or PDF file
        }
    </script>
</body>
</html>