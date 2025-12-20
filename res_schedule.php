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
$user_city = $_SESSION["city"] ?? '';

if (empty($user_barangay)) {
    $sql = "SELECT barangay, zone, city FROM users WHERE id = :id LIMIT 1";
    if ($stmt = $pdo->prepare($sql)) {
        $stmt->bindParam(":id", $user_id, PDO::PARAM_INT);
        if ($stmt->execute()) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $user_barangay = $row['barangay'] ?? $user_barangay;
                $user_zone = $row['zone'] ?? $user_zone;
                $user_city = $row['city'] ?? $user_city;
                $_SESSION['barangay'] = $user_barangay;
                $_SESSION['zone'] = $user_zone;
                $_SESSION['city'] = $user_city;
            }
        }
        unset($stmt);
    }
}
$user_type = $_SESSION["user_type"] ?? 'user';

$current_month = date('Y-m');
$schedules = [];

$schedules = [];
$barangay_param = mb_strtolower(trim($user_barangay));
$sql = "SELECT * FROM pickup_schedules WHERE LOWER(TRIM(barangay)) = :barangay AND DATE_FORMAT(schedule_date, '%Y-%m') = :current_month ORDER BY schedule_date";
if ($stmt = $pdo->prepare($sql)) {
    $stmt->bindParam(":barangay", $barangay_param, PDO::PARAM_STR);
    $stmt->bindParam(":current_month", $current_month, PDO::PARAM_STR);
    if ($stmt->execute()) {
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pickup Schedule - TrashTrace</title>
    <link rel="stylesheet" href="css/res_schedule.css">
    <link href="node_modules/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body data-is-worker="<?php echo $user_type === 'admin' ? 'true' : 'false'; ?>"
      data-user-id="<?php echo $user_id; ?>"
      data-user-barangay="<?php echo htmlspecialchars($user_barangay); ?>"
      data-user-zone="<?php echo htmlspecialchars($user_zone); ?>">
    <div class="dashboard-container">
        <header class="dashboard-header">
            <div class="header-content">
                <div class="logo">TrashTrace</div>
              <nav>
    <ul>
        <li><a href="dashboard.php" class="nav-link ">Dashboard</a></li>
        <li><a href="res_schedule.php" class="nav-link active">Schedule</a></li>
        <li><a href="res_notif.php" class="nav-link">Notifications</a></li>
        <li><a href="res_profile.php" class="nav-link">Profile</a></li>
        <li class="user-menu">
            <span>Welcome, <?php echo $_SESSION["full_name"]; ?></span>
            <a href="logout.php" class="btn btn-outline">Logout</a>
        </li>
    </ul>
</nav>
            </div>
        </header>

        <main class="schedule-main">
            <div class="container">
                <div class="schedule-header">
                    <h1>Trash Pickup Schedule</h1>
                    <div class="month-navigation">
                        <button id="prev-month" class="nav-btn">&lt; Previous</button>
                        <h2 id="current-month"><?php echo date('F Y'); ?></h2>
                        <button id="next-month" class="nav-btn">Next &gt;</button>
                    </div>
                </div>

                <?php if ($user_type === 'admin'): ?>
                <div class="worker-controls" id="worker-controls">
                    <h3>Worker Controls</h3>
                    <div style="display: flex; gap: 1rem; align-items: center;">
                        <button id="add-schedule-btn" class="btn btn-add">
                            <i class="fas fa-plus"></i> Add Schedule
                        </button>
                        <button id="bulk-add-btn" class="btn btn-add">
                            <i class="fas fa-calendar-plus"></i> Bulk Add
                        </button>
                        <span style="font-size: 0.9rem; color: #666;">
                            <i class="fas fa-user-hard-hat"></i> Barangay Worker Mode
                        </span>
                    </div>
                </div>
                <?php endif; ?>

                <div class="calendar-container">
                    <div class="calendar-header">
                        <div class="day-header">Sun</div>
                        <div class="day-header">Mon</div>
                        <div class="day-header">Tue</div>
                        <div class="day-header">Wed</div>
                        <div class="day-header">Thu</div>
                        <div class="day-header">Fri</div>
                        <div class="day-header">Sat</div>
                    </div>
                    <div class="calendar-body" id="calendar-body">
                    </div>
                </div>

                <div class="schedule-legend">
                    <div class="legend-item">
                        <div class="color-box scheduled"></div>
                        <span>Scheduled</span>
                    </div>
                    <div class="legend-item">
                        <div class="color-box completed"></div>
                        <span>Completed</span>
                    </div>
                    <div class="legend-item">
                        <div class="color-box delayed"></div>
                        <span>Delayed</span>
                    </div>
                    <div class="legend-item">
                        <div class="color-box cancelled"></div>
                        <span>Cancelled</span>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div id="schedule-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Pickup Schedule Details</h3>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body" id="modal-body-content">
            </div>
        </div>
    </div>

    <script>
        const schedules = <?php echo json_encode($schedules); ?>;
        const userBarangay = "<?php echo $user_barangay; ?>";
        const userZone = "<?php echo $user_zone; ?>";
        const userType = "<?php echo $user_type; ?>";
        const userId = <?php echo $user_id; ?>;
    </script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="js/res_schedule.js"></script>
</body>
</html>