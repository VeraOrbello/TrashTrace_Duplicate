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
    <link href="node_modules/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/res_profile.css">
</head>
<body>
    <div class="profile-container">
        <?php include 'includes/header.php'; ?>

        <main class="profile-main">
            <div class="container">
                <div class="profile-header">
                    <h1><i class="fas fa-user-circle"></i> Profile Settings</h1>
                    <p class="profile-subtitle">Manage your account and preferences</p>
                </div>
                
                <?php if($update_success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> Profile updated successfully!
                </div>
                <?php endif; ?>
                
                <?php if($password_success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> Password changed successfully!
                </div>
                <?php endif; ?>
                
                <div class="profile-grid">
                    <div class="profile-card">
                        <div class="card-header">
                            <h2><i class="fas fa-id-card"></i> Personal Information</h2>
                        </div>
                        <div class="card-body">
                            <form method="POST" class="profile-form">
                                <input type="hidden" name="update_profile" value="1">
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="full_name"><i class="fas fa-user"></i> Full Name</label>
                                        <input type="text" id="full_name" name="full_name" 
                                               value="<?php echo htmlspecialchars($user_data['full_name'] ?? ''); ?>" 
                                               required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="mobile_number"><i class="fas fa-phone"></i> Mobile Number</label>
                                        <input type="tel" id="mobile_number" name="mobile_number" 
                                               value="<?php echo htmlspecialchars($user_data['mobile_number'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="city"><i class="fas fa-city"></i> City</label>
                                        <select id="city" name="city" class="form-control" required>
                                            <option value="">Select City</option>
                                            <option value="CEBU CITY" <?php echo ($user_data['city'] ?? '') == 'CEBU CITY' ? 'selected' : ''; ?>>Cebu City</option>
                                            <option value="MANDAUE CITY" <?php echo ($user_data['city'] ?? '') == 'MANDAUE CITY' ? 'selected' : ''; ?>>Mandaue City</option>
                                            <option value="LAPU-LAPU CITY" <?php echo ($user_data['city'] ?? '') == 'LAPU-LAPU CITY' ? 'selected' : ''; ?>>Lapu-Lapu City</option>
                                            <option value="TALISAY CITY" <?php echo ($user_data['city'] ?? '') == 'TALISAY CITY' ? 'selected' : ''; ?>>Talisay City</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="barangay"><i class="fas fa-map-marker-alt"></i> Barangay</label>
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
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="zone"><i class="fas fa-home"></i> Zone/Purok</label>
                                        <input type="text" id="zone" name="zone" 
                                               value="<?php echo htmlspecialchars($user_data['zone'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="address"><i class="fas fa-road"></i> House No., Street Name</label>
                                        <input type="text" id="address" name="address" 
                                               value="<?php echo htmlspecialchars($user_data['address'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="notification_channel"><i class="fas fa-bell"></i> Notification Channel</label>
                                    <select id="notification_channel" name="notification_channel" required>
                                        <option value="Email" <?php echo ($user_data['notification_channel'] ?? '') == 'Email' ? 'selected' : ''; ?>>Email</option>
                                        <option value="SMS" <?php echo ($user_data['notification_channel'] ?? '') == 'SMS' ? 'selected' : ''; ?>>SMS</option>
                                        <option value="Both" <?php echo ($user_data['notification_channel'] ?? '') == 'Both' ? 'selected' : ''; ?>>Both</option>
                                    </select>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Profile
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="profile-card">
                        <div class="card-header">
                            <h2><i class="fas fa-lock"></i> Change Password</h2>
                        </div>
                        <div class="card-body">
                            <form method="POST" class="password-form">
                                <input type="hidden" name="change_password" value="1">
                                
                                <div class="form-group">
                                    <label for="current_password"><i class="fas fa-key"></i> Current Password</label>
                                    <input type="password" id="current_password" name="current_password" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="new_password"><i class="fas fa-lock"></i> New Password</label>
                                    <input type="password" id="new_password" name="new_password" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="confirm_password"><i class="fas fa-check-circle"></i> Confirm New Password</label>
                                    <input type="password" id="confirm_password" name="confirm_password" required>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-shield-alt"></i> Change Password
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="profile-card">
                        <div class="card-header">
                            <h2><i class="fas fa-clipboard-check"></i> Application Status</h2>
                        </div>
                        <div class="card-body">
                            <?php if($admin_application_status == 'pending'): ?>
                            <div class="worker-status pending">
                                <div class="status-icon pending-icon">
                                    <i class="fas fa-user-shield"></i>
                                </div>
                                <h3>Application Pending</h3>
                                <p>Your admin application is awaiting review by barangay administrators.</p>
                                <div class="status-details">
                                    <div class="detail-item">
                                        <i class="far fa-calendar"></i>
                                        <span><strong>Applied:</strong> <?php echo date('M d, Y', strtotime($user_data['created_at'])); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-info-circle"></i>
                                        <span><strong>Status:</strong> <span class="status-badge pending-badge">Pending Review</span></span>
                                    </div>
                                </div>
                                <p class="status-note"><i class="fas fa-exclamation-circle"></i> We will contact you via email or phone once your application is processed.</p>
                            </div>
                            <?php else: ?>
                            <div class="worker-status not-applied">
                                <div class="status-icon regular-icon">
                                    <i class="fas fa-user-clock"></i>
                                </div>
                                <h3>Regular User</h3>
                                <p>To become a barangay administrator, register and get approved.</p>
                                <div class="benefits">
                                    <h4><i class="fas fa-star"></i> Admin Benefits:</h4>
                                    <ul>
                                        <li><i class="fas fa-check"></i> Access to barangay dashboard</li>
                                        <li><i class="fas fa-check"></i> Manage pickup schedules</li>
                                        <li><i class="fas fa-check"></i> Approve worker/driver applications</li>
                                        <li><i class="fas fa-check"></i> Generate reports and analytics</li>
                                        <li><i class="fas fa-check"></i> Oversee waste management operations</li>
                                    </ul>
                                </div>
                                <a href="barangay_register.php" class="btn btn-worker">
                                    <i class="fas fa-user-plus"></i> Register as Admin
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