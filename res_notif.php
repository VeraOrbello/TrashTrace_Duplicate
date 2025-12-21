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
$user_barangay = $_SESSION['barangay'] ?? '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mark_as_read'])) {
        $notification_id = $_POST['notification_id'];
        $update_query = "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND (user_id = ? OR (user_id IS NULL AND barangay = ?))";
        $stmt = $link->prepare($update_query);
        $stmt->bind_param("iis", $notification_id, $resident_id, $user_barangay);
        $stmt->execute();

        echo json_encode(['success' => true]);
        exit();
    }

    if (isset($_POST['delete_notification'])) {
        $notification_id = $_POST['notification_id'];
        $delete_query = "DELETE FROM notifications WHERE id = ? AND (user_id = ? OR (user_id IS NULL AND barangay = ?))";
        $stmt = $link->prepare($delete_query);
        $stmt->bind_param("iis", $notification_id, $resident_id, $user_barangay);
        $stmt->execute();

        echo json_encode(['success' => true]);
        exit();
    }

    if (isset($_POST['mark_all_read'])) {
        $update_query = "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE (user_id = ? OR (user_id IS NULL AND barangay = ?)) AND is_read = 0";
        $stmt = $link->prepare($update_query);
        $stmt->bind_param("is", $resident_id, $user_barangay);
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
              WHERE (n.user_id = ? OR (n.user_id IS NULL AND n.barangay = ?))";

    $params = [$resident_id, $user_barangay];
    $types = "is";

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
        FROM notifications WHERE (user_id = ? OR (user_id IS NULL AND barangay = ?))";

    if ($stmt = $link->prepare($stats_query)) {
        $stmt->bind_param("is", $resident_id, $user_barangay);
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
            'type' => 'pickup_delayed',
            'is_read' => 0,
            'priority' => 'high',
            'type_display' => 'Schedule Change'
        ],
        [
            'id' => 2,
            'title' => 'Payment Successful',
            'message' => 'Your payment of ₱150.00 has been processed successfully.',
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'type' => 'general',
            'is_read' => 0,
            'priority' => 'normal',
            'type_display' => 'General'
        ],
        [
            'id' => 3,
            'title' => 'Important Announcement',
            'message' => 'No collection on December 25, 2023 due to Christmas holiday.',
            'created_at' => date('Y-m-d H:i:s', strtotime('-3 days')),
            'type' => 'emergency',
            'is_read' => 1,
            'priority' => 'high',
            'type_display' => 'Emergency'
        ],
        [
            'id' => 4,
            'title' => 'Collection Scheduled',
            'message' => 'Your waste collection is scheduled for tomorrow at 8:00 AM.',
            'created_at' => date('Y-m-d H:i:s', strtotime('-5 days')),
            'type' => 'pickup_scheduled',
            'is_read' => 1,
            'priority' => 'normal',
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
    <title>Notifications - TrashTrace</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/res_notif.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <!-- Main Content -->
    <main class="notifications-main">
        <div class="container">
            <!-- Page Header -->
            <div class="notifications-header">
                <div class="header-content">
                    <h1><i class="far fa-bell"></i> Notifications</h1>
                    <p class="notifications-subtitle">Stay updated with your collection schedules and important announcements</p>
                </div>
                <form method="POST" action="">
                    <button type="submit" name="mark_all_read" class="mark-all-btn">
                        <i class="fas fa-check-double"></i> Mark All as Read
                    </button>
                </form>
            </div>

            <!-- Statistics Section -->
            <section class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="far fa-bell"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-label">Total Notifications</div>
                        <div class="stat-value"><?php echo $stats['total']; ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class="far fa-envelope"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-label">Unread</div>
                        <div class="stat-value"><?php echo $stats['unread']; ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon red">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-label">Important</div>
                        <div class="stat-value"><?php echo $stats['important']; ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="far fa-calendar-alt"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-label">Today</div>
                        <div class="stat-value"><?php echo $stats['today']; ?></div>
                    </div>
                </div>
            </section>

            <!-- Filter Section -->
            <section class="filter-section">
                <div class="filter-buttons">
                    <a href="?filter=all" class="filter-btn <?php echo $filter == 'all' ? 'active' : ''; ?>">
                        <i class="fas fa-list"></i> All
                    </a>
                    <a href="?filter=unread" class="filter-btn <?php echo $filter == 'unread' ? 'active' : ''; ?>">
                        <i class="far fa-envelope"></i> Unread
                    </a>
                    <a href="?filter=today" class="filter-btn <?php echo $filter == 'today' ? 'active' : ''; ?>">
                        <i class="far fa-calendar"></i> Today
                    </a>
                    <a href="?filter=week" class="filter-btn <?php echo $filter == 'week' ? 'active' : ''; ?>">
                        <i class="far fa-calendar-week"></i> Week
                    </a>
                    <a href="?filter=important" class="filter-btn <?php echo $filter == 'important' ? 'active' : ''; ?>">
                        <i class="fas fa-star"></i> Important
                    </a>
                </div>
            </section>

            <!-- Notifications List -->
            <?php if (count($notifications) > 0): ?>
                <div class="notifications-list">
                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-card <?php echo (!$notification['is_read']) ? 'unread' : ''; ?> <?php echo ($notification['priority'] == 'high') ? 'important' : ''; ?>">
                            <div class="notif-icon <?php 
                                if ($notification['type'] == 'pickup_scheduled') echo 'blue';
                                elseif ($notification['type'] == 'pickup_completed') echo 'green';
                                elseif ($notification['type'] == 'pickup_delayed') echo 'orange';
                                elseif ($notification['type'] == 'pickup_cancelled') echo 'red';
                                else echo 'gray';
                            ?>">
                                <?php if ($notification['type'] == 'pickup_scheduled'): ?>
                                    <i class="fa-solid fa-truck"></i>
                                <?php elseif ($notification['type'] == 'pickup_completed'): ?>
                                    <i class="fa-solid fa-circle-check"></i>
                                <?php elseif ($notification['type'] == 'pickup_delayed'): ?>
                                    <i class="fa-solid fa-calendar-xmark"></i>
                                <?php elseif ($notification['type'] == 'pickup_cancelled'): ?>
                                    <i class="fa-solid fa-xmark"></i>
                                <?php else: ?>
                                    <i class="fa-solid fa-bullhorn"></i>
                                <?php endif; ?>
                            </div>
                            <div class="notif-content">
                                <div class="notif-header">
                                    <h3 class="notif-title">
                                        <?php echo htmlspecialchars($notification['title']); ?>
                                    </h3>
                                    <span class="type-badge"><?php echo $notification['type_display']; ?></span>
                                </div>
                                <p class="notif-message"><?php echo htmlspecialchars($notification['message']); ?></p>
                                <div class="notif-footer">
                                    <span class="notif-time">
                                        <i class="far fa-clock"></i>
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
                                    <div class="notif-actions">
                                        <?php if (!$notification['is_read']): ?>
                                            <form method="POST" class="mark-read-form">
                                                <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                <button type="submit" name="mark_as_read" class="action-btn read-btn">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" class="delete-form">
                                            <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                            <button type="submit" name="delete_notification" class="action-btn delete-btn" onclick="return confirm('Delete this notification?');">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-notifications">
                    <i class="far fa-bell-slash"></i>
                    <h3>No notifications found</h3>
                    <p>You're all caught up! No notifications to display.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Scripts -->
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
                            // Reload to update stats
                            setTimeout(function() {
                                location.reload();
                            }, 500);
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
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>
