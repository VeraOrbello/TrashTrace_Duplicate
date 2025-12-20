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
        
        .main-content {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }
        
        .dashboard-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(0, 0, 0, 0.05);
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
        }
        
        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(248, 253, 249, 0.8);
        }
        
        .card-header h3 {
            margin: 0;
            color: #2c3e50;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .route-list {
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 15px;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .route-item {
            background: linear-gradient(135deg, rgba(248, 253, 249, 0.8), rgba(240, 255, 244, 0.6));
            padding: 20px;
            border-radius: 12px;
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
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.1);
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
            margin-bottom: 10px;
            font-size: 1.05rem;
            font-weight: 600;
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
            grid-template-columns: repeat(2, 1fr);
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
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4caf50, #2e7d32);
            color: white;
            grid-column: span 2;
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
            padding: 20px;
        }
        
        .stat-item {
            background: linear-gradient(135deg, rgba(248, 253, 249, 0.8), rgba(240, 255, 244, 0.6));
            padding: 20px;
            border-radius: 12px;
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
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.85rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 500;
        }
        
        .map-container {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            height: 600px;
            position: relative;
            border: 1px solid rgba(0, 0, 0, 0.05);
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
        
        #map {
            width: 100%;
            height: 100%;
            border-radius: 16px;
        }
        
        .route-details-content {
            padding: 20px;
        }
        
        .detail-item {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
            padding: 15px;
            background: linear-gradient(135deg, rgba(248, 253, 249, 0.8), rgba(240, 255, 244, 0.6));
            border-radius: 10px;
            border-left: 4px solid #4caf50;
            border: 1px solid rgba(232, 245, 233, 0.5);
        }
        
        .detail-item i {
            color: #2e7d32;
            font-size: 1.2rem;
            width: 36px;
            height: 36px;
            background: rgba(240, 249, 244, 0.8);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .detail-content h4 {
            color: #2c3e50;
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .detail-content p {
            color: #666;
            font-size: 0.9rem;
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
            border-radius: 16px;
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
        
        .route-progress {
            padding: 20px;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
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
                height: 500px;
            }
        }
        
        @media (max-width: 768px) {
            .dashboard-grid {
                gap: 20px;
            }
            
            .controls-grid {
                grid-template-columns: 1fr;
            }
            
            .btn-primary {
                grid-column: span 1;
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
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Header Section -->
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
                            <li><a href="routes.php" class="nav-link active"><i class="fas fa-route"></i> <span>Routes</span></a></li>
                            <li><a href="collections.php" class="nav-link"><i class="fas fa-trash"></i> <span>Collections</span></a></li>
                            <li><a href="earnings.php" class="nav-link"><i class="fas fa-money-bill-wave"></i> <span>Earnings</span></a></li>
                            <li><a href="history.php" class="nav-link"><i class="fas fa-history"></i> <span>History</span></a></li>
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
                        <i class="fas fa-route"></i>
                        Driver Routes
                    </h1>
                    <p class="page-subtitle">View and manage your collection routes, track progress, and navigate efficiently.</p>
                </div>
                
                <div class="dashboard-grid">
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
                                    <i class="fas fa-check-circle"></i> Complete
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
                        
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h3><i class="fas fa-info-circle"></i> Route Details</h3>
                            </div>
                            <div class="route-details-content" id="routeDetails">
                                <div class="detail-item">
                                    <i class="fas fa-map-pin"></i>
                                    <div class="detail-content">
                                        <h4>Start Point</h4>
                                        <p id="startPoint">Select a route</p>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-flag-checkered"></i>
                                    <div class="detail-content">
                                        <h4>End Point</h4>
                                        <p id="endPoint">Select a route</p>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-road"></i>
                                    <div class="detail-content">
                                        <h4>Distance</h4>
                                        <p id="routeDistance">-</p>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-clock"></i>
                                    <div class="detail-content">
                                        <h4>Estimated Time</h4>
                                        <p id="routeTime">-</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="map-container">
                        <div id="map"></div>
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
                            <span>Live Location Tracking</span>
                        </div>
                    </div>
                </div>
            </div>
        </main>
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
        let watchId = null;
        let socket = null;
        let notifications = [];
        let selectedRoute = null;
        let routeProgress = 0;
        
        // Cebu City coordinates
        const CEBU_COORDS = [10.3157, 123.8854];
        
        // Sample routes data
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
                progress: 0,
                waypoints: [
                    [10.3157, 123.8854], // Cebu City Hall
                    [10.3245, 123.8943], // Lahug Area 1
                    [10.3298, 123.9021], // Lahug Area 2
                    [10.3342, 123.9087], // Lahug Market
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
                status: "active",
                progress: 35,
                waypoints: [
                    [10.3347, 123.9054], // IT Park
                    [10.3289, 123.8987], // Apas Zone 1
                    [10.3223, 123.8912], // Apas Zone 2
                    [10.3168, 123.8845], // Apas Terminal
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
                status: "completed",
                progress: 100,
                waypoints: [
                    [10.2939, 123.9025], // Carbon Market
                    [10.2987, 123.8983], // Downtown Area 1
                    [10.3021, 123.8947], // Downtown Area 2
                    [10.3054, 123.8912], // Colon Street
                ]
            }
        ];
        
        // Sample notifications
        const sampleNotifications = [
            {
                id: 1,
                title: "New Route Available",
                message: "Barangay Lahug route has been assigned to you",
                time: "10:30 AM",
                type: "info",
                read: false
            },
            {
                id: 2,
                title: "Route Update",
                message: "Apas Residential route progress: 35% completed",
                time: "9:15 AM",
                type: "success",
                read: false
            },
            {
                id: 3,
                title: "Traffic Alert",
                message: "Heavy traffic reported near IT Park",
                time: "8:45 AM",
                type: "warning",
                read: false
            },
            {
                id: 4,
                title: "Collection Reminder",
                message: "Complete Downtown route by 5:00 PM today",
                time: "Yesterday",
                type: "info",
                read: true
            }
        ];
        
        // Initialize map
        function initMap() {
            map = L.map('map').setView(CEBU_COORDS, 13);
            
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
                
                if (route.status === 'active') {
                    selectedRoute = route;
                    updateRouteDetails(route);
                    plotRoute(route.waypoints);
                }
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
            
            // Plot route on map
            plotRoute(route.waypoints);
            
            // Send notification
            addNotification(`Route selected: ${route.name}`, 'info');
        }
        
        // Update route details panel
        function updateRouteDetails(route) {
            document.getElementById('startPoint').textContent = route.start;
            document.getElementById('endPoint').textContent = route.end;
            document.getElementById('routeDistance').textContent = route.distance;
            document.getElementById('routeTime').textContent = route.estimatedTime;
        }
        
        // Update progress bar
        function updateProgressBar() {
            const progressFill = document.getElementById('progressFill');
            const progressPercentage = document.getElementById('progressPercentage');
            
            progressFill.style.width = `${routeProgress}%`;
            progressPercentage.textContent = `${routeProgress}%`;
        }
        
        // Plot route on map
        function plotRoute(waypoints) {
            // Clear previous route
            if (routingControl) {
                map.removeControl(routingControl);
            }
            
            // Create new route
            routingControl = L.Routing.control({
                waypoints: waypoints.map(wp => L.latLng(wp[0], wp[1])),
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
                            weight: 5,
                            dashArray: '10, 10'
                        }
                    ]
                }
            }).addTo(map);
            
            // Add event listener for route changes
            routingControl.on('routesfound', function(e) {
                const routes = e.routes;
                const summary = routes[0].summary;
                
                // Update route details with real calculations
                if (selectedRoute) {
                    selectedRoute.distance = (summary.totalDistance / 1000).toFixed(1) + ' km';
                    selectedRoute.estimatedTime = Math.round(summary.totalTime / 60) + ' min';
                    updateRouteDetails(selectedRoute);
                }
                
                addNotification(`Route calculated: ${(summary.totalDistance / 1000).toFixed(1)} km, ${Math.round(summary.totalTime / 60)} min`, 'success');
            });
        }
        
        // Initialize WebSocket for real-time updates
        function initWebSocket() {
            // Connect to WebSocket server
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
                        
                        // Update progress if near waypoints
                        if (selectedRoute && selectedRoute.status === 'active') {
                            map.panTo([latitude, longitude]);
                            
                            // Simulate progress update based on movement
                            if (Math.random() > 0.7 && routeProgress < 100) {
                                routeProgress += Math.random() * 5;
                                if (routeProgress > 100) routeProgress = 100;
                                updateProgressBar();
                                
                                if (socket && socket.connected) {
                                    socket.emit('progress_update', {
                                        route_id: selectedRoute.id,
                                        progress: routeProgress,
                                        driver_id: <?php echo $driver_id; ?>
                                    });
                                }
                            }
                        }
                    },
                    (error) => {
                        console.error('Geolocation error:', error);
                        addNotification('Unable to get your location', 'error');
                    },
                    {
                        enableHighAccuracy: true,
                        maximumAge: 30000,
                        timeout: 27000
                    }
                );
            } else {
                addNotification('Geolocation is not supported by your browser', 'error');
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
        
        // Event Listeners
        document.getElementById('startNavigation').addEventListener('click', () => {
            if (selectedRoute) {
                selectedRoute.status = 'active';
                updateRouteDetails(selectedRoute);
                loadRoutes();
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
                addNotification('Navigation paused', 'warning');
                
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
        
        document.getElementById('completeRoute').addEventListener('click', () => {
            if (selectedRoute && selectedRoute.status === 'active') {
                selectedRoute.status = 'completed';
                selectedRoute.progress = 100;
                updateRouteDetails(selectedRoute);
                updateProgressBar();
                loadRoutes();
                addNotification('Route completed: ' + selectedRoute.name, 'success');
                
                if (socket && socket.connected) {
                    socket.emit('route_completed', {
                        route_id: selectedRoute.id,
                        driver_id: <?php echo $driver_id; ?>
                    });
                }
            } else {
                addNotification('No active route to complete', 'warning');
            }
        });
        
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
        });
        
        // Clean up on page unload
        window.addEventListener('beforeunload', () => {
            if (watchId) {
                navigator.geolocation.clearWatch(watchId);
            }
            
            if (socket) {
                socket.disconnect();
            }
        });
    </script>
</body>
</html>