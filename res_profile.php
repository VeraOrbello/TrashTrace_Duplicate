<?php
require_once "config.php";
require_once "php/barangay_data.php";

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

if(isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin'){
    header('location: barangay_dashboard.php');
    exit;
}

$user_id = $_SESSION["id"];
$user_data = [];
$update_success = false;
$password_success = false;

$sql = "SELECT * FROM users WHERE id = :id";
if($stmt = $pdo->prepare($sql)){
    $stmt->bindParam(":id", $user_id, PDO::PARAM_INT);
    
    if($stmt->execute()){
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    unset($stmt);
}

$cebuBarangays = getCebuBarangays();

$worker_status = 'not_applied';
$worker_data = null;
$admin_application_status = null;

// Check for worker applications
$check_worker_sql = "SELECT * FROM worker_applications WHERE user_id = :user_id ORDER BY submitted_at DESC LIMIT 1";
if($check_worker_stmt = $pdo->prepare($check_worker_sql)){
    $check_worker_stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    if($check_worker_stmt->execute()){
        if($check_worker_stmt->rowCount() > 0){
            $worker_data = $check_worker_stmt->fetch(PDO::FETCH_ASSOC);
            $worker_status = $worker_data['status'] ?? 'pending';
        }
    }
    unset($check_worker_stmt);
}

// Check for admin_pending status
if($user_data['user_type'] === 'admin_pending'){
    $admin_application_status = 'pending';
}

if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_profile"])){
    $full_name = trim($_POST["full_name"]);
    $mobile_number = trim($_POST["mobile_number"]);
    $city = trim($_POST["city"]);
    $barangay = trim($_POST["barangay"]);
    $zone = trim($_POST["zone"]);
    $address = trim($_POST["address"]);
    $notification_channel = $_POST["notification_channel"];

    $sql = "UPDATE users SET full_name = :full_name, mobile_number = :mobile_number, city = :city, barangay = :barangay, zone = :zone, address = :address, notification_channel = :notification_channel WHERE id = :id";
    
    if($stmt = $pdo->prepare($sql)){
        $stmt->bindParam(":full_name", $full_name, PDO::PARAM_STR);
        $stmt->bindParam(":mobile_number", $mobile_number, PDO::PARAM_STR);
        $stmt->bindParam(":city", $city, PDO::PARAM_STR);
        $stmt->bindParam(":barangay", $barangay, PDO::PARAM_STR);
        $stmt->bindParam(":zone", $zone, PDO::PARAM_STR);
        $stmt->bindParam(":address", $address, PDO::PARAM_STR);
        $stmt->bindParam(":notification_channel", $notification_channel, PDO::PARAM_STR);
        $stmt->bindParam(":id", $user_id, PDO::PARAM_INT);
        
        if($stmt->execute()){
            $update_success = true;
            $_SESSION["full_name"] = $full_name;
            $_SESSION["barangay"] = $barangay;
            $_SESSION["zone"] = $zone;
            $user_data['full_name'] = $full_name;
            $user_data['mobile_number'] = $mobile_number;
            $user_data['city'] = $city;
            $user_data['barangay'] = $barangay;
            $user_data['zone'] = $zone;
            $user_data['address'] = $address;
            $user_data['notification_channel'] = $notification_channel;
        }
        unset($stmt);
    }
}

if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["change_password"])){
    $current_password = trim($_POST["current_password"]);
    $new_password = trim($_POST["new_password"]);
    $confirm_password = trim($_POST["confirm_password"]);
    
    if(password_verify($current_password, $user_data['password'])){
        if($new_password === $confirm_password){
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $sql = "UPDATE users SET password = :password WHERE id = :id";
            if($stmt = $pdo->prepare($sql)){
                $stmt->bindParam(":password", $hashed_password, PDO::PARAM_STR);
                $stmt->bindParam(":id", $user_id, PDO::PARAM_INT);
                
                if($stmt->execute()){
                    $password_success = true;
                }
                unset($stmt);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - TrashTrace</title>
    <link rel="stylesheet" href="css/res_profile.css">
    <link href="node_modules/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="profile-container">
        <header class="profile-header">
            <div class="header-content">
                <div class="logo">TrashTrace</div>
                <nav>
                    <ul>
                        <li><a href="dashboard.php" class="nav-link">Dashboard</a></li>
                        <li><a href="res_schedule.php" class="nav-link">Schedule</a></li>
                        <li><a href="res_notif.php" class="nav-link">Notifications</a></li>
                        <li><a href="res_profile.php" class="nav-link active">Profile</a></li>
                        <li class="user-menu">
                            <span>Welcome, <?php echo $_SESSION["full_name"]; ?></span>
                            <a href="logout.php" class="btn btn-outline">Logout</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </header>

        <main class="profile-main">
            <div class="container">
                <h1 class="page-title">Profile Settings</h1>
                
                <?php if($update_success): ?>
                <div class="alert alert-success">
                    Profile updated successfully!
                </div>
                <?php endif; ?>
                
                <?php if($password_success): ?>
                <div class="alert alert-success">
                    Password changed successfully!
                </div>
                <?php endif; ?>
                
                <div class="profile-grid">
                    <div class="profile-card personal-info">
                        <h2>Personal Information</h2>
                        <form method="POST" class="profile-form">
                            <input type="hidden" name="update_profile" value="1">
                            
                            <div class="form-group">
                                <label for="full_name">Full Name</label>
                                <input type="text" id="full_name" name="full_name" 
                                       value="<?php echo htmlspecialchars($user_data['full_name'] ?? ''); ?>" 
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label for="mobile_number">Mobile Number</label>
                                <input type="tel" id="mobile_number" name="mobile_number" 
                                       value="<?php echo htmlspecialchars($user_data['mobile_number'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="city">City</label>
                                <select id="city" name="city" class="form-control" required>
                                    <option value="">Select City</option>
                                    <option value="CEBU CITY" <?php echo ($user_data['city'] ?? '') == 'CEBU CITY' ? 'selected' : ''; ?>>Cebu City</option>
                                    <option value="MANDAUE CITY" <?php echo ($user_data['city'] ?? '') == 'MANDAUE CITY' ? 'selected' : ''; ?>>Mandaue City</option>
                                    <option value="LAPU-LAPU CITY" <?php echo ($user_data['city'] ?? '') == 'LAPU-LAPU CITY' ? 'selected' : ''; ?>>Lapu-Lapu City</option>
                                    <option value="TALISAY CITY" <?php echo ($user_data['city'] ?? '') == 'TALISAY CITY' ? 'selected' : ''; ?>>Talisay City</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="barangay">Barangay</label>
                                <select id="barangay" name="barangay" class="form-control" required>
                                    <option value="">Select Barangay</option>
                                    <?php if(isset($user_data['city']) && isset($cebuBarangays[$user_data['city']])): ?>
                                        <?php foreach($cebuBarangays[$user_data['city']] as $brgy): ?>
                                            <option value="<?php echo $brgy; ?>" 
                                                <?php echo ($user_data['barangay'] ?? '') == $brgy ? 'selected' : ''; ?>>
                                                <?php echo $brgy; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="zone">Zone/Purok</label>
                                <input type="text" id="zone" name="zone" 
                                       value="<?php echo htmlspecialchars($user_data['zone'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="address">House No., Street Name</label>
                                <input type="text" id="address" name="address" 
                                       value="<?php echo htmlspecialchars($user_data['address'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="notification_channel">Notification Channel</label>
                                <select id="notification_channel" name="notification_channel" required>
                                    <option value="Email" <?php echo ($user_data['notification_channel'] ?? '') == 'Email' ? 'selected' : ''; ?>>Email</option>
                                    <option value="SMS" <?php echo ($user_data['notification_channel'] ?? '') == 'SMS' ? 'selected' : ''; ?>>SMS</option>
                                    <option value="Both" <?php echo ($user_data['notification_channel'] ?? '') == 'Both' ? 'selected' : ''; ?>>Both</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Update Profile</button>
                        </form>
                    </div>
                    
                    <div class="profile-card password-change">
                        <h2>Change Password</h2>
                        <form method="POST" class="password-form">
                            <input type="hidden" name="change_password" value="1">
                            
                            <div class="form-group">
                                <label for="current_password">Current Password</label>
                                <input type="password" id="current_password" name="current_password" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <input type="password" id="new_password" name="new_password" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Change Password</button>
                        </form>
                    </div>
                    
                    <div class="profile-card worker-registration">
                        <h2>Application Status</h2>
                        <div class="worker-info">
                            <?php if($admin_application_status == 'pending'): ?>
                            <div class="worker-status pending">
                                <i class="fas fa-user-shield"></i>
                                <h3>Admin Application Pending</h3>
                                <p>Your admin application has been submitted and is awaiting review by barangay administrators.</p>
                                <div class="status-details">
                                    <p><strong>Applied:</strong> <?php echo date('M d, Y', strtotime($user_data['created_at'])); ?></p>
                                    <p><strong>Status:</strong> <span class="status-badge pending-badge">Pending Review</span></p>
                                </div>
                                <p class="status-note">We will contact you via email or phone once your application is processed.</p>
                            </div>
                            <?php elseif($worker_status == 'not_applied'): ?>
                            <div class="worker-status not-applied">
                                <i class="fas fa-user-clock"></i>
                                <h3>Not Yet Validated</h3>
                                <p>You have not submitted a worker validation request.</p>
                                <p>If you are an existing barangay worker, you can validate your account to access worker features.</p>
                                <div class="benefits">
                                    <h4>Benefits after validation:</h4>
                                    <ul>
                                        <li>Access to worker dashboard</li>
                                        <li>Manage pickup schedules</li>
                                        <li>Track work activities</li>
                                        <li>Help improve waste management</li>
                                    </ul>
                                </div>
                                <a href="barangay_register.php" class="btn btn-worker">
                                    <i class="fas fa-user-check"></i> Get Validated as Worker
                                </a>
                            </div>
                            <?php elseif($worker_status == 'pending'): ?>
                            <div class="worker-status pending">
                                <i class="fas fa-hourglass-half"></i>
                                <h3>Validation Pending</h3>
                                <p>Your worker validation request has been submitted and is awaiting review.</p>
                                <div class="status-details">
                                    <p><strong>Submitted:</strong> <?php echo date('M d, Y', strtotime($worker_data['submitted_at'])); ?></p>
                                    <p><strong>Worker ID:</strong> <?php echo htmlspecialchars($worker_data['id_number']); ?></p>
                                    <p><strong>Status:</strong> <span class="status-badge pending-badge">Pending Review</span></p>
                                </div>
                                <p class="status-note">We will contact you via email or phone once your validation is processed.</p>
                            </div>
                            <?php elseif($worker_status == 'reviewing'): ?>
                            <div class="worker-status reviewing">
                                <i class="fas fa-search"></i>
                                <h3>Under Review</h3>
                                <p>Your worker validation request is currently being reviewed by administrators.</p>
                                <div class="status-details">
                                    <p><strong>Submitted:</strong> <?php echo date('M d, Y', strtotime($worker_data['submitted_at'])); ?></p>
                                    <p><strong>Worker ID:</strong> <?php echo htmlspecialchars($worker_data['id_number']); ?></p>
                                    <p><strong>Status:</strong> <span class="status-badge reviewing-badge">Under Review</span></p>
                                </div>
                                <p class="status-note">Your application is being verified. This process usually takes 2-3 business days.</p>
                            </div>
                            <?php elseif($worker_status == 'accepted'): ?>
                            <div class="worker-status approved">
                                <i class="fas fa-check-circle"></i>
                                <h3>Validation Accepted</h3>
                                <p>Your worker account has been validated successfully!</p>
                                <div class="status-details">
                                      <p><strong>Accepted:</strong> <?php echo date('M d, Y', strtotime($worker_data['submitted_at'])); ?></p>
                                    <p><strong>Worker ID:</strong> <?php echo htmlspecialchars($worker_data['id_number']); ?></p>
                                    <p><strong>Status:</strong> <span class="status-badge approved-badge">Active Worker</span></p>
                                </div>
                                <div class="benefits">
                                    <h4>You now have access to:</h4>
                                    <ul>
                                        <li>Worker dashboard</li>
                                        <li>Schedule management</li>
                                        <li>Work tracking</li>
                                        <li>Community updates</li>
                                    </ul>
                                </div>
                                <div class="action-buttons">
                                    <a href="dashboard.php" class="btn btn-worker">
                                        <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                                    </a>
                                    <a href="res_schedule.php" class="btn btn-schedule">
                                        <i class="fas fa-calendar"></i> View Schedule
                                    </a>
                                </div>
                            </div>
                            <?php elseif($worker_status == 'rejected'): ?>
                            <div class="worker-status rejected">
                                <i class="fas fa-times-circle"></i>
                                <h3>Validation Rejected</h3>
                                <p>Your worker validation request was not approved.</p>
                                <div class="status-details">
                                    <p><strong>Submitted:</strong> <?php echo date('M d, Y', strtotime($worker_data['submitted_at'])); ?></p>
                                    <p><strong>Status:</strong> <span class="status-badge rejected-badge">Rejected</span></p>
                                </div>
                                <p class="status-note">If you believe this was a mistake, please contact your barangay administrator or submit a new validation request.</p>
                                <a href="barangay_register.php" class="btn btn-worker">
                                    <i class="fas fa-redo"></i> Submit New Validation
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="js/res_profile.js"></script>
</body>
</html>