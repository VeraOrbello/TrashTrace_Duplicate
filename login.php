<?php
require_once "config.php";

if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    header("location: dashboard.php");
    exit;
}

$email = $password = "";
$email_err = $password_err = "";

if($_SERVER["REQUEST_METHOD"] == "POST"){

    if(empty(trim($_POST["email"]))){
        $email_err = "Please enter email.";
    } else{
        $email = trim($_POST["email"]);
    }
    
    if(empty(trim($_POST["password"]))){
        $password_err = "Please enter your password.";
    } else{
        $password = trim($_POST["password"]);
    }
    
    if(empty($email_err) && empty($password_err)){
        $sql = "SELECT id, full_name, email, password, user_type FROM users WHERE email = :email";
        
        if($stmt = $pdo->prepare($sql)){
            $stmt->bindParam(":email", $param_email, PDO::PARAM_STR);
            $param_email = $email;
            
            if($stmt->execute()){
                if($stmt->rowCount() == 1){
                    if($row = $stmt->fetch()){
                        $id = $row["id"];
                        $full_name = $row["full_name"];
                        $email = $row["email"];
                        $hashed_password = $row["password"];
                        $user_type = $row["user_type"];
                        
                        if(password_verify($password, $hashed_password)){
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $id;
                            $_SESSION["full_name"] = $full_name;
                            $_SESSION["user_type"] = $user_type;
                            
                            // Redirect based on user type
                            if($user_type === 'admin'){
                                header("location: barangay_dashboard.php");
                            } else if($user_type === 'driver'){
                                header("location: driver_dashboard.php");
                            } else {
                                header("location: dashboard.php");
                            }
                            exit;
                        } else{
                            $password_err = "Invalid email or password.";
                        }
                    }
                } else{
                    $email_err = "Invalid email or password.";
                }
            } else{
                echo "Oops! Something went wrong. Please try again later.";
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
    <title></title>
    <link rel="stylesheet" href="css/register.css">
    <link href="node_modules/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="two-column-container">
        <!-- LEFT COLUMN - Image Placeholder -->
        <div class="left-column">
            <div class="image-placeholder">
                <div class="logo"></div>
                <div class="image-content">
                    
                    
                    <div class="image-features">
                        <div class="feature">
                            
                        </div>
                        <div class="feature">
                           
                        </div>
                        <div class="feature">
                           
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN - Login Form -->
        <div class="right-column">
            <div class="login-form-container">
                <div class="form-header">
                    <h2>Welcome to TrashTrace</h2>
                    <p>Sign in to your account</p>
                </div>
                
                <?php if($email_err || $password_err): ?>
                    <div class="alert alert-error">
                        Please check your credentials and try again.
                    </div>
                <?php endif; ?>
                
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($email); ?>">
                        <span class="invalid-feedback"><?php echo $email_err; ?></span>
                    </div>
                    
                    <div class="form-group">
    <label for="password">Password</label>
    <div style="position: relative;">
        <input type="password" id="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" style="padding-right: 40px;">
        <button type="button" class="toggle-password" onclick="togglePassword('password')" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 18px; color: #666;">
            <i class="fas fa-eye" id="password-eye"></i>
        </button>
    </div>
    <span class="invalid-feedback"><?php echo $password_err; ?></span>
</div>
                    <div class="remember-group">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember">Remember me</label>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Sign In</button>
                    </div>
                    
                    <div class="form-links">
                        <p>Don't have an account? <a href="register.php">Register here</a></p>
                        <p><a href="forgot_password.php">Forgot password?</a></p>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
       // Toggle Password Visibility with Font Awesome
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const eyeIcon = document.getElementById(fieldId + '-eye');
    
    if(field.type === 'password') {
        field.type = 'text';
        eyeIcon.classList.remove('fa-eye');
        eyeIcon.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        eyeIcon.classList.remove('fa-eye-slash');
        eyeIcon.classList.add('fa-eye');
    }
}
    </script>
</body>
</html>