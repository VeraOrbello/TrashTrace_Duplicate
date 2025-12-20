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

$driver_name = $_SESSION["full_name"];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Assignments - TrashTrace</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Assignments CSS -->
    <link rel="stylesheet" href="../css/driver/assignments.css">
</head>
<body>
    <div class="assignments-container">
        <!-- Grid Background Pattern -->
        <div class="grid-background"></div>
        
        <div class="assignments-content">
            <div class="assignments-header">
                <h1>
                    <i class="fas fa-tasks"></i>
                    Driver Assignments
                </h1>
                <p>Welcome, <?php echo htmlspecialchars($driver_name); ?>! Manage your daily collection tasks.</p>
            </div>
            
            <nav class="assignments-nav">
                <div class="assignments-nav-links">
                    <a href="../driver_dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a href="assignments.php" class="active">
                        <i class="fas fa-tasks"></i> Assignments
                    </a>
                    <a href="routes.php">
                        <i class="fas fa-route"></i> Routes
                    </a>
                    <a href="collections.php">
                        <i class="fas fa-trash"></i> Collections
                    </a>
                    <a href="earnings.php">
                        <i class="fas fa-money-bill-wave"></i> Earnings
                    </a>
                    <a href="../logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </nav>
            
            <div class="assignments-section">
                <h2><i class="fas fa-calendar-day"></i> Today's Assignments</h2>
                
                <div class="assignments-grid">
                    <!-- Zone A Collection -->
                    <div class="assignment-card pending">
                        <div class="assignment-header">
                            <h3>Zone A Collection</h3>
                            <span class="assignment-status status-pending">Pending</span>
                        </div>
                        
                        <div class="assignment-details">
                            <div class="detail-item">
                                <i class="fas fa-clock"></i>
                                <span><strong>Time:</strong> 8:00 AM - 12:00 PM</span>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><strong>Area:</strong> Barangay Lahug</span>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-weight-hanging"></i>
                                <span><strong>Estimated Weight:</strong> 150-200 kg</span>
                            </div>
                        </div>
                        
                        <div class="assignment-actions">
                            <button class="btn-start">
                                <i class="fas fa-play-circle"></i> Start Collection
                            </button>
                            <button class="btn-details">
                                <i class="fas fa-info-circle"></i> View Details
                            </button>
                        </div>
                    </div>
                    
                    <!-- Zone B Collection -->
                    <div class="assignment-card scheduled">
                        <div class="assignment-header">
                            <h3>Zone B Collection</h3>
                            <span class="assignment-status status-scheduled">Scheduled</span>
                        </div>
                        
                        <div class="assignment-details">
                            <div class="detail-item">
                                <i class="fas fa-clock"></i>
                                <span><strong>Time:</strong> 1:00 PM - 5:00 PM</span>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><strong>Area:</strong> Barangay Apas</span>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-weight-hanging"></i>
                                <span><strong>Estimated Weight:</strong> 100-150 kg</span>
                            </div>
                        </div>
                        
                        <div class="assignment-actions">
                            <button class="btn-start" disabled style="opacity: 0.6; cursor: not-allowed;">
                                <i class="fas fa-clock"></i> Starts at 1:00 PM
                            </button>
                            <button class="btn-details">
                                <i class="fas fa-info-circle"></i> View Details
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>