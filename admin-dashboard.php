<?php
require_once 'config.php';

// Check if admin is logged in
checkAdminLogin();

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin-login.php");
    exit();
}

// Handle recipe deletion
if (isset($_POST['delete_recipe'])) {
    $recipe_id = $_POST['recipe_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM recipes WHERE id = ?");
        $stmt->execute([$recipe_id]);
        $success_message = "Recipe deleted successfully!";
    } catch(PDOException $e) {
        $error_message = "Error deleting recipe!";
    }
}

// Fetch all recipes
try {
    $stmt = $pdo->query("SELECT * FROM recipes ORDER BY created_at DESC");
    $recipes = $stmt->fetchAll();
} catch(PDOException $e) {
    $recipes = [];
    $error_message = "Error fetching recipes!";
}

// Get total count
$total_recipes = count($recipes);

// Get the website URL dynamically
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$website_url = $protocol . '://' . $host . '/cookistry/index.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Cookistry</title>
    <link rel="icon" href="images/logo.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f5f7fa;
            color: #333;
        }

        .header {
            background: linear-gradient(135deg, #4ca1af 0%, #2c3e50 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo-section img {
            width: 40px;
            height: 40px;
        }

        .logo-section h1 {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .user-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-info {
            text-align: right;
        }

        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            padding: 0.5rem 1rem;
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #4ca1af, #2c3e50);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #4ca1af;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #666;
            font-weight: 500;
        }

        .actions-section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 3rem;
        }

        .section-title {
            font-size: 1.5rem;
            color: #2c3e50;
            margin-bottom: 1.5rem;
            font-weight: 600;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .action-btn {
            background: linear-gradient(135deg, #4ca1af 0%, #2c3e50 100%);
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(76, 161, 175, 0.3);
        }

        .view-website-btn {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .view-website-btn:hover {
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
        }

        .recipes-section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .recipes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .recipe-card {
            border: 1px solid #e1e5e9;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .recipe-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .recipe-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .recipe-content {
            padding: 1.5rem;
        }

        .recipe-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .recipe-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            color: #666;
        }

        .recipe-actions {
            display: flex;
            gap: 0.5rem;
        }

        .edit-btn, .delete-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .edit-btn {
            background: #4ca1af;
            color: white;
        }

        .delete-btn {
            background: #e74c3c;
            color: white;
        }

        .edit-btn:hover {
            background: #399ba8;
        }

        .delete-btn:hover {
            background: #c0392b;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
        }

        .empty-state h3 {
            margin-bottom: 1rem;
            color: #2c3e50;
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .container {
                padding: 1rem;
            }

            .action-buttons {
                flex-direction: column;
            }

            .action-btn {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="logo-section">
                <img src="images/logo.png" alt="Cookistry Logo">
                <h1>Admin Dashboard</h1>
            </div>
            <div class="user-section">
                <div class="user-info">
                    <div>Welcome, <?php echo htmlspecialchars($_SESSION['admin_username']); ?>!</div>
                    <div style="font-size: 0.9rem; opacity: 0.8;">Admin Panel</div>
                </div>
                <a href="?logout=1" class="logout-btn">Logout</a>
            </div>
        </div>
    </header>

    <div class="container">
        <?php if (isset($success_message)): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($success_message); ?>
        </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
        <div class="alert alert-error">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>

        <div class="dashboard-stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_recipes; ?></div>
                <div class="stat-label">Total Recipes</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo date('d'); ?></div>
                <div class="stat-label">Today's Date</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">1</div>
                <div class="stat-label">Active Admin</div>
            </div>
        </div>

        <div class="actions-section">
            <h2 class="section-title">Quick Actions</h2>
            <div class="action-buttons">
                <a href="add-recipe.php" class="action-btn">
                    ➕ Add New Recipe
                </a>
                <a href="<?php echo $website_url; ?>" class="action-btn view-website-btn" target="_blank">
                    🏠 View Website
                </a>
                <a href="manage-recipes.php" class="action-btn">
                    📝 Manage All Recipes
                </a>
            </div>
        </div>

        <div class="recipes-section">
            <h2 class="section-title">Recent Recipes (<?php echo $total_recipes; ?>)</h2>
            
            <?php if (empty($recipes)): ?>
            <div class="empty-state">
                <h3>No Recipes Found</h3>
                <p>Start by adding your first recipe!</p>
                <a href="add-recipe.php" class="action-btn" style="margin-top: 1rem;">Add First Recipe</a>
            </div>
            <?php else: ?>
            <div class="recipes-grid">
                <?php foreach ($recipes as $recipe): ?>
                <div class="recipe-card">
                    <?php
                    $image_path = htmlspecialchars($recipe['image']);
                    // Check if image file exists, use default if not
                    if (empty($image_path) || !file_exists($image_path)) {
                        $image_path = 'images/default-recipe.jpg'; // Ensure this file exists
                    }
                    ?>
                    <img src="<?php echo $image_path; ?>" alt="<?php echo htmlspecialchars($recipe['title']); ?>" class="recipe-image">
                    <div class="recipe-content">
                        <h3 class="recipe-title"><?php echo htmlspecialchars($recipe['title']); ?></h3>
                        <div class="recipe-meta">
                            <span>⏱️ <?php echo htmlspecialchars($recipe['prep_time']); ?></span>
                            <span>⭐ <?php echo htmlspecialchars($recipe['rating']); ?></span>
                            <span>📊 <?php echo htmlspecialchars($recipe['difficulty']); ?></span>
                        </div>
                        <div class="recipe-actions">
                            <button class="edit-btn" onclick="window.location.href='edit-recipe.php?id=<?php echo $recipe['id']; ?>'">
                                Edit
                            </button>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this recipe?')">
                                <input type="hidden" name="recipe_id" value="<?php echo $recipe['id']; ?>">
                                <button type="submit" name="delete_recipe" class="delete-btn">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Add click confirmation for View Website button
        document.querySelector('.view-website-btn').addEventListener('click', function(e) {
            // Optional: Add a small delay to show the button was clicked
            this.style.transform = 'translateY(-2px) scale(0.98)';
            setTimeout(() => {
                this.style.transform = 'translateY(-2px) scale(1)';
            }, 150);
        });

        // Add visual feedback for all action buttons
        document.querySelectorAll('.action-btn').forEach(btn => {
            btn.addEventListener('mousedown', function() {
                this.style.transform = 'translateY(0) scale(0.98)';
            });
            
            btn.addEventListener('mouseup', function() {
                this.style.transform = 'translateY(-2px) scale(1)';
            });
        });
    </script>
</body>
</html>