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
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/user_actions.css">
    <link href="node_modules/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    
</head>
<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <div class="header-content">
                <div class="logo">TrashTrace</div>
                <nav>
    <ul>
        <li><a href="dashboard.php" class="nav-link active">Dashboard</a></li>
        <li><a href="res_schedule.php" class="nav-link">Schedule</a></li>
        <li>
            <a href="res_notif.php" class="nav-link">Notifications</a>
        </li>
        <li><a href="res_profile.php" class="nav-link">Profile</a></li>
        <li class="user-menu">
            <span>Welcome, <?php echo htmlspecialchars($_SESSION["full_name"]); ?></span>
            <a href="logout.php" class="btn btn-outline">Logout</a>
        </li>
    </ul>
</nav>
            </div>
        </header>

        <main class="dashboard-main">
            <div class="container">
                <h1 class="welcome-title">Welcome back, <?php echo htmlspecialchars($_SESSION["full_name"]); ?></h1>
                
                <div class="dashboard-grid">
                    <div class="dashboard-card upcoming-pickup">
                        <h2>Upcoming Pickup</h2>
                        <div class="pickup-info">
                            <?php if($upcoming_pickup): ?>
                                <div class="pickup-date">
                                    <h3>Next Pickup</h3>
                                    <p class="date"><?php echo date('l, F j', strtotime($upcoming_pickup['schedule_date'])); ?></p>
                                    <p class="waste-type">Waste Type: Mixed Waste</p>
                                </div>
                                <a href="res_schedule.php" class="view-details">View Details</a>
                            <?php else: ?>
                                <div class="pickup-date">
                                    <h3>No Upcoming Pickups</h3>
                                    <p class="date">Check schedule for updates</p>
                                </div>
                                <a href="res_schedule.php" class="view-details">View Schedule</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="dashboard-card pickup-status">
                        <h2>Pickup Status</h2>
                        <p><?php echo $pickup_status; ?></p>
                        <?php if($upcoming_pickup): ?>
                        <div class="progress-container">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $progress_percentage; ?>%"></div>
                            </div>
                            <span class="progress-text">Truck is <?php echo $progress_percentage; ?>% along the route.</span>
                        </div>
                        <?php endif; ?>
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
                        <a href="res_notif.php" class="view-all">View All Notifications</a>
                    </div>
                    
                    <div class="dashboard-card actions">
                        <h2>Actions</h2>
                        <div class="action-buttons">
                                <button id="report-missed-btn" class="btn btn-action">Report Missed Pickup</button>
                                <button id="track-complaint-btn" class="btn btn-action">Track Complaint Status</button>
                                 <button id="feedback-btn" class="btn btn-action">Feedback</button>
                                <a href="barangay_register.php" class="btn btn-action">Application Status</a>
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