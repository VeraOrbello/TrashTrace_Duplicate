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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/barangay_dashboard.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="dashboard-main">
        <div class="container">
            <div class="dashboard-header-section">
                <h1><i class="fas fa-tachometer-alt"></i> Worker Dashboard</h1>
            </div>
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #e3f2fd; color: #1976d2;">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $todays_stats['total']; ?></h3>
                        <p>Today's Pickups</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: #e8f5e9; color: #4caf50;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $todays_stats['completed']; ?></h3>
                        <p>Completed</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: #fff3e0; color: #ff9800;">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $todays_stats['delayed']; ?></h3>
                        <p>Delayed</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: #f3e5f5; color: #9c27b0;">
                        <i class="fas fa-user-friends"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $pending_applications; ?></h3>
                        <p>Pending Applications</p>
                    </div>
                </div>
            </div>
            
            <!-- Main Grid -->
            <div class="dashboard-grid">
                <!-- Pickup Progress Card -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2><i class="fas fa-chart-line"></i> Today's Progress</h2>
                        <span class="badge"><?php echo $completion_percentage; ?>%</span>
                    </div>
                    <div class="card-body">
                        <?php if($todays_stats['total'] > 0): ?>
                        <div class="progress-stats-row">
                            <div class="progress-stat">
                                <div class="stat-value"><?php echo $todays_stats['total']; ?></div>
                                <div class="stat-label">Total</div>
                            </div>
                            <div class="progress-stat">
                                <div class="stat-value" style="color: #4caf50;"><?php echo $todays_stats['completed']; ?></div>
                                <div class="stat-label">Done</div>
                            </div>
                            <div class="progress-stat">
                                <div class="stat-value" style="color: #ff9800;"><?php echo $todays_stats['delayed']; ?></div>
                                <div class="stat-label">Delayed</div>
                            </div>
                            <div class="progress-stat">
                                <div class="stat-value" style="color: #2196f3;"><?php echo $todays_stats['total'] - $todays_stats['completed'] - $todays_stats['delayed']; ?></div>
                                <div class="stat-label">Pending</div>
                            </div>
                        </div>
                        <div class="progress-bar-container">
                            <div class="progress-bar" style="width: <?php echo $completion_percentage; ?>%"><?php echo $completion_percentage; ?>%</div>
                        </div>
                        <p class="progress-text"><?php echo date('l, F j, Y'); ?></p>
                        <?php else: ?>
                        <div class="no-items">
                            <i class="fas fa-calendar-times"></i>
                            <p>No pickups scheduled for today</p>
                            <a href="barangay_schedule.php" class="btn-primary">Schedule Now</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Upcoming Schedule Card -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2><i class="far fa-calendar"></i> Upcoming Pickup</h2>
                    </div>
                    <div class="card-body">
                        <?php if($upcoming_pickup): ?>
                        <div class="upcoming-schedule-card">
                            <div class="schedule-date-badge" style="background: linear-gradient(135deg, #4caf7d 0%, #45a070 100%);">
                                <div class="day"><?php echo date('d', strtotime($upcoming_pickup['schedule_date'])); ?></div>
                                <div class="month"><?php echo strtoupper(date('M', strtotime($upcoming_pickup['schedule_date']))); ?></div>
                            </div>
                            <div class="schedule-info">
                                <h3><?php echo date('l', strtotime($upcoming_pickup['schedule_date'])); ?></h3>
                                <p class="schedule-date"><?php echo date('F j, Y', strtotime($upcoming_pickup['schedule_date'])); ?></p>
                                <div class="schedule-meta">
                                    <span class="meta-item"><i class="fas fa-map-marker-alt"></i> <?php echo $upcoming_pickup['zone'] ?? 'All Zones'; ?></span>
                                    <span class="meta-item"><i class="fas fa-clock"></i> <?php echo date('g:i A', strtotime($upcoming_pickup['schedule_time'] ?? '08:00:00')); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="no-items">
                            <i class="fas fa-calendar-plus"></i>
                            <p>No upcoming pickups scheduled</p>
                        </div>
                        <?php endif; ?>
                        <a href="barangay_schedule.php" class="view-all">View Full Schedule <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                
                <!-- Recent Notifications Card -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2><i class="far fa-bell"></i> Recent Notifications</h2>
                        <?php if($unread_count > 0): ?>
                        <span class="badge"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="notification-list">
                            <?php if(!empty($notifications)): ?>
                                <?php foreach($notifications as $index => $notification): 
                                    if($index >= 4) break; // Show only 4 notifications
                                ?>
                                <div class="notification-item">
                                    <div class="notif-icon <?php 
                                        if(isset($notification['type'])) {
                                            switch($notification['type']) {
                                                case 'pickup_completed': echo 'success'; break;
                                                case 'pickup_delayed': 
                                                case 'emergency': echo 'warning'; break;
                                                default: echo 'info';
                                            }
                                        }
                                    ?>">
                                        <i class="<?php 
                                            if(isset($notification['type'])) {
                                                switch($notification['type']) {
                                                    case 'pickup_scheduled': echo 'fas fa-calendar-check'; break;
                                                    case 'pickup_completed': echo 'fas fa-check-circle'; break;
                                                    case 'pickup_delayed': echo 'fas fa-exclamation-triangle'; break;
                                                    case 'pickup_cancelled': echo 'fas fa-times-circle'; break;
                                                    case 'emergency': echo 'fas fa-exclamation-circle'; break;
                                                    default: echo 'fas fa-bell';
                                                }
                                            } else {
                                                echo 'fas fa-bell';
                                            }
                                        ?>"></i>
                                    </div>
                                    <div class="notif-content <?php echo (isset($notification['is_read']) && $notification['is_read'] == 0) ? 'unread' : ''; ?>">
                                        <h4><?php echo htmlspecialchars($notification['title']); ?></h4>
                                        <p><?php echo htmlspecialchars(substr($notification['message'], 0, 60)) . (strlen($notification['message']) > 60 ? '...' : ''); ?></p>
                                        <span class="notif-time"><?php echo date('M j, g:i A', strtotime($notification['created_at'])); ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-items">
                                    <i class="far fa-bell-slash"></i>
                                    <p>No recent notifications</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <a href="barangay_notifications.php" class="view-all">View All Notifications <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                
                <!-- Applications Card -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2><i class="fas fa-user-check"></i> Applications</h2>
                        <?php if($pending_applications > 0): ?>
                        <span class="badge-warning"><?php echo $pending_applications; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="app-summary-grid">
                            <div class="app-summary-item">
                                <div class="app-icon" style="background: #fff3e0; color: #ff9800;">
                                    <i class="fas fa-hourglass-half"></i>
                                </div>
                                <div class="app-info">
                                    <div class="app-count"><?php echo $pending_applications; ?></div>
                                    <div class="app-label">Pending</div>
                                </div>
                            </div>
                            <div class="app-summary-item">
                                <div class="app-icon" style="background: #e3f2fd; color: #2196f3;">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="app-info">
                                    <div class="app-count">--</div>
                                    <div class="app-label">In Review</div>
                                </div>
                            </div>
                        </div>
                        <?php if($pending_applications > 0): ?>
                        <a href="barangay_applications.php" class="btn-primary">
                            <i class="fas fa-eye"></i> Review Applications
                        </a>
                        <?php else: ?>
                        <a href="barangay_applications.php" class="view-all">View All Applications <i class="fas fa-arrow-right"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            </div>
    </main>
    
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
