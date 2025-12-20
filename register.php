<?php
require_once "config.php";

if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    header("location: dashboard.php");
    exit;
}

$full_name = $email = $mobile_number = $city = $barangay = $zone = $address = $password = $confirm_password = $notification_channel = "";
$full_name_err = $email_err = $mobile_number_err = $barangay_err = $password_err = $confirm_password_err = "";
$account_type = "user"; // Default to user
$license_number = $vehicle_type = $vehicle_plate = "";

if($_SERVER["REQUEST_METHOD"] == "POST"){

    if(empty(trim($_POST["full_name"]))){
        $full_name_err = "Please enter your full name.";
    } else{
        $full_name = trim($_POST["full_name"]);
    }
    
    if(empty(trim($_POST["email"]))){
        $email_err = "Please enter an email address.";
    } else{
        $sql = "SELECT id FROM users WHERE email = :email";
        
        if($stmt = $pdo->prepare($sql)){
            $stmt->bindParam(":email", $param_email, PDO::PARAM_STR);
            $param_email = trim($_POST["email"]);
            
            if($stmt->execute()){
                if($stmt->rowCount() == 1){
                    $email_err = "This email is already taken.";
                } else{
                    $email = trim($_POST["email"]);
                }
            } else{
                echo "Oops! Something went wrong. Please try again later.";
            }
            unset($stmt);
        }
    }
    
    if(!empty(trim($_POST["mobile_number"]))){
        $mobile_number = trim($_POST["mobile_number"]);
    }
    
    $city = trim($_POST["city"]);

    if(empty(trim($_POST["barangay"]))){
        $barangay_err = "Please select your barangay.";
    } else{
        $barangay = trim($_POST["barangay"]);
    }
    
    $zone = trim($_POST["zone"]);
    $address = trim($_POST["address"]);
    $notification_channel = trim($_POST["notification_channel"]);
    
    // NEW: Account type
    $account_type = isset($_POST["account_type"]) ? trim($_POST["account_type"]) : "user";
    
    // NEW: Driver fields
    $license_number = isset($_POST["license_number"]) ? trim($_POST["license_number"]) : "";
    $vehicle_type = isset($_POST["vehicle_type"]) ? trim($_POST["vehicle_type"]) : "";
    $vehicle_plate = isset($_POST["vehicle_plate"]) ? trim($_POST["vehicle_plate"]) : "";

    if(empty(trim($_POST["password"]))){
        $password_err = "Please enter a password.";     
    } elseif(strlen(trim($_POST["password"])) < 6){
        $password_err = "Password must have at least 6 characters.";
    } else{
        $password = trim($_POST["password"]);
    }
    
    if(empty(trim($_POST["confirm_password"]))){
        $confirm_password_err = "Please confirm password.";     
    } else{
        $confirm_password = trim($_POST["confirm_password"]);
        if(empty($password_err) && ($password != $confirm_password)){
            $confirm_password_err = "Password did not match.";
        }
    }
    
    // NEW: Driver validation
    if($account_type === "driver" && empty($license_number)) {
        $license_err = "Driver's license number is required for drivers.";
    }
    
    if(empty($full_name_err) && empty($email_err) && empty($barangay_err) && empty($password_err) && empty($confirm_password_err)){
        
        // NEW: Determine user_type based on account_type
        $user_type = ($account_type === "driver") ? "driver_pending" : "user";
        
        $sql = "INSERT INTO users (full_name, email, mobile_number, city, barangay, zone, address, notification_channel, password, user_type) 
                VALUES (:full_name, :email, :mobile_number, :city, :barangay, :zone, :address, :notification_channel, :password, :user_type)";
         
        if($stmt = $pdo->prepare($sql)){
            $stmt->bindParam(":full_name", $param_full_name, PDO::PARAM_STR);
            $stmt->bindParam(":email", $param_email, PDO::PARAM_STR);
            $stmt->bindParam(":mobile_number", $param_mobile_number, PDO::PARAM_STR);
            $stmt->bindParam(":city", $param_city, PDO::PARAM_STR);
            $stmt->bindParam(":barangay", $param_barangay, PDO::PARAM_STR);
            $stmt->bindParam(":zone", $param_zone, PDO::PARAM_STR);
            $stmt->bindParam(":address", $param_address, PDO::PARAM_STR);
            $stmt->bindParam(":notification_channel", $param_notification_channel, PDO::PARAM_STR);
            $stmt->bindParam(":password", $param_password, PDO::PARAM_STR);
            $stmt->bindParam(":user_type", $user_type, PDO::PARAM_STR);
            
            $param_full_name = $full_name;
            $param_email = $email;
            $param_mobile_number = $mobile_number;
            $param_city = $city;
            $param_barangay = $barangay;
            $param_zone = $zone;
            $param_address = $address;
            $param_notification_channel = $notification_channel;
            $param_password = password_hash($password, PASSWORD_DEFAULT);
            
            if($stmt->execute()){
                $user_id = $pdo->lastInsertId();
                
                // NEW: If driver, create driver application
                if($account_type === "driver") {
                    $driver_sql = "INSERT INTO driver_applications (user_id, license_number, vehicle_type, vehicle_plate, status) 
                                   VALUES (:user_id, :license_number, :vehicle_type, :vehicle_plate, 'pending')";
                    
                    if($driver_stmt = $pdo->prepare($driver_sql)) {
                        $driver_stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
                        $driver_stmt->bindParam(":license_number", $license_number, PDO::PARAM_STR);
                        $driver_stmt->bindParam(":vehicle_type", $vehicle_type, PDO::PARAM_STR);
                        $driver_stmt->bindParam(":vehicle_plate", $vehicle_plate, PDO::PARAM_STR);
                        $driver_stmt->execute();
                    }
                }
                
                // Auto-login for users only
                if($account_type === "user") {
                    $_SESSION["loggedin"] = true;
                    $_SESSION["id"] = $user_id;
                    $_SESSION["full_name"] = $full_name;
                    $_SESSION["user_type"] = "user";
                    header("location: dashboard.php");
                } else {
                    // For drivers, show success message
                    $success_message = "Driver application submitted successfully! Your account is pending admin approval.";
                }
                
                if(!isset($success_message)) {
                    header("location: login.php");
                }
                exit;
            } else{
                echo "Something went wrong. Please try again later.";
            }

            unset($stmt);
        }
    }
    
    unset($pdo);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - TrashTrace</title>
    <link rel="stylesheet" href="css/register.css">
    <link href="node_modules/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="two-column-container">
        <!-- LEFT COLUMN - Image Placeholder -->
        <div class="left-column">
            <div class="image-placeholder">
                <div class="logo"> </div>
                <div class="image-content">
                    <h1> </h1>
                    <p> </p>
              
                </div>
                <!-- You can replace this with an actual image -->
                <div class="placeholder-graphic">
                    <div class="graphic-circle"></div>
                    <div class="graphic-square"></div>
                    <div class="graphic-triangle"></div>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN - Registration Form -->
        <div class="right-column">
            <div class="register-form-container">
                <div class="form-header">
                    <h2>Create your account</h2>
                    <p>Manage your waste effectively.</p>
                </div>
                
                <?php if(isset($success_message)): ?>
                    <div class="success-message">
                        ✅ <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <!-- Personal Information -->
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input type="text" id="full_name" name="full_name" class="form-control <?php echo (!empty($full_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($full_name); ?>">
                        <span class="invalid-feedback"><?php echo $full_name_err; ?></span>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email address (Email preferred)</label>
                        <input type="email" id="email" name="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($email); ?>">
                        <span class="invalid-feedback"><?php echo $email_err; ?></span>
                    </div>
                    
                    <div class="form-group">
                        <label for="mobile_number">Mobile Number</label>
                        <input type="text" id="mobile_number" name="mobile_number" class="form-control" value="<?php echo htmlspecialchars($mobile_number); ?>">
                    </div>
                    
                    <!-- Location -->
                    <div class="form-group">
                        <label for="city">Select City</label>
                        <select id="city" name="city" class="form-control" required>
                            <option value="CEBU CITY">Cebu City</option>
                            <option value="MANDAUE CITY">Mandaue City</option>
                            <option value="LAPU-LAPU CITY">Lapu-Lapu City</option>
                            <option value="TALISAY CITY">Talisay City</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="barangay">Select Barangay</label>
                        <select id="barangay" name="barangay" class="form-control <?php echo (!empty($barangay_err)) ? 'is-invalid' : ''; ?>" required>
                            <option value="">Select Barangay</option>
                        </select>
                        <span class="invalid-feedback"><?php echo $barangay_err; ?></span>
                    </div>
                    
                    <div class="form-group">
                        <label for="zone">Zone/Purok</label>
                        <input type="text" id="zone" name="zone" class="form-control" value="<?php echo htmlspecialchars($zone); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="address">House No., Street Name</label>
                        <input type="text" id="address" name="address" class="form-control" value="<?php echo htmlspecialchars($address); ?>">
                    </div>
                    
                    <!-- NEW: Account Type Selection -->
                    <div class="role-section">
                        <h3>Register as:</h3>
                        <div class="role-options">
                            <div class="role-card <?php echo ($account_type === 'user') ? 'selected' : ''; ?>" onclick="selectRole('user')">
                                <input type="radio" id="role_user" name="account_type" value="user" <?php echo ($account_type === 'user') ? 'checked' : ''; ?>>
                                <div class="role-icon user">U</div>
                                <div class="role-title">Resident User</div>
                                <div class="role-desc">Schedule pickups for your household</div>
                            </div>
                            
                            <div class="role-card <?php echo ($account_type === 'driver') ? 'selected' : ''; ?>" onclick="selectRole('driver')">
                                <input type="radio" id="role_driver" name="account_type" value="driver" <?php echo ($account_type === 'driver') ? 'checked' : ''; ?>>
                                <div class="role-icon driver">D</div>
                                <div class="role-title">Driver</div>
                                <div class="role-desc">Apply to be a garbage collection driver</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- NEW: Driver Fields -->
                    <div id="driverFields" class="driver-fields">
                        <h4>Driver Information</h4>
                        <div class="form-group">
                            <label for="license_number">Driver's License Number *</label>
                            <input type="text" id="license_number" name="license_number" class="form-control" value="<?php echo htmlspecialchars($license_number); ?>" placeholder="Enter license number">
                            <?php if(isset($license_err)): ?>
                                <span class="invalid-feedback"><?php echo $license_err; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="vehicle_type">Vehicle Type</label>
                            <input type="text" id="vehicle_type" name="vehicle_type" class="form-control" value="<?php echo htmlspecialchars($vehicle_type); ?>" placeholder="e.g., Truck, Van">
                        </div>
                        
                        <div class="form-group">
                            <label for="vehicle_plate">Vehicle Plate Number</label>
                            <input type="text" id="vehicle_plate" name="vehicle_plate" class="form-control" value="<?php echo htmlspecialchars($vehicle_plate); ?>" placeholder="ABC 123">
                        </div>
                    </div>
                    
                    <!-- Security -->
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($password); ?>">
                        <span class="invalid-feedback"><?php echo $password_err; ?></span>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($confirm_password); ?>">
                        <span class="invalid-feedback"><?php echo $confirm_password_err; ?></span>
                    </div>
                    
                    <!-- Notification - FIXED RADIO BUTTONS -->
                    <div class="form-group">
                        <label>Preferred Notification Channel</label>
                        <div class="radio-group">
                            <label class="radio-option">
                                <input type="radio" name="notification_channel" value="SMS" <?php echo ($notification_channel === 'SMS' || empty($notification_channel)) ? 'checked' : ''; ?>>
                                <span class="radio-checkmark"></span>
                                <span class="radio-label">SMS</span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="notification_channel" value="Email" <?php echo ($notification_channel === 'Email') ? 'checked' : ''; ?>>
                                <span class="radio-checkmark"></span>
                                <span class="radio-label">Email</span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="notification_channel" value="Both" <?php echo ($notification_channel === 'Both') ? 'checked' : ''; ?>>
                                <span class="radio-checkmark"></span>
                                <span class="radio-label">Both</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Register</button>
                    </div>
                    
                    <div class="form-links">
                        <p>Already have an account? <a href="login.php">Login</a></p>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function selectRole(role) {
            // Update radio button
            document.getElementById('role_' + role).checked = true;
            
            // Update visual selection
            document.querySelectorAll('.role-card').forEach(card => {
                card.classList.remove('selected');
            });
            document.getElementById('role_' + role).closest('.role-card').classList.add('selected');
            
            // Show/hide driver fields
            const driverFields = document.getElementById('driverFields');
            if(role === 'driver') {
                driverFields.style.display = 'block';
                // Make license number required
                document.getElementById('license_number').required = true;
            } else {
                driverFields.style.display = 'none';
                // Make license number not required
                document.getElementById('license_number').required = false;
            }
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            const selectedRole = document.querySelector('input[name="account_type"]:checked').value;
            selectRole(selectedRole);
        });
    </script>
    <script src="js/register.js"></script>
</body>
</html> 
