<?php
require_once "../config.php";

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: ../login.php");
    exit;
}

if($_SESSION["user_type"] !== 'driver'){
    header("location: ../dashboard.php");
    exit;
}

$driver_id = $_SESSION["id"];
$driver_name = $_SESSION["full_name"];

// Get driver's assigned barangay/zone from user profile
$driver_barangay = "";
$sql = "SELECT barangay FROM users WHERE id = ?";
if($stmt = mysqli_prepare($link, $sql)){
    mysqli_stmt_bind_param($stmt, "i", $driver_id);
    if(mysqli_stmt_execute($stmt)){
        mysqli_stmt_bind_result($stmt, $driver_barangay);
        mysqli_stmt_fetch($stmt);
    }
    mysqli_stmt_close($stmt);
}

// If driver doesn't have barangay assigned, use default or show error
if(empty($driver_barangay)){
    // Try to get from driver details table if exists
    $sql = "SELECT zone_name FROM driver_details WHERE driver_id = ?";
    if($stmt = mysqli_prepare($link, $sql)){
        mysqli_stmt_bind_param($stmt, "i", $driver_id);
        if(mysqli_stmt_execute($stmt)){
            mysqli_stmt_bind_result($stmt, $driver_barangay);
            mysqli_stmt_fetch($stmt);
        }
        mysqli_stmt_close($stmt);
    }
}

// Get current date
$current_date = date('Y-m-d');
$current_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$today_start = date('Y-m-d 00:00:00');
$today_end = date('Y-m-d 23:59:59');

// Get assignments from pickup_schedules table (admin-managed calendar)
$assignments = [];
$current_assignment = null;
$today_assignments = [];
$upcoming_assignments = [];
$month_schedules = [];

try {
    // Check if pickup_schedules table exists
    $table_check = mysqli_query($link, "SHOW TABLES LIKE 'pickup_schedules'");
    
    if(mysqli_num_rows($table_check) > 0 && !empty($driver_barangay)) {
        // Fetch today's assignments from pickup_schedules
        $sql = "SELECT 
                    ps.*,
                    'pickup_schedule' as assignment_type,
                    DATE(ps.schedule_date) as assignment_date,
                    '08:00:00' as start_time,  // Default times since pickup_schedules doesn't have times
                    '17:00:00' as end_time,
                    'scheduled' as status,
                    CONCAT('Zone - ', ps.barangay) as zone_name,
                    ps.barangay as area,
                    '150-200 kg' as estimated_weight,  // Default estimate
                    15 as stops,  // Default stops
                    'Truck #TR-001' as vehicle,  // Default vehicle
                    0 as progress
                FROM pickup_schedules ps
                WHERE ps.barangay = ? 
                AND DATE(ps.schedule_date) >= CURDATE()
                AND ps.status IN ('Scheduled', 'scheduled')
                ORDER BY ps.schedule_date ASC";
        
        if($stmt = mysqli_prepare($link, $sql)){
            mysqli_stmt_bind_param($stmt, "s", $driver_barangay);
            if(mysqli_stmt_execute($stmt)){
                $result = mysqli_stmt_get_result($stmt);
                while($row = mysqli_fetch_assoc($result)){
                    $assignments[] = $row;
                    
                    // Check if assignment is for today
                    if(date('Y-m-d', strtotime($row['schedule_date'])) == $current_date) {
                        // Check if assignment should be in progress (current time between 8AM-5PM)
                        $current_hour = date('H');
                        if($current_hour >= 8 && $current_hour < 17) {
                            $row['status'] = 'in_progress';
                            $current_assignment = $row;
                        } else {
                            $today_assignments[] = $row;
                        }
                    } else if(date('Y-m-d', strtotime($row['schedule_date'])) > $current_date) {
                        $upcoming_assignments[] = $row;
                    }
                }
            }
            mysqli_stmt_close($stmt);
        }
        
        // Fetch schedules for the current month for calendar view
        $month_start = date('Y-m-01', strtotime($current_month));
        $month_end = date('Y-m-t', strtotime($current_month));
        
        $sql = "SELECT 
                    ps.*,
                    DATE(ps.schedule_date) as schedule_date_only,
                    ps.status
                FROM pickup_schedules ps
                WHERE ps.barangay = ? 
                AND DATE(ps.schedule_date) BETWEEN ? AND ?
                ORDER BY ps.schedule_date ASC";
        
        if($stmt = mysqli_prepare($link, $sql)){
            mysqli_stmt_bind_param($stmt, "sss", $driver_barangay, $month_start, $month_end);
            if(mysqli_stmt_execute($stmt)){
                $result = mysqli_stmt_get_result($stmt);
                while($row = mysqli_fetch_assoc($result)){
                    $month_schedules[] = $row;
                }
            }
            mysqli_stmt_close($stmt);
        }
        
        // If no current assignment found, try to get from assignments table as fallback
        if(empty($current_assignment)) {
            $sql = "SELECT * FROM assignments 
                    WHERE driver_id = ? 
                    AND DATE(assignment_date) = ? 
                    AND (status = 'in_progress' OR status = 'assigned')
                    ORDER BY start_time ASC
                    LIMIT 1";
            
            if($stmt = mysqli_prepare($link, $sql)){
                mysqli_stmt_bind_param($stmt, "is", $driver_id, $current_date);
                if(mysqli_stmt_execute($stmt)){
                    $result = mysqli_stmt_get_result($stmt);
                    if($row = mysqli_fetch_assoc($result)){
                        $current_assignment = $row;
                    }
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
    
} catch(Exception $e) {
    error_log("Assignments error: " . $e->getMessage());
    // Use sample data if database error
    $assignments = generateSampleAssignments($driver_barangay);
    $current_assignment = !empty($assignments) ? $assignments[0] : null;
    $today_assignments = array_slice($assignments, 1, 2);
    $upcoming_assignments = array_slice($assignments, 3, 4);
    $month_schedules = generateMonthSchedules($current_month, $driver_barangay);
}

function generateSampleAssignments($barangay = "") {
    $sample_assignments = [];
    
    if(empty($barangay)) {
        $barangay = "Lahug";
    }
    
    // Current assignment (in progress)
    $sample_assignments[] = [
        'id' => 1,
        'schedule_date' => date('Y-m-d'),
        'assignment_type' => 'pickup_schedule',
        'zone_name' => 'Zone A - ' . $barangay . ' Area',
        'assignment_date' => date('Y-m-d'),
        'start_time' => '08:00:00',
        'end_time' => '12:00:00',
        'status' => 'in_progress',
        'estimated_weight' => '150-200 kg',
        'stops' => 15,
        'vehicle' => 'Truck #TR-001',
        'area' => 'Barangay ' . $barangay . ', Cebu City',
        'progress' => 65,
        'barangay' => $barangay,
        'notes' => 'Regular collection route'
    ];
    
    // Today's assignments
    $sample_assignments[] = [
        'id' => 2,
        'schedule_date' => date('Y-m-d'),
        'assignment_type' => 'pickup_schedule',
        'zone_name' => 'Zone B - ' . $barangay . ' Residential',
        'assignment_date' => date('Y-m-d'),
        'start_time' => '13:00:00',
        'end_time' => '17:00:00',
        'status' => 'scheduled',
        'estimated_weight' => '100-150 kg',
        'stops' => 12,
        'vehicle' => 'Truck #TR-001',
        'area' => 'Barangay ' . $barangay . ', Cebu City',
        'progress' => 0,
        'barangay' => $barangay,
        'notes' => 'Residential area collection'
    ];
    
    // Upcoming assignments
    for($i = 3; $i <= 6; $i++) {
        $days_forward = $i - 2;
        $sample_assignments[] = [
            'id' => $i,
            'schedule_date' => date('Y-m-d', strtotime("+{$days_forward} days")),
            'assignment_type' => 'pickup_schedule',
            'zone_name' => 'Zone ' . chr(64 + $i) . ' - ' . $barangay . ' Area',
            'assignment_date' => date('Y-m-d', strtotime("+{$days_forward} days")),
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'status' => 'scheduled',
            'estimated_weight' => rand(50, 200) . '-' . rand(100, 250) . ' kg',
            'stops' => rand(5, 20),
            'vehicle' => 'Truck #TR-' . str_pad(rand(1, 10), 3, '0', STR_PAD_LEFT),
            'area' => 'Barangay ' . $barangay . ', Cebu City',
            'progress' => 0,
            'barangay' => $barangay,
            'notes' => 'Scheduled pickup for Zone ' . chr(64 + $i)
        ];
    }
    
    return $sample_assignments;
}

function generateMonthSchedules($month, $barangay = "") {
    if(empty($barangay)) {
        $barangay = "Lahug";
    }
    
    $schedules = [];
    $start_date = date('Y-m-01', strtotime($month));
    $end_date = date('Y-m-t', strtotime($month));
    $current_date = date('Y-m-d');
    
    $current = strtotime($start_date);
    $end = strtotime($end_date);
    
    while($current <= $end) {
        $date = date('Y-m-d', $current);
        
        // Add some random schedules (Mon, Wed, Fri)
        $day_of_week = date('N', $current);
        if(in_array($day_of_week, [1, 3, 5])) { // Monday, Wednesday, Friday
            $schedules[] = [
                'id' => rand(100, 999),
                'schedule_date' => $date,
                'schedule_date_only' => $date,
                'status' => ($date == $current_date) ? 'in_progress' : 
                           (($date < $current_date) ? 'completed' : 'scheduled'),
                'barangay' => $barangay,
                'notes' => 'Regular pickup schedule'
            ];
        }
        
        $current = strtotime('+1 day', $current);
    }
    
    return $schedules;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignments - TrashTrace Driver</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Consolidated CSS -->
    <link rel="stylesheet" href="../css/driver/master-styles.css">
    
    <style>
        .driver-barangay-info {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            border-left: 4px solid #2196f3;
        }
        
        .driver-barangay-info i {
            font-size: 1.5rem;
            color: #1976d2;
        }
        
        .barangay-details h4 {
            margin: 0 0 5px 0;
            color: #1976d2;
            font-weight: 600;
        }
        
        .barangay-details p {
            margin: 0;
            color: #555;
            font-size: 0.9rem;
        }
        
        .assignment-source {
            display: inline-block;
            padding: 3px 8px;
            background: #e8f5e9;
            color: #2e7d32;
            border-radius: 12px;
            font-size: 0.75rem;
            margin-left: 8px;
            font-weight: 500;
        }
        
        .assignment-source.pickup {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-in_progress {
            background: rgba(33, 150, 243, 0.1);
            color: #1976d2;
            border: 1px solid rgba(33, 150, 243, 0.3);
        }
        
        .status-scheduled {
            background: rgba(255, 193, 7, 0.1);
            color: #ff9800;
            border: 1px solid rgba(255, 193, 7, 0.3);
        }
        
        .status-completed {
            background: rgba(76, 175, 80, 0.1);
            color: #2e7d32;
            border: 1px solid rgba(76, 175, 80, 0.3);
        }
        
        .status-cancelled {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
            border: 1px solid rgba(244, 67, 54, 0.3);
        }
        
        .status-delayed {
            background: rgba(255, 87, 34, 0.1);
            color: #ff5722;
            border: 1px solid rgba(255, 87, 34, 0.3);
        }
        
        /* Calendar Styles */
        .calendar-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .calendar-header {
            padding: 24px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, rgba(248, 253, 249, 0.8), rgba(240, 255, 244, 0.6));
        }
        
        .calendar-header h3 {
            margin: 0;
            color: #2c3e50;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
        }
        
        .month-navigation {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .nav-btn {
            background: white;
            border: 1px solid #e8f5e9;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #666;
            transition: all 0.3s ease;
        }
        
        .nav-btn:hover {
            background: #f0f9f4;
            border-color: #c8e6c9;
            color: #2e7d32;
        }
        
        .current-month {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
            min-width: 180px;
            text-align: center;
        }
        
        .calendar-container {
            padding: 24px;
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background: #f0f7f3;
            border: 1px solid #f0f7f3;
            border-radius: 12px;
            overflow: hidden;
        }
        
        .calendar-day-header {
            background: #e8f5e9;
            padding: 15px 10px;
            text-align: center;
            font-weight: 600;
            color: #2e7d32;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .calendar-day {
            background: white;
            min-height: 100px;
            padding: 10px;
            position: relative;
            transition: all 0.3s ease;
        }
        
        .calendar-day:hover {
            background: #f8fdf9;
            transform: scale(1.02);
            z-index: 1;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .calendar-day.other-month {
            background: #f9f9f9;
            color: #ccc;
        }
        
        .calendar-day.today {
            background: #e8f5e9;
        }
        
        .day-number {
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 1.1rem;
        }
        
        .day-number.today {
            color: #2e7d32;
            background: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .schedule-indicator {
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 4px;
            margin-top: 3px;
            color: white;
            font-weight: 500;
        }
        
        .schedule-indicator.scheduled {
            background: #ff9800;
        }
        
        .schedule-indicator.in_progress {
            background: #2196f3;
        }
        
        .schedule-indicator.completed {
            background: #4caf50;
        }
        
        .schedule-indicator.cancelled {
            background: #f44336;
        }
        
        .schedule-indicator.delayed {
            background: #ff5722;
        }
        
        .calendar-legend {
            display: flex;
            justify-content: center;
            gap: 20px;
            padding: 20px;
            background: #f8fdf9;
            border-top: 1px solid #e8f5e9;
            flex-wrap: wrap;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            color: #666;
        }
        
        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 3px;
        }
        
        .legend-color.scheduled { background: #ff9800; }
        .legend-color.in_progress { background: #2196f3; }
        .legend-color.completed { background: #4caf50; }
        .legend-color.cancelled { background: #f44336; }
        .legend-color.delayed { background: #ff5722; }
        
        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }
        
        .modal-content {
            background: white;
            border-radius: 20px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
            animation: slideUp 0.3s ease;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-header h3 {
            margin: 0;
            color: #2e7d32;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #999;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }
        
        .close-modal:hover {
            background: rgba(0, 0, 0, 0.05);
            color: #f44336;
        }
        
        .day-schedules-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 15px;
        }
        
        .day-schedule-item {
            padding: 12px;
            background: #f8fdf9;
            border-radius: 8px;
            border-left: 4px solid #4caf50;
        }
        
        .day-schedule-item.scheduled { border-color: #ff9800; }
        .day-schedule-item.in_progress { border-color: #2196f3; }
        .day-schedule-item.completed { border-color: #4caf50; }
        .day-schedule-item.cancelled { border-color: #f44336; }
        .day-schedule-item.delayed { border-color: #ff5722; }
        
        .day-schedule-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }
        
        .day-schedule-time {
            font-size: 0.85rem;
            color: #666;
        }
        
        .day-schedule-notes {
            font-size: 0.9rem;
            color: #555;
            margin-top: 5px;
        }
        
        .no-schedules {
            text-align: center;
            padding: 30px;
            color: #999;
        }
        
        .no-schedules i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #e0e0e0;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @media (max-width: 768px) {
            .calendar-grid {
                grid-template-columns: repeat(7, 1fr);
            }
            
            .calendar-day {
                min-height: 80px;
                padding: 5px;
                font-size: 0.8rem;
            }
            
            .day-number {
                font-size: 0.9rem;
            }
            
            .schedule-indicator {
                font-size: 0.6rem;
                padding: 1px 4px;
            }
            
            .calendar-legend {
                flex-direction: column;
                align-items: center;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <div class="grid-background-nav"></div>
            
            <div class="header-content">
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
                            <li><a href="../driver_dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                            <li><a href="assignments.php" class="nav-link active"><i class="fas fa-tasks"></i> <span>Assignments</span></a></li>
                            <li><a href="routes.php" class="nav-link"><i class="fas fa-route"></i> <span>Routes</span></a></li>
                            <li><a href="collections.php" class="nav-link"><i class="fas fa-trash"></i> <span>Collections</span></a></li>
                            <li><a href="earnings.php" class="nav-link"><i class="fas fa-money-bill-wave"></i> <span>Earnings</span></a></li>
                          
                            <li><a href="profile.php" class="nav-link"><i class="fas fa-user"></i> <span>Profile</span></a></li>
                        </ul>
                    </div>
                </nav>
                
                <div class="user-menu">
                    <div class="user-info" onclick="window.location.href='profile.php'">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($driver_name, 0, 1)); ?>
                        </div>
                        <div class="user-details">
                            <span class="user-name"><?php echo htmlspecialchars($driver_name); ?></span>
                            <span class="user-id">ID: #<?php echo str_pad($driver_id, 4, '0', STR_PAD_LEFT); ?></span>
                        </div>
                    </div>
                    <a href="../logout.php" class="btn-logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </header>
        
        <main class="dashboard-main">
            <div class="container">
                <div class="page-header">
                    <h1 class="page-title">
                        <i class="fas fa-tasks"></i>
                        Assignments
                    </h1>
                    <p class="page-subtitle">Manage your daily collection tasks and track progress.</p>
                </div>
                
                <!-- Driver Barangay Information -->
                <?php if(!empty($driver_barangay)): ?>
                    <div class="driver-barangay-info">
                        <i class="fas fa-map-marker-alt"></i>
                        <div class="barangay-details">
                            <h4>Your Assigned Area</h4>
                            <p>Barangay: <?php echo htmlspecialchars($driver_barangay); ?> • Sync with Admin Calendar</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="driver-barangay-info" style="background: linear-gradient(135deg, #ffebee, #ffcdd2); border-color: #f44336;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div class="barangay-details">
                            <h4>Area Not Assigned</h4>
                            <p>Please contact admin to assign you a barangay/zone</p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Calendar Section -->
                <div class="calendar-card">
                    <div class="calendar-header">
                        <h3><i class="fas fa-calendar-alt"></i> Schedule Calendar</h3>
                        <div class="month-navigation">
                            <button class="nav-btn" id="prevMonth">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <div class="current-month" id="currentMonth">
                                <?php echo date('F Y', strtotime($current_month)); ?>
                            </div>
                            <button class="nav-btn" id="nextMonth">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="calendar-container">
                        <div class="calendar-grid" id="calendarGrid">
                            <!-- Calendar will be generated by JavaScript -->
                        </div>
                    </div>
                    
                    <div class="calendar-legend">
                        <div class="legend-item">
                            <div class="legend-color scheduled"></div>
                            <span>Scheduled</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color in_progress"></div>
                            <span>In Progress</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color completed"></div>
                            <span>Completed</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color cancelled"></div>
                            <span>Cancelled</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color delayed"></div>
                            <span>Delayed</span>
                        </div>
                    </div>
                </div>
                
                <?php if($current_assignment): ?>
                    <!-- Current Assignment Card -->
                    <div class="dashboard-card" style="background: linear-gradient(135deg, rgba(33, 150, 243, 0.1), rgba(33, 150, 243, 0.05)); border-color: rgba(33, 150, 243, 0.2); margin-bottom: 30px;">
                        <div class="card-header">
                            <h3><i class="fas fa-play-circle" style="color: #2196F3;"></i> Current Assignment</h3>
                            <div>
                                <span class="status-badge status-<?php echo $current_assignment['status']; ?>">
                                    <?php 
                                    if($current_assignment['status'] == 'in_progress') echo 'In Progress';
                                    elseif($current_assignment['status'] == 'scheduled') echo 'Scheduled';
                                    else echo ucfirst($current_assignment['status']);
                                    ?>
                                </span>
                                <?php if(isset($current_assignment['assignment_type']) && $current_assignment['assignment_type'] == 'pickup_schedule'): ?>
                                    <span class="assignment-source pickup">From Admin Calendar</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <div>
                                    <h4><?php echo htmlspecialchars($current_assignment['zone_name']); ?></h4>
                                    <p><?php echo htmlspecialchars($current_assignment['area']); ?></p>
                                    <small>Zone Area</small>
                                </div>
                            </div>
                            
                            <div class="detail-item">
                                <i class="fas fa-clock"></i>
                                <div>
                                    <h4><?php echo date('h:i A', strtotime($current_assignment['start_time'])); ?> - <?php echo date('h:i A', strtotime($current_assignment['end_time'])); ?></h4>
                                    <p><?php echo date('F j, Y', strtotime($current_assignment['assignment_date'])); ?></p>
                                    <small>Schedule</small>
                                </div>
                            </div>
                            
                            <div class="detail-item">
                                <i class="fas fa-weight-hanging"></i>
                                <div>
                                    <h4><?php echo $current_assignment['estimated_weight']; ?></h4>
                                    <p><?php echo $current_assignment['stops']; ?> Stops</p>
                                    <small>Estimated Load</small>
                                </div>
                            </div>
                            
                            <div class="detail-item">
                                <i class="fas fa-truck"></i>
                                <div>
                                    <h4><?php echo $current_assignment['vehicle']; ?></h4>
                                    <p>Assigned Vehicle</p>
                                    <small>Vehicle ID</small>
                                </div>
                            </div>
                        </div>
                        
                        <?php if(isset($current_assignment['notes']) && !empty($current_assignment['notes'])): ?>
                            <div style="margin: 20px 0; padding: 15px; background: rgba(255, 255, 255, 0.6); border-radius: 8px; border-left: 3px solid #2196f3;">
                                <strong><i class="fas fa-sticky-note"></i> Admin Notes:</strong>
                                <p style="margin: 5px 0 0 0; color: #555;"><?php echo htmlspecialchars($current_assignment['notes']); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Progress Bar -->
                        <div style="margin-top: 20px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <span style="color: #555; font-weight: 500;">Collection Progress</span>
                                <span style="color: #2196F3; font-weight: 600;"><?php echo $current_assignment['progress']; ?>%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $current_assignment['progress']; ?>%"></div>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 15px; margin-top: 25px;">
                            <button class="btn btn-complete" id="completeAssignment">
                                <i class="fas fa-check-circle"></i> Complete Assignment
                            </button>
                            <button class="btn btn-secondary" id="viewRoute" 
                                    onclick="window.location.href='routes.php?assignment_id=<?php echo $current_assignment['id']; ?>'">
                                <i class="fas fa-route"></i> View Route
                            </button>
                            <button class="btn btn-outline" id="reportIssue">
                                <i class="fas fa-exclamation-triangle"></i> Report Issue
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- No Current Assignment -->
                    <div class="no-assignment" style="text-align: center; padding: 50px 30px; background: white; border-radius: 16px; margin-bottom: 30px;">
                        <i class="fas fa-calendar-check" style="font-size: 64px; color: #e0e0e0; margin-bottom: 20px;"></i>
                        <h3 style="color: #555; margin-bottom: 10px;">No Active Assignment</h3>
                        <p style="color: #888; margin-bottom: 25px;">
                            <?php if(empty($driver_barangay)): ?>
                                You don't have a barangay assigned. Please contact admin.
                            <?php else: ?>
                                You don't have any active assignments for today in Barangay <?php echo htmlspecialchars($driver_barangay); ?>.
                            <?php endif; ?>
                        </p>
                        <button class="btn btn-primary" id="refreshAssignments">
                            <i class="fas fa-sync-alt"></i> Refresh Assignments
                        </button>
                    </div>
                <?php endif; ?>
                
                <div class="dashboard-grid">
                    <!-- Today's Assignments -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3><i class="fas fa-calendar-day"></i> Today's Assignments</h3>
                            <span class="badge"><?php echo count($today_assignments); ?></span>
                        </div>
                        
                        <?php if(!empty($today_assignments)): ?>
                            <div class="assignments-list">
                                <?php foreach($today_assignments as $assignment): ?>
                                    <div class="assignment-item">
                                        <div class="assignment-date">
                                            <span class="day"><?php echo date('h:i', strtotime($assignment['start_time'])); ?></span>
                                            <span class="month"><?php echo date('A', strtotime($assignment['start_time'])); ?></span>
                                        </div>
                                        <div class="assignment-info">
                                            <h4><?php echo htmlspecialchars($assignment['zone_name']); ?></h4>
                                            <p class="time">
                                                <i class="far fa-clock"></i>
                                                <?php echo date('h:i A', strtotime($assignment['start_time'])); ?> - <?php echo date('h:i A', strtotime($assignment['end_time'])); ?>
                                            </p>
                                            <p class="progress">
                                                <i class="fas fa-weight-hanging"></i>
                                                <?php echo $assignment['estimated_weight']; ?> • <?php echo $assignment['stops']; ?> stops
                                            </p>
                                            <?php if(isset($assignment['assignment_type']) && $assignment['assignment_type'] == 'pickup_schedule'): ?>
                                                <small style="color: #1976d2; font-size: 0.75rem;">
                                                    <i class="fas fa-calendar-alt"></i> From Admin Schedule
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="assignment-actions">
                                            <?php 
                                            $current_time = time();
                                            $assignment_time = strtotime($assignment['start_time']);
                                            if($assignment_time <= $current_time && $assignment['status'] == 'scheduled'): ?>
                                                <button class="btn btn-start start-assignment" data-id="<?php echo $assignment['id']; ?>" data-type="<?php echo isset($assignment['assignment_type']) ? $assignment['assignment_type'] : 'assignment'; ?>">
                                                    <i class="fas fa-play"></i> Start
                                                </button>
                                            <?php elseif($assignment_time > $current_time): ?>
                                                <button class="btn btn-secondary" disabled>
                                                    <i class="fas fa-clock"></i> Upcoming
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-check-circle"></i>
                                <p>No additional assignments scheduled for today</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Upcoming Assignments -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3><i class="fas fa-calendar-alt"></i> Upcoming Assignments</h3>
                            <span class="badge"><?php echo count($upcoming_assignments); ?></span>
                        </div>
                        
                        <?php if(!empty($upcoming_assignments)): ?>
                            <div class="assignments-list">
                                <?php foreach($upcoming_assignments as $assignment): ?>
                                    <div class="assignment-item">
                                        <div class="assignment-date">
                                            <span class="day"><?php echo date('d', strtotime($assignment['assignment_date'])); ?></span>
                                            <span class="month"><?php echo date('M', strtotime($assignment['assignment_date'])); ?></span>
                                        </div>
                                        <div class="assignment-info">
                                            <h4><?php echo htmlspecialchars($assignment['zone_name']); ?></h4>
                                            <p class="time">
                                                <i class="far fa-clock"></i>
                                                <?php echo date('h:i A', strtotime($assignment['start_time'])); ?>
                                            </p>
                                            <p class="progress">
                                                <i class="fas fa-weight-hanging"></i>
                                                <?php echo $assignment['estimated_weight']; ?>
                                            </p>
                                            <?php if(isset($assignment['assignment_type']) && $assignment['assignment_type'] == 'pickup_schedule'): ?>
                                                <small style="color: #1976d2; font-size: 0.75rem;">
                                                    <i class="fas fa-calendar-alt"></i> From Admin Schedule
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="assignment-actions">
                                            <button class="btn btn-outline view-details" 
                                                    data-id="<?php echo $assignment['id']; ?>" 
                                                    data-type="<?php echo isset($assignment['assignment_type']) ? $assignment['assignment_type'] : 'assignment'; ?>">
                                                <i class="fas fa-eye"></i> Details
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-plus"></i>
                                <p>No upcoming assignments scheduled</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Assignment Statistics -->
                <div class="stats-grid" style="margin-top: 30px;">
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #4CAF50, #2E7D32);">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stat-info">
                                <h3>
                                    <?php 
                                    $completed_count = count(array_filter($assignments, function($a) { 
                                        return isset($a['status']) && ($a['status'] == 'completed' || $a['status'] == 'Completed'); 
                                    }));
                                    echo $completed_count;
                                    ?>
                                </h3>
                                <p>Completed</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #2196F3, #0D47A1);">
                                <i class="fas fa-play-circle"></i>
                            </div>
                            <div class="stat-info">
                                <h3>
                                    <?php 
                                    $in_progress_count = count(array_filter($assignments, function($a) { 
                                        return isset($a['status']) && ($a['status'] == 'in_progress' || $a['status'] == 'In Progress'); 
                                    }));
                                    echo $in_progress_count;
                                    ?>
                                </h3>
                                <p>In Progress</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #FF9800, #F57C00);">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo count($today_assignments) + ($current_assignment ? 1 : 0); ?></h3>
                                <p>Scheduled Today</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #9C27B0, #6A1B9A);">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo count($upcoming_assignments); ?></h3>
                                <p>Upcoming</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        
        <!-- Calendar Day Details Modal -->
        <div class="modal-overlay" id="calendarModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-calendar-day"></i> <span id="modalDate">Date</span></h3>
                    <button class="close-modal" id="closeCalendarModal">×</button>
                </div>
                <div class="modal-body" id="modalBody">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
        
        <!-- Assignment Details Modal -->
        <div class="modal" id="assignmentModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
            <div style="background: white; border-radius: 16px; padding: 30px; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="margin: 0; color: #2e7d32;">Assignment Details</h3>
                    <button id="closeModal" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #999;">×</button>
                </div>
                <div id="modalContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Calendar data
            const schedules = <?php echo json_encode($month_schedules); ?>;
            const currentMonth = "<?php echo $current_month; ?>";
            const driverBarangay = "<?php echo htmlspecialchars($driver_barangay); ?>";
            
            // Calendar elements
            const calendarGrid = document.getElementById('calendarGrid');
            const currentMonthElement = document.getElementById('currentMonth');
            const prevMonthBtn = document.getElementById('prevMonth');
            const nextMonthBtn = document.getElementById('nextMonth');
            const calendarModal = document.getElementById('calendarModal');
            const closeCalendarModal = document.getElementById('closeCalendarModal');
            const modalDateElement = document.getElementById('modalDate');
            const modalBody = document.getElementById('modalBody');
            
            // Initialize calendar
            renderCalendar(currentMonth);
            
            // Calendar navigation
            prevMonthBtn.addEventListener('click', function() {
                const newMonth = new Date(currentMonth + '-01');
                newMonth.setMonth(newMonth.getMonth() - 1);
                const newMonthStr = newMonth.getFullYear() + '-' + String(newMonth.getMonth() + 1).padStart(2, '0');
                window.location.href = 'assignments.php?month=' + newMonthStr;
            });
            
            nextMonthBtn.addEventListener('click', function() {
                const newMonth = new Date(currentMonth + '-01');
                newMonth.setMonth(newMonth.getMonth() + 1);
                const newMonthStr = newMonth.getFullYear() + '-' + String(newMonth.getMonth() + 1).padStart(2, '0');
                window.location.href = 'assignments.php?month=' + newMonthStr;
            });
            
            // Close calendar modal
            closeCalendarModal.addEventListener('click', function() {
                calendarModal.style.display = 'none';
            });
            
            calendarModal.addEventListener('click', function(e) {
                if(e.target === calendarModal) {
                    calendarModal.style.display = 'none';
                }
            });
            
            function renderCalendar(month) {
                const year = parseInt(month.split('-')[0]);
                const monthNum = parseInt(month.split('-')[1]) - 1;
                const firstDay = new Date(year, monthNum, 1);
                const lastDay = new Date(year, monthNum + 1, 0);
                const startingDay = firstDay.getDay();
                const totalDays = lastDay.getDate();
                const today = new Date();
                
                // Set current month display
                currentMonthElement.textContent = firstDay.toLocaleDateString('en-US', { 
                    month: 'long', 
                    year: 'numeric' 
                });
                
                // Clear calendar grid
                calendarGrid.innerHTML = '';
                
                // Add day headers
                const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                days.forEach(day => {
                    const dayHeader = document.createElement('div');
                    dayHeader.className = 'calendar-day-header';
                    dayHeader.textContent = day;
                    calendarGrid.appendChild(dayHeader);
                });
                
                // Add empty cells for days before the first day of month
                for(let i = 0; i < startingDay; i++) {
                    const emptyDay = document.createElement('div');
                    emptyDay.className = 'calendar-day other-month';
                    calendarGrid.appendChild(emptyDay);
                }
                
                // Add days of the month
                for(let day = 1; day <= totalDays; day++) {
                    const date = new Date(year, monthNum, day);
                    const dateStr = date.getFullYear() + '-' + 
                                  String(date.getMonth() + 1).padStart(2, '0') + '-' + 
                                  String(day).padStart(2, '0');
                    
                    const dayElement = document.createElement('div');
                    dayElement.className = 'calendar-day';
                    
                    // Check if today
                    if(date.toDateString() === today.toDateString()) {
                        dayElement.classList.add('today');
                    }
                    
                    // Day number
                    const dayNumber = document.createElement('div');
                    dayNumber.className = 'day-number';
                    if(date.toDateString() === today.toDateString()) {
                        dayNumber.classList.add('today');
                    }
                    dayNumber.textContent = day;
                    dayElement.appendChild(dayNumber);
                    
                    // Find schedules for this day
                    const daySchedules = schedules.filter(schedule => 
                        schedule.schedule_date_only === dateStr
                    );
                    
                    // Add schedule indicators
                    daySchedules.forEach(schedule => {
                        const indicator = document.createElement('div');
                        indicator.className = `schedule-indicator ${schedule.status.toLowerCase().replace(' ', '_')}`;
                        indicator.textContent = schedule.status;
                        dayElement.appendChild(indicator);
                    });
                    
                    // Add click event to show day details
                    dayElement.addEventListener('click', function() {
                        showDayDetails(dateStr, daySchedules);
                    });
                    
                    calendarGrid.appendChild(dayElement);
                }
                
                // Fill remaining empty cells
                const totalCells = 42; // 6 rows * 7 days
                const cellsFilled = startingDay + totalDays;
                const remainingCells = totalCells - cellsFilled;
                
                for(let i = 0; i < remainingCells; i++) {
                    const emptyDay = document.createElement('div');
                    emptyDay.className = 'calendar-day other-month';
                    calendarGrid.appendChild(emptyDay);
                }
            }
            
            function showDayDetails(dateStr, daySchedules) {
                const date = new Date(dateStr);
                const formattedDate = date.toLocaleDateString('en-US', { 
                    weekday: 'long', 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                });
                
                modalDateElement.textContent = formattedDate;
                
                let html = '';
                
                if(daySchedules.length === 0) {
                    html = `
                        <div class="no-schedules">
                            <i class="fas fa-calendar-times"></i>
                            <h4>No Assignments</h4>
                            <p>No pickup schedules for ${formattedDate}</p>
                        </div>
                    `;
                } else {
                    html = `
                        <div class="day-schedules-list">
                            ${daySchedules.map(schedule => `
                                <div class="day-schedule-item ${schedule.status.toLowerCase().replace(' ', '_')}">
                                    <div class="day-schedule-header">
                                        <div>
                                            <strong>${schedule.status}</strong>
                                            ${schedule.notes ? `<div class="day-schedule-notes">${schedule.notes}</div>` : ''}
                                        </div>
                                        <div class="day-schedule-time">
                                            <i class="far fa-clock"></i> 8:00 AM - 5:00 PM
                                        </div>
                                    </div>
                                    ${driverBarangay ? `<div style="font-size: 0.9rem; color: #666; margin-top: 5px;">
                                        <i class="fas fa-map-marker-alt"></i> ${driverBarangay}
                                    </div>` : ''}
                                </div>
                            `).join('')}
                        </div>
                    `;
                }
                
                modalBody.innerHTML = html;
                calendarModal.style.display = 'flex';
            }
            
            // Start Assignment
            document.querySelectorAll('.start-assignment').forEach(button => {
                button.addEventListener('click', function() {
                    const assignmentId = this.getAttribute('data-id');
                    const assignmentType = this.getAttribute('data-type');
                    
                    if(confirm('Are you sure you want to start this assignment?')) {
                        if(assignmentType === 'pickup_schedule') {
                            // For pickup schedules, update status in both tables
                            fetch('update_assignment.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({
                                    assignment_id: assignmentId,
                                    status: 'in_progress',
                                    assignment_type: 'pickup_schedule'
                                })
                            })
                            .then(response => response.json())
                            .then(data => {
                                if(data.success) {
                                    alert('Assignment started successfully!');
                                    location.reload();
                                } else {
                                    alert('Error: ' + data.message);
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                alert('Error starting assignment. Please try again.');
                            });
                        } else {
                            // For regular assignments
                            fetch('update_assignment.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({
                                    assignment_id: assignmentId,
                                    status: 'in_progress'
                                })
                            })
                            .then(response => response.json())
                            .then(data => {
                                if(data.success) {
                                    alert('Assignment started successfully!');
                                    location.reload();
                                } else {
                                    alert('Error: ' + data.message);
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                alert('Error starting assignment. Please try again.');
                            });
                        }
                    }
                });
            });
            
            // Complete Assignment
            document.getElementById('completeAssignment')?.addEventListener('click', function() {
                if(confirm('Are you sure you want to mark this assignment as complete?')) {
                    // Get current assignment ID from page
                    const currentAssignmentCard = document.querySelector('.dashboard-card[style*="background: linear-gradient"]');
                    if(currentAssignmentCard) {
                        // In real implementation, you would get the assignment ID
                        // For now, simulate completion
                        alert('Assignment completed! Redirecting to collections...');
                        window.location.href = 'collections.php';
                    }
                }
            });
            
            // View Assignment Details
            document.querySelectorAll('.view-details').forEach(button => {
                button.addEventListener('click', function() {
                    const assignmentId = this.getAttribute('data-id');
                    const assignmentType = this.getAttribute('data-type');
                    
                    // Load assignment details
                    document.getElementById('modalContent').innerHTML = `
                        <div style="text-align: center; padding: 20px 0;">
                            <div class="loader" style="width: 40px; height: 40px; border: 3px solid #f3f3f3; border-top: 3px solid #4caf50; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto;"></div>
                            <p>Loading details...</p>
                        </div>
                    `;
                    
                    // Show modal
                    document.getElementById('assignmentModal').style.display = 'flex';
                    
                    // Simulate API call with different content based on assignment type
                    setTimeout(() => {
                        if(assignmentType === 'pickup_schedule') {
                            document.getElementById('modalContent').innerHTML = getPickupScheduleDetails(assignmentId);
                        } else {
                            document.getElementById('modalContent').innerHTML = getRegularAssignmentDetails(assignmentId);
                        }
                    }, 500);
                });
            });
            
            // Close Modal
            document.getElementById('closeModal')?.addEventListener('click', function() {
                document.getElementById('assignmentModal').style.display = 'none';
            });
            
            // Report Issue
            document.getElementById('reportIssue')?.addEventListener('click', function() {
                const issue = prompt('Please describe the issue:');
                if(issue) {
                    // Get current assignment details
                    const barangay = "<?php echo htmlspecialchars($driver_barangay); ?>";
                    const date = "<?php echo date('Y-m-d'); ?>";
                    
                    fetch('report_issue.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            barangay: barangay,
                            date: date,
                            issue: issue,
                            driver_id: <?php echo $driver_id; ?>,
                            driver_name: "<?php echo htmlspecialchars($driver_name); ?>"
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if(data.success) {
                            alert('Issue reported successfully! Our team will contact you shortly.');
                        } else {
                            alert('Error reporting issue: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error reporting issue. Please try again.');
                    });
                }
            });
            
            // Refresh Assignments
            document.getElementById('refreshAssignments')?.addEventListener('click', function() {
                location.reload();
            });
            
            // Close modal when clicking outside
            document.getElementById('assignmentModal')?.addEventListener('click', function(e) {
                if(e.target === this) {
                    this.style.display = 'none';
                }
            });
        });
        
        function getPickupScheduleDetails(id) {
            return `
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <div>
                        <h4 style="color: #666; margin-bottom: 5px;">Assignment Type</h4>
                        <p style="font-weight: 600; color: #1976d2;">
                            <i class="fas fa-calendar-alt"></i> Admin Scheduled Pickup
                        </p>
                    </div>
                    
                    <div>
                        <h4 style="color: #666; margin-bottom: 5px;">Barangay</h4>
                        <p style="font-weight: 600; color: #333;"><?php echo htmlspecialchars($driver_barangay); ?></p>
                    </div>
                    
                    <div>
                        <h4 style="color: #666; margin-bottom: 5px;">Schedule</h4>
                        <p style="font-weight: 600; color: #333;">Tomorrow, 8:00 AM - 5:00 PM</p>
                    </div>
                    
                    <div>
                        <h4 style="color: #666; margin-bottom: 5px;">Details</h4>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <div>
                                <div style="font-size: 0.85rem; color: #666;">Estimated Weight</div>
                                <div style="font-weight: 600; color: #2e7d32;">150-200 kg</div>
                            </div>
                            <div>
                                <div style="font-size: 0.85rem; color: #666;">Stops</div>
                                <div style="font-weight: 600; color: #2196f3;">15 stops</div>
                            </div>
                            <div>
                                <div style="font-size: 0.85rem; color: #666;">Vehicle</div>
                                <div style="font-weight: 600; color: #ff9800;">Truck #TR-001</div>
                            </div>
                            <div>
                                <div style="font-size: 0.85rem; color: #666;">Source</div>
                                <div style="font-weight: 600; color: #9c27b0;">Admin Calendar</div>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h4 style="color: #666; margin-bottom: 5px;">Notes</h4>
                        <p style="color: #555; font-size: 0.95rem; background: #f8fdf9; padding: 10px; border-radius: 8px;">
                            This assignment is synchronized with the admin's pickup schedule. Please check for any special instructions in the admin notes.
                        </p>
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button class="btn btn-primary" style="flex: 1;" onclick="window.location.href='routes.php'">
                            <i class="fas fa-route"></i> View Route
                        </button>
                        <button class="btn btn-outline" style="flex: 1;" onclick="window.location.href='collections.php'">
                            <i class="fas fa-trash"></i> Collections
                        </button>
                    </div>
                </div>
            `;
        }
        
        function getRegularAssignmentDetails(id) {
            return `
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <div>
                        <h4 style="color: #666; margin-bottom: 5px;">Assignment Name</h4>
                        <p style="font-weight: 600; color: #333;">Zone A - Lahug Area</p>
                    </div>
                    
                    <div>
                        <h4 style="color: #666; margin-bottom: 5px;">Schedule</h4>
                        <p style="font-weight: 600; color: #333;">Tomorrow, 8:00 AM - 12:00 PM</p>
                    </div>
                    
                    <div>
                        <h4 style="color: #666; margin-bottom: 5px;">Area</h4>
                        <p style="font-weight: 600; color: #333;">Barangay Lahug, Cebu City</p>
                    </div>
                    
                    <div>
                        <h4 style="color: #666; margin-bottom: 5px;">Details</h4>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <div>
                                <div style="font-size: 0.85rem; color: #666;">Estimated Weight</div>
                                <div style="font-weight: 600; color: #2e7d32;">150-200 kg</div>
                            </div>
                            <div>
                                <div style="font-size: 0.85rem; color: #666;">Stops</div>
                                <div style="font-weight: 600; color: #2196f3;">15 stops</div>
                            </div>
                            <div>
                                <div style="font-size: 0.85rem; color: #666;">Vehicle</div>
                                <div style="font-weight: 600; color: #ff9800;">Truck #TR-001</div>
                            </div>
                            <div>
                                <div style="font-size: 0.85rem; color: #666;">Priority</div>
                                <div style="font-weight: 600; color: #9c27b0;">High</div>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h4 style="color: #666; margin-bottom: 5px;">Notes</h4>
                        <p style="color: #555; font-size: 0.95rem; background: #f8fdf9; padding: 10px; border-radius: 8px;">
                            Please ensure all recyclables are properly sorted. Special attention to e-waste collection.
                        </p>
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button class="btn btn-primary" style="flex: 1;" onclick="window.location.href='routes.php'">
                            <i class="fas fa-route"></i> View Route
                        </button>
                        <button class="btn btn-outline" style="flex: 1;" onclick="window.location.href='collections.php'">
                            <i class="fas fa-trash"></i> Collections
                        </button>
                    </div>
                </div>
            `;
        }
        
        // Add spinner animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            
            .btn {
                padding: 10px 20px;
                border: none;
                border-radius: 8px;
                cursor: pointer;
                font-size: 0.9rem;
                font-weight: 500;
                transition: all 0.3s ease;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
            }
            
            .btn-complete {
                background: linear-gradient(135deg, #4CAF50, #2E7D32);
                color: white;
            }
            
            .btn-complete:hover {
                background: linear-gradient(135deg, #2E7D32, #1B5E20);
                transform: translateY(-2px);
            }
            
            .btn-secondary {
                background: linear-gradient(135deg, #2196F3, #0D47A1);
                color: white;
            }
            
            .btn-secondary:hover {
                background: linear-gradient(135deg, #0D47A1, #0D2E7D);
                transform: translateY(-2px);
            }
            
            .btn-outline {
                background: transparent;
                border: 2px solid #ddd;
                color: #666;
            }
            
            .btn-outline:hover {
                border-color: #2196F3;
                color: #2196F3;
            }
            
            .btn-primary {
                background: linear-gradient(135deg, #2196F3, #0D47A1);
                color: white;
            }
            
            .btn-start {
                background: linear-gradient(135deg, #4CAF50, #2E7D32);
                color: white;
                padding: 6px 12px;
                font-size: 0.8rem;
            }
            
            .btn-start:hover {
                background: linear-gradient(135deg, #2E7D32, #1B5E20);
            }
        `;
        document.head.appendChild(style);

        

    </script>
</body>
</html>