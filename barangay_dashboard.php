<?php
require_once "config.php";

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

if($_SESSION["user_type"] !== 'admin'){
    header("location: dashboard.php");
    exit;
}

$user_id = $_SESSION["id"];
$user_name = $_SESSION["full_name"];
$user_barangay = $_SESSION["barangay"] ?? '';

$upcoming_pickup = [];
$sql = "SELECT * FROM pickup_schedules WHERE barangay = :barangay AND schedule_date >= CURDATE() AND status = 'Scheduled' ORDER BY schedule_date ASC LIMIT 1";
if($stmt = $pdo->prepare($sql)){
    $stmt->bindParam(":barangay", $user_barangay, PDO::PARAM_STR);
    if($stmt->execute()){
        $upcoming_pickup = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    $stmt->closeCursor();
}

$today = date('Y-m-d');
$todays_stats = [
    'total' => 0,
    'completed' => 0,
    'delayed' => 0
];

$sql = "SELECT COUNT(*) as count FROM pickup_schedules WHERE barangay = :barangay AND schedule_date = :today";
if($stmt = $pdo->prepare($sql)){
    $stmt->bindParam(":barangay", $user_barangay, PDO::PARAM_STR);
    $stmt->bindParam(":today", $today, PDO::PARAM_STR);
    if($stmt->execute()){
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $todays_stats['total'] = $row['count'];
    }
    $stmt->closeCursor();
}

$sql = "SELECT COUNT(*) as count FROM pickup_schedules WHERE barangay = :barangay AND schedule_date = :today AND status = 'Completed'";
if($stmt = $pdo->prepare($sql)){
    $stmt->bindParam(":barangay", $user_barangay, PDO::PARAM_STR);
    $stmt->bindParam(":today", $today, PDO::PARAM_STR);
    if($stmt->execute()){
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $todays_stats['completed'] = $row['count'];
    }
    $stmt->closeCursor();
}

$sql = "SELECT COUNT(*) as count FROM pickup_schedules WHERE barangay = :barangay AND schedule_date = :today AND status = 'Delayed'";
if($stmt = $pdo->prepare($sql)){
    $stmt->bindParam(":barangay", $user_barangay, PDO::PARAM_STR);
    $stmt->bindParam(":today", $today, PDO::PARAM_STR);
    if($stmt->execute()){
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $todays_stats['delayed'] = $row['count'];
    }
    $stmt->closeCursor();
}

$pending_applications = 0;
$sql = "SELECT COUNT(*) as count FROM worker_applications wa 
        JOIN users u ON wa.user_id = u.id 
        WHERE (wa.status = 'pending' OR LOWER(TRIM(u.user_type)) LIKE '%pending%') 
        AND LOWER(TRIM(u.barangay)) = LOWER(TRIM(:barangay))";

if($stmt = $pdo->prepare($sql)){
    $stmt->bindParam(":barangay", $user_barangay, PDO::PARAM_STR);
    if($stmt->execute()){
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $pending_applications = $row['count'];
    }
    $stmt->closeCursor();
}

$completion_percentage = $todays_stats['total'] > 0 ? round(($todays_stats['completed'] / $todays_stats['total']) * 100) : 0;
$pickup_status = $todays_stats['total'] > 0 ? "Pickup in Progress" : "No scheduled pickups";

$one_week_ago = date('Y-m-d H:i:s', strtotime('-1 week'));
$notifications = [];
$notification_sql = "SELECT * FROM notifications 
                    WHERE (barangay = :barangay OR barangay IS NULL)
                    AND created_at >= :one_week_ago
                    ORDER BY created_at DESC 
                    LIMIT 5";
                    
if($stmt = $pdo->prepare($notification_sql)){
    $stmt->bindParam(":barangay", $user_barangay, PDO::PARAM_STR);
    $stmt->bindParam(":one_week_ago", $one_week_ago, PDO::PARAM_STR);
    
    if($stmt->execute()){
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    $stmt->closeCursor();
}

$unread_count = 0;
foreach($notifications as $notification) {
    if(isset($notification['is_read']) && $notification['is_read'] == 0){
        $unread_count++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Dashboard - TrashTrace</title>
    <link rel="stylesheet" href="css/barangay_dashboard.css">
    <link href="node_modules/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <div class="header-content">
                <div class="logo">TrashTrace</div>
                <nav>
                    <ul>
                        <li><a href="barangay_dashboard.php" class="nav-link active">Dashboard</a></li>
                        <li><a href="barangay_schedule.php" class="nav-link">Schedule</a></li>
                        <li>
                            <a href="barangay_applications.php" class="nav-link">Applications</a>
                        </li>
                        <li>
                            <a href="barangay_notifications.php" class="nav-link">Notifications</a>
                        </li>
                        <li><a href="barangay_reports.php" class="nav-link">Reports</a></li>
                        <li class="user-menu">
                            <span>Welcome, <?php echo htmlspecialchars($user_name); ?></span>
                            <a href="logout.php" class="btn btn-outline">Logout</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </header>

        <main class="dashboard-main">
            <div class="container">
                <h1 class="welcome-title">Welcome back, <?php echo htmlspecialchars($user_name); ?></h1>
                
                <div class="dashboard-grid">
                    <div class="dashboard-card upcoming-pickup">
                        <h2>Today's Pickups</h2>
                        <div class="pickup-info">
                            <div class="pickup-date">
                                <h3>Pickup Statistics</h3>
                                <p class="date"><?php echo date('l, F j'); ?></p>
                                <p class="waste-type">Total: <?php echo $todays_stats['total']; ?> | Completed: <?php echo $todays_stats['completed']; ?> | Delayed: <?php echo $todays_stats['delayed']; ?></p>
                            </div>
                            <a href="barangay_schedule.php" class="view-details">Manage Schedule</a>
                        </div>
                    </div>
                    
                    <div class="dashboard-card pickup-status">
                        <h2>Pickup Status</h2>
                        <p id="pickupStatusText"><?php echo $pickup_status; ?></p>
                        <?php if($todays_stats['total'] > 0): ?>
                        <div class="progress-container">
                            <div class="progress-bar">
                                <div class="progress-fill" id="progressFill" style="width: <?php echo $completion_percentage; ?>%"></div>
                            </div>
                            <span class="progress-text" id="progressText"><?php echo $completion_percentage; ?>% of today's pickups completed.</span>
                        </div>
                        <?php endif; ?>
                        <div id="liveUpdateIndicator" class="live-indicator" style="display: none;">
                            <span class="live-dot"></span> Live updating...
                        </div>
                    </div>
                    
                    <div class="dashboard-card notifications">
                        <h2>Recent Notifications</h2>
                        <div class="notification-list" id="notificationList">
                            <?php if(!empty($notifications)): ?>
                                <?php foreach($notifications as $notification): ?>
                                <div class="notification-item <?php echo (isset($notification['is_read']) && $notification['is_read'] == 0) ? 'unread' : ''; ?>" 
                                     data-id="<?php echo $notification['id'] ?? ''; ?>">
                                    <div class="notification-icon">
                                        <?php
                                        $icon = '📢';
                                        if(isset($notification['type'])) {
                                            switch($notification['type']) {
                                                case 'pickup_scheduled': $icon = '📅'; break;
                                                case 'pickup_completed': $icon = '✅'; break;
                                                case 'pickup_delayed': $icon = '⚠️'; break;
                                                case 'pickup_cancelled': $icon = '❌'; break;
                                                case 'emergency': $icon = '🚨'; break;
                                                default: $icon = '📢';
                                            }
                                        }
                                        echo $icon;
                                        ?>
                                    </div>
                                    <div class="notification-content">
                                        <h4><?php echo htmlspecialchars($notification['title']); ?></h4>
                                        <p><?php echo htmlspecialchars($notification['message']); ?></p>
                                        <small><?php echo date('F j, Y, g:i A', strtotime($notification['created_at'])); ?></small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-notifications">
                                    <p>No notifications from the past week.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <a href="barangay_notifications.php" class="view-all">View All Notifications</a>
                    </div>
                    
                                    <!-- Modify this section in barangay_dashboard.php -->
                <div class="dashboard-card pending-applications">
                    <h2>Worker Applications</h2>
                    <div class="applications-info">
                        <div class="applications-count">
                            <h3>Pending Validation</h3>
                            <p class="count" id="applicationsCount"><?php echo $pending_applications; ?> pending</p>
                            <?php if($pending_applications > 0): ?>
                            <div class="application-types">
                                <small>New worker applications awaiting review</small>
                            </div>
                            <?php endif; ?>
                        </div>
                        <a href="barangay_applications.php" class="btn btn-primary">
                            <?php echo $pending_applications > 0 ? 'Review Now' : 'View Applications'; ?>
                        </a>
                    </div>
                </div>
                    
                    <div class="dashboard-card upcoming-schedule">
                        <h2>Upcoming Schedule</h2>
                        <div class="schedule-info">
                            <?php if($upcoming_pickup): ?>
                                <div class="schedule-date">
                                    <h3>Next Pickup</h3>
                                    <p class="date"><?php echo date('l, F j', strtotime($upcoming_pickup['schedule_date'])); ?></p>
                                    <p class="waste-type">Zone: <?php echo $upcoming_pickup['zone'] ?? 'All Zones'; ?></p>
                                </div>
                                <a href="barangay_schedule.php" class="view-details">View Details</a>
                            <?php else: ?>
                                <div class="schedule-date">
                                    <h3>No Upcoming Pickups</h3>
                                    <p class="date">Schedule a pickup</p>
                                </div>
                                <a href="barangay_schedule.php" class="view-details">Create Schedule</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Fetch real-time collection stats from Node.js API
        async function fetchCollectionStats() {
            try {
                const response = await fetch('http://localhost:3000/api/collections/stats');
                const data = await response.json();

                // Update today's stats display
                const statsElement = document.querySelector('.pickup-info .waste-type');
                if (statsElement) {
                    statsElement.textContent = `Total: ${data.totalCollections} | Completed: ${data.completedToday} | Efficiency: ${data.efficiency}%`;
                }

                // Update progress bar
                const progressFill = document.getElementById('progressFill');
                const progressText = document.getElementById('progressText');
                if (progressFill && progressText) {
                    const percentage = Math.round(data.efficiency);
                    progressFill.style.width = `${percentage}%`;
                    progressText.textContent = `${percentage}% efficiency rate.`;
                }

                console.log('Collection stats updated:', data);
            } catch (error) {
                console.error('Failed to fetch collection stats:', error);
            }
        }

        // Fetch driver performance data
        async function fetchDriverPerformance() {
            try {
                const response = await fetch('http://localhost:3000/api/drivers/performance');
                const data = await response.json();

                // Update driver count or other metrics if needed
                console.log('Driver performance data:', data);
            } catch (error) {
                console.error('Failed to fetch driver performance:', error);
            }
        }

        // Fetch data on page load and every 60 seconds
        document.addEventListener('DOMContentLoaded', function() {
            fetchCollectionStats();
            fetchDriverPerformance();
            setInterval(fetchCollectionStats, 60000);
            setInterval(fetchDriverPerformance, 60000);
        });
    </script>
    <script src="js/barangay_dashboard.js"></script>
</body>
</html>
