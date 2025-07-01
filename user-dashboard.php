<?php
session_start();
require_once 'config.php';
require_once 'auth_functions.php';

// Require login
requireLogin();

$user = getCurrentUser();
if (!$user) {
    header("Location: login.php");
    exit();
}

// Get user statistics
try {
    // Count user's uploaded recipes - FIXED: Added is_user_recipe = 1 condition
    $stmt = $pdo->prepare("SELECT COUNT(*) as recipe_count FROM recipes WHERE author_id = ? AND is_user_recipe = 1");
    $stmt->execute([$user['id']]);
    $userRecipes = $stmt->fetchColumn();
    
    // Count user's published recipes - FIXED: Added is_user_recipe = 1 condition
    $stmt = $pdo->prepare("SELECT COUNT(*) as published_count FROM recipes WHERE author_id = ? AND status = 'published' AND is_user_recipe = 1");
    $stmt->execute([$user['id']]);
    $publishedRecipes = $stmt->fetchColumn();
    
    // Count user's favorites (placeholder - you'll need to create favorites table later)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $userFavorites = $stmt->fetchColumn();
    
    // Recent activity
    $recentActivity = []; // Placeholder
    
} catch (PDOException $e) {
    logError("Dashboard stats error: " . $e->getMessage());
    $userRecipes = 0;
    $publishedRecipes = 0;
    $userFavorites = 0;
    $recentActivity = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard - Cookistry</title>
    <link rel="icon" href="images/logo.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Updated Dashboard Styles - Homepage theme এর সাথে match করানো */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, 
                rgba(76, 161, 175, 0.1) 0%, 
                rgba(44, 62, 80, 0.05) 25%,
                rgba(235, 242, 247, 1) 50%,
                rgba(76, 161, 175, 0.08) 75%,
                rgba(44, 62, 80, 0.1) 100%);
            min-height: 100vh;
            color: #333;
        }

        /* Header - Homepage এর মতো same style */
        .header {
            background: linear-gradient(135deg, #4ca1af 0%, #2c3e50 100%);
            padding: 1rem 0;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
            text-decoration: none;
            color: white;
        }

        .logo-icon {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .logo-text {
            font-size: 1.8rem;
            font-weight: 700;
            color: white;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.8rem 1.5rem;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 25px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            font-weight: 500;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 0.8rem 1.5rem;
            border-radius: 25px;
            transition: all 0.3s ease;
            font-weight: 500;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-links a:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        .logout-btn {
            background: rgba(220, 53, 69, 0.9);
            color: white;
            padding: 0.8rem 1.5rem;
            text-decoration: none;
            border-radius: 25px;
            transition: all 0.3s ease;
            font-weight: 500;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .logout-btn:hover {
            background: rgba(220, 53, 69, 1);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.3);
        }

        /* Main Content */
        .main-content {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .welcome-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 3rem 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.6);
            position: relative;
            overflow: hidden;
        }

        .welcome-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #4ca1af 0%, #2c3e50 50%, #4ca1af 100%);
            animation: shimmer 3s ease-in-out infinite;
        }

        .welcome-section h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #4ca1af 0%, #2c3e50 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 700;
        }

        .welcome-section p {
            color: #64748b;
            font-size: 1.2rem;
            font-weight: 400;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2.5rem;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(255, 255, 255, 0.6);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(76, 161, 175, 0.1), transparent);
            transition: left 0.6s;
        }

        .stat-card:hover::before {
            left: 100%;
        }

        .stat-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 30px 60px rgba(0,0,0,0.15);
        }

        .stat-icon {
            font-size: 3rem;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, #4ca1af, #2c3e50);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 4px 8px rgba(76, 161, 175, 0.3));
        }

        .stat-number {
            font-size: 2.8rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #64748b;
            font-weight: 500;
            font-size: 1.1rem;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 2rem;
        }

        .action-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(255, 255, 255, 0.6);
            position: relative;
            overflow: hidden;
        }

        .action-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(76, 161, 175, 0.1), transparent);
            transition: left 0.6s;
        }

        .action-card:hover::before {
            left: 100%;
        }

        .action-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 30px 60px rgba(0,0,0,0.15);
        }

        .action-card h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: #1a202c;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            font-weight: 600;
        }

        .action-card h3 i {
            background: linear-gradient(135deg, #4ca1af, #2c3e50);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .action-card p {
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .action-btn {
            background: linear-gradient(135deg, #4ca1af 0%, #2c3e50 100%);
            color: white;
            padding: 1.2rem 2rem;
            text-decoration: none;
            border-radius: 15px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.8rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            font-size: 1rem;
            box-shadow: 0 8px 25px rgba(76, 161, 175, 0.3);
        }

        .action-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.8s;
        }

        .action-btn:hover::before {
            left: 100%;
        }

        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(76, 161, 175, 0.4);
            background: linear-gradient(135deg, #2c3e50 0%, #4ca1af 100%);
        }

        /* Animations */
        @keyframes shimmer {
            0%, 100% { transform: translateX(-100%); }
            50% { transform: translateX(400%); }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stat-card, .action-card, .welcome-section {
            animation: fadeInUp 0.8s ease-out;
        }

        .stat-card:nth-child(2) { animation-delay: 0.1s; }
        .stat-card:nth-child(3) { animation-delay: 0.2s; }
        .stat-card:nth-child(4) { animation-delay: 0.3s; }
        .action-card:nth-child(2) { animation-delay: 0.1s; }
        .action-card:nth-child(3) { animation-delay: 0.2s; }
        .action-card:nth-child(4) { animation-delay: 0.3s; }
        .action-card:nth-child(5) { animation-delay: 0.4s; }
        .action-card:nth-child(6) { animation-delay: 0.5s; }

        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .header-container {
                padding: 0 1rem;
                flex-direction: column;
                gap: 1rem;
            }
            
            .main-content {
                padding: 0 1rem;
            }
            
            .stats-grid, .actions-grid {
                grid-template-columns: 1fr;
            }
            
            .welcome-section {
                padding: 2rem 1.5rem;
            }
            
            .welcome-section h1 {
                font-size: 2rem;
            }
            
            .stat-card, .action-card {
                padding: 2rem;
            }
            
            .logo-text {
                font-size: 1.5rem;
            }
            
            .logo-icon {
                width: 40px;
                height: 40px;
            }

            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .welcome-section h1 {
                font-size: 1.8rem;
            }
            
            .stat-number {
                font-size: 2.2rem;
            }
            
            .action-btn {
                padding: 1rem 1.5rem;
                font-size: 0.9rem;
            }

            .header-container {
                padding: 0 0.5rem;
            }

            .nav-links a, .logout-btn {
                padding: 0.6rem 1rem;
                font-size: 0.9rem;
            }
        }

        /* Loading state */
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .loading .action-btn {
            background: #94a3b8;
        }

        /* Quick access section */
        .quick-access {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            border: 1px solid rgba(255, 255, 255, 0.6);
        }

        .quick-access h3 {
            color: #1a202c;
            margin-bottom: 1.5rem;
            font-size: 1.3rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .quick-links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .quick-link {
            background: rgba(76, 161, 175, 0.1);
            border: 2px solid rgba(76, 161, 175, 0.2);
            border-radius: 12px;
            padding: 1.2rem;
            text-decoration: none;
            color: #2c3e50;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 1rem;
            font-weight: 500;
        }

        .quick-link:hover {
            background: rgba(76, 161, 175, 0.15);
            border-color: rgba(76, 161, 175, 0.4);
            transform: translateY(-2px);
        }

        .quick-link i {
            font-size: 1.5rem;
            color: #4ca1af;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-container">
            <a href="index.php" class="logo">
                <div class="logo-icon">🍳</div>
                <span class="logo-text">Cookistry</span>
            </a>
            
            <div class="user-menu">
                <div class="user-info">
                    <i class="fas fa-user"></i>
                    <span><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></span>
                </div>
                
                <div class="nav-links">
                    <a href="index.php"><i class="fas fa-home"></i> Home</a>
                    <a href="upload-recipe.php"><i class="fas fa-plus"></i> Upload Recipe</a>
                </div>
                
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Welcome Section -->
        <section class="welcome-section">
            <h1>Welcome back, <?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?>!</h1>
            <p>Ready to explore some delicious recipes today?</p>
        </section>

        <!-- Quick Access Section -->
        <section class="quick-access">
            <h3><i class="fas fa-bolt"></i> Quick Access</h3>
            <div class="quick-links">
                <a href="upload-recipe.php" class="quick-link">
                    <i class="fas fa-plus-circle"></i>
                    <span>Upload New Recipe</span>
                </a>
                <a href="my-recipes.php" class="quick-link">
                    <i class="fas fa-utensils"></i>
                    <span>My Recipes</span>
                </a>
                <a href="favorites.php" class="quick-link">
                    <i class="fas fa-heart"></i>
                    <span>Favorites</span>
                </a>
                <a href="profile.php" class="quick-link">
                    <i class="fas fa-user-edit"></i>
                    <span>Edit Profile</span>
                </a>
            </div>
        </section>

        <!-- Stats Grid -->
        <section class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-utensils"></i>
                </div>
                <div class="stat-number"><?php echo number_format($userRecipes); ?></div>
                <div class="stat-label">Total Recipes</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-number"><?php echo number_format($publishedRecipes); ?></div>
                <div class="stat-label">Published</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-heart"></i>
                </div>
                <div class="stat-number"><?php echo number_format($userFavorites); ?></div>
                <div class="stat-label">Favorites</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar"></i>
                </div>
                <div class="stat-number"><?php echo $user['login_count'] ?? 0; ?></div>
                <div class="stat-label">Total Visits</div>
            </div>
        </section>

        <!-- Actions Grid -->
        <section class="actions-grid">
            <div class="action-card">
                <h3><i class="fas fa-search"></i> Browse Recipes</h3>
                <p>Discover thousands of delicious recipes from around the world.</p>
                <a href="index.php#categories" class="action-btn">
                    <i class="fas fa-arrow-right"></i> Browse Now
                </a>
            </div>
            
            <div class="action-card">
                <h3><i class="fas fa-heart"></i> My Favorites</h3>
                <p>View and manage your favorite recipes collection.</p>
                <a href="favorites.php" class="action-btn">
                    <i class="fas fa-arrow-right"></i> View Favorites
                </a>
            </div>
            
            <div class="action-card">
                <h3><i class="fas fa-user-cog"></i> Profile Settings</h3>
                <p>Update your profile information and preferences.</p>
                <a href="profile.php" class="action-btn">
                    <i class="fas fa-arrow-right"></i> Edit Profile
                </a>
            </div>
            
            <div class="action-card">
                <h3><i class="fas fa-upload"></i> Upload Recipe</h3>
                <p>Share your own delicious recipes with the community.</p>
                <a href="upload-recipe.php" class="action-btn">
                    <i class="fas fa-arrow-right"></i> Upload Recipe
                </a>
            </div>
            
            <div class="action-card">
                <h3><i class="fas fa-utensils"></i> My Recipes</h3>
                <p>View and manage recipes you've uploaded to the platform.</p>
                <a href="my-recipes.php" class="action-btn">
                    <i class="fas fa-arrow-right"></i> My Recipes
                </a>
            </div>
            
            <div class="action-card">
                <h3><i class="fas fa-star"></i> Recipe Reviews</h3>
                <p>See reviews and ratings for your uploaded recipes.</p>
                <a href="my-reviews.php" class="action-btn">
                    <i class="fas fa-arrow-right"></i> View Reviews
                </a>
            </div>
        </section>
    </main>

    <script>
        // Enhanced animations and interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Add loading animation to buttons
            const actionBtns = document.querySelectorAll('.action-btn');
            actionBtns.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    // Add a subtle loading effect
                    this.style.opacity = '0.8';
                    this.style.transform = 'translateY(-1px)';
                    
                    setTimeout(() => {
                        this.style.opacity = '1';
                        this.style.transform = 'translateY(-3px)';
                    }, 200);
                });
            });

            // Enhanced hover effects for cards
            const cards = document.querySelectorAll('.stat-card, .action-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-8px) scale(1.02)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });

            // Quick links hover effects
            const quickLinks = document.querySelectorAll('.quick-link');
            quickLinks.forEach(link => {
                link.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px) scale(1.02)';
                });
                
                link.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });

            // Smooth scroll for internal links
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

            // Add ripple effect to buttons
            actionBtns.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    const ripple = document.createElement('span');
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;
                    
                    ripple.style.cssText = `
                        position: absolute;
                        left: ${x}px;
                        top: ${y}px;
                        width: ${size}px;
                        height: ${size}px;
                        background: rgba(255,255,255,0.3);
                        border-radius: 50%;
                        transform: scale(0);
                        animation: ripple 0.6s ease-out;
                        pointer-events: none;
                    `;
                    
                    this.appendChild(ripple);
                    
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
            });

            // Add the ripple animation CSS
            const style = document.createElement('style');
            style.textContent = `
                @keyframes ripple {
                    to {
                        transform: scale(2);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);

            // Welcome message animation
            const welcomeSection = document.querySelector('.welcome-section');
            setTimeout(() => {
                welcomeSection.style.transform = 'scale(1.02)';
                setTimeout(() => {
                    welcomeSection.style.transform = 'scale(1)';
                }, 200);
            }, 500);

            // Stats counter animation
            const statNumbers = document.querySelectorAll('.stat-number');
            statNumbers.forEach(stat => {
                const finalValue = parseInt(stat.textContent);
                let currentValue = 0;
                const increment = Math.ceil(finalValue / 30);
                
                const timer = setInterval(() => {
                    currentValue += increment;
                    if (currentValue >= finalValue) {
                        currentValue = finalValue;
                        clearInterval(timer);
                    }
                    stat.textContent = currentValue.toLocaleString();
                }, 50);
            });
        });

        // Add interactive feedback for navigation
        const navLinks = document.querySelectorAll('.nav-links a, .logout-btn');
        navLinks.forEach(link => {
            link.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px) scale(1.05)';
            });
            
            link.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Page load animation
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