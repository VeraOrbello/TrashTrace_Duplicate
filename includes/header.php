<?php
// Shared header/navbar
$current_page = basename($_SERVER['PHP_SELF']);
$user_type = $_SESSION['user_type'] ?? '';
?>
<header class="dashboard-header">
    <div class="header-content">
        <div class="logo">
            <img src="assets/images/trashtrace logo.png" alt="TrashTrace Logo" class="logo-img">
        </div>
        <nav>
            <ul>
                <?php if($user_type === 'admin'): ?>
                    <li><a href="barangay_dashboard.php" class="nav-link <?php echo $current_page === 'barangay_dashboard.php' ? 'active' : ''; ?>"><i class="fa-solid fa-gauge"></i>Dashboard</a></li>
                    <li><a href="barangay_schedule.php" class="nav-link <?php echo $current_page === 'barangay_schedule.php' ? 'active' : ''; ?>"><i class="fa-regular fa-calendar"></i>Schedule</a></li>
                    <li><a href="barangay_applications.php" class="nav-link <?php echo $current_page === 'barangay_applications.php' ? 'active' : ''; ?>"><i class="fa-solid fa-file-lines"></i>Applications</a></li>
                    <li><a href="barangay_notifications.php" class="nav-link <?php echo $current_page === 'barangay_notifications.php' ? 'active' : ''; ?>"><i class="fa-regular fa-bell"></i>Notifications</a></li>
                    <li><a href="barangay_reports.php" class="nav-link <?php echo $current_page === 'barangay_reports.php' ? 'active' : ''; ?>"><i class="fa-regular fa-chart-bar"></i>Reports</a></li>
                <?php else: ?>
                    <li><a href="dashboard.php" class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>"><i class="fa-solid fa-gauge"></i>Dashboard</a></li>
                    <li><a href="res_schedule.php" class="nav-link <?php echo $current_page === 'res_schedule.php' ? 'active' : ''; ?>"><i class="fa-regular fa-calendar"></i>Schedule</a></li>
                    <li><a href="res_notif.php" class="nav-link <?php echo $current_page === 'res_notif.php' ? 'active' : ''; ?>"><i class="fa-regular fa-bell"></i>Notifications</a></li>
                    <li><a href="res_profile.php" class="nav-link <?php echo $current_page === 'res_profile.php' ? 'active' : ''; ?>"><i class="fa-regular fa-user"></i>Profile</a></li>
                <?php endif; ?>

                <li class="user-menu">
                    <a href="logout.php" class="btn btn-outline">Logout</a>
                </li>
            </ul>
        </nav>
    </div>
</header>
