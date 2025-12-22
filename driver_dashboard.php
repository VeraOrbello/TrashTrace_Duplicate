<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

// Only drivers can access this page
if($_SESSION["user_type"] !== 'driver'){
    header("location: dashboard.php");
    exit;
}

// Now include config.php
require_once "config.php";

$driver_id = $_SESSION["id"] ?? 0;
$driver_name = $_SESSION["full_name"] ?? 'Driver';

// Initialize stats with default values
$stats = [
    'today_assignments' => 5,
    'monthly_collections' => 42,
    'monthly_earnings' => 12500.50,
    'active_routes' => 3
];

// Only try database queries if connection exists
if (isset($link) && $link !== null) {
    try {
        $today = date('Y-m-d');
        $month_start = date('Y-m-01');
        
        // Today's assignments count
        $query = "SELECT COUNT(*) as count FROM assignments WHERE driver_id = ? AND DATE(assigned_date) = ?";
        if ($stmt = $link->prepare($query)) {
            $stmt->bind_param("is", $driver_id, $today);
            if ($stmt->execute()) {
                $assignments_result = $stmt->get_result();
                if ($assignments_result) {
                    $row = $assignments_result->fetch_assoc();
                    $stats['today_assignments'] = $row['count'] ?? 0;
                }
            }
            $stmt->close();
        }
        
        // Total collections this month
        $query = "SELECT COUNT(*) as count FROM collections WHERE driver_id = ? AND collection_date >= ?";
        if ($stmt = $link->prepare($query)) {
            $stmt->bind_param("is", $driver_id, $month_start);
            if ($stmt->execute()) {
                $collections_result = $stmt->get_result();
                if ($collections_result) {
                    $row = $collections_result->fetch_assoc();
                    $stats['monthly_collections'] = $row['count'] ?? 0;
                }
            }
            $stmt->close();
        }
        
        // Total earnings this month
        $query = "SELECT SUM(amount) as total FROM earnings WHERE driver_id = ? AND DATE(earned_date) >= ?";
        if ($stmt = $link->prepare($query)) {
            $stmt->bind_param("is", $driver_id, $month_start);
            if ($stmt->execute()) {
                $earnings_result = $stmt->get_result();
                if ($earnings_result) {
                    $row = $earnings_result->fetch_assoc();
                    $stats['monthly_earnings'] = $row['total'] ?? 0;
                }
            }
            $stmt->close();
        }
        
        // Active routes
        $query = "SELECT COUNT(*) as count FROM routes WHERE driver_id = ? AND status = 'active'";
        if ($stmt = $link->prepare($query)) {
            $stmt->bind_param("i", $driver_id);
            if ($stmt->execute()) {
                $routes_result = $stmt->get_result();
                if ($routes_result) {
                    $row = $routes_result->fetch_assoc();
                    $stats['active_routes'] = $row['count'] ?? 0;
                }
            }
            $stmt->close();
        }
        
    } catch (Exception $e) {
        // Keep using sample data if there's an error
        error_log("Driver dashboard database error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Dashboard - TrashTrace</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Consolidated CSS -->
    <link rel="stylesheet" href="css/driver/master-styles.css">
    
    <style>
        /* Dashboard Grid Layout - Matching routes.php */
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
        
        /* Stats Cards - Matching routes.php style */
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
        
        /* Dashboard Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            margin-top: 20px;
        }
        
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        .content-card {
            padding: 24px;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .content-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, rgba(76, 175, 80, 0.1), rgba(46, 125, 50, 0.05));
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            color: #2e7d32;
            font-size: 1.5rem;
        }
        
        .content-card h3 {
            color: #2c3e50;
            margin-bottom: 12px;
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .content-card p {
            color: #666;
            line-height: 1.6;
            font-size: 0.95rem;
            margin-bottom: 20px;
            flex: 1;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4caf50, #2e7d32);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            text-decoration: none;
            text-align: center;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: fit-content;
            transition: all 0.3s ease;
            font-size: 0.95rem;
            cursor: pointer;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(76, 175, 80, 0.3);
            background: linear-gradient(135deg, #43a047, #1b5e20);
        }
        
        /* Quick Actions */
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            padding: 24px;
        }
        
        @media (max-width: 1200px) {
            .quick-actions-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .quick-actions-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .action-item {
            background: linear-gradient(135deg, rgba(248, 253, 249, 0.8), rgba(240, 255, 244, 0.6));
            padding: 20px;
            border-radius: 16px;
            text-align: center;
            border: 1px solid rgba(232, 245, 233, 0.5);
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .action-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(76, 175, 80, 0.1);
            border-color: #4caf50;
            background: linear-gradient(135deg, rgba(232, 245, 233, 0.9), rgba(241, 248, 233, 0.8));
        }
        
        .action-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #e8f5e9, #f1f8e9);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            color: #2e7d32;
            font-size: 1.3rem;
        }
        
        .action-item h4 {
            color: #2c3e50;
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .action-item p {
            color: #666;
            font-size: 0.85rem;
            line-height: 1.4;
        }
        
        /* Welcome Section */
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
        
        /* Status Indicator */
        .status-indicator {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            animation: fadeInUp 0.6s ease;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(232, 245, 233, 0.5);
        }
        
        .status-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #4caf50;
            animation: pulse 2s infinite;
        }
        
        .status-text {
            color: #666;
            font-weight: 500;
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
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(76, 175, 80, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(76, 175, 80, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(76, 175, 80, 0);
            }
        }
        
        /* Notifications - Matching routes.php */
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
        
        /* Recent Activity */
        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            padding: 24px;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: linear-gradient(135deg, rgba(248, 253, 249, 0.8), rgba(240, 255, 244, 0.6));
            border-radius: 12px;
            border: 1px solid rgba(232, 245, 233, 0.5);
            transition: all 0.3s ease;
        }
        
        .activity-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.1);
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #e8f5e9, #f1f8e9);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #2e7d32;
            font-size: 1.1rem;
        }
        
        .activity-content h4 {
            color: #2c3e50;
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .activity-content p {
            color: #666;
            font-size: 0.85rem;
        }
        
        .activity-time {
            font-size: 0.8rem;
            color: #888;
            margin-left: auto;
            white-space: nowrap;
        }
        
        /* Custom scrollbar */
        .activity-list::-webkit-scrollbar,
        .notification-list::-webkit-scrollbar {
            width: 6px;
        }
        
        .activity-list::-webkit-scrollbar-track,
        .notification-list::-webkit-scrollbar-track {
            background: rgba(241, 241, 241, 0.5);
            border-radius: 10px;
        }
        
        .activity-list::-webkit-scrollbar-thumb,
        .notification-list::-webkit-scrollbar-thumb {
            background: rgba(200, 230, 201, 0.8);
            border-radius: 10px;
        }
        
        .activity-list::-webkit-scrollbar-thumb:hover,
        .notification-list::-webkit-scrollbar-thumb:hover {
            background: rgba(76, 175, 80, 0.8);
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
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/header.php'; ?>
        
        <main class="dashboard-main">
            <!-- Grid Background Pattern -->
            <div class="grid-background"></div>
            
            <div class="container">
                <div class="page-header">
                    <h1 class="page-title">
                        <i class="fas fa-truck"></i>
                        <?php
                        $hour = date('H');
                        $greeting = '';
                        if ($hour < 12) {
                            $greeting = 'Good Morning';
                        } elseif ($hour < 18) {
                            $greeting = 'Good Afternoon';
                        } else {
                            $greeting = 'Good Evening';
                        }
                        echo $greeting . ', ' . htmlspecialchars(explode(' ', $driver_name)[0]) . '!';
                        ?>
                    </h1>
                    <p class="page-subtitle">Here's what's happening with your collections today</p>
                </div>

                <!-- Dashboard Grid - Matching routes.php layout -->
                <div class="dashboard-grid">
                    <!-- Sidebar with stats and notifications -->
                    <div class="sidebar">
                        <!-- Statistics Card -->
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h3><i class="fas fa-chart-line"></i> Today's Statistics</h3>
                            </div>
                            <div class="stats-grid">
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $stats['today_assignments']; ?></div>
                                    <div class="stat-label">Assignments</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $stats['active_routes']; ?></div>
                                    <div class="stat-label">Active Routes</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $stats['monthly_collections']; ?></div>
                                    <div class="stat-label">Collections</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value">₱<?php echo number_format($stats['monthly_earnings'], 0); ?></div>
                                    <div class="stat-label">Earnings</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Notifications Card -->
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h3><i class="fas fa-bell"></i> Recent Activity</h3>
                            </div>
                            <div class="activity-list">
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div class="activity-content">
                                        <h4>Route Completed</h4>
                                        <p>Barangay Lahug Morning Route</p>
                                    </div>
                                    <div class="activity-time">10:30 AM</div>
                                </div>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-map-marker-alt"></i>
                                    </div>
                                    <div class="activity-content">
                                        <h4>New Assignment</h4>
                                        <p>IT Park Collection Route</p>
                                    </div>
                                    <div class="activity-time">9:15 AM</div>
                                </div>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </div>
                                    <div class="activity-content">
                                        <h4>Earnings Updated</h4>
                                        <p>₱1,250 added to your account</p>
                                    </div>
                                    <div class="activity-time">Yesterday</div>
                                </div>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-calendar-check"></i>
                                    </div>
                                    <div class="activity-content">
                                        <h4>Schedule Updated</h4>
                                        <p>New route for tomorrow</p>
                                    </div>
                                    <div class="activity-time">2 days ago</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Main Content Area -->
                    <div class="main-content">
                        <!-- Quick Actions -->
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                            </div>
                            <div class="quick-actions-grid">
                                <a href="driver/assignments.php" class="action-item">
                                    <div class="action-icon">
                                        <i class="fas fa-clipboard-list"></i>
                                    </div>
                                    <h4>View Assignments</h4>
                                    <p>Check today's pickup tasks</p>
                                </a>
                                <a href="driver/routes.php" class="action-item">
                                    <div class="action-icon">
                                        <i class="fas fa-route"></i>
                                    </div>
                                    <h4>Plan Route</h4>
                                    <p>Optimize your collection path</p>
                                </a>
                                <a href="driver/collections.php?action=log" class="action-item">
                                    <div class="action-icon">
                                        <i class="fas fa-trash-restore"></i>
                                    </div>
                                    <h4>Log Collection</h4>
                                    <p>Record completed pickups</p>
                                </a>
                                <a href="driver/earnings.php" class="action-item">
                                    <div class="action-icon">
                                        <i class="fas fa-chart-line"></i>
                                    </div>
                                    <h4>Track Earnings</h4>
                                    <p>View your performance</p>
                                </a>
                            </div>
                        </div>
                        
                        <!-- Dashboard Content Grid -->
                        <div class="content-grid">
                            <div class="dashboard-card">
                                <div class="content-card">
                                    <div class="content-icon">
                                        <i class="fas fa-tasks"></i>
                                    </div>
                                    <h3>Today's Assignments</h3>
                                    <p>Check your pickup assignments for today. View locations, times, and completion status.</p>
                                    <a href="driver/assignments.php" class="btn-primary">
                                        <i class="fas fa-eye"></i> View Assignments
                                    </a>
                                </div>
                            </div>
                            
                            <div class="dashboard-card">
                                <div class="content-card">
                                    <div class="content-icon">
                                        <i class="fas fa-route"></i>
                                    </div>
                                    <h3>My Routes</h3>
                                    <p>View your assigned collection routes with optimized paths and navigation assistance.</p>
                                    <a href="driver/routes.php" class="btn-primary">
                                        <i class="fas fa-map"></i> View Routes
                                    </a>
                                </div>
                            </div>
                            
                            <div class="dashboard-card">
                                <div class="content-card">
                                    <div class="content-icon">
                                        <i class="fas fa-trash-restore"></i>
                                    </div>
                                    <h3>Collections</h3>
                                    <p>Log your daily collections, track progress, and update completion status in real-time.</p>
                                    <a href="driver/collections.php" class="btn-primary">
                                        <i class="fas fa-plus-circle"></i> Log Collections
                                    </a>
                                </div>
                            </div>
                            
                            <div class="dashboard-card">
                                <div class="content-card">
                                    <div class="content-icon">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </div>
                                    <h3>Earnings</h3>
                                    <p>Track your earnings, view payment history, and monitor your financial performance.</p>
                                    <a href="driver/earnings.php" class="btn-primary">
                                        <i class="fas fa-chart-line"></i> View Earnings
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Status Indicator -->
                        <div class="status-indicator">
                            <div class="status-dot"></div>
                            <div class="status-text">You are currently active and ready for assignments</div>
                        </div>
                    </div>
                </div>
                
                <!-- Notification Badge and Panel -->
                <div class="notification-badge" id="notificationBadge">3</div>
                <div class="notification-panel" id="notificationPanel">
                    <div class="notification-header">
                        <h4><i class="fas fa-bell"></i> Notifications</h4>
                        <button class="close-btn" id="closeNotifications">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="notification-list" id="notificationList">
                        <div class="notification-item unread">
                            <h4>New Route Assigned</h4>
                            <p>You have been assigned to Barangay Lahug route</p>
                            <small>10:30 AM</small>
                        </div>
                        <div class="notification-item unread">
                            <h4>Collection Reminder</h4>
                            <p>Complete IT Park route by 5:00 PM today</p>
                            <small>9:15 AM</small>
                        </div>
                        <div class="notification-item">
                            <h4>System Update</h4>
                            <p>New features added to the driver dashboard</p>
                            <small>Yesterday</small>
                        </div>
                        <div class="notification-item">
                            <h4>Earnings Update</h4>
                            <p>Your weekly earnings have been calculated</p>
                            <small>2 days ago</small>
                        </div>
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
            
            if (mobileMenuToggle && mainNav) {
                mobileMenuToggle.addEventListener('click', function() {
                    const navContainer = mainNav.querySelector('.nav-container');
                    if (navContainer) {
                        navContainer.style.display = navContainer.style.display === 'flex' ? 'none' : 'flex';
                    }
                });
            }
            
            // Notification handling
            const notificationBadge = document.getElementById('notificationBadge');
            const notificationPanel = document.getElementById('notificationPanel');
            const closeNotifications = document.getElementById('closeNotifications');
            
            if (notificationBadge && notificationPanel) {
                notificationBadge.addEventListener('click', function() {
                    notificationPanel.style.display = notificationPanel.style.display === 'block' ? 'none' : 'block';
                    
                    // Mark all as read when opening
                    const unreadItems = notificationPanel.querySelectorAll('.notification-item.unread');
                    unreadItems.forEach(item => {
                        item.classList.remove('unread');
                    });
                    
                    // Update badge count
                    notificationBadge.textContent = '0';
                    notificationBadge.style.display = 'none';
                });
            }
            
            if (closeNotifications && notificationPanel) {
                closeNotifications.addEventListener('click', function() {
                    notificationPanel.style.display = 'none';
                });
            }
            
            // Close notification panel when clicking outside
            document.addEventListener('click', function(event) {
                if (notificationPanel && notificationPanel.style.display === 'block') {
                    if (!notificationPanel.contains(event.target) && event.target !== notificationBadge) {
                        notificationPanel.style.display = 'none';
                    }
                }
            });
            
            // Add hover effects to cards
            const cards = document.querySelectorAll('.dashboard-card, .stat-item, .action-item');
            cards.forEach(card => {
                card.addEventListener('mouseenter', () => {
                    if (card.classList.contains('dashboard-card')) {
                        card.style.transform = 'translateY(-5px)';
                    } else if (card.classList.contains('stat-item')) {
                        card.style.transform = 'translateY(-3px)';
                    } else if (card.classList.contains('action-item')) {
                        card.style.transform = 'translateY(-3px)';
                    }
                });
                
                card.addEventListener('mouseleave', () => {
                    if (card.classList.contains('dashboard-card')) {
                        card.style.transform = 'translateY(0)';
                    } else if (card.classList.contains('stat-item')) {
                        card.style.transform = 'translateY(0)';
                    } else if (card.classList.contains('action-item')) {
                        card.style.transform = 'translateY(0)';
                    }
                });
            });
            
            // Responsive navigation
            function handleResize() {
                if (window.innerWidth > 768) {
                    if (mainNav) {
                        const navContainer = mainNav.querySelector('.nav-container');
                        if (navContainer) navContainer.style.display = 'flex';
                    }
                } else {
                    if (mainNav) {
                        const navContainer = mainNav.querySelector('.nav-container');
                        if (navContainer) navContainer.style.display = 'none';
                    }
                }
            }
            
            // Initial check
            handleResize();
            
            // Listen for resize events
            window.addEventListener('resize', handleResize);
            
            // Add animation to cards on load
            const statCards = document.querySelectorAll('.stat-item');
            statCards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
                card.style.animation = 'fadeInUp 0.6s ease';
            });
        });
    </script>
</body>
</html>