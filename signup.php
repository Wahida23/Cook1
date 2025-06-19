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
    
    if ($action === 'signup') {
        $fullname = sanitizeInput($_POST['fullname']);
        $email = sanitizeInput($_POST['email']);
        $username = sanitizeInput($_POST['username']);
        $password = $_POST['password'];
        
        // Basic validation
        if (empty($fullname) || empty($email) || empty($username) || empty($password)) {
            $error = "Please fill in all fields.";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters long.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            // Call signup function
            $result = signupUser($fullname, $email, $username, $password);
            
            if ($result['success']) {
                // Auto-login after successful signup
                $_SESSION['user_id'] = $result['user_id'];
                $_SESSION['username'] = $username;
                $_SESSION['email'] = $email;
                $_SESSION['full_name'] = $fullname;
                $_SESSION['logged_in'] = true;
                
                // Redirect to user dashboard
                header("Location: user-dashboard.php");
                exit();
            } else {
                $error = $result['error'];
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
    <title>Join Cookistry - Your Culinary Journey Starts Here</title>
    <link rel="icon" href="images/logo.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css" />
    
    <style>
        /* Override body to match your website */
        body {
            font-family: 'Poppins', sans-serif;
            background: rgb(235, 242, 247) !important;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            margin: 0;
            padding: 0;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 4rem 2rem;
            position: relative;
        }

        /* Signup Container - Matching your recipe cards */
        .signup-container {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border: 1px solid #f1f5f9;
            max-width: 500px;
            width: 100%;
            position: relative;
            animation: slideUpFade 0.8s ease-out;
        }

        @keyframes slideUpFade {
            from {
                opacity: 0;
                transform: translateY(40px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .signup-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .signup-header .icon {
            font-size: 3rem;
            background: linear-gradient(135deg, #4ca1af 0%, #2c3e50 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
            display: block;
        }

        .signup-header h1 {
            color: #1a202c;
            font-size: 2.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .signup-header p {
            color: #64748b;
            font-size: 1.1rem;
            font-weight: 400;
        }

        /* Alert styling - matching your website */
        .alert {
            padding: 1.2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            font-weight: 500;
            animation: alertSlide 0.5s ease-out;
        }

        @keyframes alertSlide {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border: 2px solid #fecaca;
        }

        .alert-success {
            background: #f0fdf4;
            color: #16a34a;
            border: 2px solid #bbf7d0;
        }

        /* Features Info - matching your website's info boxes */
        .features-info {
            background: #f0f9ff;
            border: 2px solid #0ea5e9;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .features-info h4 {
            color: #0c4a6e;
            margin-bottom: 1rem;
            font-size: 1.2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .features-list {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.8rem;
            color: #0c4a6e;
            font-size: 0.9rem;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            background: rgba(14, 165, 233, 0.05);
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .feature-item:hover {
            background: rgba(14, 165, 233, 0.1);
            transform: translateX(2px);
        }

        /* Form styling - matching your website forms */
        .form-group {
            margin-bottom: 2rem;
            position: relative;
        }

        .form-group label {
            display: block;
            color: #374151;
            font-weight: 600;
            margin-bottom: 0.8rem;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-group input {
            width: 100%;
            padding: 1.2rem 1.5rem;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
            font-family: 'Poppins', sans-serif;
            color: #374151;
        }

        .form-group input:focus {
            outline: none;
            border-color: #4ca1af;
            box-shadow: 0 0 0 3px rgba(76, 161, 175, 0.1);
            transform: translateY(-2px);
        }

        .form-group input:valid {
            border-color: #10b981;
        }

        .required {
            color: #dc2626;
        }

        /* Submit button - matching your website buttons */
        .submit-btn {
            width: 100%;
            padding: 1.3rem;
            background: linear-gradient(135deg, #4ca1af 0%, #2c3e50 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.2rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            font-family: 'Poppins', sans-serif;
            box-shadow: 0 4px 15px rgba(76, 161, 175, 0.3);
        }

        .submit-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.6s;
        }

        .submit-btn:hover::before {
            left: 100%;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(76, 161, 175, 0.4);
            background: linear-gradient(135deg, #2c3e50 0%, #4ca1af 100%);
        }

        .submit-btn:active {
            transform: translateY(-1px);
        }

        /* Auth switch */
        .auth-switch {
            text-align: center;
            margin-top: 2rem;
            color: #64748b;
            font-size: 1rem;
        }

        .auth-switch a {
            color: #4ca1af;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .auth-switch a:hover {
            color: #2c3e50;
            text-decoration: underline;
        }

        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .main-content {
                padding: 2rem 1rem;
            }

            .signup-container {
                margin: 1rem;
                padding: 2rem;
            }

            .signup-header h1 {
                font-size: 1.8rem;
            }

            .features-list {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .signup-container {
                padding: 1.5rem;
            }

            .signup-header .icon {
                font-size: 2.5rem;
            }

            .signup-header h1 {
                font-size: 1.6rem;
            }
        }

        /* Loading state */
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .loading .submit-btn {
            background: #94a3b8;
        }

        /* Password strength indicator */
        .password-strength {
            height: 4px;
            background: #e5e7eb;
            border-radius: 2px;
            margin-top: 0.5rem;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }

        .strength-weak { background: #ef4444; width: 25%; }
        .strength-fair { background: #f59e0b; width: 50%; }
        .strength-good { background: #10b981; width: 75%; }
        .strength-strong { background: #059669; width: 100%; }
    </style>
</head>
<body>
    <!-- Header - Using your website's exact navbar -->
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
                    <li><a href="login.php">Login</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="signup-container">
            <div class="signup-header">
                <i class="icon fas fa-user-plus"></i>
                <h1>Join Cookistry</h1>
                <p>Start your culinary adventure today</p>
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

            <!-- Features Info -->
            <div class="features-info">
                <h4><i class="fas fa-star"></i> What You'll Get</h4>
                <div class="features-list">
                    <div class="feature-item">
                        <i class="fas fa-heart"></i> Save Favorites
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-star"></i> Rate Recipes
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-comment"></i> Add Reviews
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-upload"></i> Upload Recipes
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-bookmark"></i> Personal Notes
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-users"></i> Join Community
                    </div>
                </div>
            </div>

            <form method="POST" id="signupForm">
                <input type="hidden" name="action" value="signup">
                
                <div class="form-group">
                    <label for="fullname">
                        <i class="fas fa-user"></i> Full Name <span class="required">*</span>
                    </label>
                    <input type="text" id="fullname" name="fullname" required 
                           placeholder="Enter your full name" 
                           value="<?php echo isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="email">
                        <i class="fas fa-envelope"></i> Email Address <span class="required">*</span>
                    </label>
                    <input type="email" id="email" name="email" required 
                           placeholder="Enter your email address"
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="username">
                        <i class="fas fa-at"></i> Username <span class="required">*</span>
                    </label>
                    <input type="text" id="username" name="username" required 
                           placeholder="Choose a unique username"
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i> Password <span class="required">*</span>
                    </label>
                    <input type="password" id="password" name="password" required 
                           placeholder="Create a strong password (min 6 characters)">
                    <div class="password-strength">
                        <div class="password-strength-bar" id="strengthBar"></div>
                    </div>
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-rocket"></i> Create My Account
                </button>
            </form>

            <p class="auth-switch">
                Already have an account? <a href="login.php">Sign In Here</a>
            </p>
        </div>
    </main>

    <!-- Footer - Using your website's exact footer -->
    <footer class="enhanced-footer">
        <div class="footer-container">
            <div class="footer-grid">
                <!-- About Section -->
                <div class="footer-section">
                    <div class="footer-logo">
                        <img src="images/logo.png" alt="Cookistry Logo" class="footer-logo-img" />
                        <h3>Cookistry</h3>
                    </div>
                    <p>Your gateway to creative cooking. Discover exciting recipes, cooking tips, and kitchen inspirations to make every meal memorable.</p>
                    <div class="social-links">
                        <a href="#" class="social-link" title="Facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="social-link" title="Instagram">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" class="social-link" title="Twitter">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="social-link" title="YouTube">
                            <i class="fab fa-youtube"></i>
                        </a>
                    </div>
                </div>

                <!-- Quick Links Section -->
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

                <!-- Categories Section -->
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

                <!-- Contact Info Section -->
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
                        <a href="#" class="social-link" title="Facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="social-link" title="Instagram">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" class="social-link" title="Twitter">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="social-link" title="YouTube">
                            <i class="fab fa-youtube"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Footer Bottom -->
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
        // Enhanced form validation and user experience
        document.getElementById('signupForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('.submit-btn');
            const originalText = submitBtn.innerHTML;
            
            // Add loading state
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Account...';
            submitBtn.disabled = true;
            
            // Re-enable button after form submission (handled by PHP redirect or error)
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 3000);
        });

        // Real-time validation feedback
        const inputs = document.querySelectorAll('input[required]');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                if (this.value.trim() !== '') {
                    this.style.borderColor = '#10b981';
                } else {
                    this.style.borderColor = '#e5e7eb';
                }
            });
        });

        // Password strength indicator
        const passwordInput = document.getElementById('password');
        const strengthBar = document.getElementById('strengthBar');
        
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            if (password.length >= 6) strength++;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
            if (password.match(/\d/)) strength++;
            if (password.match(/[^a-zA-Z\d]/)) strength++;
            
            // Remove all strength classes
            strengthBar.className = 'password-strength-bar';
            
            if (strength === 0) {
                this.style.borderColor = '#e5e7eb';
            } else if (strength === 1) {
                this.style.borderColor = '#ef4444';
                strengthBar.classList.add('strength-weak');
            } else if (strength === 2) {
                this.style.borderColor = '#f59e0b';
                strengthBar.classList.add('strength-fair');
            } else if (strength === 3) {
                this.style.borderColor = '#10b981';
                strengthBar.classList.add('strength-good');
            } else {
                this.style.borderColor = '#059669';
                strengthBar.classList.add('strength-strong');
            }
        });

        // Username validation
        const usernameInput = document.getElementById('username');
        usernameInput.addEventListener('input', function() {
            const username = this.value;
            const validPattern = /^[a-zA-Z0-9_]+$/;
            
            if (username.length < 3) {
                this.style.borderColor = '#ef4444';
            } else if (!validPattern.test(username)) {
                this.style.borderColor = '#f59e0b';
            } else {
                this.style.borderColor = '#10b981';
            }
        });

        // Email validation
        const emailInput = document.getElementById('email');
        emailInput.addEventListener('input', function() {
            const email = this.value;
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (emailPattern.test(email)) {
                this.style.borderColor = '#10b981';
            } else if (email.length > 0) {
                this.style.borderColor = '#f59e0b';
            } else {
                this.style.borderColor = '#e5e7eb';
            }
        });

        // Smooth scrolling for navigation (if any anchor links)
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>