<?php
session_start();
require_once 'config.php';
require_once 'auth_functions.php';

// Redirect if already logged in
if (isUserLoggedIn()) {
    header("Location: user-dashboard.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'login') {
        $username = sanitizeInput($_POST['username']);
        $password = $_POST['password'];
        
        if (!empty($username) && !empty($password)) {
            $result = loginUser($username, $password);
            
            if ($result['success']) {
                header("Location: user-dashboard.php");
                exit();
            } else {
                $error = $result['error'];
            }
        } else {
            $error = "Please fill in all fields.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - Cookistry</title>
    <link rel="icon" href="images/logo.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css" />
    
    <style>
        /* Premium Modern Design */
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, 
                rgba(76, 161, 175, 0.1) 0%, 
                rgba(44, 62, 80, 0.05) 25%,
                rgba(235, 242, 247, 1) 50%,
                rgba(76, 161, 175, 0.08) 75%,
                rgba(44, 62, 80, 0.1) 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow-x: hidden;
        }

        /* Animated Background Elements */
        body::before {
            content: '';
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at 25% 25%, rgba(76, 161, 175, 0.15) 0%, transparent 50%),
                        radial-gradient(circle at 75% 75%, rgba(44, 62, 80, 0.1) 0%, transparent 50%),
                        radial-gradient(circle at 50% 50%, rgba(76, 161, 175, 0.05) 0%, transparent 70%);
            z-index: 1;
            animation: float 20s ease-in-out infinite;
        }

        /* Floating Particles */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 2;
        }

        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: linear-gradient(45deg, #4ca1af, #2c3e50);
            border-radius: 50%;
            opacity: 0.6;
            animation: particleFloat 15s infinite linear;
        }

        .particle:nth-child(1) { left: 10%; animation-delay: 0s; }
        .particle:nth-child(2) { left: 20%; animation-delay: 2s; }
        .particle:nth-child(3) { left: 30%; animation-delay: 4s; }
        .particle:nth-child(4) { left: 70%; animation-delay: 6s; }
        .particle:nth-child(5) { left: 80%; animation-delay: 8s; }
        .particle:nth-child(6) { left: 90%; animation-delay: 10s; }

        @keyframes particleFloat {
            0% {
                transform: translateY(100vh) rotate(0deg) scale(0);
                opacity: 0;
            }
            10% {
                opacity: 0.6;
                transform: scale(1);
            }
            90% {
                opacity: 0.6;
                transform: scale(1);
            }
            100% {
                transform: translateY(-100px) rotate(360deg) scale(0);
                opacity: 0;
            }
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(-30px, -30px) rotate(120deg); }
            66% { transform: translate(30px, -60px) rotate(240deg); }
        }

        /* Main Content */
        .main-content {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 4rem 2rem;
            position: relative;
            z-index: 10;
        }

        /* Premium Login Container */
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(30px);
            border-radius: 28px;
            padding: 3.5rem;
            box-shadow: 
                0 32px 64px rgba(0, 0, 0, 0.12),
                0 16px 32px rgba(76, 161, 175, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.6);
            max-width: 520px;
            width: 100%;
            position: relative;
            animation: premiumSlideUp 1s ease-out;
            overflow: hidden;
        }

        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #4ca1af 0%, #2c3e50 50%, #4ca1af 100%);
            border-radius: 28px 28px 0 0;
            animation: shimmer 3s ease-in-out infinite;
        }

        .login-container::after {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, 
                rgba(76, 161, 175, 0.1), 
                rgba(44, 62, 80, 0.05), 
                rgba(76, 161, 175, 0.1));
            border-radius: 30px;
            z-index: -1;
            opacity: 0;
            animation: borderPulse 4s ease-in-out infinite;
        }

        @keyframes premiumSlideUp {
            0% {
                opacity: 0;
                transform: translateY(60px) scale(0.9);
                filter: blur(10px);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
                filter: blur(0);
            }
        }

        @keyframes shimmer {
            0%, 100% { transform: translateX(-100%); }
            50% { transform: translateX(400%); }
        }

        @keyframes borderPulse {
            0%, 100% { opacity: 0; }
            50% { opacity: 1; }
        }

        /* Premium Header */
        .login-header {
            text-align: center;
            margin-bottom: 3rem;
            position: relative;
        }

        .login-header .icon-container {
            position: relative;
            display: inline-block;
            margin-bottom: 1.5rem;
        }

        .login-header .icon {
            font-size: 4rem;
            background: linear-gradient(135deg, #4ca1af 0%, #2c3e50 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 8px 16px rgba(76, 161, 175, 0.3));
            animation: iconBounce 2s ease-in-out infinite;
        }

        .login-header .icon-container::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 80px;
            height: 80px;
            background: radial-gradient(circle, rgba(76, 161, 175, 0.2) 0%, transparent 70%);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            animation: iconGlow 3s ease-in-out infinite;
        }

        @keyframes iconBounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }

        @keyframes iconGlow {
            0%, 100% { transform: translate(-50%, -50%) scale(1); opacity: 0.3; }
            50% { transform: translate(-50%, -50%) scale(1.2); opacity: 0.6; }
        }

        .login-header h1 {
            color: #1a202c;
            font-size: 2.8rem;
            font-weight: 700;
            margin-bottom: 0.8rem;
            background: linear-gradient(135deg, #2c3e50 0%, #4ca1af 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .login-header p {
            color: #64748b;
            font-size: 1.2rem;
            font-weight: 400;
            opacity: 0.8;
        }

        /* Premium Alert */
        .alert {
            padding: 1.5rem 2rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            font-weight: 500;
            backdrop-filter: blur(10px);
            animation: alertSlideIn 0.6s ease-out;
            border: 1px solid;
        }

        .alert-error {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(220, 38, 38, 0.05) 100%);
            color: #dc2626;
            border-color: rgba(239, 68, 68, 0.3);
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.15);
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.05) 100%);
            color: #059669;
            border-color: rgba(16, 185, 129, 0.3);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.15);
        }

        /* Premium Demo Section */
        .demo-info {
            background: linear-gradient(135deg, 
                rgba(14, 165, 233, 0.08) 0%, 
                rgba(59, 130, 246, 0.05) 100%);
            border: 2px solid rgba(14, 165, 233, 0.2);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2.5rem;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(20px);
        }

        .demo-info::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: conic-gradient(from 0deg, transparent, rgba(14, 165, 233, 0.1), transparent);
            animation: rotate 6s linear infinite;
        }

        .demo-info-content {
            position: relative;
            z-index: 2;
        }

        .demo-info h4 {
            color: #0c4a6e;
            margin-bottom: 1.5rem;
            font-size: 1.3rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .demo-account {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin: 0.8rem 0;
            font-family: 'Monaco', 'Menlo', monospace;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(14, 165, 233, 0.2);
            position: relative;
            overflow: hidden;
        }

        .demo-account::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(14, 165, 233, 0.1), transparent);
            transition: left 0.6s;
        }

        .demo-account:hover::before {
            left: 100%;
        }

        .demo-account:hover {
            background: rgba(255, 255, 255, 0.9);
            transform: translateY(-2px) scale(1.02);
            border-color: rgba(14, 165, 233, 0.4);
            box-shadow: 0 12px 30px rgba(14, 165, 233, 0.2);
        }

        /* Premium Form Elements */
        .form-group {
            margin-bottom: 2.5rem;
            position: relative;
        }

        .form-group label {
            display: block;
            color: #374151;
            font-weight: 600;
            margin-bottom: 1rem;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            transition: all 0.3s ease;
        }

        .form-group input {
            width: 100%;
            padding: 1.5rem 2rem;
            border: 2px solid rgba(229, 231, 235, 0.8);
            border-radius: 16px;
            font-size: 1.1rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            font-family: 'Poppins', sans-serif;
            color: #374151;
            position: relative;
        }

        .form-group input:focus {
            outline: none;
            border-color: #4ca1af;
            background: rgba(255, 255, 255, 0.95);
            transform: translateY(-4px);
            box-shadow: 
                0 12px 24px rgba(76, 161, 175, 0.15),
                0 8px 16px rgba(0, 0, 0, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.8);
        }

        .form-group input:focus + .input-highlight {
            opacity: 1;
            transform: scaleX(1);
        }

        .input-highlight {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #4ca1af, #2c3e50);
            border-radius: 0 0 16px 16px;
            opacity: 0;
            transform: scaleX(0);
            transition: all 0.4s ease;
        }

        /* Premium Submit Button */
        .submit-btn {
            width: 100%;
            padding: 1.8rem 2rem;
            background: linear-gradient(135deg, #4ca1af 0%, #2c3e50 100%);
            color: white;
            border: none;
            border-radius: 18px;
            font-size: 1.3rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            font-family: 'Poppins', sans-serif;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 
                0 12px 24px rgba(76, 161, 175, 0.3),
                0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .submit-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, 
                transparent, 
                rgba(255, 255, 255, 0.4), 
                transparent);
            transition: left 0.8s;
        }

        .submit-btn::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: all 0.6s ease;
        }

        .submit-btn:hover::before {
            left: 100%;
        }

        .submit-btn:hover::after {
            width: 300px;
            height: 300px;
        }

        .submit-btn:hover {
            transform: translateY(-6px) scale(1.02);
            box-shadow: 
                0 20px 40px rgba(76, 161, 175, 0.4),
                0 12px 24px rgba(0, 0, 0, 0.2);
            background: linear-gradient(135deg, #2c3e50 0%, #4ca1af 100%);
        }

        .submit-btn:active {
            transform: translateY(-2px) scale(0.98);
        }

        /* Floating Elements */
        .floating-elements {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            overflow: hidden;
        }

        .floating-circle {
            position: absolute;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(76, 161, 175, 0.1), rgba(44, 62, 80, 0.05));
            animation: floatAround 20s infinite linear;
        }

        .floating-circle:nth-child(1) {
            width: 60px;
            height: 60px;
            top: 10%;
            left: 10%;
            animation-delay: 0s;
        }

        .floating-circle:nth-child(2) {
            width: 40px;
            height: 40px;
            top: 20%;
            right: 15%;
            animation-delay: 5s;
        }

        .floating-circle:nth-child(3) {
            width: 80px;
            height: 80px;
            bottom: 15%;
            left: 20%;
            animation-delay: 10s;
        }

        @keyframes floatAround {
            0% { transform: translate(0, 0) rotate(0deg); }
            25% { transform: translate(30px, -30px) rotate(90deg); }
            50% { transform: translate(-20px, -60px) rotate(180deg); }
            75% { transform: translate(-40px, -30px) rotate(270deg); }
            100% { transform: translate(0, 0) rotate(360deg); }
        }

        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Mobile Optimizations */
        @media (max-width: 768px) {
            .main-content {
                padding: 2rem 1rem;
            }

            .login-container {
                margin: 1rem;
                padding: 2.5rem;
                border-radius: 24px;
            }

            .login-header h1 {
                font-size: 2.2rem;
            }

            .login-header .icon {
                font-size: 3rem;
            }

            .form-group input {
                padding: 1.3rem 1.5rem;
                font-size: 1rem;
            }

            .submit-btn {
                padding: 1.5rem;
                font-size: 1.1rem;
            }
        }

        /* Loading Animation */
        .loading .submit-btn {
            background: linear-gradient(135deg, #94a3b8 0%, #64748b 100%);
            cursor: not-allowed;
        }

        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Floating Particles -->
    <div class="particles">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
    </div>

    <!-- Header -->
    <header>
        <div class="navbar">
            <img src="images/logo.png" alt="Cookistry Logo" class="logo" />
            <nav>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li class="dropdown">
                        <a href="#">Categories <i class="fas fa-chevron-down"></i></a>
                        <ul class="dropdown-content">
                            <li><a href="appetizer.php">Appetizer</a></li>
                            <li><a href="breakfast.php">Breakfast</a></li>
                            <li><a href="lunch.php">Lunch</a></li>
                            <li><a href="dinner.php">Dinner</a></li>
                            <li><a href="dessert.php">Dessert</a></li>
                            <li><a href="bread-bakes.php">Bread & Bakes</a></li>
                            <li><a href="salads.php">Salads</a></li>
                            <li><a href="healthy.php">Healthy Food</a></li>
                            <li><a href="beverages.php">Beverages</a></li>
                            <li><a href="snacks.php">Snacks</a></li>
                        </ul>
                    </li>
                    <li><a href="contact.php">Contact</a></li>
                    <li><a href="about.php">About</a></li>
                    <li><a href="signup.php">Signup</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="login-container">
            <!-- Floating Elements -->
            <div class="floating-elements">
                <div class="floating-circle"></div>
                <div class="floating-circle"></div>
                <div class="floating-circle"></div>
            </div>

            <div class="login-header">
                <div class="icon-container">
                    <i class="icon fas fa-sign-in-alt"></i>
                </div>
                <h1>Welcome Back!</h1>
                <p>Sign in to your culinary dashboard</p>
            </div>

            <!-- Error/Success Messages -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>

            <!-- Premium Demo Info -->
            <div class="demo-info">
                <div class="demo-info-content">
                    <h4><i class="fas fa-sparkles"></i> Demo Accounts Available</h4>
                    <div class="demo-account" onclick="fillDemo('demo_user', 'password123')">
                        <strong>Username:</strong> demo_user | <strong>Password:</strong> password123
                    </div>
                    <div class="demo-account" onclick="fillDemo('chef_master', 'password123')">
                        <strong>Username:</strong> chef_master | <strong>Password:</strong> password123
                    </div>
                </div>
            </div>

            <form method="POST" id="loginForm">
                <input type="hidden" name="action" value="login">
                
                <div class="form-group">
                    <label for="username">
                        <i class="fas fa-user"></i> Username or Email
                    </label>
                    <input type="text" id="username" name="username" required 
                           placeholder="Enter your username or email" 
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    <div class="input-highlight"></div>
                </div>
                
                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i> Password
                    </label>
                    <input type="password" id="password" name="password" required 
                           placeholder="Enter your password">
                    <div class="input-highlight"></div>
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-rocket"></i> Sign In to Dashboard
                </button>
            </form>

            <p class="auth-switch" style="text-align: center; margin-top: 2rem; color: #64748b;">
                Don't have an account? 
                <a href="signup.php" style="color: #4ca1af; text-decoration: none; font-weight: 600;">Create your free account</a>
            </p>
        </div>
    </main>

    <!-- Footer -->
    <footer class="enhanced-footer">
        <div class="footer-container">
            <div class="footer-grid">
                <div class="footer-section">
                    <div class="footer-logo">
                        <img src="images/logo.png" alt="Cookistry Logo" class="footer-logo-img" />
                        <h3>Cookistry</h3>
                    </div>
                    <p>Your gateway to creative cooking. Discover exciting recipes, cooking tips, and kitchen inspirations to make every meal memorable.</p>
                    <div class="social-links">
                        <a href="#" class="social-link" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-link" title="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-link" title="Twitter"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-link" title="YouTube"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <ul class="footer-links">
                        <li><a href="index.php">Home</a></li>
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="contact.php">Contact</a></li>
                        <li><a href="#">Privacy Policy</a></li>
                        <li><a href="#">Terms of Service</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Recipe Categories</h3>
                    <div class="category-grid">
                        <ul class="footer-links">
                            <li><a href="appetizer.php">Appetizers</a></li>
                            <li><a href="breakfast.php">Breakfast</a></li>
                            <li><a href="lunch.php">Lunch</a></li>
                            <li><a href="dinner.php">Dinner</a></li>
                        </ul>
                        <ul class="footer-links">
                            <li><a href="dessert.php">Desserts</a></li>
                            <li><a href="bread-bakes.php">Bread & Bakes</a></li>
                            <li><a href="salads.php">Salads</a></li>
                            <li><a href="healthy.php">Healthy Food</a></li>
                        </ul>
                    </div>
                </div>
                <div class="footer-section">
                    <h3>Get In Touch</h3>
                    <div class="contact-info">
                        <div class="contact-item">
                            <i class="fas fa-envelope"></i>
                            <span>support@cookistry.com</span>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>Chittagong, Bangladesh</span>
                        </div>
                    </div>
                    <div class="social-links">
                        <a href="#" class="social-link" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-link" title="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-link" title="Twitter"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-link" title="YouTube"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <div class="footer-bottom-content">
                    <p>&copy; 2025 Cookistry. All rights reserved.</p>
                    <div class="footer-bottom-links">
                        <a href="#">Terms</a>
                        <span>|</span>
                        <a href="#">Privacy</a>
                        <span>|</span>
                        <a href="#">Cookies</a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Demo account auto-fill with animation
        function fillDemo(username, password) {
            const usernameField = document.getElementById('username');
            const passwordField = document.getElementById('password');
            
            // Clear fields first
            usernameField.value = '';
            passwordField.value = '';
            
            // Animate typing effect
            typeEffect(usernameField, username, 0, () => {
                typeEffect(passwordField, password, 0);
            });
        }

        function typeEffect(element, text, index, callback) {
            if (index < text.length) {
                element.value += text.charAt(index);
                element.focus();
                setTimeout(() => typeEffect(element, text, index + 1, callback), 80);
            } else if (callback) {
                callback();
            }
        }

        // Enhanced form validation with premium animations
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('.submit-btn');
            const originalText = submitBtn.innerHTML;
            
            // Add premium loading state
            submitBtn.innerHTML = '<div class="spinner"></div> Signing In...';
            submitBtn.disabled = true;
            document.body.classList.add('loading');
            
            // Create ripple effect
            const ripple = document.createElement('div');
            ripple.style.cssText = `
                position: absolute;
                top: 50%; left: 50%;
                width: 0; height: 0;
                background: rgba(255,255,255,0.5);
                border-radius: 50%;
                transform: translate(-50%, -50%);
                animation: ripple 0.6s ease-out;
                pointer-events: none;
            `;
            submitBtn.appendChild(ripple);
            
            // Re-enable button after form submission
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                document.body.classList.remove('loading');
                if (ripple.parentNode) {
                    ripple.remove();
                }
            }, 3000);
        });

        // Real-time validation with premium effects
        const inputs = document.querySelectorAll('input[required]');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
                this.style.transform = 'translateY(-4px)';
            });

            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('focused');
                if (this.value.trim() !== '') {
                    this.style.borderColor = '#10b981';
                    this.style.boxShadow = '0 8px 25px rgba(16, 185, 129, 0.15)';
                } else {
                    this.style.borderColor = 'rgba(229, 231, 235, 0.8)';
                    this.style.boxShadow = 'none';
                    this.style.transform = 'translateY(0)';
                }
            });

            input.addEventListener('input', function() {
                if (this.value.trim()) {
                    this.style.borderColor = '#4ca1af';
                }
            });
        });

        // Particle system enhancement
        function createParticle() {
            const particle = document.createElement('div');
            particle.className = 'particle';
            particle.style.left = Math.random() * 100 + '%';
            particle.style.animationDuration = (Math.random() * 10 + 10) + 's';
            particle.style.opacity = Math.random() * 0.5 + 0.3;
            
            document.querySelector('.particles').appendChild(particle);
            
            setTimeout(() => {
                if (particle.parentNode) {
                    particle.remove();
                }
            }, 15000);
        }

        // Create particles periodically
        setInterval(createParticle, 3000);

        // Premium hover effects for demo accounts
        document.querySelectorAll('.demo-account').forEach(account => {
            account.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px) scale(1.02)';
                this.style.filter = 'brightness(1.05)';
            });
            
            account.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
                this.style.filter = 'brightness(1)';
            });
        });

        // Add CSS animation for ripple effect
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                to {
                    width: 300px;
                    height: 300px;
                    opacity: 0;
                }
            }
            
            .focused label {
                color: #4ca1af !important;
                transform: translateY(-2px);
            }
            
            @keyframes alertSlideIn {
                0% {
                    opacity: 0;
                    transform: translateX(-30px) scale(0.9);
                }
                100% {
                    opacity: 1;
                    transform: translateX(0) scale(1);
                }
            }
        `;
        document.head.appendChild(style);

        // Enhanced keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                const focused = document.activeElement;
                if (focused.tagName === 'INPUT') {
                    const form = focused.closest('form');
                    if (form) {
                        const inputs = form.querySelectorAll('input[required]');
                        const allFilled = Array.from(inputs).every(input => input.value.trim());
                        if (allFilled) {
                            form.submit();
                        } else {
                            // Focus next empty input
                            const emptyInput = Array.from(inputs).find(input => !input.value.trim());
                            if (emptyInput) {
                                emptyInput.focus();
                            }
                        }
                    }
                }
            }
        });

        // Smooth page load animation
        window.addEventListener('load', function() {
            document.body.style.opacity = '0';
            document.body.style.transition = 'opacity 0.5s ease-in-out';
            setTimeout(() => {
                document.body.style.opacity = '1';
            }, 100);
        });
    </script>
</body>
</html>