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

// Get current date and time
$current_date = date('l, F j, Y');
$current_time = date('h:i A');

// Get driver assignments from database
$assignments = [];
$current_assignment = null;
$today_assignments = [];
$upcoming_assignments = [];

// Try to fetch assignments from database
try {
    // Check if assignments table exists
    $table_check = mysqli_query($link, "SHOW TABLES LIKE 'assignments'");
    
    if(mysqli_num_rows($table_check) > 0) {
        // Fetch today's assignments
        $today = date('Y-m-d');
        $sql = "SELECT * FROM assignments 
                WHERE driver_id = ? AND DATE(assignment_date) = ? 
                ORDER BY start_time ASC";
        
        if($stmt = mysqli_prepare($link, $sql)){
            mysqli_stmt_bind_param($stmt, "is", $driver_id, $today);
            if(mysqli_stmt_execute($stmt)){
                $result = mysqli_stmt_get_result($stmt);
                while($row = mysqli_fetch_assoc($result)){
                    $assignments[] = $row;
                    
                    if($row['status'] == 'in_progress' || $row['status'] == 'assigned'){
                        $current_assignment = $row;
                    } else if($row['status'] == 'scheduled'){
                        $today_assignments[] = $row;
                    }
                }
            }
            mysqli_stmt_close($stmt);
        }
        
        // Fetch upcoming assignments (next 7 days)
        $next_week = date('Y-m-d', strtotime('+7 days'));
        $sql = "SELECT * FROM assignments 
                WHERE driver_id = ? 
                AND DATE(assignment_date) > ? 
                AND DATE(assignment_date) <= ?
                AND status = 'scheduled'
                ORDER BY assignment_date, start_time ASC
                LIMIT 10";
        
        if($stmt = mysqli_prepare($link, $sql)){
            mysqli_stmt_bind_param($stmt, "iss", $driver_id, $today, $next_week);
            if(mysqli_stmt_execute($stmt)){
                $result = mysqli_stmt_get_result($stmt);
                while($row = mysqli_fetch_assoc($result)){
                    $upcoming_assignments[] = $row;
                }
            }
            mysqli_stmt_close($stmt);
        }
    }
    
} catch(Exception $e) {
    error_log("Assignments error: " . $e->getMessage());
    // Use sample data if database error
    $assignments = generateSampleAssignments();
    $current_assignment = $assignments[0];
    $today_assignments = array_slice($assignments, 1, 2);
    $upcoming_assignments = array_slice($assignments, 3, 4);
}

function generateSampleAssignments() {
    $sample_assignments = [];
    
    // Current assignment (in progress)
    $sample_assignments[] = [
        'id' => 1,
        'zone_name' => 'Zone A - Lahug Area',
        'assignment_date' => date('Y-m-d'),
        'start_time' => '08:00:00',
        'end_time' => '12:00:00',
        'status' => 'in_progress',
        'estimated_weight' => '150-200 kg',
        'stops' => 15,
        'vehicle' => 'Truck #TR-001',
        'area' => 'Barangay Lahug, Cebu City',
        'progress' => 65
    ];
    
    // Today's assignments
    $sample_assignments[] = [
        'id' => 2,
        'zone_name' => 'Zone B - Apas Residential',
        'assignment_date' => date('Y-m-d'),
        'start_time' => '13:00:00',
        'end_time' => '17:00:00',
        'status' => 'scheduled',
        'estimated_weight' => '100-150 kg',
        'stops' => 12,
        'vehicle' => 'Truck #TR-001',
        'area' => 'Barangay Apas, Cebu City',
        'progress' => 0
    ];
    
    $sample_assignments[] = [
        'id' => 3,
        'zone_name' => 'Zone C - IT Park',
        'assignment_date' => date('Y-m-d'),
        'start_time' => '18:00:00',
        'end_time' => '21:00:00',
        'status' => 'scheduled',
        'estimated_weight' => '80-120 kg',
        'stops' => 8,
        'vehicle' => 'Truck #TR-001',
        'area' => 'IT Park, Cebu City',
        'progress' => 0
    ];
    
    // Upcoming assignments
    for($i = 4; $i <= 7; $i++) {
        $sample_assignments[] = [
            'id' => $i,
            'zone_name' => 'Zone ' . chr(64 + $i) . ' - Area ' . ($i - 3),
            'assignment_date' => date('Y-m-d', strtotime("+".($i-3)." days")),
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'status' => 'scheduled',
            'estimated_weight' => rand(50, 200) . '-' . rand(100, 250) . ' kg',
            'stops' => rand(5, 20),
            'vehicle' => 'Truck #TR-' . str_pad(rand(1, 10), 3, '0', STR_PAD_LEFT),
            'area' => 'Various Locations, Cebu City',
            'progress' => 0
        ];
    }
    
    return $sample_assignments;
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
</head>
<body>
    <div class="dashboard-container">
       <!-- Replace the entire header section with this -->

       <header class="dashboard-header">
    <!-- Grid Background Pattern -->
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
                    <li><a href="assignments.php" class="nav-link"><i class="fas fa-tasks"></i> <span>Assignments</span></a></li>
                    <li><a href="routes.php" class="nav-link"><i class="fas fa-route"></i> <span>Routes</span></a></li>
                    <li><a href="collections.php" class="nav-link"><i class="fas fa-trash"></i> <span>Collections</span></a></li>
                    <li><a href="earnings.php" class="nav-link"><i class="fas fa-money-bill-wave"></i> <span>Earnings</span></a></li>
                    <li><a href="history.php" class="nav-link"><i class="fas fa-history"></i> <span>History</span></a></li>
                    <li><a href="profile.php" class="nav-link active"><i class="fas fa-user"></i> <span>Profile</span></a></li>
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
                
                <?php if($current_assignment): ?>
                    <!-- Current Assignment Card -->
                    <div class="dashboard-card" style="background: linear-gradient(135deg, rgba(33, 150, 243, 0.1), rgba(33, 150, 243, 0.05)); border-color: rgba(33, 150, 243, 0.2); margin-bottom: 30px;">
                        <div class="card-header">
                            <h3><i class="fas fa-play-circle" style="color: #2196F3;"></i> Current Assignment</h3>
                            <span class="status-badge status-in_progress">In Progress</span>
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
                            <button class="btn btn-secondary" id="viewRoute">
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
                        <p style="color: #888; margin-bottom: 25px;">You don't have any active assignments right now. Check your scheduled tasks below.</p>
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
                                        </div>
                                        <div class="assignment-actions">
                                            <?php if(strtotime($assignment['start_time']) <= time()): ?>
                                                <button class="btn btn-start start-assignment" data-id="<?php echo $assignment['id']; ?>">
                                                    <i class="fas fa-play"></i> Start
                                                </button>
                                            <?php else: ?>
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
                                <p>No assignments scheduled for today</p>
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
                                        </div>
                                        <div class="assignment-actions">
                                            <button class="btn btn-outline view-details" data-id="<?php echo $assignment['id']; ?>">
                                                <i class="fas fa-eye"></i> Details
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-plus"></i>
                                <p>No upcoming assignments</p>
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
                                <h3><?php echo count(array_filter($assignments, function($a) { return $a['status'] == 'completed'; })); ?></h3>
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
                                <h3><?php echo count(array_filter($assignments, function($a) { return $a['status'] == 'in_progress'; })); ?></h3>
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
                                <h3><?php echo count($today_assignments); ?></h3>
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
            // Start Assignment
            document.querySelectorAll('.start-assignment').forEach(button => {
                button.addEventListener('click', function() {
                    const assignmentId = this.getAttribute('data-id');
                    if(confirm('Are you sure you want to start this assignment?')) {
                        // Send AJAX request to start assignment
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
                });
            });
            
            // Complete Assignment
            document.getElementById('completeAssignment')?.addEventListener('click', function() {
                if(confirm('Are you sure you want to mark this assignment as complete?')) {
                    // Simulate API call
                    alert('Assignment completed! Redirecting to collections...');
                    window.location.href = 'collections.php';
                }
            });
            
            // View Route
            document.getElementById('viewRoute')?.addEventListener('click', function() {
                window.location.href = 'routes.php';
            });
            
            // View Assignment Details
            document.querySelectorAll('.view-details').forEach(button => {
                button.addEventListener('click', function() {
                    const assignmentId = this.getAttribute('data-id');
                    
                    // Load assignment details
                    document.getElementById('modalContent').innerHTML = `
                        <div style="text-align: center; padding: 20px 0;">
                            <div class="loader" style="width: 40px; height: 40px; border: 3px solid #f3f3f3; border-top: 3px solid #4caf50; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto;"></div>
                            <p>Loading details...</p>
                        </div>
                    `;
                    
                    // Show modal
                    document.getElementById('assignmentModal').style.display = 'flex';
                    
                    // Simulate API call
                    setTimeout(() => {
                        document.getElementById('modalContent').innerHTML = `
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
                    alert('Issue reported successfully! Our team will contact you shortly.');
                    // Send issue report to server
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
        
        // Add spinner animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>