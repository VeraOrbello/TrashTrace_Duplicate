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

$user_barangay = $_SESSION["barangay"] ?? '';
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'workers';

// Get worker applications and admin applications
$worker_applications = [];
if($current_tab == 'workers') {
    // Get worker applications
    $sql = "SELECT wa.id, wa.id_number, u.full_name, u.email, wa.contact_number, wa.submitted_at, wa.status, u.user_type, 'worker' as application_type
            FROM worker_applications wa
            JOIN users u ON wa.user_id = u.id
            WHERE LOWER(TRIM(wa.barangay)) = LOWER(TRIM(:barangay)) AND wa.status = 'pending'
            UNION ALL
            SELECT u.id, NULL as id_number, u.full_name, u.email, u.mobile_number as contact_number, u.created_at as submitted_at, 'pending' as status, u.user_type, 'admin' as application_type
            FROM users u
            WHERE u.user_type = 'admin_pending' AND u.barangay = :barangay
            ORDER BY submitted_at DESC";

    if($stmt = $pdo->prepare($sql)){
        $stmt->bindParam(":barangay", $user_barangay, PDO::PARAM_STR);
        if($stmt->execute()){
            $worker_applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        $stmt->closeCursor();
    }
}

// Get driver applications
$driver_applications = [];
if($current_tab == 'drivers') {
    $sql = "SELECT da.*, u.full_name, u.email, u.user_type, u.mobile_number
            FROM driver_applications da
            JOIN users u ON da.user_id = u.id
            WHERE u.user_type = 'driver_pending' AND u.barangay = :barangay
            ORDER BY da.application_date DESC";

    if($stmt = $pdo->prepare($sql)){
        $stmt->bindParam(":barangay", $user_barangay, PDO::PARAM_STR);
        if($stmt->execute()){
            $driver_applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        $stmt->closeCursor();
    }
}





// Handle approval/rejection
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $application_id = $_POST['application_id'] ?? 0;
    $application_type = $_POST['application_type'] ?? ''; // 'worker', 'driver', or 'admin'
    $action = $_POST['action']; // 'approve' or 'reject'

    if($application_type == 'worker') {
        if($action == 'approve') {
            // Update worker application status to approved
            $sql = "UPDATE worker_applications SET status = 'approved', reviewed_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$application_id]);

            $success_message = "Worker application approved successfully!";
        } else if($action == 'reject') {
            // Update worker application status to rejected
            $sql = "UPDATE worker_applications SET status = 'rejected', reviewed_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$application_id]);

            $success_message = "Worker application rejected.";
        }
    } elseif($application_type == 'driver') {
        if($action == 'approve') {
            // Get driver application details
            $sql = "SELECT da.*, u.id as user_id
                    FROM driver_applications da
                    JOIN users u ON da.user_id = u.id
                    WHERE da.id = ? AND u.barangay = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$application_id, $user_barangay]);
            $driver_app = $stmt->fetch(PDO::FETCH_ASSOC);

            if($driver_app) {
                // Update user to driver
                $sql = "UPDATE users SET user_type = 'driver' WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$driver_app['user_id']]);

                // Create driver profile
                $sql = "INSERT INTO driver_profiles (driver_id, license_number, vehicle_type, vehicle_plate, status)
                        VALUES (?, ?, ?, ?, 'active')";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $driver_app['user_id'],
                    $driver_app['license_number'],
                    $driver_app['vehicle_type'],
                    $driver_app['vehicle_plate']
                ]);

                // Update application status
                $sql = "UPDATE driver_applications SET status = 'approved', reviewed_at = NOW(), reviewed_by = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$_SESSION['id'], $application_id]);

                $success_message = "Driver application approved successfully!";
            }
        } else if($action == 'reject') {
            // Get driver application details to reset user type
            $sql = "SELECT da.user_id FROM driver_applications da WHERE da.id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$application_id]);
            $driver_app = $stmt->fetch(PDO::FETCH_ASSOC);

            if($driver_app){
                // Reset user to regular user
                $sql = "UPDATE users SET user_type = 'user' WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$driver_app['user_id']]);
            }

            // Update application status
            $sql = "UPDATE driver_applications SET status = 'rejected', reviewed_at = NOW(), reviewed_by = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$_SESSION['id'], $application_id]);

            $success_message = "Driver application rejected.";
        }
    } elseif($application_type == 'admin') {
        if($action == 'approve') {
            // Update user to admin
            $sql = "UPDATE users SET user_type = 'admin' WHERE id = ? AND barangay = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$application_id, $user_barangay]);

            $success_message = "Admin application approved successfully!";
        } else if($action == 'reject') {
            // Update user back to regular user
            $sql = "UPDATE users SET user_type = 'user' WHERE id = ? AND barangay = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$application_id, $user_barangay]);

            $success_message = "Admin application rejected.";
        }
    }

    // Redirect to prevent form resubmission
    header("location: barangay_applications.php?tab=" . $current_tab);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applications - Barangay - TrashTrace</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/barangay_applications.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="dashboard-main">
        <div class="container">
            <div class="applications-header-section">
                <h1><i class="fas fa-user-check"></i> Application Management</h1>
            </div>
            
            <?php if(isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <!-- Stats Row -->
            <div class="application-stats">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #e3f2fd; color: #1976d2;">
                        <i class="fas fa-user-hard-hat"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo count($worker_applications); ?></h3>
                        <p>Worker Applications</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: #fff3e0; color: #ff9800;">
                        <i class="fas fa-truck"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo count($driver_applications); ?></h3>
                        <p>Driver Applications</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: #e8f5e9; color: #4caf50;">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo htmlspecialchars($user_barangay); ?></h3>
                        <p>Barangay</p>
                    </div>
                </div>
            </div>
                
            <!-- Tab Navigation -->
            <div class="application-tabs">
                <a href="?tab=workers" class="tab-btn <?php echo $current_tab == 'workers' ? 'active' : ''; ?>">
                    <i class="fas fa-user-hard-hat"></i> Workers
                </a>
                <a href="?tab=drivers" class="tab-btn <?php echo $current_tab == 'drivers' ? 'active' : ''; ?>">
                    <i class="fas fa-truck"></i> Drivers
                </a>
            </div>
                
            <!-- Worker Applications Tab -->
            <?php if($current_tab == 'workers'): ?>
            <div class="applications-card">
                <div class="card-header">
                    <h2><i class="fas fa-user-hard-hat"></i> Worker Applications</h2>
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input id="searchInput" type="search" placeholder="Search applications..." />
                    </div>
                </div>
                <div class="card-body">
                    <?php if(!empty($worker_applications)): ?>
                        <div class="applications-grid">
                            <?php foreach($worker_applications as $app): ?>
                            <div class="application-item">
                                <div class="app-header">
                                    <div class="app-avatar">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div class="app-info">
                                        <h3><?php echo htmlspecialchars($app['full_name'] ?? ''); ?></h3>
                                        <p class="app-type"><?php echo ucfirst($app['application_type']); ?> Application</p>
                                    </div>
                                    <?php
                                        $display_status = $app['status'] ?? '';
                                        $user_type = $app['user_type'] ?? '';
                                        if(strtolower(trim($user_type)) === 'admin'){
                                            $display_status = 'accepted';
                                        } else {
                                            if(stripos($user_type, 'pending') !== false){
                                                $display_status = 'pending';
                                            }
                                        }
                                    ?>
                                    <span class="status-badge status-<?php echo htmlspecialchars($display_status); ?>">
                                        <?php echo ucfirst($display_status); ?>
                                    </span>
                                </div>
                                <div class="app-details">
                                    <div class="detail-row">
                                        <i class="fas fa-id-card"></i>
                                        <span><?php echo htmlspecialchars($app['id_number'] ?? 'N/A'); ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <i class="fas fa-envelope"></i>
                                        <span><?php echo htmlspecialchars($app['email'] ?? ''); ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <i class="fas fa-phone"></i>
                                        <span><?php echo htmlspecialchars($app['contact_number'] ?? ''); ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <i class="fas fa-calendar"></i>
                                        <span><?php echo isset($app['submitted_at']) ? date('M d, Y g:i A', strtotime($app['submitted_at'])) : ''; ?></span>
                                    </div>
                                </div>
                                <div class="app-actions">
                                    <button class="btn-view view-app-btn" data-id="<?php echo (int)$app['id']; ?>" data-type="<?php echo $app['application_type']; ?>">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                        <input type="hidden" name="application_type" value="<?php echo $app['application_type']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn-approve" onclick="return confirm('Approve this <?php echo $app['application_type']; ?> application?')">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                        <input type="hidden" name="application_type" value="<?php echo $app['application_type']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="btn-reject" onclick="return confirm('Reject this <?php echo $app['application_type']; ?> application?')">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-applications">
                            <i class="fas fa-inbox"></i>
                            <h3>No Worker Applications</h3>
                            <p>There are currently no pending worker applications.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
                
            <!-- Driver Applications Tab -->
            <?php if($current_tab == 'drivers'): ?>
            <div class="applications-card">
                <div class="card-header">
                    <h2><i class="fas fa-truck"></i> Driver Applications</h2>
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input id="searchDriverInput" type="search" placeholder="Search applications..." />
                    </div>
                </div>
                <div class="card-body">
                    <?php if(!empty($driver_applications)): ?>
                        <div class="applications-grid">
                            <?php foreach($driver_applications as $app): ?>
                            <div class="application-item">
                                <div class="app-header">
                                    <div class="app-avatar">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div class="app-info">
                                        <h3><?php echo htmlspecialchars($app['full_name'] ?? ''); ?></h3>
                                        <p class="app-type">Driver Application</p>
                                    </div>
                                    <span class="status-badge status-pending">Pending</span>
                                </div>
                                <div class="app-details">
                                    <div class="detail-row">
                                        <i class="fas fa-id-card-alt"></i>
                                        <span><?php echo htmlspecialchars($app['license_number'] ?? ''); ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <i class="fas fa-car"></i>
                                        <span><?php echo htmlspecialchars($app['vehicle_type'] ?? ''); ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <i class="fas fa-hashtag"></i>
                                        <span><?php echo htmlspecialchars($app['vehicle_plate'] ?? ''); ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <i class="fas fa-envelope"></i>
                                        <span><?php echo htmlspecialchars($app['email'] ?? ''); ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <i class="fas fa-phone"></i>
                                        <span><?php echo htmlspecialchars($app['mobile_number'] ?? ''); ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <i class="fas fa-calendar"></i>
                                        <span><?php echo isset($app['application_date']) ? date('M d, Y g:i A', strtotime($app['application_date'])) : ''; ?></span>
                                    </div>
                                </div>
                                <div class="app-actions">
                                    <button class="btn-view view-driver-btn" data-id="<?php echo (int)$app['id']; ?>" data-type="driver">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                        <input type="hidden" name="application_type" value="driver">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn-approve" onclick="return confirm('Approve this driver application?')">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                        <input type="hidden" name="application_type" value="driver">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="btn-reject" onclick="return confirm('Reject this driver application?')">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-applications">
                            <i class="fas fa-inbox"></i>
                            <h3>No Driver Applications</h3>
                            <p>There are currently no pending driver applications.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </main>

    <!-- Modal for viewing application details (for workers) -->
    <div id="applicationModal" class="modal" style="display:none;">
        <div class="modal-overlay"></div>
        <div class="modal-content">
            <button class="modal-close">×</button>
            <h3 id="modalName">Application Details</h3>
            <div class="modal-body">
                <div class="modal-row"><strong>ID Number:</strong> <span id="modalIdNumber"></span></div>
                <div class="modal-row"><strong>Contact:</strong> <span id="modalContact"></span></div>
                <div class="modal-row"><strong>City / Barangay / Zone:</strong> <span id="modalLocation"></span></div>
                <div class="modal-row"><strong>Experience:</strong> <span id="modalExperience"></span></div>
                <div class="modal-row"><strong>Availability:</strong> <span id="modalAvailability"></span></div>
                <div class="modal-row"><strong>Vehicle Access:</strong> <span id="modalVehicle"></span></div>
                <div class="modal-row"><strong>Health Conditions:</strong><div id="modalHealth"></div></div>
                <div class="modal-row"><strong>Reason:</strong><div id="modalReason"></div></div>
                <div class="modal-row"><strong>Submitted:</strong> <span id="modalSubmitted"></span></div>
                <div class="modal-row"><strong>Document:</strong><div id="modalDoc"></div></div>
            </div>
            <div class="modal-actions">
                <button id="approveBtn" class="btn btn-action">Approve</button>
                <button id="rejectBtn" class="btn btn-action" style="background:#f8d7da;color:#842029;border-color:#f5c2c7">Reject</button>
            </div>
        </div>
    </div>

    <!-- Modal for viewing driver application details -->
    <div id="driverApplicationModal" class="modal" style="display:none;">
        <div class="modal-overlay"></div>
        <div class="modal-content">
            <button class="modal-close">×</button>
            <h3 id="driverModalName">Driver Application Details</h3>
            <div class="modal-body">
                <div class="modal-row"><strong>Full Name:</strong> <span id="driverModalNameValue"></span></div>
                <div class="modal-row"><strong>Email:</strong> <span id="driverModalEmail"></span></div>
                <div class="modal-row"><strong>Mobile Number:</strong> <span id="driverModalMobile"></span></div>
                <div class="modal-row"><strong>Location:</strong> <span id="driverModalLocation"></span></div>
                <div class="modal-row"><strong>License Number:</strong> <span id="driverModalLicense"></span></div>
                <div class="modal-row"><strong>Vehicle Type:</strong> <span id="driverModalVehicleType"></span></div>
                <div class="modal-row"><strong>Vehicle Plate:</strong> <span id="driverModalVehiclePlate"></span></div>
                <div class="modal-row"><strong>Application Date:</strong> <span id="driverModalDate"></span></div>
                <div class="modal-row"><strong>Status:</strong> <span id="driverModalStatus"></span></div>
            </div>
            <div class="modal-actions">
                <button id="driverApproveBtn" class="btn btn-action">Approve</button>
                <button id="driverRejectBtn" class="btn btn-action" style="background:#f8d7da;color:#842029;border-color:#f5c2c7">Reject</button>
            </div>
        </div>
    </div>



    <script src="js/barangay_applications.js"></script>
</body>
</html>
