<?php
// Include database configuration
require_once 'config.php';

// Get page number for pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$recipes_per_page = 12;
$offset = ($page - 1) * $recipes_per_page;

// Get filter parameters
$category_filter = isset($_GET['category']) ? $_GET['category'] : 'all';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$difficulty_filter = isset($_GET['difficulty']) ? $_GET['difficulty'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build SQL query
$where_conditions = [];
$params = [];

// Add category filter
if ($category_filter !== 'all') {
    $where_conditions[] = "category = ?";
    $params[] = $category_filter;
}

// Add search condition
if (!empty($search)) {
    $where_conditions[] = "(title LIKE ? OR description LIKE ? OR ingredients LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Add difficulty filter
if ($difficulty_filter !== 'all') {
    $where_conditions[] = "difficulty = ?";
    $params[] = $difficulty_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Determine ORDER BY clause
$order_by = "created_at DESC"; // default
switch ($sort_by) {
    case 'rating':
        $order_by = "rating DESC, created_at DESC";
        break;
    case 'time':
        $order_by = "cooking_time ASC, created_at DESC";
        break;
    case 'title':
        $order_by = "title ASC";
        break;
    case 'views':
        $order_by = "views DESC, created_at DESC";
        break;
}

try {
    // Get total count for pagination
    $count_sql = "SELECT COUNT(*) as total FROM recipes $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_recipes = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_recipes / $recipes_per_page);

    // Get recipes for current page
    $sql = "SELECT * FROM recipes $where_clause ORDER BY $order_by LIMIT $recipes_per_page OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $recipes = $stmt->fetchAll();

    // Get category counts for filter
    $category_counts = $pdo->query("SELECT category, COUNT(*) as count FROM recipes GROUP BY category")->fetchAll();

} catch(PDOException $e) {
    logError("All recipes page error: " . $e->getMessage());
    $recipes = [];
    $total_recipes = 0;
    $total_pages = 0;
    $category_counts = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Recipes - Cookistry</title>
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="images/logo.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 100px 0 80px;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="50" cy="10" r="0.5" fill="white" opacity="0.15"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }
        
        .page-header h1 {
            font-size: 4rem;
            margin-bottom: 1rem;
            position: relative;
            z-index: 2;
        }
        
        .page-header p {
            font-size: 1.3rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
            position: relative;
            z-index: 2;
        }
        
        .filters-section {
            background: white;
            padding: 40px 0;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .filters-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .search-bar {
            margin-bottom: 30px;
        }
        
        .search-bar input {
            width: 100%;
            max-width: 500px;
            padding: 15px 25px;
            border: 2px solid #e1e8ed;
            border-radius: 30px;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            display: block;
            margin: 0 auto;
        }
        
        .search-bar input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
        }
        
        .filter-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            justify-content: center;
            margin-bottom: 30px;
        }
        
        .filter-tab {
            padding: 12px 25px;
            border: 2px solid #e1e8ed;
            border-radius: 25px;
            background: white;
            color: #5a6c7d;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .filter-tab.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .filter-tab:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
            transform: translateY(-2px);
        }
        
        .filter-controls {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
            align-items: center;
        }
        
        .filter-controls select {
            padding: 10px 15px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            background: white;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .filter-controls select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .recipes-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 60px 20px;
        }
        
        .results-info {
            text-align: center;
            margin-bottom: 40px;
            color: #7f8c8d;
            font-size: 1.1rem;
        }
        
        .recipes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 30px;
            margin-bottom: 60px;
        }
        
        .recipe-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            position: relative;
        }
        
        .recipe-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }
        
        .recipe-image-container {
            position: relative;
            height: 220px;
            overflow: hidden;
        }
        
        .recipe-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .recipe-card:hover .recipe-image {
            transform: scale(1.1);
        }
        
        .recipe-category-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            background: rgba(102, 126, 234, 0.9);
            color: white;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: capitalize;
        }
        
        .recipe-time-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .recipe-content {
            padding: 25px;
        }
        
        .recipe-content h3 {
            font-size: 1.4rem;
            color: #2c3e50;
            margin-bottom: 12px;
            line-height: 1.3;
        }
        
        .recipe-description {
            color: #5a6c7d;
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 20px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .recipe-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            font-size: 0.85rem;
            color: #7f8c8d;
        }
        
        .recipe-stats {
            display: flex;
            gap: 15px;
        }
        
        .recipe-stats span {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .recipe-rating {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #f39c12;
        }
        
        .btn-recipe {
            display: block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 25px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 500;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .btn-recipe:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin: 60px 0;
            flex-wrap: wrap;
        }
        
        .pagination a, .pagination span {
            padding: 12px 18px;
            border: 2px solid #e1e8ed;
            border-radius: 10px;
            text-decoration: none;
            color: #5a6c7d;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .pagination a:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
            transform: translateY(-2px);
        }
        
        .pagination .active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .no-recipes {
            text-align: center;
            padding: 80px 20px;
            color: #7f8c8d;
        }
        
        .no-recipes i {
            font-size: 5rem;
            margin-bottom: 30px;
            color: #bdc3c7;
        }
        
        .no-recipes h3 {
            font-size: 2rem;
            margin-bottom: 15px;
            color: #5a6c7d;
        }
        
        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 2.5rem;
            }
            
            .filter-tabs {
                justify-content: flex-start;
                overflow-x: auto;
                padding-bottom: 10px;
            }
            
            .filter-controls {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-controls select {
                width: 100%;
            }
            
            .recipes-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="navbar">
            <a href="index.php">
                <img src="images/logo.png" alt="Cookistry Logo" class="logo">
            </a>
            <nav>
                <ul class="nav-links">
                    <li><a href="index.php">Home</a></li>
                    <li class="dropdown">
                        <a href="#">Categories <i class="fas fa-chevron-down"></i></a>
                        <ul class="dropdown-content">
                            <li><a href="appetizer.php?ppetizer">Appetizer</a></li>
                            <li><a href="breakfast.php?Breakfast">Breakfast</a></li>
                            <li><a href="lunch.php?Lunch">Lunch</a></li>
                            <li><a href="dinner.php?Dinner">Dinner</a></li>
                            <li><a href="dessert.php?Dessert">Dessert</a></li>
                        </ul>
                    </li>
                    <li><a href="all-recipes.php" class="active">All Recipes</a></li>
                    <li><a href="contact.html">Contact</a></li>
                    <li><a href="about.html">About</a></li>
                    <li><a href="login.html">Login</a></li>
                    <li><a href="signup.html">Signup</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Page Header -->
    <section class="page-header">
        <h1>All Recipes</h1>
        <p>Discover amazing recipes from our community of passionate cooks</p>
    </section>

    <!-- Filters Section -->
    <section class="filters-section">
        <div class="filters-container">
            <!-- Search Bar -->
            <div class="search-bar">
                <form method="GET" action="">
                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($category_filter); ?>">
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort_by); ?>">
                    <input type="hidden" name="difficulty" value="<?php echo htmlspecialchars($difficulty_filter); ?>">
                    <input type="text" name="search" placeholder="Search recipes, ingredients, or descriptions..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </form>
            </div>
            
            <!-- Category Filter Tabs -->
            <div class="filter-tabs">
                <a href="?category=all&sort=<?php echo $sort_by; ?>&difficulty=<?php echo $difficulty_filter; ?>&search=<?php echo urlencode($search); ?>" 
                   class="filter-tab <?php echo $category_filter === 'all' ? 'active' : ''; ?>">
                    All Categories
                </a>
                <?php foreach ($category_counts as $cat): ?>
                    <a href="?category=<?php echo $cat['category']; ?>&sort=<?php echo $sort_by; ?>&difficulty=<?php echo $difficulty_filter; ?>&search=<?php echo urlencode($search); ?>" 
                       class="filter-tab <?php echo $category_filter === $cat['category'] ? 'active' : ''; ?>">
                        <?php echo ucfirst($cat['category']); ?> (<?php echo $cat['count']; ?>)
                    </a>
                <?php endforeach; ?>
            </div>
            
            <!-- Sort and Filter Controls -->
            <div class="filter-controls">
                <select onchange="applyFilters()" id="sort-filter">
                    <option value="newest" <?php echo $sort_by === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                    <option value="rating" <?php echo $sort_by === 'rating' ? 'selected' : ''; ?>>Highest Rated</option>
                    <option value="views" <?php echo $sort_by === 'views' ? 'selected' : ''; ?>>Most Popular</option>
                    <option value="time" <?php echo $sort_by === 'time' ? 'selected' : ''; ?>>Quickest First</option>
                    <option value="title" <?php echo $sort_by === 'title' ? 'selected' : ''; ?>>Alphabetical</option>
                </select>
                
                <select onchange="applyFilters()" id="difficulty-filter">
                    <option value="all" <?php echo $difficulty_filter === 'all' ? 'selected' : ''; ?>>All Difficulty Levels</option>
                    <option value="Easy" <?php echo $difficulty_filter === 'Easy' ? 'selected' : ''; ?>>Easy</option>
                    <option value="Medium" <?php echo $difficulty_filter === 'Medium' ? 'selected' : ''; ?>>Medium</option>
                    <option value="Hard" <?php echo $difficulty_filter === 'Hard' ? 'selected' : ''; ?>>Hard</option>
                </select>
            </div>
        </div>
    </section>

    <!-- Recipes Container -->
    <section class="recipes-container">
        <!-- Results Info -->
        <?php if ($total_recipes > 0): ?>
        <div class="results-info">
            Showing <?php echo count($recipes); ?> of <?php echo $total_recipes; ?> recipes
            <?php if (!empty($search)): ?>
                for "<?php echo htmlspecialchars($search); ?>"
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Recipes Grid -->
        <div class="recipes-grid">
            <?php if (empty($recipes)): ?>
                <div class="no-recipes">
                    <i class="fas fa-search"></i>
                    <h3>No recipes found</h3>
                    <p>Try adjusting your search criteria or browse our categories.</p>
                </div>
            <?php else: ?>
                <?php foreach ($recipes as $recipe): ?>
                    <div class="recipe-card">
                        <div class="recipe-image-container">
                            <?php
                            $imagePath = !empty($recipe['image']) ? "uploads/" . $recipe['image'] : "images/recipe-placeholder.jpg";
                            if (!empty($recipe['image']) && !file_exists($imagePath)) {
                                $imagePath = "images/recipe-placeholder.jpg";
                            }
                            ?>
                            <img src="<?php echo htmlspecialchars($imagePath); ?>" 
                                 alt="<?php echo htmlspecialchars($recipe['title']); ?>" 
                                 class="recipe-image" loading="lazy">
                            
                            <div class="recipe-category-badge">
                                <?php echo htmlspecialchars($recipe['category']); ?>
                            </div>
                            
                            <?php if (!empty($recipe['cooking_time'])): ?>
                                <div class="recipe-time-badge">
                                    <i class="fas fa-clock"></i>
                                    <?php echo htmlspecialchars($recipe['cooking_time']); ?>m
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="recipe-content">
                            <h3><?php echo htmlspecialchars($recipe['title']); ?></h3>
                            
                            <p class="recipe-description">
                                <?php 
                                $description = htmlspecialchars($recipe['description']);
                                echo strlen($description) > 120 ? substr($description, 0, 120) . '...' : $description;
                                ?>
                            </p>
                            
                            <div class="recipe-meta">
                                <div class="recipe-stats">
                                    <?php if (!empty($recipe['difficulty'])): ?>
                                        <span><i class="fas fa-signal"></i> <?php echo htmlspecialchars($recipe['difficulty']); ?></span>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($recipe['servings'])): ?>
                                        <span><i class="fas fa-users"></i> <?php echo htmlspecialchars($recipe['servings']); ?></span>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($recipe['views'])): ?>
                                        <span><i class="fas fa-eye"></i> <?php echo number_format($recipe['views']); ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (!empty($recipe['rating']) && $recipe['rating'] > 0): ?>
                                    <div class="recipe-rating">
                                        <i class="fas fa-star"></i>
                                        <?php echo number_format($recipe['rating'], 1); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <a href="recipe_detail.php?id=<?php echo $recipe['id']; ?>" class="btn-recipe">
                                View Recipe
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?category=<?php echo $category_filter; ?>&page=<?php echo $page-1; ?>&sort=<?php echo $sort_by; ?>&difficulty=<?php echo $difficulty_filter; ?>&search=<?php echo urlencode($search); ?>">
                    <i class="fas fa-chevron-left"></i> Previous
                </a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="active"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?category=<?php echo $category_filter; ?>&page=<?php echo $i; ?>&sort=<?php echo $sort_by; ?>&difficulty=<?php echo $difficulty_filter; ?>&search=<?php echo urlencode($search); ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?category=<?php echo $category_filter; ?>&page=<?php echo $page+1; ?>&sort=<?php echo $sort_by; ?>&difficulty=<?php echo $difficulty_filter; ?>&search=<?php echo urlencode($search); ?>">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </section>

    <!-- Footer -->
    <footer class="enhanced-footer">
        <div class="footer-grid">
            <div class="footer-section">
                <h3>About Cookistry</h3>
                <p>Cookistry is your gateway to creative cooking. Whether you're a beginner or a seasoned chef, we help you discover exciting recipes, cooking tips, and kitchen inspirations to make every meal memorable.</p>
                <div class="social-links">
                    <a href="#" class="social-link" aria-label="Facebook"><i class="fab fa-facebook"></i></a>
                    <a href="#" class="social-link" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="social-link" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="social-link" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
                </div>
            </div>

            <div class="footer-section">
                <h3>Quick Links</h3>
                <ul>
                    <li><a href="all-recipes.php">All Recipes</a></li>
                    <li><a href="add_recipe.php">Submit Recipe</a></li>
                    <li><a href="about.html">About Us</a></li>
                    <li><a href="contact.html">Contact</a></li>
                    <li><a href="privacy.html">Privacy Policy</a></li>
                </ul>
            </div>

            <div class="footer-section">
                <h3>Contact Us</h3>
                <div class="contact-info">
                    <p><i class="fas fa-envelope"></i> support@cookistry.com</p>
                    <p><i class="fas fa-phone"></i> +880-1234-567890</p>
                    <p><i class="fas fa-map-marker-alt"></i> Chittagong, Bangladesh</p>
                </div>
            </div>

            <div class="footer-section">
                <h3>Newsletter</h3>
                <p>Subscribe to get the latest recipes and cooking tips!</p>
                <form class="newsletter-form" action="subscribe.php" method="POST">
                    <input type="email" name="email" placeholder="Your email address" required>
                    <button type="submit"><i class="fas fa-paper-plane"></i></button>
                </form>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="container">
                <p>&copy; 2025 Cookistry. All rights reserved. Made with <i class="fas fa-heart"></i> in Bangladesh</p>
            </div>
        </div>
    </footer>

    <script>
        function applyFilters() {
            const category = '<?php echo $category_filter; ?>';
            const sort = document.getElementById('sort-filter').value;
            const difficulty = document.getElementById('difficulty-filter').value;
            const search = '<?php echo addslashes($search); ?>';
            
            const url = `all-recipes.php?category=${category}&sort=${sort}&difficulty=${difficulty}&search=${encodeURIComponent(search)}`;
            window.location.href = url;
        }

        // Auto-submit search form on Enter
        document.querySelector('input[name="search"]').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.form.submit();
            }
        });

        // Smooth scroll for anchor links
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

        // Add loading animation to recipe cards
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        document.querySelectorAll('.recipe-card').forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(30px)';
            card.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
            observer.observe(card);
        });
    </script>
</body>
</html>