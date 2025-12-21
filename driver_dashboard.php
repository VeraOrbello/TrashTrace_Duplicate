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
    <title>Dashboard - TrashTrace</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Consolidated CSS -->
    <link rel="stylesheet" href="css/driver/master-styles.css">
    
    <style>
        /* Resident Dashboard Specific Styles */
        .resident-dashboard-container {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        .grid-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                linear-gradient(rgba(76, 175, 80, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(76, 175, 80, 0.03) 1px, transparent 1px);
            background-size: 50px 50px;
            z-index: -1;
            opacity: 0.5;
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
        
        .card-header h2, .card-header h3 {
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
        
        .card-content {
            padding: 24px;
        }
        
        .welcome-title {
            color: #2c3e50;
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .welcome-subtitle {
            color: #666;
            font-size: 1.1rem;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Header Section - Matching routes.php style -->
        <header class="dashboard-header">
            <!-- Grid Background Pattern -->
            <div class="grid-background-nav"></div>
            
            <div class="header-content">
                <a href="dashboard.php" class="logo">
                    <i class="fas fa-recycle"></i>
                    <span>Trash<span style="font-weight: 700;">Trace</span></span>
                </a>
                
                <button class="mobile-menu-toggle" id="mobileMenuToggle">
                    <i class="fas fa-bars"></i>
                </button>
                
                <nav id="mainNav">
                    <div class="nav-container">
                        <ul>
                            <li><a href="dashboard.php" class="nav-link active"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
                            <li><a href="res_schedule.php" class="nav-link"><i class="fas fa-calendar"></i> <span>Schedule</span></a></li>
                            <li><a href="res_notif.php" class="nav-link"><i class="fas fa-bell"></i> <span>Notifications</span></a></li>
                            <li><a href="res_profile.php" class="nav-link"><i class="fas fa-user"></i> <span>Profile</span></a></li>
                        </ul>
                    </div>
                </nav>
                
                <div class="user-menu">
                    <div class="user-info" onclick="window.location.href='res_profile.php'">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                        </div>
                        <div class="user-details">
                            <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span>
                            <span class="user-id">Resident</span>
                        </div>
                    </div>
                    <a href="logout.php" class="btn-logout">
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
                        <i class="fas fa-home"></i>
                        Welcome back, <?php echo htmlspecialchars($user_name); ?>
                    </h1>
                    <p class="page-subtitle">Stay updated with your waste collection schedule and notifications.</p>
                </div>

                <!-- Dashboard Grid -->
                <div class="dashboard-grid">
                    <!-- Sidebar with upcoming pickup -->
                    <div class="sidebar">
                        <!-- Upcoming Pickup Card -->
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h3><i class="fas fa-truck"></i> Upcoming Pickup</h3>
                            </div>
                            <div class="card-content">
                                <?php if($upcoming_pickup): ?>
                                    <div class="pickup-info">
                                        <div class="pickup-date">
                                            <h3>Next Pickup</h3>
                                            <p class="date"><?php echo date('l, F j', strtotime($upcoming_pickup['schedule_date'])); ?></p>
                                            <p class="waste-type">Waste Type: Mixed Waste</p>
                                        </div>
                                        <a href="res_schedule.php" class="view-details">
                                            <i class="fas fa-eye"></i> View Details
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="pickup-info">
                                        <div class="pickup-date">
                                            <h3>No Upcoming Pickups</h3>
                                            <p class="date">Check schedule for updates</p>
                                        </div>
                                        <a href="res_schedule.php" class="view-details">
                                            <i class="fas fa-calendar"></i> View Schedule
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Pickup Status Card -->
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h3><i class="fas fa-chart-line"></i> Pickup Status</h3>
                            </div>
                            <div class="card-content">
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
                        </div>
                    </div>
                    
                    <!-- Main Content Area -->
                    <div class="main-content">
                        <!-- Notifications Card -->
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h3><i class="fas fa-bell"></i> Recent Notifications</h3>
                            </div>
                            <div class="card-content">
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
                                <a href="res_notif.php" class="view-all">
                                    <i class="fas fa-list"></i> View All Notifications
                                </a>
                            </div>
                        </div>
                        
                        <!-- Actions Card -->
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                            </div>
                            <div class="card-content">
                                <div class="action-buttons">
                                    <button id="report-missed-btn" class="resident-action-btn">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <span>Report Missed Pickup</span>
                                    </button>
                                    <button id="track-complaint-btn" class="resident-action-btn">
                                        <i class="fas fa-search"></i>
                                        <span>Track Complaint Status</span>
                                    </button>
                                    <button id="feedback-btn" class="resident-action-btn">
                                        <i class="fas fa-comment"></i>
                                        <span>Submit Feedback</span>
                                    </button>
                                    <a href="barangay_register.php" class="resident-action-btn">
                                        <i class="fas fa-file-alt"></i>
                                        <span>Application Status</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal for user actions -->
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
        // Mobile menu toggle
        document.getElementById('mobileMenuToggle').addEventListener('click', function() {
            document.getElementById('mainNav').classList.toggle('active');
        });

        // Modal functionality
        const modal = document.getElementById('user-modal');
        const modalClose = document.getElementById('modal-close');
        const modalCancel = document.getElementById('modal-cancel');
        const reportMissedBtn = document.getElementById('report-missed-btn');
        const trackComplaintBtn = document.getElementById('track-complaint-btn');
        const feedbackBtn = document.getElementById('feedback-btn');
        const formFeedback = document.getElementById('form-feedback');
        const userActionForm = document.getElementById('user-action-form');
        const reportTypeSelect = document.getElementById('report-type');

        // Set default address
        document.getElementById('address').value = '<?php echo htmlspecialchars($user_zone . ', ' . $user_barangay); ?>';

        // Button click handlers
        reportMissedBtn.addEventListener('click', function() {
            document.getElementById('modal-title').textContent = 'Report Missed Pickup';
            document.getElementById('action-type').value = 'Missed Pickup';
            reportTypeSelect.value = 'Missed Pickup';
            modal.style.display = 'block';
        });

        trackComplaintBtn.addEventListener('click', function() {
            alert('Redirecting to complaint tracking page...');
            window.location.href = 'complaint_tracking.php';
        });

        feedbackBtn.addEventListener('click', function() {
            document.getElementById('modal-title').textContent = 'Submit Feedback';
            document.getElementById('action-type').value = 'Feedback';
            reportTypeSelect.value = 'Feedback';
            modal.style.display = 'block';
        });

        // Close modal
        modalClose.addEventListener('click', function() {
            modal.style.display = 'none';
            formFeedback.style.display = 'none';
            userActionForm.reset();
        });

        modalCancel.addEventListener('click', function() {
            modal.style.display = 'none';
            formFeedback.style.display = 'none';
            userActionForm.reset();
        });

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                modal.style.display = 'none';
                formFeedback.style.display = 'none';
                userActionForm.reset();
            }
        });

        // Form submission
        userActionForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('user_id', <?php echo $user_id; ?>);
            formData.append('user_name', '<?php echo addslashes($user_name); ?>');
            formData.append('barangay', '<?php echo addslashes($user_barangay); ?>');
            
            try {
                const response = await fetch('submit_user_action.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                formFeedback.style.display = 'block';
                if (result.success) {
                    formFeedback.textContent = result.message;
                    formFeedback.className = 'success';
                    setTimeout(() => {
                        modal.style.display = 'none';
                        formFeedback.style.display = 'none';
                        userActionForm.reset();
                    }, 2000);
                } else {
                    formFeedback.textContent = result.message || 'Error submitting form';
                    formFeedback.className = 'error';
                }
            } catch (error) {
                formFeedback.style.display = 'block';
                formFeedback.textContent = 'Network error. Please try again.';
                formFeedback.className = 'error';
                console.error('Error:', error);
            }
        });

        // Mark notifications as read when clicked
        document.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', function() {
                const notificationId = this.dataset.id;
                if (notificationId && this.classList.contains('unread')) {
                    // Send AJAX request to mark as read
                    fetch('mark_notification_read.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ notification_id: notificationId })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            this.classList.remove('unread');
                            // Update unread count in bell icon if exists
                            const bellIcon = document.querySelector('.fa-bell');
                            if (bellIcon) {
                                const currentCount = parseInt(bellIcon.dataset.count || '0');
                                if (currentCount > 0) {
                                    bellIcon.dataset.count = currentCount - 1;
                                }
                            }
                        }
                    });
                }
            });
        });

        // Real-time data fetching
        const user_id = <?php echo json_encode($user_id); ?>;
        const user_barangay = <?php echo json_encode($user_barangay); ?>;
        const initial_unread_count = <?php echo $unread_count; ?>;

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
</body>
</html>