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
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Dashboard - TrashTrace</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Consolidated CSS -->
    <link rel="stylesheet" href="css/driver/master-styles.css">
    <link rel="stylesheet" href="css/driver/dashboard.css">
   
    
 
</head>
<body>
    <!-- Grid Background -->
    <div class="grid-background"></div>
    
    <div class="dashboard-container">
        <header class="dashboard-header">
            <div class="header-content">
                <div class="logo">
                    <i class="fas fa-recycle"></i>
                    TrashTrace Driver
                </div>
                <nav>
                    <ul>
                        <li><a href="driver_dashboard.php" class="nav-link active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <li><a href="driver/assignments.php" class="nav-link"><i class="fas fa-tasks"></i> Assignments</a></li>
                        <li><a href="driver/routes.php" class="nav-link"><i class="fas fa-route"></i> Routes</a></li>
                        <li><a href="driver/collections.php" class="nav-link"><i class="fas fa-trash-restore"></i> Collections</a></li>
                        <li><a href="driver/earnings.php" class="nav-link"><i class="fas fa-money-bill-wave"></i> Earnings</a></li>
                        <li class="user-menu">
                            <span class="user-greeting"><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($driver_name); ?></span>
                            <a href="logout.php" class="btn-outline"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </header>

        <main class="dashboard-main">
            <div class="welcome-section">
                <h1 class="welcome-title">
                    <i class="fas fa-truck"></i>
                    Welcome Back, <?php echo htmlspecialchars(explode(' ', $driver_name)[0]); ?>!
                </h1>
                <p class="welcome-subtitle">Here's what's happening with your collections today</p>
            </div>

            <!-- Statistics Cards -->
            <div class="dashboard-stats">
                <div class="stat-card" style="--order: 1;">
                    <div class="stat-icon assignments">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo $stats['today_assignments']; ?></div>
                        <div class="stat-label">Today's Assignments</div>
                    </div>
                </div>
                
                <div class="stat-card" style="--order: 2;">
                    <div class="stat-icon routes">
                        <i class="fas fa-map-marked-alt"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo $stats['active_routes']; ?></div>
                        <div class="stat-label">Active Routes</div>
                    </div>
                </div>
                
                <div class="stat-card" style="--order: 3;">
                    <div class="stat-icon collections">
                        <i class="fas fa-trash-alt"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo $stats['monthly_collections']; ?></div>
                        <div class="stat-label">Monthly Collections</div>
                    </div>
                </div>
                
                <div class="stat-card" style="--order: 4;">
                    <div class="stat-icon earnings">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value">₱<?php echo number_format($stats['monthly_earnings'], 2); ?></div>
                        <div class="stat-label">Monthly Earnings</div>
                    </div>
                </div>
            </div>

            <!-- Main Dashboard Grid -->
            <div class="dashboard-grid">
                <div class="dashboard-card" style="--order: 1;">
                    <div class="card-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <h2>Today's Assignments</h2>
                    <div class="card-content">
                        <p>Check your pickup assignments for today. View locations, times, and completion status.</p>
                        <a href="driver/assignments.php" class="btn-primary">
                            <i class="fas fa-eye"></i> View Assignments
                        </a>
                    </div>
                </div>
                
                <div class="dashboard-card" style="--order: 2;">
                    <div class="card-icon">
                        <i class="fas fa-route"></i>
                    </div>
                    <h2>My Routes</h2>
                    <div class="card-content">
                        <p>View your assigned collection routes with optimized paths and navigation assistance.</p>
                        <a href="driver/routes.php" class="btn-primary">
                            <i class="fas fa-map"></i> View Routes
                        </a>
                    </div>
                </div>
                
                <div class="dashboard-card" style="--order: 3;">
                    <div class="card-icon">
                        <i class="fas fa-trash-restore"></i>
                    </div>
                    <h2>Collections</h2>
                    <div class="card-content">
                        <p>Log your daily collections, track progress, and update completion status in real-time.</p>
                        <a href="driver/collections.php" class="btn-primary">
                            <i class="fas fa-plus-circle"></i> Log Collections
                        </a>
                    </div>
                </div>
                
                <div class="dashboard-card" style="--order: 4;">
                    <div class="card-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <h2>Earnings</h2>
                    <div class="card-content">
                        <p>Track your earnings, view payment history, and monitor your financial performance.</p>
                        <a href="driver/earnings.php" class="btn-primary">
                            <i class="fas fa-chart-line"></i> View Earnings
                        </a>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                <div class="action-buttons">
                    <a href="driver/collections.php?action=log" class="action-btn">
                        <i class="fas fa-plus"></i> Log New Collection
                    </a>
                    <a href="driver/assignments.php?filter=today" class="action-btn">
                        <i class="fas fa-calendar-day"></i> Today's Schedule
                    </a>
                    <a href="driver/routes.php?view=map" class="action-btn">
                        <i class="fas fa-map-marked-alt"></i> View Route Map
                    </a>
                    <a href="driver/earnings.php?period=current" class="action-btn">
                        <i class="fas fa-wallet"></i> Check Earnings
                    </a>
                </div>
            </div>

            <!-- Status Indicator -->
            <div class="status-indicator">
                <div class="status-dot"></div>
                <div class="status-text">You are currently active and ready for assignments</div>
            </div>
        </main>
    </div>

    <script>
        // Add smooth animations on scroll
        document.addEventListener('DOMContentLoaded', function() {
            // Animate cards on load with staggered delay
            const cards = document.querySelectorAll('.dashboard-card, .stat-card');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
            
            // Add hover effect for cards
            cards.forEach(card => {
                card.addEventListener('mouseenter', () => {
                    card.style.transform = 'translateY(-8px)';
                });
                
                card.addEventListener('mouseleave', () => {
                    card.style.transform = 'translateY(0)';
                });
            });
            
            // Update greeting based on time of day
            const greeting = document.querySelector('.welcome-title');
            const hour = new Date().getHours();
            let timeGreeting = 'Good ';
            
            if (hour < 12) timeGreeting = 'Good Morning';
            else if (hour < 18) timeGreeting = 'Good Afternoon';
            else timeGreeting = 'Good Evening';
            
            // Update only the first part of the title
            const titleText = greeting.innerHTML;
            greeting.innerHTML = titleText.replace('Welcome Back', timeGreeting);
        });
    </script>
</body>
</html>