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

// Get user's recipes
try {
    $stmt = $pdo->prepare("SELECT * FROM recipes WHERE author_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user['id']]);
    $myRecipes = $stmt->fetchAll();
} catch (PDOException $e) {
    logError("My recipes error: " . $e->getMessage());
    $myRecipes = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Recipes - Cookistry</title>
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

        /* Header styles - same as other pages */
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

        /* Recipe Grid */
        .recipes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .recipe-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            transition: all 0.4s ease;
            border: 1px solid rgba(255, 255, 255, 0.6);
            position: relative;
        }

        .recipe-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 30px 60px rgba(0,0,0,0.15);
        }

        .recipe-image {
            height: 200px;
            background: linear-gradient(135deg, #4ca1af, #2c3e50);
            position: relative;
            overflow: hidden;
        }

        .recipe-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .recipe-status {
            position: absolute;
            top: 1rem;
            right: 1rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            backdrop-filter: blur(10px);
        }

        .status-pending {
            background: rgba(251, 191, 36, 0.9);
            color: #92400e;
        }

        .status-published {
            background: rgba(34, 197, 94, 0.9);
            color: #166534;
        }

        .status-rejected {
            background: rgba(239, 68, 68, 0.9);
            color: #991b1b;
        }

        .recipe-content {
            padding: 2rem;
        }

        .recipe-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 0.8rem;
        }

        .recipe-category {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            background: rgba(76, 161, 175, 0.1);
            color: #4ca1af;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
            margin-bottom: 1rem;
            text-transform: capitalize;
        }

        .recipe-description {
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 1.5rem;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .recipe-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid rgba(226, 232, 240, 0.5);
        }

        .recipe-date {
            color: #94a3b8;
            font-size: 0.9rem;
        }

        .recipe-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-small {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        .btn-view {
            background: rgba(76, 161, 175, 0.1);
            color: #4ca1af;
            border: 1px solid rgba(76, 161, 175, 0.3);
        }

        .btn-view:hover {
            background: #4ca1af;
            color: white;
        }

        .btn-edit {
            background: rgba(251, 191, 36, 0.1);
            color: #d97706;
            border: 1px solid rgba(251, 191, 36, 0.3);
        }

        .btn-edit:hover {
            background: #d97706;
            color: white;
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
            transition: all 0.4s ease;
            font-size: 1rem;
            box-shadow: 0 8px 25px rgba(76, 161, 175, 0.3);
        }

        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(76, 161, 175, 0.4);
            background: linear-gradient(135deg, #2c3e50 0%, #4ca1af 100%);
        }

        @keyframes shimmer {
            0%, 100% { transform: translateX(-100%); }
            50% { transform: translateX(400%); }
        }

        /* Stats Bar */
        .stats-bar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            border: 1px solid rgba(255, 255, 255, 0.6);
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 2rem;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.9rem;
            font-weight: 500;
        }

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
            
            .recipes-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-bar {
                grid-template-columns: repeat(2, 1fr);
                padding: 1.5rem;
            }
            
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .recipe-content {
                padding: 1.5rem;
            }
            
            .stats-bar {
                grid-template-columns: 1fr;
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
                <a href="upload-recipe.php"><i class="fas fa-plus"></i> Upload Recipe</a>
                <a href="index.php"><i class="fas fa-home"></i> Home</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Page Header -->
        <section class="page-header">
            <h1><i class="fas fa-utensils"></i> My Recipes</h1>
            <p>Manage your uploaded recipes and track their status</p>
        </section>

        <?php if (!empty($myRecipes)): ?>
            <!-- Stats Bar -->
            <section class="stats-bar">
                <div class="stat-item">
                    <div class="stat-number"><?php echo count($myRecipes); ?></div>
                    <div class="stat-label">Total Recipes</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">
                        <?php echo count(array_filter($myRecipes, function($r) { return ($r['status'] ?? 'pending') === 'published'; })); ?>
                    </div>
                    <div class="stat-label">Published</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">
                        <?php echo count(array_filter($myRecipes, function($r) { return ($r['status'] ?? 'pending') === 'pending'; })); ?>
                    </div>
                    <div class="stat-label">Pending Review</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">
                        <?php echo count(array_filter($myRecipes, function($r) { return ($r['status'] ?? 'pending') === 'rejected'; })); ?>
                    </div>
                    <div class="stat-label">Rejected</div>
                </div>
            </section>

            <!-- Recipes Grid -->
            <section class="recipes-grid">
                <?php foreach ($myRecipes as $recipe): ?>
                    <div class="recipe-card">
                        <div class="recipe-image">
                            <?php if (!empty($recipe['image'])): ?>
                                <img src="<?php echo htmlspecialchars($recipe['image']); ?>" 
                                     alt="<?php echo htmlspecialchars($recipe['title']); ?>" />
                            <?php else: ?>
                                <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: white; font-size: 3rem;">
                                    <i class="fas fa-utensils"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="recipe-status status-<?php echo ($recipe['status'] ?? 'pending'); ?>">
                                <?php 
                                $status = $recipe['status'] ?? 'pending';
                                echo ucfirst($status);
                                ?>
                            </div>
                        </div>
                        
                        <div class="recipe-content">
                            <h3 class="recipe-title"><?php echo htmlspecialchars($recipe['title']); ?></h3>
                            
                            <div class="recipe-category">
                                <?php echo htmlspecialchars($recipe['category']); ?>
                            </div>
                            
                            <p class="recipe-description">
                                <?php echo htmlspecialchars($recipe['description']); ?>
                            </p>
                            
                            <div class="recipe-meta">
                                <span class="recipe-date">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo date('M j, Y', strtotime($recipe['created_at'])); ?>
                                </span>
                                
                                <div class="recipe-actions">
                                    <?php if (($recipe['status'] ?? 'pending') === 'published'): ?>
                                        <a href="recipe-detail.php?id=<?php echo $recipe['id']; ?>" class="btn-small btn-view">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    <?php endif; ?>
                                    <a href="edit-recipe.php?id=<?php echo $recipe['id']; ?>" class="btn-small btn-edit">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </section>

        <?php else: ?>
            <!-- Empty State -->
            <section class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-utensils"></i>
                </div>
                <h3>No Recipes Yet</h3>
                <p>You haven't uploaded any recipes yet. Start sharing your delicious creations with our community!</p>
                <a href="upload-recipe.php" class="action-btn">
                    <i class="fas fa-plus"></i> Upload Your First Recipe
                </a>
            </section>
        <?php endif; ?>
    </main>

    <script>
        // Add some interactive animations
        document.querySelectorAll('.recipe-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-8px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Smooth scrolling
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

        // Add fade-in animation for cards
        const cards = document.querySelectorAll('.recipe-card');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(30px)';
            card.style.transition = 'all 0.6s ease';
            
            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });
    </script>
</body>
</html>
            