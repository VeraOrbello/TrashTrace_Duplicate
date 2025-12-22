<?php
session_start();
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
$driver_email = $_SESSION["email"];
$driver_phone = $_SESSION["phone"] ?? 'Not set';
$driver_address = $_SESSION["address"] ?? 'Not set';

// Get driver statistics
$driver_stats = [
    'total_collections' => 0,
    'total_earnings' => 0,
    'rating' => 4.8,
    'completed_routes' => 0,
    'vehicle' => 'Truck #TR-001',
    'license' => 'DL-1234567',
    'hire_date' => date('Y-m-d', strtotime('-6 months')),
    'status' => 'active'
];

try {
    // Get total collections
    $sql = "SELECT COUNT(*) as total FROM collections WHERE driver_id = ? AND status = 'completed'";
    if($stmt = mysqli_prepare($link, $sql)){
        mysqli_stmt_bind_param($stmt, "i", $driver_id);
        if(mysqli_stmt_execute($stmt)){
            $result = mysqli_stmt_get_result($stmt);
            if($row = mysqli_fetch_assoc($result)){
                $driver_stats['total_collections'] = $row['total'];
            }
        }
        mysqli_stmt_close($stmt);
    }
    
    // Get total earnings
    $sql = "SELECT SUM(payment_amount) as total FROM collections WHERE driver_id = ? AND status = 'completed'";
    if($stmt = mysqli_prepare($link, $sql)){
        mysqli_stmt_bind_param($stmt, "i", $driver_id);
        if(mysqli_stmt_execute($stmt)){
            $result = mysqli_stmt_get_result($stmt);
            if($row = mysqli_fetch_assoc($result)){
                $driver_stats['total_earnings'] = $row['total'] ?? 0;
            }
        }
        mysqli_stmt_close($stmt);
    }
    
    // Get completed routes
    $sql = "SELECT COUNT(*) as total FROM assignments WHERE driver_id = ? AND status = 'completed'";
    if($stmt = mysqli_prepare($link, $sql)){
        mysqli_stmt_bind_param($stmt, "i", $driver_id);
        if(mysqli_stmt_execute($stmt)){
            $result = mysqli_stmt_get_result($stmt);
            if($row = mysqli_fetch_assoc($result)){
                $driver_stats['completed_routes'] = $row['total'];
            }
        }
        mysqli_stmt_close($stmt);
    }
    
} catch(Exception $e) {
    error_log("Profile stats error: " . $e->getMessage());
    // Use default values
}

// Handle profile update
$update_message = '';
if($_SERVER["REQUEST_METHOD"] == "POST"){
    if(isset($_POST['update_profile'])){
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        
        $sql = "UPDATE users SET phone = ?, address = ? WHERE id = ?";
        if($stmt = mysqli_prepare($link, $sql)){
            mysqli_stmt_bind_param($stmt, "ssi", $phone, $address, $driver_id);
            if(mysqli_stmt_execute($stmt)){
                $_SESSION['phone'] = $phone;
                $_SESSION['address'] = $address;
                $driver_phone = $phone;
                $driver_address = $address;
                $update_message = '<div style="padding: 10px; background: #e8f5e9; color: #2e7d32; border-radius: 8px; margin-bottom: 20px;">Profile updated successfully!</div>';
            } else {
                $update_message = '<div style="padding: 10px; background: #ffebee; color: #c62828; border-radius: 8px; margin-bottom: 20px;">Error updating profile.</div>';
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    if(isset($_POST['change_password'])){
        $current_password = trim($_POST['current_password']);
        $new_password = trim($_POST['new_password']);
        $confirm_password = trim($_POST['confirm_password']);
        
        // Verify current password
        $sql = "SELECT password FROM users WHERE id = ?";
        if($stmt = mysqli_prepare($link, $sql)){
            mysqli_stmt_bind_param($stmt, "i", $driver_id);
            if(mysqli_stmt_execute($stmt)){
                mysqli_stmt_store_result($stmt);
                if(mysqli_stmt_num_rows($stmt) == 1){
                    mysqli_stmt_bind_result($stmt, $hashed_password);
                    mysqli_stmt_fetch($stmt);
                    
                    if(password_verify($current_password, $hashed_password)){
                        if($new_password == $confirm_password){
                            $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                            $sql = "UPDATE users SET password = ? WHERE id = ?";
                            if($stmt2 = mysqli_prepare($link, $sql)){
                                mysqli_stmt_bind_param($stmt2, "si", $new_hashed_password, $driver_id);
                                if(mysqli_stmt_execute($stmt2)){
                                    $update_message = '<div style="padding: 10px; background: #e8f5e9; color: #2e7d32; border-radius: 8px; margin-bottom: 20px;">Password changed successfully!</div>';
                                } else {
                                    $update_message = '<div style="padding: 10px; background: #ffebee; color: #c62828; border-radius: 8px; margin-bottom: 20px;">Error changing password.</div>';
                                }
                                mysqli_stmt_close($stmt2);
                            }
                        } else {
                            $update_message = '<div style="padding: 10px; background: #ffebee; color: #c62828; border-radius: 8px; margin-bottom: 20px;">New passwords do not match.</div>';
                        }
                    } else {
                        $update_message = '<div style="padding: 10px; background: #ffebee; color: #c62828; border-radius: 8px; margin-bottom: 20px;">Current password is incorrect.</div>';
                    }
                }
            }
            mysqli_stmt_close($stmt);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - TrashTrace Driver</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Consolidated CSS -->
    <link rel="stylesheet" href="../css/driver/master-styles.css">
    
    <style>
        /* Additional styles for profile page */
        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: #4caf50;
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        
        @media (max-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .main-content {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }
        
        .dashboard-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .dashboard-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.12);
        }
        
        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e8f5e9;
            background: #f8fdf9;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header h3 {
            margin: 0;
            color: #2c3e50;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4caf50, #2e7d32);
            color: white;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #43a047, #1b5e20);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(76, 175, 80, 0.2);
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid #4caf50;
            color: #4caf50;
        }
        
        .btn-outline:hover {
            background: #e8f5e9;
            transform: translateY(-1px);
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-title {
            color: #2c3e50;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.8rem;
        }
        
        .page-subtitle {
            color: #666;
            font-size: 1rem;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Header Section -->
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
                            <li><a href="assignments.php" class="nav-link"><i class="fas fa-tasks"></i> <span>Assignments</span></a></li>
                            <li><a href="routes.php" class="nav-link"><i class="fas fa-route"></i> <span>Routes</span></a></li>
                            <li><a href="collections.php" class="nav-link"><i class="fas fa-trash"></i> <span>Collections</span></a></li>
                            <li><a href="earnings.php" class="nav-link"><i class="fas fa-money-bill-wave"></i> <span>Earnings</span></a></li>
                         
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
                        <i class="fas fa-user"></i>
                        Driver Profile
                    </h1>
                    <p class="page-subtitle">Manage your profile information, settings, and view your driver statistics.</p>
                </div>
                
                <?php echo $update_message; ?>
                
                <div class="dashboard-grid">
                    <!-- Profile Information -->
                    <div class="main-content">
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h3><i class="fas fa-id-card"></i> Profile Information</h3>
                                <button class="btn btn-outline" id="editProfileBtn">
                                    <i class="fas fa-edit"></i> Edit Profile
                                </button>
                            </div>
                            
                            <div id="profileView">
                                <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 30px; padding: 0 24px; padding-top: 20px;">
                                    <div style="width: 100px; height: 100px; background: linear-gradient(135deg, #4caf50, #2e7d32); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; color: white; font-weight: bold;">
                                        <?php echo strtoupper(substr($driver_name, 0, 1)); ?>
                                    </div>
                                    <div>
                                        <h2 style="color: #2c3e50; margin-bottom: 5px;"><?php echo htmlspecialchars($driver_name); ?></h2>
                                        <p style="color: #666; margin-bottom: 10px;">Driver ID: #<?php echo str_pad($driver_id, 4, '0', STR_PAD_LEFT); ?></p>
                                        <div style="display: flex; gap: 15px;">
                                            <div style="display: flex; align-items: center; gap: 5px; color: #4caf50; font-weight: 500;">
                                                <i class="fas fa-star"></i>
                                                <?php echo number_format($driver_stats['rating'], 1); ?> Rating
                                            </div>
                                            <div style="display: flex; align-items: center; gap: 5px; color: #2196f3; font-weight: 500;">
                                                <i class="fas fa-check-circle"></i>
                                                <?php echo $driver_stats['completed_routes']; ?> Routes
                                            </div>
                                            <div style="display: flex; align-items: center; gap: 5px; color: #ff9800; font-weight: 500;">
                                                <i class="fas fa-trophy"></i>
                                                Top Driver
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; padding: 0 24px; padding-bottom: 20px;">
                                    <div style="padding: 15px; background: #f8fdf9; border-radius: 10px;">
                                        <div style="font-size: 0.9rem; color: #666; margin-bottom: 5px;">Email</div>
                                        <div style="font-weight: 600; color: #333;"><?php echo htmlspecialchars($driver_email); ?></div>
                                    </div>
                                    <div style="padding: 15px; background: #f8fdf9; border-radius: 10px;">
                                        <div style="font-size: 0.9rem; color: #666; margin-bottom: 5px;">Phone</div>
                                        <div style="font-weight: 600; color: #333;"><?php echo htmlspecialchars($driver_phone); ?></div>
                                    </div>
                                    <div style="padding: 15px; background: #f8fdf9; border-radius: 10px;">
                                        <div style="font-size: 0.9rem; color: #666; margin-bottom: 5px;">Address</div>
                                        <div style="font-weight: 600; color: #333;"><?php echo htmlspecialchars($driver_address); ?></div>
                                    </div>
                                    <div style="padding: 15px; background: #f8fdf9; border-radius: 10px;">
                                        <div style="font-size: 0.9rem; color: #666; margin-bottom: 5px;">Status</div>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <div style="width: 8px; height: 8px; background: #4caf50; border-radius: 50%;"></div>
                                            <span style="font-weight: 600; color: #4caf50;">Active</span>
                                        </div>
                                    </div>
                                    <div style="padding: 15px; background: #f8fdf9; border-radius: 10px;">
                                        <div style="font-size: 0.9rem; color: #666; margin-bottom: 5px;">Driver License</div>
                                        <div style="font-weight: 600; color: #333;"><?php echo htmlspecialchars($driver_stats['license']); ?></div>
                                    </div>
                                    <div style="padding: 15px; background: #f8fdf9; border-radius: 10px;">
                                        <div style="font-size: 0.9rem; color: #666; margin-bottom: 5px;">Vehicle</div>
                                        <div style="font-weight: 600; color: #333;"><?php echo htmlspecialchars($driver_stats['vehicle']); ?></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="profileEdit" style="display: none; padding: 24px;">
                                <form method="POST" action="">
                                    <input type="hidden" name="update_profile" value="1">
                                    
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                                        <div>
                                            <label style="display: block; font-size: 0.9rem; color: #666; margin-bottom: 8px;">Full Name</label>
                                            <input type="text" value="<?php echo htmlspecialchars($driver_name); ?>" disabled style="width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 8px; background: #f5f5f5;">
                                            <small style="color: #999; font-size: 0.8rem;">Name can only be changed by admin</small>
                                        </div>
                                        <div>
                                            <label style="display: block; font-size: 0.9rem; color: #666; margin-bottom: 8px;">Email</label>
                                            <input type="email" value="<?php echo htmlspecialchars($driver_email); ?>" disabled style="width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 8px; background: #f5f5f5;">
                                        </div>
                                        <div>
                                            <label style="display: block; font-size: 0.9rem; color: #666; margin-bottom: 8px;">Phone Number *</label>
                                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($driver_phone); ?>" required style="width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 8px;">
                                        </div>
                                        <div>
                                            <label style="display: block; font-size: 0.9rem; color: #666; margin-bottom: 8px;">Address *</label>
                                            <textarea name="address" required style="width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 8px; height: 100px; resize: vertical;"><?php echo htmlspecialchars($driver_address); ?></textarea>
                                        </div>
                                    </div>
                                    
                                    <div style="display: flex; gap: 10px;">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Save Changes
                                        </button>
                                        <button type="button" class="btn btn-outline" id="cancelEditBtn">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Change Password -->
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h3><i class="fas fa-lock"></i> Change Password</h3>
                            </div>
                            
                            <form method="POST" action="" style="margin-top: 20px; padding: 0 24px 24px;">
                                <input type="hidden" name="change_password" value="1">
                                
                                <div style="display: grid; grid-template-columns: 1fr; gap: 15px; max-width: 400px;">
                                    <div>
                                        <label style="display: block; font-size: 0.9rem; color: #666; margin-bottom: 8px;">Current Password *</label>
                                        <input type="password" name="current_password" required style="width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 8px;">
                                    </div>
                                    <div>
                                        <label style="display: block; font-size: 0.9rem; color: #666; margin-bottom: 8px;">New Password *</label>
                                        <input type="password" name="new_password" required style="width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 8px;">
                                    </div>
                                    <div>
                                        <label style="display: block; font-size: 0.9rem; color: #666; margin-bottom: 8px;">Confirm New Password *</label>
                                        <input type="password" name="confirm_password" required style="width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 8px;">
                                    </div>
                                </div>
                                
                                <div style="margin-top: 20px;">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-key"></i> Change Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Sidebar -->
                    <div class="sidebar">
                        <!-- Driver Statistics -->
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h3><i class="fas fa-chart-bar"></i> Driver Statistics</h3>
                            </div>
                            
                            <div style="padding: 20px 24px;">
                                <div style="display: flex; flex-direction: column; gap: 15px;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 15px; background: #f8fdf9; border-radius: 10px;">
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <div style="width: 40px; height: 40px; background: #e8f5e9; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #2e7d32;">
                                                <i class="fas fa-trash-alt"></i>
                                            </div>
                                            <div>
                                                <div style="font-size: 0.9rem; color: #666;">Total Collections</div>
                                                <div style="font-size: 1.2rem; font-weight: 600; color: #2c3e50;"><?php echo $driver_stats['total_collections']; ?></div>
                                            </div>
                                        </div>
                                        <div style="font-size: 0.8rem; color: #4caf50; background: #e8f5e9; padding: 4px 10px; border-radius: 20px;">
                                            <i class="fas fa-arrow-up"></i> 12%
                                        </div>
                                    </div>
                                    
                                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 15px; background: #f8fdf9; border-radius: 10px;">
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <div style="width: 40px; height: 40px; background: #e3f2fd; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #1976d2;">
                                                <i class="fas fa-money-bill-wave"></i>
                                            </div>
                                            <div>
                                                <div style="font-size: 0.9rem; color: #666;">Total Earnings</div>
                                                <div style="font-size: 1.2rem; font-weight: 600; color: #2c3e50;">₱<?php echo number_format($driver_stats['total_earnings'], 2); ?></div>
                                            </div>
                                        </div>
                                        <div style="font-size: 0.8rem; color: #4caf50; background: #e8f5e9; padding: 4px 10px; border-radius: 20px;">
                                            <i class="fas fa-arrow-up"></i> 8%
                                        </div>
                                    </div>
                                    
                                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 15px; background: #f8fdf9; border-radius: 10px;">
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <div style="width: 40px; height: 40px; background: #fff3e0; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #ef6c00;">
                                                <i class="fas fa-star"></i>
                                            </div>
                                            <div>
                                                <div style="font-size: 0.9rem; color: #666;">Rating</div>
                                                <div style="font-size: 1.2rem; font-weight: 600; color: #2c3e50;"><?php echo number_format($driver_stats['rating'], 1); ?>/5.0</div>
                                            </div>
                                        </div>
                                        <div style="font-size: 0.8rem; color: #4caf50; background: #e8f5e9; padding: 4px 10px; border-radius: 20px;">
                                            47 reviews
                                        </div>
                                    </div>
                                    
                                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 15px; background: #f8fdf9; border-radius: 10px;">
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <div style="width: 40px; height: 40px; background: #f3e5f5; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #7b1fa2;">
                                                <i class="fas fa-calendar-alt"></i>
                                            </div>
                                            <div>
                                                <div style="font-size: 0.9rem; color: #666;">Member Since</div>
                                                <div style="font-size: 1.2rem; font-weight: 600; color: #2c3e50;"><?php echo date('M Y', strtotime($driver_stats['hire_date'])); ?></div>
                                            </div>
                                        </div>
                                        <div style="font-size: 0.8rem; color: #666; padding: 4px 10px; border-radius: 20px;">
                                            <?php echo date_diff(date_create($driver_stats['hire_date']), date_create('today'))->format('%m months'); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Account Settings -->
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h3><i class="fas fa-cog"></i> Account Settings</h3>
                            </div>
                            
                            <div style="padding: 20px 24px;">
                                <div style="display: flex; flex-direction: column; gap: 10px;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: #f8fdf9; border-radius: 8px;">
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <i class="fas fa-bell" style="color: #4caf50;"></i>
                                            <span style="font-size: 0.9rem; color: #333;">Notifications</span>
                                        </div>
                                        <label class="switch">
                                            <input type="checkbox" checked>
                                            <span class="slider"></span>
                                        </label>
                                    </div>
                                    
                                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: #f8fdf9; border-radius: 8px;">
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <i class="fas fa-map-marker-alt" style="color: #2196f3;"></i>
                                            <span style="font-size: 0.9rem; color: #333;">Location Sharing</span>
                                        </div>
                                        <label class="switch">
                                            <input type="checkbox" checked>
                                            <span class="slider"></span>
                                        </label>
                                    </div>
                                    
                                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: #f8fdf9; border-radius: 8px;">
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <i class="fas fa-language" style="color: #ff9800;"></i>
                                            <span style="font-size: 0.9rem; color: #333;">Language</span>
                                        </div>
                                        <select style="padding: 5px 10px; border: 1px solid #e0e0e0; border-radius: 6px; font-size: 0.9rem;">
                                            <option>English</option>
                                            <option>Filipino</option>
                                        </select>
                                    </div>
                                    
                                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: #f8fdf9; border-radius: 8px;">
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <i class="fas fa-moon" style="color: #9c27b0;"></i>
                                            <span style="font-size: 0.9rem; color: #333;">Dark Mode</span>
                                        </div>
                                        <label class="switch">
                                            <input type="checkbox" id="darkModeToggle">
                                            <span class="slider"></span>
                                        </label>
                                    </div>
                                </div>
                                
                                <div style="margin-top: 20px; display: flex; flex-direction: column; gap: 8px;">
                                    <button class="btn btn-outline" id="backupData">
                                        <i class="fas fa-download"></i> Backup Data
                                    </button>
                                    <button class="btn btn-outline" id="deleteAccount">
                                        <i class="fas fa-trash-alt"></i> Delete Account
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Links -->
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h3><i class="fas fa-link"></i> Quick Links</h3>
                            </div>
                            
                            <div style="padding: 20px 24px;">
                                <div style="display: flex; flex-direction: column; gap: 10px;">
                                    <a href="history.php" class="btn btn-outline" style="text-align: left; justify-content: flex-start;">
                                        <i class="fas fa-history"></i> Collection History
                                    </a>
                                    <a href="earnings.php" class="btn btn-outline" style="text-align: left; justify-content: flex-start;">
                                        <i class="fas fa-chart-line"></i> Earnings Report
                                    </a>
                                    <a href="routes.php" class="btn btn-outline" style="text-align: left; justify-content: flex-start;">
                                        <i class="fas fa-map-marked-alt"></i> Saved Routes
                                    </a>
                                    <a href="../help.php" class="btn btn-outline" style="text-align: left; justify-content: flex-start;">
                                        <i class="fas fa-question-circle"></i> Help & Support
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle profile edit mode
            const editBtn = document.getElementById('editProfileBtn');
            const cancelBtn = document.getElementById('cancelEditBtn');
            const profileView = document.getElementById('profileView');
            const profileEdit = document.getElementById('profileEdit');
            
            if (editBtn && cancelBtn && profileView && profileEdit) {
                editBtn.addEventListener('click', function() {
                    profileView.style.display = 'none';
                    profileEdit.style.display = 'block';
                    editBtn.style.display = 'none';
                });
                
                cancelBtn.addEventListener('click', function() {
                    profileView.style.display = 'block';
                    profileEdit.style.display = 'none';
                    editBtn.style.display = 'flex';
                });
            }
            
            // Dark mode toggle
            const darkModeToggle = document.getElementById('darkModeToggle');
            if (darkModeToggle) {
                darkModeToggle.addEventListener('change', function() {
                    if(this.checked) {
                        document.body.style.backgroundColor = '#1a1a1a';
                        document.body.style.color = '#ffffff';
                        // You would need to update all colors for dark mode
                    } else {
                        document.body.style.backgroundColor = '';
                        document.body.style.color = '';
                    }
                });
            }
            
            // Backup Data
            const backupBtn = document.getElementById('backupData');
            if (backupBtn) {
                backupBtn.addEventListener('click', function() {
                    if(confirm('Download your data backup? This may take a few moments.')) {
                        alert('Your data backup is being prepared. You will receive a download link via email.');
                    }
                });
            }
            
            // Delete Account
            const deleteBtn = document.getElementById('deleteAccount');
            if (deleteBtn) {
                deleteBtn.addEventListener('click', function() {
                    if(confirm('WARNING: This will permanently delete your account and all associated data. This action cannot be undone.\n\nType "DELETE" to confirm:')) {
                        const confirmation = prompt('Type "DELETE" to confirm account deletion:');
                        if(confirmation === 'DELETE') {
                            alert('Account deletion requested. Our team will contact you for verification.');
                        } else {
                            alert('Account deletion cancelled.');
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>