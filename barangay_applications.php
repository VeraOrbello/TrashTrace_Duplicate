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
    <link rel="stylesheet" href="css/barangay_applications.css">
    <link rel="stylesheet" href="css/barangay_dashboard.css">
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
                        <li>
                            <a href="barangay_applications.php" class="nav-link active">Applications</a>
                        </li>
                        <li><a href="barangay_notifications.php" class="nav-link">Notifications</a></li>
                        <li><a href="barangay_reports.php" class="nav-link">Reports</a></li>
                        <li class="user-menu">
                            <span>Welcome, <?php echo htmlspecialchars($_SESSION["full_name"] ?? ''); ?></span>
                            <a href="logout.php" class="btn btn-outline">Logout</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </header>

        <main class="dashboard-main page-transition">
            <div class="container">
                <h1 class="welcome-title">Barangay Applications - <?php echo htmlspecialchars($user_barangay); ?></h1>
                
                <?php if(isset($success_message)): ?>
                    <div class="alert alert-success">
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Tab Navigation -->
                <div class="application-tabs">
                    <a href="?tab=workers" class="tab-btn <?php echo $current_tab == 'workers' ? 'active' : ''; ?>">
                        <i class="fas fa-user-hard-hat"></i> Worker Applications
                    </a>
                    <a href="?tab=drivers" class="tab-btn <?php echo $current_tab == 'drivers' ? 'active' : ''; ?>">
                        <i class="fas fa-truck"></i> Driver Applications
                    </a>
                </div>
                
                <!-- Worker Applications Tab -->
                <?php if($current_tab == 'workers'): ?>
                <div class="applications-card dashboard-card">
                    <h2>Worker Applications</h2>
                    <div class="applications-actions">
                        <input id="searchInput" type="search" placeholder="Search by name, id number, email" />
                    </div>
                    <div class="table-wrapper">
                        <table id="applicationsTable">
                            <thead>
                                <tr>
                                    <th>Applicant</th>
                                    <th>ID Number</th>
                                    <th>Contact</th>
                                    <th>Submitted</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(!empty($worker_applications)): ?>
                                    <?php foreach($worker_applications as $app): ?>
                                    <tr>
                                        <td class="app-name"><?php echo htmlspecialchars($app['full_name'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($app['id_number'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($app['contact_number'] ?? '') . '<br>' . htmlspecialchars($app['email'] ?? ''); ?></td>
                                        <td><?php echo isset($app['submitted_at']) ? date('M d, Y g:i A', strtotime($app['submitted_at'])) : ''; ?></td>
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
                                        <td class="status-cell"><span class="status-badge status-<?php echo htmlspecialchars($display_status); ?>"><?php echo ucfirst($display_status); ?></span></td>
                                        <td>
                                            <button class="btn btn-action view-app-btn" data-id="<?php echo (int)$app['id']; ?>" data-type="<?php echo $app['application_type']; ?>">View</button>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                                <input type="hidden" name="application_type" value="<?php echo $app['application_type']; ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn btn-action btn-approve" onclick="return confirm('Approve this <?php echo $app['application_type']; ?> application?')">
                                                    Approve
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                                <input type="hidden" name="application_type" value="<?php echo $app['application_type']; ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" class="btn btn-action btn-reject" onclick="return confirm('Reject this <?php echo $app['application_type']; ?> application?')">
                                                    Reject
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="no-data">No worker applications found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Driver Applications Tab -->
                <?php if($current_tab == 'drivers'): ?>
                <div class="applications-card dashboard-card">
                    <h2>Driver Applications</h2>
                    <div class="applications-actions">
                        <input id="searchDriverInput" type="search" placeholder="Search by name, license, vehicle" />
                    </div>
                    <div class="table-wrapper">
                        <table id="driverApplicationsTable">
                            <thead>
                                <tr>
                                    <th>Applicant</th>
                                    <th>License Number</th>
                                    <th>Vehicle</th>
                                    <th>Submitted</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(!empty($driver_applications)): ?>
                                    <?php foreach($driver_applications as $app): ?>
                                    <tr>
                                        <td class="app-name"><?php echo htmlspecialchars($app['full_name'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($app['license_number'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($app['vehicle_type'] ?? '') . ' - ' . htmlspecialchars($app['vehicle_plate'] ?? ''); ?></td>
                                        <td><?php echo isset($app['application_date']) ? date('M d, Y g:i A', strtotime($app['application_date'])) : ''; ?></td>
                                        <td class="status-cell">
                                            <span class="status-badge status-pending">Pending</span>
                                        </td>
                                        <td>
                                            <button class="btn btn-action view-driver-btn" data-id="<?php echo (int)$app['id']; ?>" data-type="driver">View</button>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                                <input type="hidden" name="application_type" value="driver">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn btn-action btn-approve" onclick="return confirm('Approve this driver application?')">
                                                    Approve
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                                <input type="hidden" name="application_type" value="driver">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" class="btn btn-action btn-reject" onclick="return confirm('Reject this driver application?')">
                                                    Reject
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="no-data">No pending driver applications found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>


            </div>
        </main>
    </div>

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
