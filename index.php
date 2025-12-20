<?php
require_once "config.php";

if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    header("location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TrashTrace - Waste Management System</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="node_modules/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <header>
        <div class="container header-content">
            <div class="logo">TrashTrace</div>
            <nav>
                <ul>
                    <li><a href="#features">Features</a></li>
                    <li><a href="#about">About</a></li>
                    <div class="auth-buttons">
                        <a href="login.php" class="btn btn-outline">Login</a>
                        <a href="register.php" class="btn">Sign Up</a>
                    </div>
                </ul>
            </nav>
        </div>
    </header>

    <section class="hero">
        <div class="container">
            <h1>Manage Your Waste Effectively</h1>
            <p>TrashTrace helps barangays schedule trash pickups efficiently and keeps users informed about collection schedules and any changes in real-time.</p>
            <a href="register.php" class="btn">Get Started</a>
        </div>
    </section>

    <section id="features" class="features">
        <div class="container">
            <h2 class="section-title">How TrashTrace Works</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">📅</div>
                    <h3>Manual Scheduling</h3>
                    <p>Barangay administrators can manually schedule trash pickups to ensure accuracy and prevent confusion.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🔔</div>
                    <h3>Automated Notifications</h3>
                    <p>Users receive timely reminders about pickup dates via their preferred notification channel.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🔄</div>
                    <h3>Real-time Updates</h3>
                    <p>Get instant notifications about delays or changes to the collection schedule.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">💬</div>
                    <h3>Feedback System</h3>
                    <p>Report missed pickups or other issues directly through the platform.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="about" class="features" style="background-color: #f5f9f7;">
        <div class="container">
            <h2 class="section-title">About TrashTrace</h2>
            <div style="max-width: 800px; margin: 0 auto;">
                <p style="text-align: center; margin-bottom: 2rem;">
                    In many barangays, users face the problem of trash piling up on streets when collectors don't show up on scheduled days. This creates unsanitary conditions and sometimes blocks streets.
                </p>
                <p style="text-align: center;">
                    TrashTrace solves this by providing accurate scheduling and real-time updates, ensuring neighborhoods stay clean and users are always informed.
                </p>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date("Y"); ?> TrashTrace. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>