<?php
// res_notif.php - Resident Notifications Page
require_once 'config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit();
}

// Check user type (assuming you store this in session)
$user_type = $_SESSION['user_type'] ?? '';

// Get resident ID from session
$resident_id = $_SESSION['user_id'] ?? 0;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mark_as_read'])) {
        $notification_id = $_POST['notification_id'];
        $update_query = "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND resident_id = ?";
        $stmt = $link->prepare($update_query);
        $stmt->bind_param("ii", $notification_id, $resident_id);
        $stmt->execute();
        
        echo json_encode(['success' => true]);
        exit();
    }
    
    if (isset($_POST['delete_notification'])) {
        $notification_id = $_POST['notification_id'];
        $delete_query = "DELETE FROM notifications WHERE id = ? AND resident_id = ?";
        $stmt = $link->prepare($delete_query);
        $stmt->bind_param("ii", $notification_id, $resident_id);
        $stmt->execute();
        
        echo json_encode(['success' => true]);
        exit();
    }
    
    if (isset($_POST['mark_all_read'])) {
        $update_query = "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE resident_id = ? AND is_read = 0";
        $stmt = $link->prepare($update_query);
        $stmt->bind_param("i", $resident_id);
        $stmt->execute();
        
        header("Location: res_notif.php?success=marked_all");
        exit();
    }
}

// Get filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Initialize notifications array
$notifications = [];
$stats = [
    'total' => 0,
    'unread' => 0,
    'important' => 0,
    'today' => 0
];

// Check if notifications table exists
$table_check = $link->query("SHOW TABLES LIKE 'notifications'");
if ($table_check && $table_check->num_rows > 0) {
    // Build query based on filter
    $query = "SELECT n.*,
                     CASE
                         WHEN n.type = 'pickup_scheduled' THEN 'Collection Scheduled'
                         WHEN n.type = 'pickup_completed' THEN 'Collection Completed'
                         WHEN n.type = 'pickup_delayed' THEN 'Schedule Change'
                         WHEN n.type = 'pickup_cancelled' THEN 'Collection Cancelled'
                         WHEN n.type = 'emergency' THEN 'Emergency'
                         WHEN n.type = 'general' THEN 'General'
                         ELSE n.type
                     END as type_display,
                     CASE
                         WHEN n.type IN ('pickup_delayed', 'emergency') THEN 'high'
                         ELSE 'normal'
                     END as priority
              FROM notifications n
              WHERE n.user_id = ?";

    $params = [$resident_id];
    $types = "i";

    switch ($filter) {
        case 'unread':
            $query .= " AND n.is_read = 0";
            break;
        case 'today':
            $query .= " AND DATE(n.created_at) = CURDATE()";
            break;
        case 'week':
            $query .= " AND n.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            break;
        case 'important':
            $query .= " AND n.type IN ('pickup_delayed', 'emergency')";
            break;
    }

    $query .= " ORDER BY n.created_at DESC";
    
    if ($stmt = $link->prepare($query)) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $notifications_result = $stmt->get_result();
        
        while ($notification = $notifications_result->fetch_assoc()) {
            $notifications[] = $notification;
        }
    }

    // Get statistics
    $stats_query = "SELECT
        COUNT(*) as total,
        SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread,
        SUM(CASE WHEN type IN ('pickup_delayed', 'emergency') THEN 1 ELSE 0 END) as important,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today
        FROM notifications WHERE user_id = ?";
    
    if ($stmt = $link->prepare($stats_query)) {
        $stmt->bind_param("i", $resident_id);
        $stmt->execute();
        $stats_result = $stmt->get_result();
        if ($stats_result) {
            $stats = $stats_result->fetch_assoc();
        }
    }
} else {
    // Use sample data if table doesn't exist
    $notifications = [
        [
            'id' => 1,
            'title' => 'Collection Schedule Updated',
            'message' => 'Your waste collection schedule has been updated to every Wednesday.',
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
            'notification_type' => 'schedule_change',
            'is_read' => 0,
            'priority' => 'medium',
            'type_display' => 'Schedule Change'
        ],
        [
            'id' => 2,
            'title' => 'Payment Successful',
            'message' => 'Your payment of ₱150.00 has been processed successfully.',
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'notification_type' => 'payment_reminder',
            'is_read' => 0,
            'priority' => 'low',
            'type_display' => 'Payment Reminder'
        ],
        [
            'id' => 3,
            'title' => 'Important Announcement',
            'message' => 'No collection on December 25, 2023 due to Christmas holiday.',
            'created_at' => date('Y-m-d H:i:s', strtotime('-3 days')),
            'notification_type' => 'important_announcement',
            'is_read' => 1,
            'priority' => 'high',
            'type_display' => 'Important Announcement'
        ],
        [
            'id' => 4,
            'title' => 'Collection Scheduled',
            'message' => 'Your waste collection is scheduled for tomorrow at 8:00 AM.',
            'created_at' => date('Y-m-d H:i:s', strtotime('-5 days')),
            'notification_type' => 'collection_scheduled',
            'is_read' => 1,
            'priority' => 'medium',
            'type_display' => 'Collection Scheduled'
        ]
    ];
    
    $stats = [
        'total' => count($notifications),
        'unread' => 2,
        'important' => 1,
        'today' => 1
    ];
}

// Ensure stats are set
$stats['total'] = $stats['total'] ?? 0;
$stats['unread'] = $stats['unread'] ?? 0;
$stats['important'] = $stats['important'] ?? 0;
$stats['today'] = $stats['today'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - TrashTrace Resident</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-green: #2E7D32;
            --light-green: #4CAF50;
            --dark-green: #1B5E20;
            --warning-yellow: #FFA000;
            --danger-red: #D32F2F;
            --gray-bg: #f5f5f5;
            --card-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--primary-green), var(--light-green));
            box-shadow: 0 2px 15px rgba(46, 125, 50, 0.2);
        }
        
        .notification-card {
            background: white;
            border-radius: 12px;
            border-left: 4px solid var(--primary-green);
            transition: all 0.3s ease;
            margin-bottom: 15px;
            box-shadow: var(--card-shadow);
        }
        
        .notification-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .notification-card.unread {
            border-left: 4px solid var(--warning-yellow);
            background-color: #FFF9C4;
        }
        
        .notification-card.important {
            border-left: 4px solid var(--danger-red);
            background-color: #FFEBEE;
        }
        
        .notification-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-right: 15px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: var(--card-shadow);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-title {
            font-size: 0.9rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .filter-btn {
            border-radius: 20px;
            padding: 8px 20px;
            margin: 0 5px 10px 0;
            transition: all 0.3s ease;
        }
        
        .filter-btn.active {
            background-color: var(--primary-green);
            color: white;
            border-color: var(--primary-green);
        }
        
        .toast {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 1050;
        }
        
        .notification-time {
            font-size: 0.85rem;
            color: #666;
        }
        
        .action-btn {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            margin-left: 5px;
        }
        
        .no-notifications {
            text-align: center;
            padding: 50px 20px;
            color: #666;
        }
        
        .no-notifications i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #ddd;
        }
        
        .type-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .navbar-brand {
            font-weight: 600;
        }
        
        .nav-link.active {
            font-weight: 500;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-recycle me-2"></i>
                <strong>TrashTrace</strong> Resident
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-home me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="res_schedule.php">
                            <i class="fas fa-calendar-alt me-1"></i> Schedule
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="res_notif.php">
                            <i class="fas fa-bell me-1"></i> Notifications
                            <?php if ($stats['unread'] > 0): ?>
                                <span class="badge bg-danger ms-1"><?php echo $stats['unread']; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i> <?php echo $_SESSION['username'] ?? 'Account'; ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="res_profile.php"><i class="fas fa-user-cog me-2"></i> Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-bell text-success me-2"></i> Notifications</h2>
                    <form method="POST" action="" class="d-inline">
                        <button type="submit" name="mark_all_read" class="btn btn-success btn-sm">
                            <i class="fas fa-check-double me-1"></i> Mark All as Read
                        </button>
                    </form>
                </div>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="stat-card">
                            <div class="stat-icon text-primary">
                                <i class="fas fa-bell"></i>
                            </div>
                            <div class="stat-number"><?php echo $stats['total']; ?></div>
                            <div class="stat-title">Total Notifications</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="stat-card">
                            <div class="stat-icon text-warning">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="stat-number"><?php echo $stats['unread']; ?></div>
                            <div class="stat-title">Unread</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="stat-card">
                            <div class="stat-icon text-danger">
                                <i class="fas fa-exclamation-circle"></i>
                            </div>
                            <div class="stat-number"><?php echo $stats['important']; ?></div>
                            <div class="stat-title">Important</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="stat-card">
                            <div class="stat-icon text-success">
                                <i class="fas fa-calendar-day"></i>
                            </div>
                            <div class="stat-number"><?php echo $stats['today']; ?></div>
                            <div class="stat-title">Today</div>
                        </div>
                    </div>
                </div>
                
                <!-- Filter Buttons -->
                <div class="mb-4">
                    <a href="?filter=all" class="btn btn-outline-secondary filter-btn <?php echo $filter == 'all' ? 'active' : ''; ?>">
                        All Notifications
                    </a>
                    <a href="?filter=unread" class="btn btn-outline-warning filter-btn <?php echo $filter == 'unread' ? 'active' : ''; ?>">
                        <i class="fas fa-envelope me-1"></i> Unread
                    </a>
                    <a href="?filter=today" class="btn btn-outline-primary filter-btn <?php echo $filter == 'today' ? 'active' : ''; ?>">
                        <i class="fas fa-sun me-1"></i> Today
                    </a>
                    <a href="?filter=week" class="btn btn-outline-info filter-btn <?php echo $filter == 'week' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-week me-1"></i> This Week
                    </a>
                    <a href="?filter=important" class="btn btn-outline-danger filter-btn <?php echo $filter == 'important' ? 'active' : ''; ?>">
                        <i class="fas fa-exclamation-triangle me-1"></i> Important
                    </a>
                </div>
                
                <!-- Notifications List -->
                <div class="card">
                    <div class="card-body">
                        <?php if (count($notifications) > 0): ?>
                            <div class="notifications-list">
                                <?php foreach ($notifications as $notification): ?>
                                    <div class="notification-card p-3 <?php echo (!$notification['is_read']) ? 'unread' : ''; ?> <?php echo ($notification['priority'] == 'high') ? 'important' : ''; ?>">
                                        <div class="d-flex align-items-start">
                                            <div class="notification-icon 
                                                <?php echo $notification['notification_type'] == 'collection_scheduled' ? 'bg-success text-white' : 
                                                      ($notification['notification_type'] == 'collection_completed' ? 'bg-primary text-white' : 
                                                      ($notification['notification_type'] == 'schedule_change' ? 'bg-warning text-dark' : 
                                                      ($notification['notification_type'] == 'payment_reminder' ? 'bg-danger text-white' : 'bg-secondary text-white'))); ?>">
                                                <?php if ($notification['notification_type'] == 'collection_scheduled'): ?>
                                                    <i class="fas fa-truck"></i>
                                                <?php elseif ($notification['notification_type'] == 'collection_completed'): ?>
                                                    <i class="fas fa-check-circle"></i>
                                                <?php elseif ($notification['notification_type'] == 'schedule_change'): ?>
                                                    <i class="fas fa-calendar-times"></i>
                                                <?php elseif ($notification['notification_type'] == 'payment_reminder'): ?>
                                                    <i class="fas fa-money-bill-wave"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-bullhorn"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <h5 class="mb-1"><?php echo htmlspecialchars($notification['title']); ?></h5>
                                                        <p class="mb-2"><?php echo htmlspecialchars($notification['message']); ?></p>
                                                        
                                                        <div class="d-flex align-items-center">
                                                            <span class="type-badge 
                                                                <?php echo $notification['notification_type'] == 'collection_scheduled' ? 'bg-success' : 
                                                                      ($notification['notification_type'] == 'collection_completed' ? 'bg-primary' : 
                                                                      ($notification['notification_type'] == 'schedule_change' ? 'bg-warning' : 
                                                                      ($notification['notification_type'] == 'payment_reminder' ? 'bg-danger' : 'bg-secondary'))); ?> text-white me-2">
                                                                <?php echo $notification['type_display']; ?>
                                                            </span>
                                                            
                                                            <?php if (isset($notification['collector_name']) && $notification['collector_name']): ?>
                                                                <span class="badge bg-light text-dark me-2">
                                                                    <i class="fas fa-user-hard-hat me-1"></i> <?php echo $notification['collector_name']; ?>
                                                                </span>
                                                            <?php endif; ?>
                                                            
                                                            <span class="notification-time">
                                                                <i class="far fa-clock me-1"></i> 
                                                                <?php 
                                                                    $time = strtotime($notification['created_at']);
                                                                    $now = time();
                                                                    $diff = $now - $time;
                                                                    
                                                                    if ($diff < 60) {
                                                                        echo 'Just now';
                                                                    } elseif ($diff < 3600) {
                                                                        echo floor($diff / 60) . ' minutes ago';
                                                                    } elseif ($diff < 86400) {
                                                                        echo floor($diff / 3600) . ' hours ago';
                                                                    } elseif ($diff < 604800) {
                                                                        echo floor($diff / 86400) . ' days ago';
                                                                    } else {
                                                                        echo date('M d, Y', $time);
                                                                    }
                                                                ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex">
                                                        <?php if (!$notification['is_read']): ?>
                                                            <form method="POST" class="mark-read-form d-inline">
                                                                <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                                <button type="submit" name="mark_as_read" class="btn btn-success btn-sm action-btn">
                                                                    <i class="fas fa-check me-1"></i> Mark Read
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <form method="POST" class="delete-form d-inline ms-2">
                                                            <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                            <button type="submit" name="delete_notification" class="btn btn-outline-danger btn-sm action-btn" onclick="return confirm('Delete this notification?');">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="no-notifications">
                                <i class="far fa-bell-slash"></i>
                                <h4>No notifications found</h4>
                                <p class="text-muted">You're all caught up! No notifications to display.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toastContainer"></div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Handle mark as read with AJAX
            $('.mark-read-form').on('submit', function(e) {
                e.preventDefault();
                var form = $(this);
                var notificationCard = form.closest('.notification-card');
                
                $.ajax({
                    type: 'POST',
                    url: '',
                    data: form.serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            notificationCard.removeClass('unread');
                            form.remove();
                            showToast('Marked as read!', 'success');
                            updateNotificationCount();
                        }
                    }
                });
            });
            
            // Handle delete with AJAX
            $('.delete-form').on('submit', function(e) {
                if (!confirm('Are you sure you want to delete this notification?')) {
                    e.preventDefault();
                    return false;
                }
                
                var form = $(this);
                var notificationCard = form.closest('.notification-card');
                
                e.preventDefault();
                $.ajax({
                    type: 'POST',
                    url: '',
                    data: form.serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            notificationCard.fadeOut(300, function() {
                                $(this).remove();
                                if ($('.notification-card').length === 0) {
                                    location.reload();
                                }
                            });
                            showToast('Notification deleted!', 'success');
                            updateNotificationCount();
                        }
                    }
                });
            });
            
            // Show toast message
            function showToast(message, type) {
                var toast = $(`
                    <div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0" role="alert">
                        <div class="d-flex">
                            <div class="toast-body">
                                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
                                ${message}
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                        </div>
                    </div>
                `);
                
                $('#toastContainer').append(toast);
                var bsToast = new bootstrap.Toast(toast[0]);
                bsToast.show();
                
                toast.on('hidden.bs.toast', function() {
                    $(this).remove();
                });
            }
            
            // Update notification count in navbar
            function updateNotificationCount() {
                // Reload the stats section
                location.reload();
            }
            
    // Check for success messages in URL
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('success')) {
        if (urlParams.get('success') === 'marked_all') {
            showToast('All notifications marked as read!', 'success');
            // Remove parameter from URL without reload
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    }

    // Real-time notifications polling
    let lastNotificationCount = <?php echo $stats['total']; ?>;
    function checkForNewNotifications() {
        fetch('php/check_new_notifications.php', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.new_count > 0) {
                // Update notification count in navbar
                const badge = document.querySelector('.nav-link.active .badge');
                if (badge) {
                    const currentCount = parseInt(badge.textContent) || 0;
                    badge.textContent = currentCount + data.new_count;
                }
                // Optionally show a toast for new notifications
                showToast(`You have ${data.new_count} new notification(s)!`, 'info');
                // Refresh the page to show new notifications
                setTimeout(() => {
                    location.reload();
                }, 2000);
            }
        })
        .catch(error => {
            console.log('Error checking for new notifications:', error);
        });
    }

    // Check for new notifications every 30 seconds
    setInterval(checkForNewNotifications, 30000);
        });
    </script>
</body>
</html>
