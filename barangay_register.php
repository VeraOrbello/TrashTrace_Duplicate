<?php
require_once "config.php";
require_once "php/barangay_data.php";

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

$user_id = $_SESSION["id"];
$user_data = [];
$success_message = '';
$error_message = '';

$sql = "SELECT * FROM users WHERE id = :id";
if($stmt = $pdo->prepare($sql)){
    $stmt->bindParam(":id", $user_id, PDO::PARAM_INT);
    if($stmt->execute()){
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    unset($stmt);
}

$cebuBarangays = getCebuBarangays();

// Check for success parameter
if(isset($_GET['success']) && $_GET['success'] == 1){
    $success_message = "Worker validation submitted successfully! We will contact you via email or phone once your validation is confirmed.";
}

// Check for existing worker application
$check_application_sql = "SELECT * FROM worker_applications WHERE user_id = :user_id AND status IN ('pending', 'reviewing')";
if($check_app_stmt = $pdo->prepare($check_application_sql)){
    $check_app_stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    if($check_app_stmt->execute()){
        if($check_app_stmt->rowCount() > 0){
            $existing_app = $check_app_stmt->fetch(PDO::FETCH_ASSOC);
            $error_message = "You already have a pending worker validation request. You cannot submit another request.";
        }
    }
    unset($check_app_stmt);
}

// Check if user is already a worker
if(isset($user_data['user_type']) && $user_data['user_type'] === 'admin'){
    $error_message = "You are already a validated worker. You cannot submit another validation request.";
}

if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["register_worker"]) && empty($error_message)){
    // Basic validation
    $required_fields = ['id_number', 'birthdate', 'contact_number', 'city', 'barangay', 'availability'];
    foreach($required_fields as $field){
        if(empty(trim($_POST[$field]))){
            $error_message = "Please fill all required fields.";
            break;
        }
    }
    
    if(empty($error_message)){
        $full_name = trim($user_data['full_name'] ?? '');
        $id_number = trim($_POST["id_number"]);
        $birthdate = trim($_POST["birthdate"]);
        $contact_number = trim($_POST["contact_number"]);
        $emergency_contact = trim($_POST["emergency_contact"] ?? '');
        $city = trim($_POST["city"]);
        $barangay = trim($_POST["barangay"]);
        $zone = trim($_POST["zone"] ?? '');
        $experience_years = isset($_POST["experience_years"]) ? (int)$_POST["experience_years"] : 0;
        $application_type = trim($_POST["application_type"] ?? 'admin');
        $license_number = trim($_POST["license_number"] ?? '');
        $vehicle_registration = trim($_POST["vehicle_registration"] ?? '');
        $availability = $_POST["availability"];
        $vehicle_access = isset($_POST["vehicle_access"]) ? $_POST["vehicle_access"] : 'No';
        $health_conditions = trim($_POST["health_conditions"] ?? '');
        $reason_application = trim($_POST["reason_application"] ?? '');
        
        // Check if ID number already exists
        $check_id_sql = "SELECT * FROM worker_applications WHERE id_number = :id_number";
        if($check_id_stmt = $pdo->prepare($check_id_sql)){
            $check_id_stmt->bindParam(":id_number", $id_number, PDO::PARAM_STR);
            if($check_id_stmt->execute()){
                if($check_id_stmt->rowCount() > 0){
                    $existing = $check_id_stmt->fetch(PDO::FETCH_ASSOC);
                    if($existing['user_id'] != $user_id){
                        $error_message = "This Worker ID Number is already registered by another user.";
                    }
                }
            }
            unset($check_id_stmt);
        }
        
        if(empty($error_message)){
            // Handle file upload - store path in a variable but don't use it if column doesn't exist
            $id_proof_path = null;
            if(isset($_FILES['id_proof']) && $_FILES['id_proof']['error'] === UPLOAD_ERR_OK){
                // Use a directory that we know is writable
                $baseDir = '/Applications/XAMPP/xamppfiles/temp/trash_trace_uploads/';
                
                // Create directory if it doesn't exist
                if(!is_dir($baseDir)){
                    if(!mkdir($baseDir, 0777, true)){
                        // Try one more location
                        $baseDir = sys_get_temp_dir() . '/trash_trace_uploads/';
                        if(!is_dir($baseDir)){
                            mkdir($baseDir, 0777, true);
                        }
                    }
                }
                
                if(is_dir($baseDir) && is_writable($baseDir)){
                    // Validate file
                    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
                    $file_type = mime_content_type($_FILES['id_proof']['tmp_name']);
                    
                    if(in_array($file_type, $allowed_types)){
                        // Sanitize filename
                        $original_name = basename($_FILES['id_proof']['name']);
                        $clean_name = preg_replace("/[^a-zA-Z0-9\._-]/", "_", $original_name);
                        $file_name = uniqid() . '_' . $clean_name;
                        $destination = $baseDir . $file_name;
                        
                        if(move_uploaded_file($_FILES['id_proof']['tmp_name'], $destination)){
                            // Store relative path
                            $id_proof_path = 'temp_uploads/' . $file_name;
                            $_SESSION['uploaded_files'][$file_name] = $destination;
                        } else {
                            $error_message = "Failed to move uploaded file. Error: " . error_get_last()['message'];
                        }
                    } else {
                        $error_message = "Invalid file type. Only JPG, PNG, and PDF files are allowed.";
                    }
                } else {
                    // Debug information
                    $error_message = "Upload directory is not writable. BaseDir: $baseDir, Is Dir: " . (is_dir($baseDir) ? 'Yes' : 'No') . ", Is Writable: " . (is_writable($baseDir) ? 'Yes' : 'No');
                }
            }
            
            if(empty($error_message)){
                try {
                    // First, let's check what columns exist by trying a simpler insert
                    $worker_sql = "INSERT INTO worker_applications 
                        (user_id, id_number, birthdate, contact_number, emergency_contact, 
                         city, barangay, zone, experience_years, availability, 
                         vehicle_access, health_conditions, reason_application, 
                         license_number, vehicle_registration, 
                         application_type, status, submitted_at) 
                        VALUES 
                        (:user_id, :id_number, :birthdate, :contact_number, :emergency_contact,
                         :city, :barangay, :zone, :experience_years, :availability,
                         :vehicle_access, :health_conditions, :reason_application,
                         :license_number, :vehicle_registration,
                         :application_type, 'pending', NOW())";
                    
                    if($worker_stmt = $pdo->prepare($worker_sql)){
                        $worker_stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
                        $worker_stmt->bindParam(":id_number", $id_number, PDO::PARAM_STR);
                        $worker_stmt->bindParam(":birthdate", $birthdate, PDO::PARAM_STR);
                        $worker_stmt->bindParam(":contact_number", $contact_number, PDO::PARAM_STR);
                        $worker_stmt->bindParam(":emergency_contact", $emergency_contact, PDO::PARAM_STR);
                        $worker_stmt->bindParam(":city", $city, PDO::PARAM_STR);
                        $worker_stmt->bindParam(":barangay", $barangay, PDO::PARAM_STR);
                        $worker_stmt->bindParam(":zone", $zone, PDO::PARAM_STR);
                        $worker_stmt->bindParam(":experience_years", $experience_years, PDO::PARAM_INT);
                        $worker_stmt->bindParam(":availability", $availability, PDO::PARAM_STR);
                        $worker_stmt->bindParam(":vehicle_access", $vehicle_access, PDO::PARAM_STR);
                        $worker_stmt->bindParam(":health_conditions", $health_conditions, PDO::PARAM_STR);
                        $worker_stmt->bindParam(":reason_application", $reason_application, PDO::PARAM_STR);
                        $worker_stmt->bindParam(":license_number", $license_number, PDO::PARAM_STR);
                        $worker_stmt->bindParam(":vehicle_registration", $vehicle_registration, PDO::PARAM_STR);
                        $worker_stmt->bindParam(":application_type", $application_type, PDO::PARAM_STR);
                        
                        if($worker_stmt->execute()){
                            // Update user type to admin_pending
                            $update_user_sql = "UPDATE users SET user_type = 'admin_pending' WHERE id = :user_id";
                            $update_stmt = $pdo->prepare($update_user_sql);
                            $update_stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
                            $update_stmt->execute();

                            // Redirect to prevent form resubmission
                            header("Location: barangay_register.php?success=1");
                            exit;
                        } else {
                            $error_message = "Failed to save application. Please try again.";
                        }
                        unset($worker_stmt);
                    }
                } catch (Exception $e) {
                    $error_message = "Database error: " . $e->getMessage();
                    
                    // Try without the optional columns
                    try {
                        $simple_sql = "INSERT INTO worker_applications 
                            (user_id, id_number, birthdate, contact_number, city, barangay, availability, status, submitted_at) 
                            VALUES 
                            (:user_id, :id_number, :birthdate, :contact_number, :city, :barangay, :availability, 'pending', NOW())";
                        
                        if($simple_stmt = $pdo->prepare($simple_sql)){
                            $simple_stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
                            $simple_stmt->bindParam(":id_number", $id_number, PDO::PARAM_STR);
                            $simple_stmt->bindParam(":birthdate", $birthdate, PDO::PARAM_STR);
                            $simple_stmt->bindParam(":contact_number", $contact_number, PDO::PARAM_STR);
                            $simple_stmt->bindParam(":city", $city, PDO::PARAM_STR);
                            $simple_stmt->bindParam(":barangay", $barangay, PDO::PARAM_STR);
                            $simple_stmt->bindParam(":availability", $availability, PDO::PARAM_STR);
                            
                            if($simple_stmt->execute()){
                                header("Location: barangay_register.php?success=1");
                                exit;
                            }
                        }
                    } catch (Exception $e2) {
                        $error_message = "Database error (simplified): " . $e2->getMessage();
                    }
                }
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
    <title>Register as Barangay Worker - TrashTrace</title>
    <link rel="stylesheet" href="css/barangay_register.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Character counter styles */
        .char-counter {
            font-size: 12px;
            color: #7f8c8d;
            text-align: right;
            margin-top: 5px;
            font-weight: 400;
        }

        .char-counter.warning {
            color: #f39c12;
        }

        .char-counter.error {
            color: #e74c3c;
            font-weight: bold;
        }

        /* Textarea container styling */
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: inherit;
            resize: vertical;
            min-height: 100px;
        }

        .form-group textarea:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.1);
        }

        /* Label styling */
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        /* Optional field indicator */
        .form-group label:has(+ textarea[placeholder*="Optional"])::after {
            content: " (Optional)";
            font-weight: normal;
            color: #7f8c8d;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <header class="register-header">
            <div class="header-content">
                <div class="logo">
                    <a href="dashboard.php"><i class="fas fa-recycle"></i> TrashTrace</a>
                </div>
                <nav>
                    <ul>
                        <li><a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a></li>
                        <li><a href="res_schedule.php" class="nav-link"><i class="fas fa-calendar"></i> Schedule</a></li>
                        <li><a href="res_notif.php" class="nav-link"><i class="fas fa-bell"></i> Notifications</a></li>
                        <li><a href="res_profile.php" class="nav-link"><i class="fas fa-user"></i> Profile</a></li>
                        <li class="user-menu">
                            <span><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION["full_name"]); ?></span>
                            <a href="logout.php" class="btn btn-outline"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </header>

        <main class="register-main">
            <div class="container">
                <div class="registration-wrapper">
                    <div class="registration-header">
                        <h1><i class="fas fa-hands-helping"></i> Barangay Worker Validation</h1>
                        <p class="subtitle">Register as an existing barangay worker to access work features</p>
                    </div>
                    
                    <?php if($success_message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                        <div class="notification-info">
                            <p><i class="fas fa-envelope"></i> We will contact you via email or phone once your validation is confirmed.</p>
                            <p><i class="fas fa-clock"></i> Your application status is now: <strong>Pending Review</strong></p>
                        </div>
                        <div class="success-actions">
                            <a href="dashboard.php" class="btn btn-continue">
                                <i class="fas fa-home"></i> Continue to Dashboard
                            </a>
                            <a href="res_profile.php" class="btn btn-profile">
                                <i class="fas fa-user"></i> View My Profile
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if($error_message): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                        <?php if(strpos($error_message, 'already have') !== false): ?>
                        <div class="existing-app-info">
                            <a href="res_profile.php" class="btn btn-profile">
                                <i class="fas fa-eye"></i> View Application Status
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php 
                    // Check if user can submit application
                    $can_submit = true;
                    $check_sql = "SELECT * FROM worker_applications WHERE user_id = :user_id AND status IN ('pending', 'reviewing')";
                    if($check_stmt = $pdo->prepare($check_sql)){
                        $check_stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
                        if($check_stmt->execute()){
                            if($check_stmt->rowCount() > 0){
                                $can_submit = false;
                                $app_data = $check_stmt->fetch(PDO::FETCH_ASSOC);
                                ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> You already have a worker validation request. 
                                    <p>Status: <strong><?php echo ucfirst($app_data['status']); ?></strong></p>
                                    <p>Submitted: <?php echo date('M d, Y', strtotime($app_data['submitted_at'])); ?></p>
                                    <div class="existing-app-info">
                                        <a href="res_profile.php" class="btn btn-profile">
                                            <i class="fas fa-eye"></i> View Application Status
                                        </a>
                                    </div>
                                </div>
                                <?php
                            }
                        }
                        unset($check_stmt);
                    }
                    
                    if(isset($user_data['user_type']) && $user_data['user_type'] === 'admin'): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-check-circle"></i> You are already a validated worker.
                        <p>You have access to all worker features.</p>
                        <div class="success-actions">
                            <a href="barangay_dashboard.php" class="btn btn-continue">
                                <i class="fas fa-tachometer-alt"></i> Go to Worker Dashboard
                            </a>
                        </div>
                    </div>
                    <?php elseif($can_submit && empty($success_message)): 
                        showRegistrationForm($user_data, $cebuBarangays);
                    endif;
                    
                    function showRegistrationForm($user_data, $cebuBarangays) {
                    ?>
                    <div class="registration-content">
                        <div class="benefits-section">
                            <div class="benefits-card">
                                <h2><i class="fas fa-star"></i> Worker Benefits</h2>
                                <div class="benefit-item">
                                    <div class="benefit-icon">
                                        <i class="fas fa-tachometer-alt"></i>
                                    </div>
                                    <div class="benefit-content">
                                        <h3>Access to Worker Dashboard</h3>
                                        <p>Manage schedules and track your work activities</p>
                                    </div>
                                </div>
                                <div class="benefit-item">
                                    <div class="benefit-icon">
                                        <i class="fas fa-calendar-check"></i>
                                    </div>
                                    <div class="benefit-content">
                                        <h3>Manage Pickup Schedules</h3>
                                        <p>Coordinate waste collection efficiently</p>
                                    </div>
                                </div>
                                <div class="benefit-item">
                                    <div class="benefit-icon">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </div>
                                    <div class="benefit-content">
                                        <h3>Earn While Serving</h3>
                                        <p>Get compensated for your community service</p>
                                    </div>
                                </div>
                                <div class="benefit-item">
                                    <div class="benefit-icon">
                                        <i class="fas fa-leaf"></i>
                                    </div>
                                    <div class="benefit-content">
                                        <h3>Improve Waste Management</h3>
                                        <p>Make your barangay cleaner and greener</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="requirements-card">
                                <h2><i class="fas fa-clipboard-check"></i> Verification Requirements</h2>
                                <ul class="requirements-list">
                                    <li><i class="fas fa-check"></i> Valid Worker ID</li>
                                    <li><i class="fas fa-check"></i> Barangay Employment Proof</li>
                                    <li><i class="fas fa-check"></i> Medical Certificate</li>
                                    <li><i class="fas fa-check"></i> Barangay Clearance</li>
                                    <li><i class="fas fa-check"></i> Current Work Schedule</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="registration-form-section">
                            <div class="form-card">
                                <div class="form-header">
                                    <h2><i class="fas fa-user-check"></i> Worker Validation Form</h2>
                                    <p>Register as an existing barangay worker to access work features</p>
                                </div>
                                
                                <form method="POST" enctype="multipart/form-data" class="worker-form" autocomplete="off">
                                    <input type="hidden" name="register_worker" value="1">
                                    
                                    <div class="form-section">
                                        <h3><i class="fas fa-user-check"></i> Verification Details</h3>
                                        
                                        <div class="form-group">
                                            <label for="full_name"><i class="fas fa-user-check"></i> Verified Name</label>
                                            <input type="text" id="full_name" name="full_name" 
                                                   value="<?php echo htmlspecialchars($user_data['full_name'] ?? ''); ?>" 
                                                   readonly>
                                        </div>
                                        
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label for="id_number"><i class="fas fa-id-card"></i> Worker ID Number *</label>
                                                <input type="text" id="id_number" name="id_number" required>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label for="birthdate"><i class="fas fa-birthday-cake"></i> Birthdate *</label>
                                                <input type="date" id="birthdate" name="birthdate" required>
                                            </div>
                                        </div>
                                        
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label for="contact_number"><i class="fas fa-phone"></i> Contact Number *</label>
                                                <input type="tel" id="contact_number" name="contact_number" required>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label for="emergency_contact"><i class="fas fa-phone-alt"></i> Emergency Contact</label>
                                                <input type="tel" id="emergency_contact" name="emergency_contact">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-section">
                                        <h3><i class="fas fa-map-marker-alt"></i> Work Location</h3>
                                        <div class="form-group">
                                            <label for="city"><i class="fas fa-city"></i> City *</label>
                                            <select id="city" name="city" required>
                                                <option value="">Select City</option>
                                                <option value="CEBU CITY">Cebu City</option>
                                                <option value="MANDAUE CITY">Mandaue City</option>
                                                <option value="LAPU-LAPU CITY">Lapu-Lapu City</option>
                                                <option value="TALISAY CITY">Talisay City</option>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="barangay"><i class="fas fa-map-pin"></i> Barangay *</label>
                                            <select id="barangay" name="barangay" required>
                                                <option value="">Select Barangay</option>
                                                <?php if(isset($user_data['city']) && isset($cebuBarangays[$user_data['city']])): ?>
                                                    <?php foreach($cebuBarangays[$user_data['city']] as $brgy): ?>
                                                        <option value="<?php echo htmlspecialchars($brgy); ?>" 
                                                            <?php echo (isset($user_data['barangay']) && $user_data['barangay'] == $brgy) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($brgy); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="zone"><i class="fas fa-street-view"></i> Zone/Purok</label>
                                            <input type="text" id="zone" name="zone" 
                                                   value="<?php echo htmlspecialchars($user_data['zone'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="form-section">
                                        <h3><i class="fas fa-briefcase"></i> Work Information</h3>
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label for="experience_years"><i class="fas fa-history"></i> Years of Service</label>
                                                <select id="experience_years" name="experience_years">
                                                    <option value="0">Less than 1 year</option>
                                                    <option value="1">1 Year</option>
                                                    <option value="2">2 Years</option>
                                                    <option value="3">3 Years</option>
                                                    <option value="4">4 Years</option>
                                                    <option value="5">5+ Years</option>
                                                </select>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label for="availability"><i class="fas fa-calendar-alt"></i> Work Schedule *</label>
                                                <select id="availability" name="availability" required>
                                                    <option value="">Select Schedule</option>
                                                    <option value="Full Time">Full Time</option>
                                                    <option value="Part Time">Part Time</option>
                                                    <option value="Weekends Only">Weekends Only</option>
                                                    <option value="Flexible">Flexible Schedule</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label><i class="fas fa-car"></i> Vehicle Access</label>
                                            <div class="radio-group">
                                                <label class="radio-label">
                                                    <input type="radio" name="vehicle_access" value="Yes" checked>
                                                    <span class="radio-custom"></span>
                                                    <span>Yes</span>
                                                </label>
                                                <label class="radio-label">
                                                    <input type="radio" name="vehicle_access" value="No">
                                                    <span class="radio-custom"></span>
                                                    <span>No</span>
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="license_number"><i class="fas fa-id-card"></i> Driver's License Number (Optional)</label>
                                            <input type="text" id="license_number" name="license_number" placeholder="License number if applicable">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="vehicle_registration"><i class="fas fa-car-side"></i> Vehicle Registration / Plate (Optional)</label>
                                            <input type="text" id="vehicle_registration" name="vehicle_registration" placeholder="Vehicle registration or plate number">
                                        </div>
                                    </div>
                                    
                                    <div class="form-section">
                                        <h3><i class="fas fa-file-upload"></i> Verification Documents</h3>
                                        <!-- Temporarily remove required attribute for file upload -->
                                        <div class="form-group">
                                            <label for="id_proof"><i class="fas fa-file-upload"></i> Upload Worker ID/Proof (JPG/PNG/PDF)</label>
                                            <input type="file" id="id_proof" name="id_proof" accept=".jpg,.jpeg,.png,.pdf">
                                            <small class="file-hint">Max size: 2MB. Optional for now.</small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="health_conditions"><i class="fas fa-heartbeat"></i> Health Conditions (if any)</label>
                                            <textarea id="health_conditions" name="health_conditions" rows="3" 
                                                      placeholder="Please mention any health conditions..." 
                                                      maxlength="500"></textarea>
                                            <div class="char-counter" id="health_counter">0/500</div>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="reason_application"><i class="fas fa-comment"></i> Additional Information (Optional)</label>
                                            <textarea id="reason_application" name="reason_application" rows="4" 
                                                      placeholder="Any additional information about your work..." 
                                                      maxlength="1000"></textarea>
                                            <div class="char-counter" id="reason_counter">0/1000</div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-footer">
                                        <button type="submit" class="btn btn-submit">
                                            <i class="fas fa-paper-plane"></i> Submit Validation
                                        </button>
                                        <a href="dashboard.php" class="btn btn-cancel">
                                            <i class="fas fa-times"></i> Cancel
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </main>
    </div>
    
<script>
// Character counter functionality
document.addEventListener('DOMContentLoaded', function() {
    // Function to update character counter
    function setupCounter(textareaId, counterId, maxLength) {
        const textarea = document.getElementById(textareaId);
        const counter = document.getElementById(counterId);
        
        if(!textarea || !counter) return;
        
        // Initialize counter
        updateCounter();
        
        // Update on input
        textarea.addEventListener('input', updateCounter);
        
        function updateCounter() {
            const currentLength = textarea.value.length;
            counter.textContent = `${currentLength}/${maxLength}`;
            
            // Change color based on length
            if(currentLength >= maxLength) {
                counter.style.color = '#e74c3c';
            } else if(currentLength >= maxLength * 0.9) {
                counter.style.color = '#f39c12';
            } else {
                counter.style.color = '#7f8c8d';
            }
        }
    }
    
    // Setup counters
    setupCounter('health_conditions', 'health_counter', 500);
    setupCounter('reason_application', 'reason_counter', 1000);
    
    // Handle form submission to prevent browser saved draft
    const form = document.querySelector('.worker-form');
    if(form) {
        form.addEventListener('submit', function() {
            // Add a small delay to ensure form is submitted before any page refresh
            setTimeout(function() {
                if(window.history.replaceState) {
                    window.history.replaceState(null, null, window.location.href);
                }
            }, 100);
        });
    }
});
</script>
</body>
</html>