<?php
// Shared header/navbar
$current_page = basename($_SERVER['PHP_SELF']);
$user_type = $_SESSION['user_type'] ?? '';
?>
<header class="dashboard-header">
    <div class="header-content">
        <div class="logo">
            <img src="assets/images/trashtrace logo green.png" alt="TrashTrace Logo" class="logo-img">
            <?php if($user_type === 'admin'): ?>
            <span class="portal-label">Admin Portal</span>
            <?php endif; ?>
        </div>
        <nav class="main-nav">
            <ul>
                <?php if($user_type === 'admin'): ?>
                    <li><a href="barangay_dashboard.php" class="nav-link <?php echo $current_page === 'barangay_dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-th-large"></i>
                        <span>Dashboard</span>
                    </a></li>
                    <li><a href="barangay_schedule.php" class="nav-link <?php echo $current_page === 'barangay_schedule.php' ? 'active' : ''; ?>">
                        <i class="far fa-calendar"></i>
                        <span>Schedule</span>
                    </a></li>
                    <li><a href="barangay_applications.php" class="nav-link <?php echo $current_page === 'barangay_applications.php' ? 'active' : ''; ?>">
                        <i class="far fa-file-alt"></i>
                        <span>Applications</span>
                    </a></li>
                    <li><a href="barangay_notifications.php" class="nav-link <?php echo $current_page === 'barangay_notifications.php' ? 'active' : ''; ?>">
                        <i class="far fa-bell"></i>
                        <span>Notifications</span>
                    </a></li>
                    <li><a href="barangay_reports.php" class="nav-link <?php echo $current_page === 'barangay_reports.php' ? 'active' : ''; ?>">
                        <i class="far fa-chart-bar"></i>
                        <span>Reports</span>
                    </a></li>
                <?php else: ?>
                    <li><a href="dashboard.php" class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-th-large"></i>
                        <span>Dashboard</span>
                    </a></li>
                    <li><a href="res_schedule.php" class="nav-link <?php echo $current_page === 'res_schedule.php' ? 'active' : ''; ?>">
                        <i class="far fa-calendar"></i>
                        <span>Schedule</span>
                    </a></li>
                    <li><a href="res_notif.php" class="nav-link <?php echo $current_page === 'res_notif.php' ? 'active' : ''; ?>">
                        <i class="far fa-bell"></i>
                        <span>Notifications</span>
                    </a></li>
                    <li><a href="res_profile.php" class="nav-link <?php echo $current_page === 'res_profile.php' ? 'active' : ''; ?>">
                        <i class="far fa-user"></i>
                        <span>Profile</span>
                    </a></li>
                <?php endif; ?>
            </ul>
        </nav>
        <div class="header-actions">
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
</header>
