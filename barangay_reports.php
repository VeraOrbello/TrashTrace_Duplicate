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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Barangay Reports - TrashTrace</title>
    <link rel="stylesheet" href="css/barangay_dashboard.css">
    <link rel="stylesheet" href="css/barangay_reports.css">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <div class="header-content">
                <div class="logo">TrashTrace</div>
                <nav>
                    <ul>
                        <li><a href="barangay_dashboard.php" class="nav-link">Dashboard</a></li>
                        <li><a href="barangay_schedule.php" class="nav-link">Schedule</a></li>
                        <li><a href="barangay_applications.php" class="nav-link">Applications</a></li>
                        <li><a href="barangay_notifications.php" class="nav-link">Notifications</a></li>
                        <li><a href="barangay_reports.php" class="nav-link active">Reports</a></li>
                        <li class="user-menu"><span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?></span><a href="logout.php" class="btn btn-outline">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </header>

        <main class="dashboard-main page-transition">
            <div class="container">
                <h1 class="welcome-title">Resident Reports / Feedback</h1>
                <div class="reports-toolbar" style="display:flex;gap:12px;align-items:center;margin-bottom:12px;">
                    <input id="report-search" type="text" placeholder="Search by name, address or message..." style="flex:1;padding:8px;border-radius:6px;border:1px solid #e6efe9" />
                    <select id="report-filter-category" style="padding:8px;border-radius:6px;border:1px solid #e6efe9">
                        <option value="">All Categories</option>
                        <option value="Complaint">Complaint</option>
                        <option value="Feedback">Feedback</option>
                        <option value="Missed Pickup">Missed Pickup</option>
                        <option value="Other">Other</option>
                    </select>
                    <button id="refresh-reports" class="btn">Refresh</button>
                </div>
                <div id="reports-list" class="reports-list">
                    <p>Loading reports...</p>
                </div>
            </div>
        </main>
    </div>

    <script>
        const barangayName = <?php echo json_encode($user_barangay); ?>;
    </script>
    <script src="js/barangay_reports.js"></script>
</body>
</html>
