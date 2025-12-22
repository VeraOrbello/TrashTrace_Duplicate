<?php
require_once "config.php";

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

if(isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin'){
    header('location: barangay_dashboard.php');
    exit;
}

$user_id = $_SESSION["id"];
$user_barangay = $_SESSION["barangay"] ?? '';
$user_zone = $_SESSION["zone"] ?? '';

$upcoming_pickup = [];
$pickup_status = "No scheduled pickups";
$progress_percentage = 0;

$sql = "SELECT * FROM pickup_schedules WHERE barangay = :barangay AND schedule_date >= CURDATE() AND status = 'Scheduled' ORDER BY schedule_date ASC LIMIT 1";
if($stmt = $pdo->prepare($sql)){
    $stmt->bindParam(":barangay", $user_barangay, PDO::PARAM_STR);
    
    if($stmt->execute()){
        $upcoming_pickup = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    $stmt->closeCursor();
}

if($upcoming_pickup){
    $pickup_status = "Pickup in Progress";
    $progress_percentage = 75;
}

$one_week_ago = date('Y-m-d H:i:s', strtotime('-1 week'));


$notifications = [];
$notification_sql = "SELECT * FROM notifications 
                    WHERE (user_id = :user_id OR (user_id IS NULL AND barangay = :barangay))
                    AND created_at >= :one_week_ago
                    ORDER BY created_at DESC 
                    LIMIT 10";
                    
if($stmt = $pdo->prepare($notification_sql)){
    $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
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
    <title>Dashboard - TrashTrace</title>
    <link href="node_modules/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/header.php'; ?>

        <main class="dashboard-main">
            <div class="container">
                <div class="dashboard-header-section">
                    <div class="header-left">
                        <h1 class="welcome-title">Welcome back, <?php echo htmlspecialchars($_SESSION["full_name"]); ?></h1>
                        <p class="welcome-subtitle">Here's what's happening with your waste management</p>
                    </div>
                </div>
                
                <!-- Quick Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon green">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-details">
                            <span class="stat-label">Next Pickup</span>
                            <span class="stat-value">
                                <?php if($upcoming_pickup): ?>
                                    <?php echo date('M j', strtotime($upcoming_pickup['schedule_date'])); ?>
                                <?php else: ?>
                                    Not Scheduled
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon blue">
                            <i class="fas fa-bell"></i>
                        </div>
                        <div class="stat-details">
                            <span class="stat-label">Notifications</span>
                            <span class="stat-value"><?php echo $unread_count; ?> New</span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon orange">
                            <i class="fas fa-truck"></i>
                        </div>
                        <div class="stat-details">
                            <span class="stat-label">Status</span>
                            <span class="stat-value"><?php echo $upcoming_pickup ? 'Scheduled' : 'Pending'; ?></span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon purple">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="stat-details">
                            <span class="stat-label">Location</span>
                            <span class="stat-value"><?php echo htmlspecialchars($user_barangay); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Main Content Grid -->
                <div class="dashboard-grid">
                    <!-- Upcoming Pickup Card -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h2><i class="fas fa-calendar-alt"></i> Upcoming Pickup</h2>
                        </div>
                        <div class="card-body">
                            <?php if($upcoming_pickup): ?>
                                <div class="pickup-details">
                                    <div class="pickup-date-display">
                                        <div class="date-circle">
                                            <span class="day"><?php echo date('d', strtotime($upcoming_pickup['schedule_date'])); ?></span>
                                            <span class="month"><?php echo date('M', strtotime($upcoming_pickup['schedule_date'])); ?></span>
                                        </div>
                                        <div class="date-info">
                                            <h3><?php echo date('l, F j, Y', strtotime($upcoming_pickup['schedule_date'])); ?></h3>
                                            <p class="waste-type"><i class="fas fa-trash"></i> Mixed Waste Collection</p>
                                        </div>
                                    </div>
                                    <div class="pickup-meta">
                                        <div class="meta-item">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <span><?php echo htmlspecialchars($user_barangay); ?></span>
                                        </div>
                                        <div class="meta-item">
                                            <i class="fas fa-clock"></i>
                                            <span>Morning Schedule</span>
                                        </div>
                                    </div>
                                </div>
                                <a href="res_schedule.php" class="card-link">View Full Schedule <i class="fas fa-arrow-right"></i></a>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-calendar-times"></i>
                                    <h3>No Upcoming Pickups</h3>
                                    <p>Check the schedule page for future pickups</p>
                                    <a href="res_schedule.php" class="btn-secondary">View Schedule</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Pickup Status Card -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h2><i class="fas fa-truck-loading"></i> Pickup Status</h2>
                        </div>
                        <div class="card-body">
                            <div class="status-display">
                                <div class="status-icon">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <p class="status-text"><?php echo $pickup_status; ?></p>
                            </div>
                            <?php if($upcoming_pickup): ?>
                            <div class="progress-section">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $progress_percentage; ?>%"></div>
                                </div>
                                <div class="progress-info">
                                    <span><?php echo $progress_percentage; ?>%</span>
                                    <span>Complete</span>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Recent Notifications Card -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h2><i class="fas fa-bell"></i> Recent Notifications</h2>
                            <a href="res_notif.php" class="header-link">View All</a>
                        </div>
                        <div class="card-body">
                            <div class="notification-list" id="notificationList">
                                <?php if(!empty($notifications)): ?>
                                    <?php $count = 0; ?>
                                    <?php foreach($notifications as $notification): ?>
                                        <?php if($count >= 5) break; ?>
                                        <div class="notification-item <?php echo (isset($notification['is_read']) && $notification['is_read'] == 0) ? 'unread' : ''; ?>" 
                                             data-id="<?php echo $notification['id'] ?? ''; ?>">
                                            <div class="notif-icon">
                                                <?php
                                                $icon = 'fa-bullhorn';
                                                $iconColor = 'blue';
                                                if(isset($notification['type'])) {
                                                    switch($notification['type']) {
                                                        case 'pickup_scheduled': $icon = 'fa-calendar-check'; $iconColor = 'green'; break;
                                                        case 'pickup_completed': $icon = 'fa-check-circle'; $iconColor = 'green'; break;
                                                        case 'pickup_delayed': $icon = 'fa-exclamation-triangle'; $iconColor = 'orange'; break;
                                                        case 'pickup_cancelled': $icon = 'fa-times-circle'; $iconColor = 'red'; break;
                                                        case 'emergency': $icon = 'fa-exclamation-circle'; $iconColor = 'red'; break;
                                                        default: $icon = 'fa-bullhorn'; $iconColor = 'blue';
                                                    }
                                                }
                                                ?>
                                                <i class="fas <?php echo $icon; ?> <?php echo $iconColor; ?>"></i>
                                            </div>
                                            <div class="notif-content">
                                                <h4><?php echo htmlspecialchars($notification['title']); ?></h4>
                                                <p><?php echo htmlspecialchars(substr($notification['message'], 0, 80)); ?><?php echo strlen($notification['message']) > 80 ? '...' : ''; ?></p>
                                                <small><i class="far fa-clock"></i> <?php echo date('M j, g:i A', strtotime($notification['created_at'])); ?></small>
                                            </div>
                                        </div>
                                        <?php $count++; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state-small">
                                        <i class="fas fa-inbox"></i>
                                        <p>No notifications from the past week</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions Card -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
                        </div>
                        <div class="card-body">
                            <div class="action-grid">
                                <button id="report-missed-btn" class="action-btn">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <span>Report Missed Pickup</span>
                                </button>
                                <button id="track-complaint-btn" class="action-btn">
                                    <i class="fas fa-search"></i>
                                    <span>Track Complaint</span>
                                </button>
                                <button id="feedback-btn" class="action-btn">
                                    <i class="fas fa-comment-dots"></i>
                                    <span>Give Feedback</span>
                                </button>
                                <a href="barangay_register.php" class="action-btn">
                                    <i class="fas fa-file-alt"></i>
                                    <span>Application Status</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
        
        <div id="user-modal" class="modal" style="display:none;">
            <div class="modal-content">
                <span id="modal-close" class="modal-close">&times;</span>
                <h3 id="modal-title">Report Issue</h3>
                <form id="user-action-form">
                    <input type="hidden" id="action-type" name="type" value="Feedback">
                    <div class="form-group">
                        <label for="report-type">Type</label>
                        <select id="report-type" name="type">
                            <option value="Missed Pickup">Missed Pickup</option>
                            <option value="Complaint">Complaint</option>
                            <option value="Feedback">Feedback</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="4" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="address">Address</label>
                        <input id="address" name="address" type="text" placeholder="House number, Street, Barangay" required />
                    </div>
                    <div class="form-group">
                        <label for="location">Location (optional)</label>
                        <input id="location" name="location" type="text" placeholder="e.g. Near Market / Landmark" />
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Submit</button>
                        <button type="button" id="modal-cancel" class="btn btn-outline">Cancel</button>
                    </div>
                    <div id="form-feedback" style="margin-top:8px;display:none;"></div>
                </form>
            </div>
        </div>

    <script>
        const user_id = <?php echo json_encode($user_id); ?>;
        const user_barangay = <?php echo json_encode($user_barangay); ?>;
        const initial_unread_count = <?php echo $unread_count; ?>;

        // Fetch real-time data from Node.js API
        async function fetchRealtimeData() {
            try {
                const response = await fetch('http://localhost:3000/api/collections/stats');
                const data = await response.json();

                // Update pickup status with real data
                const statusElement = document.querySelector('.pickup-status p');
                if (statusElement) {
                    statusElement.textContent = `Completed: ${data.completedToday} | Pending: ${data.pendingPickups}`;
                }

                // Update progress bar
                const progressFill = document.querySelector('.progress-fill');
                const progressText = document.querySelector('.progress-text');
                if (progressFill && progressText && data.totalCollections > 0) {
                    const percentage = Math.round((data.completedToday / (data.completedToday + data.pendingPickups)) * 100);
                    progressFill.style.width = `${percentage}%`;
                    progressText.textContent = `${percentage}% of today's collections completed.`;
                }

                console.log('Real-time data updated:', data);
            } catch (error) {
                console.error('Failed to fetch real-time data:', error);
            }
        }

        // Fetch data on page load and every 30 seconds
        document.addEventListener('DOMContentLoaded', function() {
            fetchRealtimeData();
            setInterval(fetchRealtimeData, 30000);
        });
    </script>
    <script src="js/dashboard.js"></script>
    <script src="js/user_actions.js"></script>
</body>
</html>