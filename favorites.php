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

// Handle remove favorite action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_favorite') {
    $recipe_id = intval($_POST['recipe_id']);
    
    try {
        $stmt = $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND recipe_id = ?");
        $stmt->execute([$user['id'], $recipe_id]);
        
        // Redirect to refresh the page
        header("Location: favorites.php?removed=1");
        exit();
    } catch (PDOException $e) {
        $error = "Failed to remove favorite. Please try again.";
    }
}

// Get user's favorite recipes
try {
    $stmt = $pdo->prepare("
        SELECT r.*, f.created_at as favorited_at 
        FROM favorites f 
        JOIN recipes r ON f.recipe_id = r.id 
        WHERE f.user_id = ? 
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$user['id']]);
    $favorites = $stmt->fetchAll();
    
    // Get favorite count
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ?");
    $countStmt->execute([$user['id']]);
    $favoriteCount = $countStmt->fetchColumn();
    
} catch (PDOException $e) {
    logError("Favorites page error: " . $e->getMessage());
    $favorites = [];
    $favoriteCount = 0;
}

$success = isset($_GET['removed']) ? "Recipe removed from favorites successfully!" : "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Favorites - Cookistry</title>
    <link rel="icon" href="images/logo.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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

        /* Header */
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

        /* Main Content */
        .main-content {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .page-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            border: 1px solid rgba(255, 255, 255, 0.6);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #4ca1af 0%, #2c3e50 50%, #4ca1af 100%);
            animation: shimmer 3s ease-in-out infinite;
        }

        .page-header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #4ca1af 0%, #2c3e50 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 700;
        }

        .page-header p {
            color: #64748b;
            font-size: 1.2rem;
            font-weight: 400;
        }

        .favorites-count {
            display: inline-block;
            background: rgba(76, 161, 175, 0.1);
            color: #4ca1af;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            margin-top: 1rem;
        }

        /* Success Message */
        .success-message {
            background: rgba(16, 185, 129, 0.1);
            border: 2px solid rgba(16, 185, 129, 0.3);
            color: #059669;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            font-weight: 500;
        }

        /* Favorites Grid */
        .favorites-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
        }

        .favorite-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(255, 255, 255, 0.6);
            position: relative;
        }

        .favorite-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 30px 60px rgba(0,0,0,0.15);
        }

        .favorite-image {
            height: 200px;
            position: relative;
            overflow: hidden;
        }

        .favorite-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }

        .favorite-card:hover .favorite-image img {
            transform: scale(1.1);
        }

        .favorite-overlay {
            position: absolute;
            top: 1rem;
            left: 1rem;
            right: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .favorite-date {
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 0.5rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            backdrop-filter: blur(10px);
        }

        .remove-favorite {
            background: rgba(239, 68, 68, 0.9);
            color: white;
            border: none;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .remove-favorite:hover {
            background: rgba(239, 68, 68, 1);
            transform: scale(1.1);
        }

        .favorite-content {
            padding: 2rem;
        }

        .favorite-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 0.8rem;
            line-height: 1.3;
        }

        .favorite-category {
            display: inline-block;
            background: rgba(76, 161, 175, 0.1);
            color: #4ca1af;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
            margin-bottom: 1rem;
            text-transform: capitalize;
        }

        .favorite-description {
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 1.5rem;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .favorite-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            color: #64748b;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .favorite-actions {
            display: flex;
            gap: 0.8rem;
        }

        .action-btn {
            flex: 1;
            background: linear-gradient(135deg, #4ca1af 0%, #2c3e50 100%);
            color: white;
            padding: 0.8rem 1rem;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            text-align: center;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(76, 161, 175, 0.3);
            color: white;
            text-decoration: none;
        }

        .remove-btn {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            padding: 0.8rem;
            border: none;
            border-radius: 12px;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .remove-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.3);
        }

        /* Empty State */
        .empty-state {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 4rem 2rem;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            border: 1px solid rgba(255, 255, 255, 0.6);
        }

        .empty-state-icon {
            font-size: 5rem;
            color: #94a3b8;
            margin-bottom: 2rem;
        }

        .empty-state h3 {
            font-size: 1.8rem;
            color: #374151;
            margin-bottom: 1rem;
        }

        .empty-state p {
            color: #64748b;
            font-size: 1.1rem;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .action-btn-primary {
            background: linear-gradient(135deg, #4ca1af 0%, #2c3e50 100%);
            color: white;
            padding: 1.2rem 2rem;
            text-decoration: none;
            border-radius: 15px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.8rem;
            transition: all 0.4s ease;
            font-size: 1rem;
            box-shadow: 0 8px 25px rgba(76, 161, 175, 0.3);
        }

        .action-btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(76, 161, 175, 0.4);
            color: white;
            text-decoration: none;
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

        .favorite-card {
            animation: fadeInUp 0.6s ease forwards;
        }

        .favorite-card:nth-child(1) { animation-delay: 0.1s; }
        .favorite-card:nth-child(2) { animation-delay: 0.2s; }
        .favorite-card:nth-child(3) { animation-delay: 0.3s; }
        .favorite-card:nth-child(4) { animation-delay: 0.4s; }

        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .header-container {
                padding: 0 1rem;
                flex-direction: column;
                gap: 1rem;
            }
            
            .main-content {
                padding: 0 1rem;
                margin: 1rem auto;
            }
            
            .page-header {
                padding: 2rem;
            }
            
            .page-header h1 {
                font-size: 2rem;
            }
            
            .favorites-grid {
                grid-template-columns: 1fr;
            }
            
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }

            .favorite-actions {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .favorite-content {
                padding: 1.5rem;
            }

            .favorite-meta {
                flex-direction: column;
                gap: 0.5rem;
                align-items: flex-start;
            }
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
            
            <div class="nav-links">
                <a href="user-dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="index.php"><i class="fas fa-home"></i> Home</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Page Header -->
        <section class="page-header">
            <h1><i class="fas fa-heart"></i> My Favorite Recipes</h1>
            <p>Your collection of saved recipes</p>
            <?php if ($favoriteCount > 0): ?>
                <div class="favorites-count">
                    <i class="fas fa-bookmark"></i> <?php echo $favoriteCount; ?> Recipe<?php echo $favoriteCount != 1 ? 's' : ''; ?> Saved
                </div>
            <?php endif; ?>
        </section>

        <!-- Success Message -->
        <?php if (!empty($success)): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <!-- Content -->
        <?php if (!empty($favorites)): ?>
            <section class="favorites-grid">
                <?php foreach ($favorites as $recipe): ?>
                    <div class="favorite-card">
                        <div class="favorite-image">
                            <?php 
                            $imagePath = !empty($recipe['image']) ? htmlspecialchars($recipe['image']) : 'images/default-recipe.jpg';
                            if (!file_exists($imagePath) && !filter_var($imagePath, FILTER_VALIDATE_URL)) {
                                $imagePath = 'images/default-recipe.jpg';
                            }
                            ?>
                            <img src="<?php echo $imagePath; ?>" alt="<?php echo htmlspecialchars($recipe['title']); ?>">
                            
                            <div class="favorite-overlay">
                                <div class="favorite-date">
                                    <i class="fas fa-heart"></i> Saved <?php echo date('M j', strtotime($recipe['favorited_at'])); ?>
                                </div>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Remove this recipe from favorites?')">
                                    <input type="hidden" name="action" value="remove_favorite">
                                    <input type="hidden" name="recipe_id" value="<?php echo $recipe['id']; ?>">
                                    <button type="submit" class="remove-favorite" title="Remove from favorites">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <div class="favorite-content">
                            <h3 class="favorite-title"><?php echo htmlspecialchars($recipe['title']); ?></h3>
                            
                            <div class="favorite-category">
                                <?php echo htmlspecialchars($recipe['category']); ?>
                            </div>
                            
                            <p class="favorite-description">
                                <?php echo htmlspecialchars($recipe['description']); ?>
                            </p>
                            
                            <div class="favorite-meta">
                                <div class="meta-item">
                                    <i class="fas fa-clock"></i>
                                    <span><?php echo htmlspecialchars($recipe['prep_time'] ?? '30 min'); ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-users"></i>
                                    <span><?php echo htmlspecialchars($recipe['servings'] ?? '4'); ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-signal"></i>
                                    <span><?php echo htmlspecialchars($recipe['difficulty'] ?? 'Medium'); ?></span>
                                </div>
                            </div>
                            
                            <div class="favorite-actions">
                                <a href="recipe-detail.php?id=<?php echo $recipe['id']; ?>" class="action-btn">
                                    <i class="fas fa-eye"></i> View Recipe
                                </a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Remove this recipe from favorites?')">
                                    <input type="hidden" name="action" value="remove_favorite">
                                    <input type="hidden" name="recipe_id" value="<?php echo $recipe['id']; ?>">
                                    <button type="submit" class="remove-btn">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </section>

        <?php else: ?>
            <!-- Empty State -->
            <section class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-heart-broken"></i>
                </div>
                <h3>No Favorites Yet</h3>
                <p>Start exploring recipes and add them to your favorites by clicking the heart icon. Your saved recipes will appear here for easy access.</p>
                <a href="index.php#categories" class="action-btn-primary">
                    <i class="fas fa-search"></i> Browse Recipes
                </a>
            </section>
        <?php endif; ?>
    </main>

    <script>
        // Enhanced animations and interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to favorite cards
            const favoriteCards = document.querySelectorAll('.favorite-card');
            favoriteCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-8px) scale(1.02)';
                });
                
                card.addEventListener('mouseleave', function() {
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

            // Enhanced button interactions
            const actionBtns = document.querySelectorAll('.action-btn, .action-btn-primary');
            actionBtns.forEach(btn => {
                btn.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-3px) scale(1.02)';
                });
                
                btn.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });

            // Auto-hide success message
            const successMessage = document.querySelector('.success-message');
            if (successMessage) {
                setTimeout(() => {
                    successMessage.style.opacity = '0';
                    successMessage.style.transform = 'translateY(-10px)';
                    setTimeout(() => {
                        successMessage.remove();
                    }, 300);
                }, 5000);
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