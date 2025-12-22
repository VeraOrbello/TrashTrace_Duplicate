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
    <link href="node_modules/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/barangay_reports.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="dashboard-main">
        <div class="container">
            <div class="reports-header-section">
                <h1><i class="fas fa-file-alt"></i> Resident Reports</h1>
                <p class="reports-subtitle">View and manage resident feedback and complaints</p>
            </div>
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

    <script>
        const barangayName = <?php echo json_encode($user_barangay); ?>;
    </script>
    <script src="js/barangay_reports.js"></script>
</body>
</html>
