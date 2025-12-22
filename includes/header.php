 <?php
// Shared header/navbar
$current_page = basename($_SERVER['PHP_SELF']);
$user_type = $_SESSION['user_type'] ?? '';
$current_dir = dirname($_SERVER['PHP_SELF']);
$is_driver_page = strpos($current_dir, '/driver') !== false || $current_page === 'driver_dashboard.php';
$base_path = '/TrashTrace_Duplicate/';
?>
<header class="dashboard-header">
    <div class="grid-background-nav"></div>
    
    <div class="header-content">
        <?php if($user_type === 'driver'): ?>
            <a href="../driver_dashboard.php" class="logo">
                <i class="fas fa-recycle"></i>
                <span>TrashTrace Driver</span>
            </a>
            <button class="mobile-menu-toggle" id="mobileMenuToggle">
                <i class="fas fa-bars"></i>
            </button>
            <nav id="mainNav">
                <div class="nav-container">
                    <ul>
        <?php else: ?>
            <div class="logo">
                <img src="assets/images/trashtrace logo green.png" alt="TrashTrace Logo" class="logo-img">
                <?php if($user_type === 'admin'): ?>
                <span class="portal-label">Admin Portal</span>
                <?php endif; ?>
            </div>
            <nav class="main-nav">
                <ul>
        <?php endif; ?>
                <?php if($user_type === 'admin'): ?>
                    <li><a href="TrashTrace_Duplicate/driver_dashboard.php" class="nav-link <?php echo $current_page === 'barangay_dashboard.php' ? 'active' : ''; ?>">
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
                <?php elseif($user_type === 'driver'): ?>
                    <li><a href="/TrashTrace_Duplicate/driver_dashboard.php" class="nav-link <?php echo $current_page === 'driver_dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a></li>
                    <li><a href="/TrashTrace_Duplicate/driver/assignments.php" class="nav-link <?php echo $current_page === 'assignments.php' ? 'active' : ''; ?>">
                        <i class="fas fa-tasks"></i>
                        <span>Assignments</span>
                    </a></li>
                    <li><a href="/TrashTrace_Duplicate/driver/routes.php" class="nav-link <?php echo $current_page === 'routes.php' ? 'active' : ''; ?>">
                        <i class="fas fa-route"></i>
                        <span>Routes</span>
                    </a></li>
                    <li><a href="/TrashTrace_Duplicate/driver/collections.php" class="nav-link <?php echo $current_page === 'collections.php' ? 'active' : ''; ?>">
                        <i class="fas fa-trash"></i>
                        <span>Collections</span>
                    </a></li>
                    <li><a href="/TrashTrace_Duplicate/driver/earnings.php" class="nav-link <?php echo $current_page === 'earnings.php' ? 'active' : ''; ?>">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Earnings</span>
                    </a></li>
                    <li><a href="/TrashTrace_Duplicate/driver/profile.php" class="nav-link <?php echo $current_page === 'profile.php' ? 'active' : ''; ?>">
                        <i class="fas fa-user"></i>
                        <span>Profile</span>
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
