<?php
session_start();
require_once 'config.php';
require_once 'auth_functions.php';

// Set basic security headers
setBasicSecurityHeaders();
basicSessionSecurity();

// Check if user is logged in
$isLoggedIn = isUserLoggedIn();
$currentUser = null;
if ($isLoggedIn) {
    $currentUser = getCurrentUser();
}

// Generate CSRF token for forms
$csrfToken = generateCSRFToken();

// Get search and filter parameters
$searchTerm = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$selectedCategory = isset($_GET['category']) ? sanitizeInput($_GET['category']) : '';

try {
    // Base query for quick recipes (recipes with prep time <= 30 minutes or quick keywords)
    $baseCondition = "(status = 'published' OR status IS NULL OR status = '') 
                      AND (prep_time LIKE '%15%' OR prep_time LIKE '%20%' OR prep_time LIKE '%30%' 
                           OR prep_time LIKE '%quick%' OR prep_time LIKE '%fast%' OR prep_time LIKE '%easy%')";
    
    // Get all quick recipes grouped by category
    $categorySql = "SELECT category, COUNT(*) as count FROM recipes WHERE $baseCondition GROUP BY category ORDER BY category";
    $categoryStmt = $pdo->prepare($categorySql);
    $categoryStmt->execute();
    $categoryStats = $categoryStmt->fetchAll();

    // Build main query with filters
    $sql = "SELECT * FROM recipes WHERE $baseCondition";
    $params = [];

    if (!empty($searchTerm)) {
        $sql .= " AND (title LIKE :search OR description LIKE :search OR tags LIKE :search)";
        $params[':search'] = "%$searchTerm%";
    }

    if (!empty($selectedCategory) && $selectedCategory !== 'all') {
        $sql .= " AND LOWER(category) = :category";
        $params[':category'] = strtolower($selectedCategory);
    }

    $sql .= " ORDER BY category ASC, created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $allQuickRecipes = $stmt->fetchAll();

    // Group recipes by category
    $recipesByCategory = [];
    foreach ($allQuickRecipes as $recipe) {
        $category = $recipe['category'] ?: 'Other';
        if (!isset($recipesByCategory[$category])) {
            $recipesByCategory[$category] = [];
        }
        $recipesByCategory[$category][] = $recipe;
    }

    // Get total count
    $totalCount = count($allQuickRecipes);

} catch (PDOException $e) {
    logError("Quick picks page error: " . $e->getMessage());
    $allQuickRecipes = [];
    $recipesByCategory = [];
    $categoryStats = [];
    $totalCount = 0;
}

// Category display names
$categoryNames = [
    'appetizer' => 'Appetizers',
    'breakfast' => 'Breakfast',
    'lunch' => 'Lunch',
    'dinner' => 'Dinner',
    'dessert' => 'Desserts',
    'bread-bakes' => 'Bread & Bakes',
    'salads' => 'Salads',
    'healthy' => 'Healthy Food',
    'beverages' => 'Beverages',
    'snacks' => 'Snacks'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Quick Picks - Fast & Easy Recipes | Cookistry</title>
    <link rel="stylesheet" href="style.css" />
    <link rel="icon" href="images/logo.png" type="image/png" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Quick Picks Page Specific Styles */
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4rem 0 2rem;
            text-align: center;
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
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            opacity: 0.3;
        }

        .page-header-content {
            position: relative;
            z-index: 2;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .page-header h1 {
            font-size: 3.5rem;
            margin-bottom: 1rem;
            font-weight: 600;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .page-header .subtitle {
            font-size: 1.3rem;
            opacity: 0.9;
            margin-bottom: 2rem;
        }

        .page-stats {
            display: flex;
            justify-content: center;
            gap: 3rem;
            margin-top: 2rem;
        }

        .stat-item {
            text-align: center;
            background: rgba(255,255,255,0.1);
            padding: 1.5rem;
            border-radius: 15px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 600;
            display: block;
            color: #fff;
        }

        .stat-label {
            font-size: 1rem;
            opacity: 0.8;
            margin-top: 0.5rem;
        }

        /* Search and Filter Section */
        .search-filter-section {
            background: #f8fafc;
            padding: 2rem 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .search-filter-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .search-filter-grid {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 2rem;
            align-items: center;
        }

        .search-box-large {
            display: flex;
            background: white;
            border-radius: 50px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .search-box-large:focus-within {
            border-color: #667eea;
            box-shadow: 0 4px 20px rgba(102,126,234,0.2);
        }

        .search-box-large input {
            flex: 1;
            padding: 1rem 1.5rem;
            border: none;
            font-size: 1rem;
            background: transparent;
        }

        .search-box-large input:focus {
            outline: none;
        }

        .search-box-large button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 1rem 2rem;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .search-box-large button:hover {
            background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
        }

        .category-filter {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .filter-select {
            padding: 0.8rem 1.2rem;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1rem;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-select:focus {
            outline: none;
            border-color: #667eea;
        }

        /* Category Navigation */
        .category-nav {
            background: white;
            padding: 1.5rem 0;
            border-bottom: 1px solid #e2e8f0;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .category-nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .category-tabs {
            display: flex;
            gap: 1rem;
            overflow-x: auto;
            padding-bottom: 0.5rem;
        }

        .category-tab {
            padding: 0.8rem 1.5rem;
            background: #f1f5f9;
            color: #64748b;
            text-decoration: none;
            border-radius: 25px;
            white-space: nowrap;
            transition: all 0.3s ease;
            font-weight: 500;
            border: 2px solid transparent;
        }

        .category-tab:hover {
            background: #e2e8f0;
            color: #475569;
            text-decoration: none;
        }

        .category-tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: rgba(255,255,255,0.3);
        }

        .category-count {
            background: rgba(255,255,255,0.2);
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.8rem;
            margin-left: 0.5rem;
        }

        /* Recipes Display */
        .recipes-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 3rem 2rem;
        }

        .category-section {
            margin-bottom: 4rem;
        }

        .category-title {
            font-size: 2rem;
            color: #1e293b;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 3px solid #667eea;
            display: inline-block;
        }

        .recipes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .quick-recipe-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: 1px solid #e2e8f0;
        }

        .quick-recipe-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }

        .recipe-image {
            position: relative;
            height: 200px;
            overflow: hidden;
        }

        .recipe-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .quick-recipe-card:hover .recipe-image img {
            transform: scale(1.05);
        }

        .quick-badge {
            position: absolute;
            top: 1rem;
            left: 1rem;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .recipe-content {
            padding: 1.5rem;
        }

        .recipe-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.8rem;
            line-height: 1.4;
        }

        .recipe-description {
            color: #64748b;
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 1rem;
        }

        .recipe-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #f1f5f9;
        }

        .recipe-time {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            color: #667eea;
            font-weight: 500;
        }

        .recipe-category {
            background: #f1f5f9;
            color: #64748b;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .recipe-actions {
            display: flex;
            gap: 1rem;
        }

        .btn-view-recipe {
            flex: 1;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 10px;
            text-decoration: none;
            text-align: center;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-view-recipe:hover {
            background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
            transform: translateY(-2px);
            text-decoration: none;
            color: white;
        }

        .btn-favorite {
            background: #f8fafc;
            color: #64748b;
            border: 2px solid #e2e8f0;
            padding: 0.8rem;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-favorite:hover {
            background: #fef2f2;
            color: #ef4444;
            border-color: #fecaca;
        }

        .btn-favorite.active {
            background: #fef2f2;
            color: #ef4444;
            border-color: #ef4444;
        }

        /* No Results */
        .no-results {
            text-align: center;
            padding: 4rem 2rem;
            color: #64748b;
        }

        .no-results i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .no-results h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: #374151;
        }

        /* Loading Animation */
        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 3rem;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f4f6;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 2.5rem;
            }

            .page-stats {
                flex-direction: column;
                gap: 1rem;
            }

            .search-filter-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .category-filter {
                justify-content: center;
            }

            .recipes-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .category-tabs {
                justify-content: flex-start;
            }

            .recipe-actions {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .page-header {
                padding: 3rem 0 1.5rem;
            }

            .page-header h1 {
                font-size: 2rem;
            }

            .recipes-container {
                padding: 2rem 1rem;
            }

            .category-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>

<body>
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
                    
                    <?php if ($isLoggedIn): ?>
                        <!-- Logged in user menu -->
                        <li class="dropdown user-dropdown">
                            <a href="#" class="user-menu-toggle">
                                <i class="fas fa-user-circle"></i> 
                                <?php echo htmlspecialchars($currentUser['full_name'] ?: $currentUser['username']); ?>
                                <i class="fas fa-chevron-down"></i>
                            </a>
                            <ul class="dropdown-content user-menu">
                                <li><a href="user-dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                                <li><a href="profile.php"><i class="fas fa-user-edit"></i> My Profile</a></li>
                                <li><a href="my-recipes.php"><i class="fas fa-utensils"></i> My Recipes</a></li>
                                <li><a href="favorites.php"><i class="fas fa-heart"></i> Favorites</a></li>
                                <li class="divider"></li>
                                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                            </ul>
                        </li>
                        <li><a href="upload-recipe.php"><i class="fas fa-plus-circle"></i> Submit Recipe</a></li>
                    <?php else: ?>
                        <!-- Guest user menu -->
                        <li><a href="login.php">Login</a></li>
                        <li><a href="signup.php">Signup</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Page Header -->
    <section class="page-header">
        <div class="page-header-content">
            <h1><i class="fas fa-bolt"></i> Quick Picks</h1>
            <p class="subtitle">Fast & delicious recipes for busy days - all under 30 minutes!</p>
            
            <div class="page-stats">
                <div class="stat-item">
                    <span class="stat-number"><?php echo $totalCount; ?></span>
                    <span class="stat-label">Quick Recipes</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo count($recipesByCategory); ?></span>
                    <span class="stat-label">Categories</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number">≤30</span>
                    <span class="stat-label">Minutes Max</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Search and Filter Section -->
    <section class="search-filter-section">
        <div class="search-filter-container">
            <div class="search-filter-grid">
                <form method="GET" class="search-box-large">
                    <input type="text" name="search" placeholder="Search quick recipes..." 
                           value="<?php echo htmlspecialchars($searchTerm); ?>" />
                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($selectedCategory); ?>" />
                    <button type="submit">
                        <i class="fas fa-search"></i> Search
                    </button>
                </form>
                
                <div class="category-filter">
                    <form method="GET" onchange="this.submit()">
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" />
                        <select name="category" class="filter-select">
                            <option value="">All Categories</option>
                            <?php foreach ($categoryStats as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                                        <?php echo ($selectedCategory === $cat['category']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($categoryNames[$cat['category']] ?? ucfirst($cat['category'])); ?> 
                                    (<?php echo $cat['count']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Category Navigation -->
    <nav class="category-nav">
        <div class="category-nav-container">
            <div class="category-tabs">
                <a href="?<?php echo http_build_query(array_merge($_GET, ['category' => ''])); ?>" 
                   class="category-tab <?php echo empty($selectedCategory) ? 'active' : ''; ?>">
                    <i class="fas fa-th-large"></i> All
                    <span class="category-count"><?php echo $totalCount; ?></span>
                </a>
                
                <?php foreach ($categoryStats as $cat): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['category' => $cat['category']])); ?>" 
                       class="category-tab <?php echo ($selectedCategory === $cat['category']) ? 'active' : ''; ?>">
                        <?php 
                        $icons = [
                            'appetizer' => 'fas fa-seedling',
                            'breakfast' => 'fas fa-sun',
                            'lunch' => 'fas fa-hamburger',
                            'dinner' => 'fas fa-utensils',
                            'dessert' => 'fas fa-ice-cream',
                            'bread-bakes' => 'fas fa-bread-slice',
                            'salads' => 'fas fa-leaf',
                            'healthy' => 'fas fa-heartbeat',
                            'beverages' => 'fas fa-glass-cheers',
                            'snacks' => 'fas fa-cookie-bite'
                        ];
                        ?>
                        <i class="<?php echo $icons[$cat['category']] ?? 'fas fa-utensils'; ?>"></i>
                        <?php echo htmlspecialchars($categoryNames[$cat['category']] ?? ucfirst($cat['category'])); ?>
                        <span class="category-count"><?php echo $cat['count']; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </nav>

    <!-- Recipes Display -->
    <main class="recipes-container">
        <?php if (!empty($recipesByCategory)): ?>
            <?php if (empty($selectedCategory)): ?>
                <!-- Show all categories -->
                <?php foreach ($recipesByCategory as $category => $recipes): ?>
                    <section class="category-section">
                        <h2 class="category-title">
                            <?php 
                            $icons = [
                                'appetizer' => 'fas fa-seedling',
                                'breakfast' => 'fas fa-sun',
                                'lunch' => 'fas fa-hamburger',
                                'dinner' => 'fas fa-utensils',
                                'dessert' => 'fas fa-ice-cream',
                                'bread-bakes' => 'fas fa-bread-slice',
                                'salads' => 'fas fa-leaf',
                                'healthy' => 'fas fa-heartbeat',
                                'beverages' => 'fas fa-glass-cheers',
                                'snacks' => 'fas fa-cookie-bite'
                            ];
                            ?>
                            <i class="<?php echo $icons[strtolower($category)] ?? 'fas fa-utensils'; ?>"></i>
                            <?php echo htmlspecialchars($categoryNames[strtolower($category)] ?? ucfirst($category)); ?>
                            <span style="font-size: 1rem; color: #64748b; font-weight: normal;">
                                (<?php echo count($recipes); ?> recipes)
                            </span>
                        </h2>
                        
                        <div class="recipes-grid">
                            <?php foreach ($recipes as $recipe): ?>
                                <div class="quick-recipe-card">
                                    <div class="recipe-image">
                                        <img src="<?php echo htmlspecialchars($recipe['image'] ?? 'images/default-recipe.jpg'); ?>" 
                                             alt="<?php echo htmlspecialchars($recipe['title']); ?>" />
                                        <div class="quick-badge">
                                            <i class="fas fa-clock"></i>
                                            <?php echo htmlspecialchars($recipe['prep_time'] ?? '30 min'); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="recipe-content">
                                        <h3 class="recipe-title"><?php echo htmlspecialchars($recipe['title']); ?></h3>
                                        <p class="recipe-description">
                                            <?php echo htmlspecialchars(substr($recipe['description'], 0, 120) . '...'); ?>
                                        </p>
                                        
                                        <div class="recipe-meta">
                                            <div class="recipe-time">
                                                <i class="fas fa-clock"></i>
                                                <?php echo htmlspecialchars($recipe['prep_time'] ?? '30 min'); ?>
                                            </div>
                                            <div class="recipe-category">
                                                <?php echo htmlspecialchars($categoryNames[strtolower($recipe['category'])] ?? ucfirst($recipe['category'])); ?>
                                            </div>
                                        </div>
                                        
                                        <div class="recipe-actions">
                                            <a href="recipe-detail.php?id=<?php echo $recipe['id']; ?>" class="btn-view-recipe">
                                                <i class="fas fa-eye"></i> View Recipe
                                            </a>
                                            <button class="btn-favorite" onclick="toggleFavorite(<?php echo $recipe['id']; ?>)">
                                                <i class="fas fa-heart"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Show selected category only -->
                <?php 
                $categoryRecipes = $recipesByCategory[array_key_first($recipesByCategory)] ?? [];
                ?>
                <section class="category-section">
                    <h2 class="category-title">
                        <?php echo htmlspecialchars($categoryNames[strtolower($selectedCategory)] ?? ucfirst($selectedCategory)); ?>
                        <span style="font-size: 1rem; color: #64748b; font-weight: normal;">
                            (<?php echo count($categoryRecipes); ?> recipes)
                        </span>
                    </h2>
                    
                    <div class="recipes-grid">
                        <?php foreach ($categoryRecipes as $recipe): ?>
                            <div class="quick-recipe-card">
                                <div class="recipe-image">
                                    <img src="<?php echo htmlspecialchars($recipe['image'] ?? 'images/default-recipe.jpg'); ?>" 
                                         alt="<?php echo htmlspecialchars($recipe['title']); ?>" />
                                    <div class="quick-badge">
                                        <i class="fas fa-clock"></i>
                                        <?php echo htmlspecialchars($recipe['prep_time'] ?? '30 min'); ?>
                                    </div>
                                </div>
                                
                                <div class="recipe-content">
                                    <h3 class="recipe-title"><?php echo htmlspecialchars($recipe['title']); ?></h3>
                                    <p class="recipe-description">
                                        <?php echo htmlspecialchars(substr($recipe['description'], 0, 120) . '...'); ?>
                                    </p>
                                    
                                    <div class="recipe-meta">
                                        <div class="recipe-time">
                                            <i class="fas fa-clock"></i>
                                            <?php echo htmlspecialchars($recipe['prep_time'] ?? '30 min'); ?>
                                        </div>
                                        <div class="recipe-category">
                                            <?php echo htmlspecialchars($categoryNames[strtolower($recipe['category'])] ?? ucfirst($recipe['category'])); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="recipe-actions">
                                        <a href="recipe-detail.php?id=<?php echo $recipe['id']; ?>" class="btn-view-recipe">
                                            <i class="fas fa-eye"></i> View Recipe
                                        </a>
                                        <button class="btn-favorite" onclick="toggleFavorite(<?php echo $recipe['id']; ?>)">
                                            <i class="fas fa-heart"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        <?php else: ?>
            <!-- No Results -->
            <div class="no-results">
                <i class="fas fa-search"></i>
                <h3>No Quick Recipes Found</h3>
                <p>Try adjusting your search terms or browse different categories.</p>
                <a href="quick-picks.php" class="btn-view-recipe" style="display: inline-block; margin-top: 1rem;">
                    <i class="fas fa-refresh"></i> Clear Filters
                </a>
            </div>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h3>Quick & Easy</h3>
                <p>Discover delicious recipes that can be prepared in 30 minutes or less. Perfect for busy lifestyles!</p>
            </div>
            <div class="footer-section">
                <h3>Categories</h3>
                <ul>
                    <li><a href="breakfast.php">Quick Breakfast</a></li>
                    <li><a href="lunch.php">Fast Lunch</a></li>
                    <li><a href="dinner.php">Easy Dinner</a></li>
                    <li><a href="snacks.php">Quick Snacks</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Connect</h3>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-pinterest"></i></a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2024 Cookistry. All rights reserved.</p>
        </div>
    </footer>

    <!-- JavaScript -->
    <script>
        // Toggle favorite functionality
        function toggleFavorite(recipeId) {
            <?php if ($isLoggedIn): ?>
                fetch('toggle-favorite.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '<?php echo $csrfToken; ?>'
                    },
                    body: JSON.stringify({
                        recipe_id: recipeId,
                        csrf_token: '<?php echo $csrfToken; ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const button = event.target.closest('.btn-favorite');
                        button.classList.toggle('active');
                        
                        // Show toast notification
                        showToast(data.favorited ? 'Added to favorites!' : 'Removed from favorites!');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Error updating favorites', 'error');
                });
            <?php else: ?>
                showToast('Please login to add favorites', 'error');
                setTimeout(() => {
                    window.location.href = 'login.php';
                }, 1500);
            <?php endif; ?>
        }

        // Toast notification system
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.textContent = message;
            
            toast.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${type === 'success' ? '#10b981' : '#ef4444'};
                color: white;
                padding: 1rem 1.5rem;
                border-radius: 10px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.2);
                z-index: 1000;
                transform: translateX(100%);
                transition: transform 0.3s ease;
            `;
            
            document.body.appendChild(toast);
            
            // Animate in
            setTimeout(() => {
                toast.style.transform = 'translateX(0)';
            }, 10);
            
            // Remove after 3 seconds
            setTimeout(() => {
                toast.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    document.body.removeChild(toast);
                }, 300);
            }, 3000);
        }

        // Smooth scrolling for category navigation
        document.querySelectorAll('.category-tab').forEach(tab => {
            tab.addEventListener('click', function(e) {
                if (this.getAttribute('href').includes('#')) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                }
            });
        });

        // Search functionality enhancement
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    this.closest('form').submit();
                }
            });
        }

        // Lazy loading for recipe images
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src || img.src;
                        img.classList.remove('lazy');
                        observer.unobserve(img);
                    }
                });
            });

            document.querySelectorAll('img[data-src]').forEach(img => {
                imageObserver.observe(img);
            });
        }

        // Add loading animation for recipe cards
        function showLoading() {
            const container = document.querySelector('.recipes-container');
            container.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
        }

        // Filter form auto-submit delay
        let filterTimeout;
        const filterSelect = document.querySelector('.filter-select');
        if (filterSelect) {
            filterSelect.addEventListener('change', function() {
                clearTimeout(filterTimeout);
                filterTimeout = setTimeout(() => {
                    this.closest('form').submit();
                }, 300);
            });
        }
    </script>
</body>
</html>