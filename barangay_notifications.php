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

$user_barangay = $_SESSION['barangay'] ?? '';
$notifications = [];
$sql = "SELECT * FROM notifications WHERE (barangay = :barangay OR barangay IS NULL) AND user_id IS NULL AND type != 'pickup_scheduled' ORDER BY created_at DESC LIMIT 100";
if($stmt = $pdo->prepare($sql)){
    $stmt->bindParam(':barangay', $user_barangay, PDO::PARAM_STR);
    if($stmt->execute()){
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    $stmt->closeCursor();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Notifications - Barangay - TrashTrace</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/barangay_notifications.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="dashboard-main">
        <div class="container">
            <div class="notifications-header-section">
                <h1><i class="far fa-bell"></i> Notifications</h1>
                <p class="notifications-subtitle">Stay updated with important barangay updates</p>
            </div>
                <div class="notifications-actions" style="display:flex;gap:8px;margin-bottom:12px;align-items:center;">
                    <button id="mark-all-read-b" class="btn">Mark All as Read</button>
                    <button id="refresh-notifs-b" class="btn btn-outline">Refresh</button>
                </div>
                <div class="notifications-list">
                    <?php if(empty($notifications)): ?>
                        <div class="no-notifs">No notifications.</div>
                    <?php else: ?>
                        <?php foreach($notifications as $n): ?>
                            <div class="notif-item <?php echo $n['is_read'] ? '' : 'unread'; ?>" data-id="<?php echo $n['id']; ?>">
                                <div class="notif-left">
                                    <div class="notif-type"><?php echo htmlspecialchars($n['type'] ?? ''); ?></div>
                                </div>
                                <div class="notif-body">
                                    <h4><?php echo htmlspecialchars($n['title'] ?? ''); ?></h4>
                                    <p><?php echo htmlspecialchars($n['message'] ?? ''); ?></p>
                                    <small><?php echo date('M j, Y g:i A', strtotime($n['created_at'])); ?></small>
                                </div>
                                <div class="notif-actions">
                                    <?php if(!$n['is_read']): ?>
                                        <button class="btn btn-action mark-read" data-id="<?php echo $n['id']; ?>">Mark read</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
        </div>
    </main>

    <script src="js/barangay_dashboard.js"></script>
    <script src="js/barangay_notifications.js"></script>
    <script>
        window.userBarangay = <?php echo json_encode($user_barangay); ?>;
    </script>
</body>
</html>
