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
    // Base query for quick recipes (recipes with prep time <= 20 minutes)
    $baseCondition = "(status = 'published' OR status IS NULL OR status = '') 
                      AND (prep_time LIKE '%5%' OR prep_time LIKE '%10%' OR prep_time LIKE '%15%' OR prep_time LIKE '%20%' 
                           OR prep_time LIKE '%quick%' OR prep_time LIKE '%fast%' OR prep_time LIKE '%easy%'
                           OR CAST(REGEXP_REPLACE(prep_time, '[^0-9]', '') AS UNSIGNED) <= 20)";
    
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
    <title>Quick Picks - Fast & Easy Recipes Under 20 Minutes | Cookistry</title>
    <link rel="stylesheet" href="style.css" />
    <link rel="icon" href="images/logo.png" type="image/png" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Quick Picks Page Styles - Matching Homepage Color Scheme */
        body {
            background: rgb(235, 242, 247);
        }

        /* Page Header - Matching Homepage Hero Style */
        .page-header {
            background: linear-gradient(135deg, #4ca1af 0%, #2c3e50 100%);
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
            border-color: #4ca1af;
            box-shadow: 0 4px 20px rgba(76, 161, 175, 0.2);
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
            background: linear-gradient(135deg, #4ca1af 0%, #2c3e50 100%);
            color: white;
            border: none;
            padding: 1rem 2rem;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .search-box-large button:hover {
            background: linear-gradient(135deg, #2c3e50 0%, #4ca1af 100%);
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
            border-color: #4ca1af;
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
            background: linear-gradient(135deg, #4ca1af 0%, #2c3e50 100%);
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
            border-bottom: 3px solid #4ca1af;
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
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
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
            color: #4ca1af;
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
            background: linear-gradient(135deg, #4ca1af 0%, #2c3e50 100%);
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
            box-shadow: 0 4px 15px rgba(76, 161, 175, 0.3);
        }

        .btn-view-recipe:hover {
            background: linear-gradient(135deg, #2c3e50 0%, #4ca1af 100%);
            transform: translateY(-2px);
            text-decoration: none;
            color: white;
            box-shadow: 0 8px 25px rgba(76, 161, 175, 0.4);
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

        /* User dropdown styles - matching homepage */
        .user-dropdown .dropdown-content {
            min-width: 200px;
            right: 0;
            left: auto;
        }

        .user-menu li {
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .user-menu li:last-child {
            border-bottom: none;
        }

        .user-menu .divider {
            height: 1px;
            background: rgba(255,255,255,0.2);
            margin: 0.5rem 0;
            border: none;
            padding: 0;
        }

        .user-menu a {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.8rem 1rem;
            transition: all 0.3s ease;
        }

        .user-menu-toggle {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: white;
            text-decoration: none;
        }

        .user-menu a:hover {
            background: rgba(255,255,255,0.1);
            padding-left: 1.2rem;
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

            .user-dropdown .dropdown-content {
                position: relative;
                display: none;
                box-shadow: none;
                background: rgba(255,255,255,0.1);
                border-radius: 10px;
                margin-top: 0.5rem;
            }
            
            .user-dropdown:hover .dropdown-content {
                display: block;
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
            <p class="subtitle">Super fast & delicious recipes - all under 20 minutes!</p>
            
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
                    <span class="stat-number">≤20</span>
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
                                            <?php echo htmlspecialchars($recipe['prep_time'] ?? '20 min'); ?>
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
                                                <?php echo htmlspecialchars($recipe['prep_time'] ?? '20 min'); ?>
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
                                        <?php echo htmlspecialchars($recipe['prep_time'] ?? '20 min'); ?>
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
                                            <?php echo htmlspecialchars($recipe['prep_time'] ?? '20 min'); ?>
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

    <!-- Footer - Using Same Footer from Homepage -->
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

        // Search functionality enhancement
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    this.closest('form').submit();
                }
            });
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