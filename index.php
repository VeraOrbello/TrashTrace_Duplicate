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
    <link href="node_modules/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="landing-container">
        <header class="landing-header">
            <div class="header-content">
                <div class="logo">
                    <img src="assets/images/trashtrace logo green.png" alt="TrashTrace Logo" class="logo-img">
                </div>
                <button class="mobile-menu-toggle" id="mobileMenuToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <nav class="landing-nav" id="landingNav">
                    <ul>
                        <li><a href="#features" class="nav-link">Features</a></li>
                        <li><a href="#about" class="nav-link">About</a></li>
                        <li><a href="login.php" class="btn-nav-login">Login</a></li>
                        <li><a href="register.php" class="btn-nav-signup">Sign Up</a></li>
                    </ul>
                </nav>
            </div>
        </header>

        <main class="landing-main">
            <section class="hero-section">
                <div class="hero-background">
                    <div class="floating-shape shape-1"></div>
                    <div class="floating-shape shape-2"></div>
                    <div class="floating-shape shape-3"></div>
                    <div class="floating-shape shape-4"></div>
                    <div class="floating-shape shape-5"></div>
                    <div class="location-pin pin-1"><i class="fas fa-map-marker-alt"></i></div>
                    <div class="location-pin pin-2"><i class="fas fa-map-marker-alt"></i></div>
                    <div class="location-pin pin-3"><i class="fas fa-map-marker-alt"></i></div>
                    <div class="location-pin pin-4"><i class="fas fa-map-marker-alt"></i></div>
                    <div class="location-pin pin-5"><i class="fas fa-map-marker-alt"></i></div>
                    <div class="location-pin pin-6"><i class="fas fa-map-marker-alt"></i></div>
                    <div class="location-pin pin-7"><i class="fas fa-map-marker-alt"></i></div>
                    <div class="location-pin pin-8"><i class="fas fa-map-marker-alt"></i></div>
                    <div class="location-pin pin-9"><i class="fas fa-map-marker-alt"></i></div>
                    <div class="location-pin pin-10"><i class="fas fa-map-marker-alt"></i></div>
                    <div class="location-pin pin-11"><i class="fas fa-map-marker-alt"></i></div>
                    <div class="location-pin pin-12"><i class="fas fa-map-marker-alt"></i></div>
                    <div class="truck-icon truck-1"><i class="fas fa-truck"></i></div>
                    <div class="truck-icon truck-2"><i class="fas fa-truck"></i></div>
                    <div class="truck-icon truck-3"><i class="fas fa-truck"></i></div>
                    <div class="recycle-icon recycle-1"><i class="fas fa-recycle"></i></div>
                    <div class="recycle-icon recycle-2"><i class="fas fa-recycle"></i></div>
                    <div class="leaf-icon leaf-1"><i class="fas fa-leaf"></i></div>
                    <div class="leaf-icon leaf-2"><i class="fas fa-leaf"></i></div>
                </div>
                <div class="container">
                    <div class="hero-content">
                        <div class="hero-badge">
                            <i class="fas fa-shield-alt"></i> Trusted by Communities
                        </div>
                        <h1 class="hero-title">
                            <span class="gradient-text">Manage Your Waste</span><br>Effectively
                        </h1>
                        <p class="hero-subtitle">TrashTrace helps barangays schedule trash pickups efficiently with real-time GPS tracking and keeps users informed about collection schedules and any changes in real-time.</p>
                        <div class="hero-buttons">
                            <a href="register.php" class="btn-hero-primary">
                                Get Started <i class="fas fa-arrow-right"></i>
                            </a>
                            <a href="#features" class="btn-hero-secondary">
                                <i class="fas fa-lightbulb"></i> Learn More
                            </a>
                        </div>
                    </div>
                </div>
            </section>

            <section id="features" class="features-section">
                <div class="container">
                    <h2 class="section-title">How TrashTrace Works</h2>
                    <p class="section-subtitle">Streamline your waste management with our powerful features</p>
                    <div class="features-grid">
                        <div class="feature-card" data-color="purple">
                            <div class="feature-icon">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <h3>Manual Scheduling</h3>
                            <p>Barangay administrators can manually schedule trash pickups to ensure accuracy and prevent confusion.</p>
                            <div class="feature-badge">Smart</div>
                        </div>
                        <div class="feature-card" data-color="blue">
                            <div class="feature-icon">
                                <i class="fas fa-bell"></i>
                            </div>
                            <h3>Automated Notifications</h3>
                            <p>Users receive timely reminders about pickup dates via their preferred notification channel.</p>
                            <div class="feature-badge">Instant</div>
                        </div>
                        <div class="feature-card" data-color="orange">
                            <div class="feature-icon">
                                <i class="fas fa-map-marked-alt"></i>
                            </div>
                            <h3>Real-time GPS Tracking</h3>
                            <p>Track collection trucks in real-time and know exactly when they'll arrive at your location.</p>
                            <div class="feature-badge">Live</div>
                        </div>
                        <div class="feature-card" data-color="teal">
                            <div class="feature-icon">
                                <i class="fas fa-comments"></i>
                            </div>
                            <h3>Feedback System</h3>
                            <p>Report missed pickups or other issues directly through the platform.</p>
                            <div class="feature-badge">Direct</div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="about" class="about-section">
                <div class="container">
                    <h2 class="section-title">About TrashTrace</h2>
                    <p class="section-subtitle">Making waste management smarter and more efficient</p>
                    <div class="about-content">
                        <div class="about-card problem-card">
                            <div class="about-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <span class="about-label">Challenge</span>
                            <h3>The Problem</h3>
                            <p>In many barangays, users face the problem of trash piling up on streets when collectors don't show up on scheduled days. This creates unsanitary conditions and sometimes blocks streets.</p>
                            <div class="problem-stats">
                                <div class="stat-bubble">Missed Pickups</div>
                                <div class="stat-bubble">Street Blockage</div>
                            </div>
                        </div>
                        <div class="about-card solution-card">
                            <div class="about-icon">
                                <i class="fas fa-lightbulb"></i>
                            </div>
                            <span class="about-label">Innovation</span>
                            <h3>The Solution</h3>
                            <p>TrashTrace solves this by providing accurate scheduling and real-time updates, ensuring neighborhoods stay clean and users are always informed.</p>
                            <div class="solution-features">
                                <div class="mini-feature"><i class="fas fa-check-circle"></i> Real-time Updates</div>
                                <div class="mini-feature"><i class="fas fa-check-circle"></i> Smart Scheduling</div>
                                <div class="mini-feature"><i class="fas fa-check-circle"></i> GPS Tracking</div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="landing-footer">
            <div class="container">
                <p>&copy; <?php echo date("Y"); ?> TrashTrace. All rights reserved.</p>
            </div>
        </footer>
    </div>

    <script>
        // Mobile menu toggle
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const landingNav = document.getElementById('landingNav');
        
        if (mobileMenuToggle) {
            mobileMenuToggle.addEventListener('click', function() {
                landingNav.classList.toggle('active');
                this.classList.toggle('active');
            });
        }

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                    // Close mobile menu if open
                    if (landingNav.classList.contains('active')) {
                        landingNav.classList.remove('active');
                        mobileMenuToggle.classList.remove('active');
                    }
                }
            });
        });

        // Add scroll effect to header
        let lastScroll = 0;
        const header = document.querySelector('.landing-header');
        
        window.addEventListener('scroll', () => {
            const currentScroll = window.pageYOffset;
            
            if (currentScroll > 100) {
                header.style.boxShadow = '0 6px 30px rgba(0, 0, 0, 0.15)';
            } else {
                header.style.boxShadow = '0 4px 20px rgba(0, 0, 0, 0.1)';
            }
            
            lastScroll = currentScroll;
        });
    </script>
</body>
</html>